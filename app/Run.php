<?php

namespace WPFreighter;

class Run {

    public function __construct() {
        if ( defined( 'WP_FREIGHTER_DEV_MODE' ) ) {
            add_filter('https_ssl_verify', '__return_false');
            add_filter('https_local_ssl_verify', '__return_false');
            add_filter('http_request_host_is_external', '__return_true');
        }
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'freighter', 'WPFreighter\CLI' );
        }
        add_action( 'admin_bar_menu', [ $this, 'admin_toolbar' ], 100 );
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_endpoints' ] );
        add_action( 'init', [ $this, 'handle_auto_login' ] );
        register_activation_hook( plugin_dir_path( __DIR__ ) . "wp-freighter.php", [ $this, 'activate' ] );
        register_deactivation_hook( plugin_dir_path( __DIR__ ) . "wp-freighter.php", [ $this, 'deactivate' ] );
        $plugin_file = dirname ( plugin_basename( __DIR__ ) ) . "/wp-freighter.php" ;
        add_filter( "plugin_action_links_{$plugin_file}", [ $this, 'settings_link' ] );
    }

    public function settings_link( $links ) {
        $settings_link = "<a href='/wp-admin/tools.php?page=wp-freighter'>" . __( 'Settings' ) . "</a>";
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function register_rest_endpoints() {
        $namespace = 'wp-freighter/v1';
        
        $routes = [
            '/sites' => [
                'GET'  => 'get_sites',
                'POST' => 'new_site'
            ],
            '/sites/delete' => [
                'POST' => 'delete_site'
            ],
            '/sites/clone' => [
                'POST' => 'clone_existing'
            ],
            '/sites/stats' => [
                'POST' => 'get_site_stats'
            ],
            '/sites/autologin' => [
                'POST' => 'auto_login'
            ],
            '/configurations' => [
                'POST' => 'save_configurations'
            ],
            '/switch' => [
                'POST' => 'switch_to'
            ],
            '/exit' => [
                'POST' => 'exit_freighter'
            ],
        ];

        foreach ( $routes as $route => $methods ) {
            foreach ( $methods as $method => $callback ) {
                register_rest_route( $namespace, $route, [
                    'methods'             => $method,
                    'callback'            => [ $this, $callback ],
                    'permission_callback' => [ $this, 'permissions_check' ]
                ]);
            }
        }
    }

    public function permissions_check() {
        return current_user_can( 'manage_options' );
    }

    // --- REST API Callbacks ---

    public function get_sites( $request ) {
        $sites = ( new Sites )->get();
        return empty( $sites ) ? [] : $sites;
    }

    public function new_site( $request ) {
        $params = $request->get_json_params();
        
        // Sanitize incoming parameters
        $clean_params = [];
        $clean_params['title']    = isset($params['title']) ? sanitize_text_field($params['title']) : 'New Site';
        $clean_params['name']     = isset($params['name']) ? sanitize_text_field($params['name']) : 'New Site';
        
        // For domains, we sanitize as text to allow partials/localhost, but strip harmful chars
        $clean_params['domain']   = isset($params['domain']) ? sanitize_text_field($params['domain']) : '';
        
        $clean_params['username'] = isset($params['username']) ? sanitize_user($params['username']) : 'admin';
        $clean_params['email']    = isset($params['email']) ? sanitize_email($params['email']) : '';
        
        // Passwords should be kept raw for complexity, but only if they are set
        $clean_params['password'] = isset($params['password']) ? $params['password'] : '';

        // Delegate to Site Model with clean data
        $result = Site::create( $clean_params );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Return full list for UI update
        return ( new Sites )->get();
    }

    public function delete_site( $request ) {
        $params = $request->get_json_params();
        $site_id = (int) $params['site_id'];

        // Delegate to Site Model
        Site::delete( $site_id );
        
        // If we are deleting the site we are currently viewing, kill the session cookie
        if ( isset( $_COOKIE['stacked_site_id'] ) && $_COOKIE['stacked_site_id'] == $site_id ) {
            $this->exit_freighter( $request );
        }

        return ( new Sites )->get();
    }

    public function clone_existing( $request ) {
        $params    = $request->get_json_params();
        
        // Allow 'main' or an integer ID
        $source_id = isset( $params['source_id'] ) ? $params['source_id'] : 'main';
        if ( $source_id !== 'main' ) {
            $source_id = (int) $source_id;
        }
        
        $args = [
            'name'   => isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '',
            'domain' => isset( $params['domain'] ) ? sanitize_text_field( $params['domain'] ) : '',
        ];

        $result = Site::clone( $source_id, $args );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return ( new Sites )->get();
    }

    public function auto_login( $request ) {
        $params = $request->get_json_params();
        $site_id = (int) $params['site_id'];

        // Delegate to Site Model
        $login_url = Site::login( $site_id );

        if ( is_wp_error( $login_url ) ) {
            return $login_url;
        }

        return [ 'url' => $login_url ];
    }

    public function save_configurations( $request ) {
        $params = $request->get_json_params();
        $sites_data = isset($params['sites']) ? $params['sites'] : [];
        $configs_data = isset($params['configurations']) ? $params['configurations'] : [];
        
        // Use Sites class for bulk update
        ( new Sites )->update( $sites_data );
        ( new Configurations )->update( $configs_data );
        
        return ( new Configurations )->get();
    }

    public function get_site_stats( $request ) {
        $params = $request->get_json_params();
        $site_id = (int) $params['site_id'];
        
        $path = ABSPATH . "content/$site_id/";
        
        if ( file_exists( $path ) ) {
            $bytes = Sites::get_directory_size( $path );
            $relative_path = str_replace( ABSPATH, '', $path );
            return [
                'has_dedicated_content' => true,
                'path' => $relative_path,
                'size' => Sites::format_size( $bytes )
            ];
        }

        return [
            'has_dedicated_content' => false,
            'path' => '',
            'size' => 0
        ];
    }

    // --- Session / Cookie Management ---

    public function switch_to( $request ) {
        $params = $request->get_json_params();
        $site_id = (int) $params['site_id'];

        // 1. Set Cookie for Context Switch
        setcookie( 'stacked_site_id', $site_id, time() + 31536000, '/' );
        $_COOKIE[ "stacked_site_id" ] = $site_id;

        // 2. Generate Magic Login URL for the Target Site
        // We use Site::login which generates the token in the target DB
        $login_url = Site::login( $site_id );

        if ( is_wp_error( $login_url ) ) {
             return [ 'success' => false, 'message' => $login_url->get_error_message() ];
        }

        return [ 'success' => true, 'site_id' => $site_id, 'url' => $login_url ];
    }
    
    public function exit_freighter( $request ) {
        // 1. Generate Login URL for Main Site with Redirection
        $login_url = Site::login( 'main', 'wp-admin/tools.php?page=wp-freighter' );

        // 2. Clear Session Cookie
        setcookie( 'stacked_site_id', null, -1, '/');
        unset( $_COOKIE[ "stacked_site_id" ] );

        // 3. Return URL
        if ( is_wp_error( $login_url ) ) {
            return [ 'success' => true ];
        }
        return [ 'success' => true, 'url' => $login_url ];
    }

    // --- Admin Interface ---

    public function admin_toolbar( $admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $domain_mapping  = ( new Configurations )->domain_mapping();
        if ( $domain_mapping ) {
            return;
        }
        $stacked_sites   = ( new Sites )->get();
        $stacked_site_id = empty ( $_COOKIE[ "stacked_site_id" ] ) ? "" : $_COOKIE[ "stacked_site_id" ];
        $item = null;

        foreach( $stacked_sites as $stacked_site ) {
            if ( $stacked_site_id == $stacked_site['stacked_site_id'] ) {
                $item = $stacked_site;
                break;
            }
        }
        
        if ( ! empty( $stacked_site_id ) && $item ) {
            $label = ( $item['name'] ? "{$item['name']} - " : "" ) . wp_date( "M j, Y, g:i a", $item['created_at'] );
            $admin_bar->add_menu( [
                'id'    => 'wp-freighter',
                'title' => '<span class="ab-icon dashicons dashicons-welcome-view-site"></span> <span style="font-size: 0.8em !important;background-color: #fff;color: #000;padding: 1px 4px;border-radius: 2px;margin-left: 2px;position:relative;top:-2px">' . $label .'</span>',
                'href'  => '/wp-admin/tools.php?page=wp-freighter',
            ] );
            $admin_bar->add_menu( [
                'id'    => 'wp-freighter-exit',
                'title' => '<span class="ab-icon dashicons dashicons-backup"></span>Exit WP Freighter',
                'href'  => '#',
                'meta'  => [ 'onclick' => 'fetch( "' . esc_url( get_rest_url( null, 'wp-freighter/v1/exit' ) ) . '", { method: "POST", headers: { "X-WP-Nonce": "' . wp_create_nonce( 'wp_rest' ) . '" } } ).then( res => res.json() ).then( data => { if ( data.url ) { window.location.href = data.url; } else { window.location.reload(); } } ); return false;' ]
            ] );
        }
        if ( empty( $stacked_site_id ) ) {
            $admin_bar->add_menu( [
                'id'    => 'wp-freighter-enter',
                'title' => '<span class="ab-icon dashicons dashicons-welcome-view-site"></span>View Stacked Sites',
                'href'  => '/wp-admin/tools.php?page=wp-freighter',
            ] );
        }
    }

    public function enqueue_assets( $hook ) {
        if ( 'tools_page_wp-freighter' !== $hook ) {
            return;
        }

        // Define the root URL for the plugin assets
        $plugin_url = plugin_dir_url( dirname( __DIR__ ) . '/wp-freighter.php' );

        // 1. Enqueue Local CSS
        wp_enqueue_style( 'vuetify', $plugin_url . 'assets/css/vuetify.min.css', [], '2.6.13' );
        wp_enqueue_style( 'mdi', $plugin_url . 'assets/css/materialdesignicons.min.css', [], 'latest' );

        // 2. Enqueue Local JS
        wp_enqueue_script( 'axios', $plugin_url . 'assets/js/axios.min.js', [], '1.13.2', true );
        // Note: Use vue.js for dev or vue.min.js for production
        wp_enqueue_script( 'vue', $plugin_url . 'assets/js/vue.min.js', [], '2.7.16', true ); 
        wp_enqueue_script( 'vuetify', $plugin_url . 'assets/js/vuetify.min.js', [ 'vue' ], '2.6.13', true );

        // 3. Enqueue App Logic (Dependent on local libraries)
        wp_enqueue_script( 'wp-freighter-app', $plugin_url . 'assets/js/admin-app.js', [ 'vuetify', 'axios' ], '1.3.0', true );

        // 4. Localize Data
        $data = [
            'root'            => esc_url_raw( rest_url( 'wp-freighter/v1/' ) ),
            'nonce'           => wp_create_nonce( 'wp_rest' ),
            'current_site_id' => isset( $_COOKIE['stacked_site_id'] ) ? sanitize_text_field( $_COOKIE['stacked_site_id'] ) : '',
            'currentUser'     => [
                'username' => wp_get_current_user()->user_login,
                'email'    => wp_get_current_user()->user_email,
            ],
            'configurations'  => ( new Configurations )->get(),
            'stacked_sites'   => ( new Sites )->get(),
        ];
        wp_localize_script( 'wp-freighter-app', 'wpFreighterSettings', $data );
        
        // 5. Set Axios Defaults
        wp_add_inline_script( 'wp-freighter-app', "axios.defaults.headers.common['X-WP-Nonce'] = wpFreighterSettings.nonce; axios.defaults.headers.common['Content-Type'] = 'application/json';", 'after' );
    }

    public function admin_menu() {
        if ( current_user_can( 'manage_options' ) ) {
            add_management_page( "WP Freighter", "WP Freighter", "manage_options", "wp-freighter", array( $this, 'admin_view' ) );
        }
    }

    public function admin_view() {
        require_once plugin_dir_path( __DIR__ ) . '/templates/admin-wp-freighter.php';
    }
    
    // --- Lifecycle Methods (Activation/Deactivation) ---

    public function activate() {
        // Add default configurations right after $table_prefix
        $lines_to_add = [
            '',
            '/* WP Freighter */',
            '$stacked_site_id = ( isset( $_COOKIE[ "stacked_site_id" ] ) ? $_COOKIE[ "stacked_site_id" ] : "" );',
            'if ( defined( \'WP_CLI\' ) && WP_CLI ) { $stacked_site_id = getenv( \'STACKED_SITE_ID\' ); }',
            'if ( ! empty( $stacked_site_id ) ) { define( \'TABLE_PREFIX\', $table_prefix ); $table_prefix = "stacked_{$stacked_site_id}_"; }',
        ];

        $wp_config_file = $this->get_wp_config_path();

        if ( empty ( $wp_config_file ) ) {
            ( new Configurations )->update_config( "unable_to_save", $lines_to_add );
            return;
        }

        $wp_config_content = file_get_contents( $wp_config_file );
        $working           = preg_split( '/\R/', $wp_config_content );

        // Clean existing freighter configs
        $working = $this->clean_wp_config_lines( $working );

        // Comment out manually set WP_HOME or WP_SITEURL
        foreach( $working as $key => $line ) {
            if ( strpos( $line, "define('WP_HOME'" ) === 0 || strpos( $line, "define('WP_SITEURL'" ) === 0 ) {
                $working[ $key ] = "//$line";
            }
        }

        // Append WP_CACHE_KEY_SALT with unique identifier if found
        foreach( $working as $key => $line ) {
            if ( strpos( $line, "define('WP_CACHE_KEY_SALT', '" ) === 0 ) {
                $working[ $key ] = str_replace( "define('WP_CACHE_KEY_SALT', '", "define('WP_CACHE_KEY_SALT', \$stacked_site_id . '", $line );
            }
        }

        $table_prefix_line = 0;
        foreach( $working as $key => $line ) {
            if ( strpos( $line, '$table_prefix' ) !== false ) {
                $table_prefix_line = $key;
                break;
            }
        }

        // Cleanup empty lines after prefix
        if ( isset( $working[ $table_prefix_line + 1 ] ) && $working[ $table_prefix_line + 1 ] == "" && isset( $working[ $table_prefix_line + 2 ] ) && $working[ $table_prefix_line + 2 ] == "" ) {
            unset( $working[ $table_prefix_line + 1 ] );
        }

        // Insert new lines
        $updated = array_merge( array_slice( $working, 0, $table_prefix_line + 1, true ), $lines_to_add, array_slice( $working, $table_prefix_line + 1, count( $working ), true ) );

        // Save
        $results = @file_put_contents( $wp_config_file, implode( PHP_EOL, $updated ) );
        if ( empty( $results ) ) {
            ( new Configurations )->update_config( "unable_to_save", $lines_to_add );
        }
    }

    public function deactivate() {
        $wp_config_file = $this->get_wp_config_path();

        if ( empty ( $wp_config_file ) ) {
            return;
        }

        $wp_config_content = file_get_contents( $wp_config_file );
        $working           = preg_split( '/\R/', $wp_config_content );

        // Remove WP Freighter configs
        $working = $this->clean_wp_config_lines( $working );

        @file_put_contents( $wp_config_file, implode( PHP_EOL, $working ) );
    }

    public function handle_auto_login() {
        global $pagenow;

        // 1. Handle Context Switch
        if ( isset( $_GET['freighter_switch'] ) ) {
            $site_id = (int) $_GET['freighter_switch'];
            $user_id = (int) $_GET['freighter_user'];
            $token   = $_GET['freighter_token'];
            
            // Set Cookie
            $cookie_path   = defined( 'SITECOOKIEPATH' ) ? SITECOOKIEPATH : '/';
            $cookie_domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
            setcookie( 'stacked_site_id', $site_id, time() + 31536000, $cookie_path, $cookie_domain );
            
            // [FIXED] Use site_url() instead of admin_url()
            // This prevents generating /wp-admin/wp-login.php
            $login_url = add_query_arg([
                'user_id'                 => $user_id,
                'captaincore_login_token' => $token,
                'redirect_to'             => isset($_GET['redirect_to']) ? $_GET['redirect_to'] : admin_url()
            ], site_url( 'wp-login.php' ) ); 
            
            nocache_headers();
            wp_safe_redirect( $login_url );
            exit;
        }

        // 2. Standard Token Verification
        if ( 'wp-login.php' !== $pagenow || empty( $_GET['user_id'] ) || empty( $_GET['captaincore_login_token'] ) ) {
            return;
        }

        $user = get_user_by( 'id', (int) $_GET['user_id'] );
        
        if ( ! $user ) {
            wp_die( 'Invalid User' );
        }

        $token = get_user_meta( $user->ID, 'captaincore_login_token', true );

        if ( ! hash_equals( $token, $_GET['captaincore_login_token'] ) ) {
            wp_die( 'Invalid one-time login token.' );
        }

        delete_user_meta( $user->ID, 'captaincore_login_token' );
        wp_set_auth_cookie( $user->ID, 1 );

        $redirect_to = ! empty( $_GET['redirect_to'] ) ? $_GET['redirect_to'] : admin_url();
        wp_safe_redirect( $redirect_to );
        exit;
    }

    // --- Helpers ---

    private function get_wp_config_path() {
        if ( file_exists( ABSPATH . "wp-config.php" ) ) {
            return ABSPATH . "wp-config.php";
        }
        if ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
            return dirname( ABSPATH ) . '/wp-config.php';
        }
        return false;
    }

    private function clean_wp_config_lines( $lines ) {
        foreach( $lines as $key => $line ) {
            if ( strpos( $line, '/* WP Freighter */' ) !== false || strpos( $line, 'stacked_site_id' ) !== false || strpos( $line, '$stacked_mappings' ) !== false ) {
                unset( $lines[ $key ] );
            }
        }
        return $lines;
    }

}