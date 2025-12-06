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
        add_action( 'rest_api_init', [ $this, 'register_rest_endpoints' ] );
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

        register_rest_route( $namespace, '/sites', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_sites' ],
            'permission_callback' => [ $this, 'permissions_check' ]
        ]);

        register_rest_route( $namespace, '/sites', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'new_site' ],
            'permission_callback' => [ $this, 'permissions_check' ]
        ]);

        register_rest_route( $namespace, '/sites/delete', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'delete_site' ],
            'permission_callback' => [ $this, 'permissions_check' ]
        ]);

        register_rest_route( $namespace, '/sites/clone', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'clone_existing' ],
            'permission_callback' => [ $this, 'permissions_check' ]
        ]);

        register_rest_route( $namespace, '/configurations', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'save_configurations' ],
            'permission_callback' => [ $this, 'permissions_check' ]
        ]);

        register_rest_route( $namespace, '/switch', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'switch_to' ],
            'permission_callback' => [ $this, 'permissions_check' ]
        ]);
        
        register_rest_route( $namespace, '/exit', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'exit_freighter' ],
            'permission_callback' => [ $this, 'permissions_check' ]
        ]);
        register_rest_route( $namespace, '/sites/stats', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'get_site_stats' ],
            'permission_callback' => [ $this, 'permissions_check' ]
        ]);
        register_rest_route( $namespace, '/sites/autologin', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'auto_login' ],
            'permission_callback' => [ $this, 'permissions_check' ]
        ]);
    }

    public function permissions_check() {
        return current_user_can( 'manage_options' );
    }

    public function get_sites( $request ) {
        $sites = ( new Sites )->get();
        if ( empty( $sites ) ) {
            $sites = [];
        }
        return $sites;
    }

    public function save_configurations( $request ) {
        $params = $request->get_json_params();
        $sites_data = isset($params['sites']) ? $params['sites'] : [];
        $configs_data = isset($params['configurations']) ? $params['configurations'] : [];
        
        ( new Sites )->update( $sites_data );
        ( new Configurations )->update( $configs_data );
        
        return ( new Configurations )->get();
    }

    public function switch_to( $request ) {
        $params = $request->get_json_params();
        $site_id = (int) $params['site_id'];

        setcookie( 'stacked_site_id', $site_id, time() + 31536000, '/' );
        $_COOKIE[ "stacked_site_id" ] = $site_id;

        return [ 'success' => true, 'site_id' => $site_id ];
    }
    
    public function exit_freighter( $request ) {
        setcookie( 'stacked_site_id', null, -1, '/');
        unset( $_COOKIE[ "stacked_site_id" ] );
        return [ 'success' => true ];
    }

    public function delete_site( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        $site_id_to_delete = (int) $params['site_id'];

        $stacked_sites = ( new Sites )->get();
        $db_prefix_primary = $this->get_primary_prefix();
        // Remove from array
        foreach( $stacked_sites as $key => $item ) {
            if ( $site_id_to_delete == $item['stacked_site_id'] ) {
                unset( $stacked_sites[$key] );
            }
        }

        // Save Options
        $stacked_sites_serialize = serialize( $stacked_sites );
        $wpdb->query( $wpdb->prepare( "UPDATE {$db_prefix_primary}options set option_value = %s where option_name = 'stacked_sites'", $stacked_sites_serialize ) );
        // Drop Tables
        if ( ! empty ( $site_id_to_delete ) ) {
            $site_table_prefix = "stacked_{$site_id_to_delete}_";
            $tables = array_column( $wpdb->get_results("show tables"), "Tables_in_". DB_NAME );
            foreach ( $tables as $table ) {
                if ( substr( $table, 0, strlen( $site_table_prefix ) ) != $site_table_prefix ) {
                    continue;
                }
                $wpdb->query( "DROP TABLE IF EXISTS $table" );
            }

            // Purge Files
            $site_content_path = ABSPATH . "content/$site_id_to_delete/";
            if ( file_exists( $site_content_path ) ) {
                Sites::delete_directory( $site_content_path );
            }
        }

        // If we are deleting the site we are currently viewing, kill the session cookie
        if ( isset( $_COOKIE['stacked_site_id'] ) && $_COOKIE['stacked_site_id'] == $site_id_to_delete ) {
            setcookie( 'stacked_site_id', null, -1, '/' );
            unset( $_COOKIE[ "stacked_site_id" ] );
        }

        ( new Configurations )->refresh_configs();
        // Return updated list
        return array_values($stacked_sites);
    }

    public function new_site( $request ) {
        global $wpdb, $table_prefix;
        $original_table_prefix = $table_prefix;

        $params = $request->get_json_params();
        $new_site_data = (object) $params;

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $stacked_sites = ( new Sites )->get();
        $db_prefix = $wpdb->prefix;
        $db_prefix_primary = $this->get_primary_prefix();

        $current_stacked_ids = array_column( $stacked_sites, "stacked_site_id" );
        $stacked_site_id     = ( empty( $current_stacked_ids ) ? 1 : (int) max( $current_stacked_ids ) + 1 );
        $new_table_prefix    = "stacked_{$stacked_site_id}_";
        
        $tables = array_column( $wpdb->get_results("show tables"), "Tables_in_". DB_NAME );
        foreach ( $tables as $table ) {
            if ( substr( $table, 0, strlen( $new_table_prefix ) ) != $new_table_prefix ) {
                continue;
            }
            $wpdb->query( "DROP TABLE IF EXISTS $table" );
        }

        $stacked_sites[] = [
            "stacked_site_id" => $stacked_site_id,
            "created_at"      => strtotime("now"),
            "name"            => $new_site_data->name,
            "domain"          => $new_site_data->domain
        ];
        $stacked_sites_serialize = serialize( $stacked_sites );
        
        $results = $wpdb->get_results("select option_id from {$db_prefix_primary}options where option_name = 'stacked_sites'");
        if ( empty( $results ) ) {
            $wpdb->query( $wpdb->prepare( "INSERT INTO {$db_prefix_primary}options ( option_name, option_value) VALUES ( 'stacked_sites', %s )", $stacked_sites_serialize ) );
        } else {
            $wpdb->query( $wpdb->prepare( "UPDATE {$db_prefix_primary}options set option_value = %s where option_name = 'stacked_sites'", $stacked_sites_serialize ) );
        }

        ( new Configurations )->refresh_configs();

        $files_mode = ( new Configurations )->get()->files;

        // Prepare content folders for Dedicated OR Hybrid
        if ( $files_mode == "dedicated" || $files_mode == "hybrid" ) {
            $site_content_path = ABSPATH . "content/$stacked_site_id";

            // Always create uploads directory for both modes
            // (mkdir with recursive=true will create the parent /content/ID/ folder automatically)
            if ( ! file_exists( "$site_content_path/uploads/" ) ) {
                mkdir( "$site_content_path/uploads/", 0777, true );
            }

            // Only create themes/plugins if fully Dedicated
            if ( $files_mode == "dedicated" ) {
                if ( ! file_exists( "$site_content_path/themes/" ) ) {
                    mkdir( "$site_content_path/themes/", 0777, true );
                }
                if ( ! file_exists( "$site_content_path/plugins/" ) ) {
                    mkdir( "$site_content_path/plugins/", 0777, true );
                }
                // Kinsta Compatibility
                $this->copy_kinsta_assets( $stacked_site_id );
            }
        }

        // Install WordPress to new table prefix
        $table_prefix = $new_table_prefix;
        wp_set_wpdb_vars();

        ob_start();
        $response = wp_install( $new_site_data->title, $new_site_data->username, $new_site_data->email, true, '', wp_slash( $new_site_data->password ), "en" );
        ob_end_clean();
        if ( ! empty ( $new_site_data->domain ) ) {
            $wpdb->query( $wpdb->prepare("UPDATE stacked_{$stacked_site_id}_options set option_value = %s where option_name = 'siteurl'", 'https://' . $new_site_data->domain) );
            $wpdb->query( $wpdb->prepare("UPDATE stacked_{$stacked_site_id}_options set option_value = %s where option_name = 'home'", 'https://' . $new_site_data->domain) );
        }

        // Activate WP Freighter
        $wpdb->query( "UPDATE {$new_table_prefix}options set `option_value` = 'a:1:{i:0;s:23:\"wp-freighter/wp-freighter.php\";}' WHERE `option_name` = 'active_plugins'" );
        // Fix permissions
        $wpdb->query( "UPDATE {$new_table_prefix}options set `option_name` = 'stacked_{$stacked_site_id}_user_roles' WHERE `option_name` = '{$db_prefix}user_roles'" );
        
        // Check if default theme is installed on new site
        
        if ( $files_mode == "dedicated" ) {
            $default_theme_path = ABSPATH . "content/$stacked_site_id/themes/" . WP_DEFAULT_THEME ."/";
            if ( ! file_exists( $default_theme_path ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
                include_once ABSPATH . 'wp-admin/includes/theme.php';

                // Remove hooks that trigger update checks
                remove_action( 'upgrader_process_complete', [ 'Language_Pack_Upgrader', 'async_upgrade' ], 20 );
                remove_action( 'upgrader_process_complete', 'wp_version_check', 10 );
                remove_action( 'upgrader_process_complete', 'wp_update_plugins', 10 );
                remove_action( 'upgrader_process_complete', 'wp_update_themes', 10 );
                // FIX: Use an anonymous class to silence the upgrader output completely
                $skin = new class extends \WP_Upgrader_Skin {
                    public function feedback( $string, ...$args ) { /* Silence */ }
                    public function header() { /* Silence */ }
                    public function footer() { /* Silence */ }
                    public function error( $errors ) { /* Silence */ }
                };
                $upgrader = new \Theme_Upgrader( $skin );
                
                $api      = themes_api( 'theme_information', [ 'slug'  => WP_DEFAULT_THEME, 'fields' => [ 'sections' => false ] ] );
                // No ob_start/ob_clean needed anymore because the skin generates no output
                $result   = $upgrader->run( [
                    'package'           => $api->download_link,
                    'destination'       => $default_theme_path,
                    'clear_destination' => false,
                    'clear_working'     => true,
                    'hook_extra'        => [
                        'type'   => 'theme',
                        'action' => 'install',
                    ],
                ] );
            }
        }
        
        $table_prefix = $original_table_prefix;
        wp_set_wpdb_vars();
        return Sites::fetch();
    }

    public function clone_existing( $request ) {
        global $wpdb;

        $params    = $request->get_json_params();
        $source_id = isset( $params['source_id'] ) ? $params['source_id'] : false;
        
        // Retrieve custom name/domain from request, default to empty if not set
        $new_name   = isset( $params['name'] ) ? $params['name'] : "";
        $new_domain = isset( $params['domain'] ) ? $params['domain'] : "";

        if ( $source_id && $source_id !== 'main' ) {
            $source    = ABSPATH . "content/$source_id";
            $db_prefix = "stacked_{$source_id}_";
        } else {
            // Force Main Site paths regardless of current view context
            $source    = ABSPATH . 'wp-content';
            $db_prefix = $this->get_primary_prefix();
        }

        $db_prefix_primary   = $this->get_primary_prefix();
        $stacked_sites       = ( new Sites )->get();
        $current_stacked_ids = array_column( $stacked_sites, "stacked_site_id" );
        $stacked_site_id     = ( empty( $current_stacked_ids ) ? 1 : (int) max( $current_stacked_ids ) + 1 );
        $tables              = array_column( $wpdb->get_results("show tables"), "Tables_in_". DB_NAME );
        
        // 1. Duplicate Database Tables
        foreach ( $tables as $table ) {
            if ( substr( $table, 0, strlen( $db_prefix ) ) != $db_prefix ) {
                continue;
            }
            $table_name     = substr( $table, strlen( $db_prefix ), strlen( $table ) );
            $new_table_name = "stacked_{$stacked_site_id}_{$table_name}";
            $wpdb->query( "DROP TABLE  IF EXISTS $new_table_name" );
            $wpdb->query( "CREATE TABLE $new_table_name LIKE $table" );
            $wpdb->query( "INSERT INTO $new_table_name SELECT * FROM $table" );
            
            if ( $table_name == "options" ) {
                $wpdb->query( "UPDATE $new_table_name set `option_name` = 'stacked_{$stacked_site_id}_user_roles' WHERE `option_name` = '{$db_prefix}user_roles'" );
            }
            if ( $table_name == "usermeta" ) {
                $wpdb->query( "UPDATE $new_table_name set `meta_key` = 'stacked_{$stacked_site_id}_capabilities' WHERE `meta_key` = '{$db_prefix}capabilities'" );
                $wpdb->query( "UPDATE $new_table_name set `meta_key` = 'stacked_{$stacked_site_id}_user_level' WHERE `meta_key` = '{$db_prefix}user_level'" );
            }
        }

        // 2. Generate Fallback Name if user left it empty
        if ( empty( $new_name ) && $source_id ) {
            foreach( $stacked_sites as $site ) {
                if ( $site['stacked_site_id'] == $source_id ) {
                    $new_name = $site['name'] . " (Clone)";
                    break;
                }
            }
        }

        $stacked_sites[] = [
            "stacked_site_id" => $stacked_site_id,
            "created_at"      => strtotime("now"),
            "name"            => $new_name,
            "domain"          => $new_domain
        ];

        $stacked_sites_serialize = serialize( $stacked_sites );
        $results = $wpdb->get_results("select option_id from {$db_prefix_primary}options where option_name = 'stacked_sites'");
        if ( empty( $results ) ) {
            $wpdb->query( $wpdb->prepare("INSERT INTO {$db_prefix_primary}options ( option_name, option_value) VALUES ( 'stacked_sites', %s )", $stacked_sites_serialize) );
        } else {
            $wpdb->query( $wpdb->prepare("UPDATE {$db_prefix_primary}options set option_value = %s where option_name = 'stacked_sites'", $stacked_sites_serialize) );
        }

        // 3. Duplicate Files
        if ( ( new Configurations )->get()->files == "dedicated" ) {
            if ( ! file_exists( ABSPATH . "content/$stacked_site_id/" ) ) {
                mkdir( ABSPATH . "content/$stacked_site_id/", 0777, true );
                $dest   = ABSPATH . "content/$stacked_site_id";
                
                if ( file_exists( $source ) ) {
                    foreach (
                        $iterator = new \RecursiveIteratorIterator(
                            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                            \RecursiveIteratorIterator::SELF_FIRST) as $item
                    ) 
                    {
                        $subPathName = $iterator->getSubPathname();
                        $destination_file = $dest . DIRECTORY_SEPARATOR . $subPathName;

                        // 1. Handle Symlinks (Shortcuts)
                        if ( $item->isLink() ) {
                            // Get where the original link points
                            $target = readlink( $item->getPathname() );
                            // Create a new symlink at the destination pointing to the same target
                            symlink( $target, $destination_file );
                        }
                        // 2. Handle Directories (mkdir)
                        elseif ( $item->isDir() ) {
                             if ( ! file_exists( $destination_file ) ) {
                                mkdir( $destination_file );
                            }
                        } 
                        // 3. Handle Regular Files (copy)
                        else {
                            copy( $item, $destination_file );
                        }
                    }

                    // Kinsta Compatibility (Ensures they are copied even if cloning a sub-site that lacked them)
                    $this->copy_kinsta_assets( $stacked_site_id );
                }
            }
        }
        
        // Apply Domain Mapping Update if domain is set
        if ( ! empty ( $new_domain ) ) {
             $wpdb->query( $wpdb->prepare("UPDATE stacked_{$stacked_site_id}_options set option_value = %s where option_name = 'siteurl'", 'https://' . $new_domain) );
             $wpdb->query( $wpdb->prepare("UPDATE stacked_{$stacked_site_id}_options set option_value = %s where option_name = 'home'", 'https://' . $new_domain) );
        }

        ( new Configurations )->refresh_configs();

        return array_values($stacked_sites);
    }

    private function copy_kinsta_assets( $stacked_site_id ) {
        // Only run if using dedicated files
        if ( ( new Configurations )->get()->files != "dedicated" ) {
            return;
        }

        // Hardcoded Source: Always pull from the Main Site root
        $mu_plugins_source = ABSPATH . 'wp-content/mu-plugins';
        $mu_plugins_dest   = ABSPATH . "content/$stacked_site_id/mu-plugins";

        // 1. Copy kinsta-mu-plugins.php
        if ( file_exists( "$mu_plugins_source/kinsta-mu-plugins.php" ) ) {
            if ( ! file_exists( $mu_plugins_dest ) ) {
                mkdir( $mu_plugins_dest, 0777, true );
            }
            copy( "$mu_plugins_source/kinsta-mu-plugins.php", "$mu_plugins_dest/kinsta-mu-plugins.php" );
        }

        // 2. Copy kinsta-mu-plugins/ directory
        if ( file_exists( "$mu_plugins_source/kinsta-mu-plugins" ) ) {
            if ( ! file_exists( $mu_plugins_dest ) ) {
                mkdir( $mu_plugins_dest, 0777, true );
            }
            Sites::copy_recursive( "$mu_plugins_source/kinsta-mu-plugins", "$mu_plugins_dest/kinsta-mu-plugins" );
        }
    }

    // Helper to abstract the primary prefix logic used in multiple places
    private function get_primary_prefix() {
        global $wpdb;
        $db_prefix = $wpdb->prefix;
        $db_prefix_primary = ( defined( 'TABLE_PREFIX' ) ? TABLE_PREFIX : $db_prefix );
        if ( $db_prefix_primary == "TABLE_PREFIX" ) { 
            $db_prefix_primary = $db_prefix;
        }
        return $db_prefix_primary;
    }

    public function configurations() {
        global $wpdb;
        $db_prefix         = $wpdb->prefix;
        $db_prefix_primary = ( defined( 'TABLE_PREFIX' ) ? TABLE_PREFIX : $db_prefix );
        if ( $db_prefix_primary == "TABLE_PREFIX" ) { 
            $db_prefix_primary = $db_prefix;
        }
        $configurations     = maybe_unserialize( $wpdb->get_results("select option_value from {$db_prefix_primary}options where option_name = 'stacked_configurations'")[0]->option_value );
        if ( empty( $configurations ) ) {
            $configurations = [];
        }
        return $configurations;
    }

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
        foreach( $stacked_sites as $stacked_site ) {
            if ( $stacked_site_id == $stacked_site['stacked_site_id'] ) {
                $item = $stacked_site;
                break;
            }
        }
        
        if ( ! empty( $stacked_site_id ) ) {
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
                'meta'  => [ 'onclick' => 'fetch( "' . esc_url( get_rest_url( null, 'wp-freighter/v1/exit' ) ) . '", { method: "POST", headers: { "X-WP-Nonce": "' . wp_create_nonce( 'wp_rest' ) . '" } } ).then( () => window.location.reload() ); return false;' ]
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

    public function admin_menu() {
        if ( current_user_can( 'manage_options' ) ) {
            add_management_page( "WP Freighter", "WP Freighter", "manage_options", "wp-freighter", array( $this, 'admin_view' ) );
        }
    }

    public function admin_view() {
        require_once plugin_dir_path( __DIR__ ) . '/templates/admin-wp-freighter.php';
    }
    
    public function activate() {
        // Add default configurations right after $table_prefix
        $lines_to_add = [
            '',
            '/* WP Freighter */',
            '$stacked_site_id = ( isset( $_COOKIE[ "stacked_site_id" ] ) ? $_COOKIE[ "stacked_site_id" ] : "" );',
            'if ( defined( \'WP_CLI\' ) && WP_CLI ) { $stacked_site_id = getenv( \'STACKED_SITE_ID\' ); }',
            'if ( ! empty( $stacked_site_id ) ) { define( \'TABLE_PREFIX\', $table_prefix ); $table_prefix = "stacked_{$stacked_site_id}_"; }',
        ];
        if ( file_exists( ABSPATH . "wp-config.php" ) ) {
            $wp_config_file = ABSPATH . "wp-config.php";
        }

        if ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
            $wp_config_file = dirname( ABSPATH ) . '/wp-config.php';
        }

        if ( empty ( $wp_config_file ) ) {
            ( new Configurations )->update_config( "unable_to_save", $lines_to_add );
            return;
        }

        $wp_config_content = file_get_contents( $wp_config_file );
        $working           = explode( "\n", $wp_config_content );
        // Remove WP Freighter configs. Any lines containing '/* WP Freighter */', 'stacked_site_id' and '$stacked_mappings'.
        foreach( $working as $key => $line ) {
            if ( strpos( $line, '/* WP Freighter */' ) !== false || strpos( $line, 'stacked_site_id' ) !== false || strpos( $line, '$stacked_mappings' ) !== false ) {
                unset( $working[ $key ] );
            }
        }

        // Comment out manually set WP_HOME or WP_SITEURL
        foreach( $working as $key => $line ) {
            $compare_home     = "define('WP_HOME'";
            $compare_site_url = "define('WP_SITEURL'";
            if ( substr( $line, 0, 16 ) === $compare_home ) {
                $working[ $key ] = "//$line";
            }
            if ( substr( $line, 0, 19 ) === $compare_site_url ) {
                $working[ $key ] = "//$line";
            }
        }

        // Append WP_CACHE_KEY_SALT with unique identifier if found
        foreach( $working as $key => $line ) {
            $wp_cache_key_salt = "define('WP_CACHE_KEY_SALT', '";
            if ( substr( $line, 0, 29 ) === $wp_cache_key_salt ) {
                $updated_line    = str_replace( "define('WP_CACHE_KEY_SALT', '", "define('WP_CACHE_KEY_SALT', \$stacked_site_id . '", $line );
                $working[ $key ] = $updated_line;
            }
        }

        foreach( $working as $key => $line ) {
            if ( strpos( $line, '$table_prefix' ) !== false ) {
                $table_prefix_line = $key;
                break;
            }
        }

        // Remove extra line space if found.
        if ( $working[ $table_prefix_line + 1 ] == "" && $working[ $table_prefix_line + 2 ] == "" ) {
            unset( $working[ $table_prefix_line + 1 ] );
        }

        // Updated content
        $updated = array_merge( array_slice( $working, 0, $table_prefix_line + 1, true ), $lines_to_add, array_slice( $working, $table_prefix_line + 1, count( $working ), true ) );
        // Save changes to wp-config.php
        $results = @file_put_contents( $wp_config_file, implode( "\n", $updated ) );
        if ( empty( $results ) ) {
            ( new Configurations )->update_config( "unable_to_save", $lines_to_add );
        }
    }

    public function deactivate() {
        if ( file_exists( ABSPATH . "wp-config.php" ) ) {
            $wp_config_file = ABSPATH . "wp-config.php";
        }

        if ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
            $wp_config_file = dirname( ABSPATH ) . '/wp-config.php';
        }

        if ( empty ( $wp_config_file ) ) {
            return;
        }

        $wp_config_content = file_get_contents( $wp_config_file );
        $working           = explode( "\n", $wp_config_content );
        // Remove WP Freighter configs. Any lines containing '/* WP Freighter */', 'stacked_site_id' and '$stacked_mappings'.
        foreach( $working as $key => $line ) {
            if ( strpos( $line, '/* WP Freighter */' ) !== false || strpos( $line, 'stacked_site_id' ) !== false || strpos( $line, '$stacked_mappings' ) !== false ) {
                unset( $working[ $key ] );
            }
        }

        // Remove extra line space if found.
        if ( $working[ $table_prefix_line + 1 ] == "" && $working[ $table_prefix_line + 2 ] == "" ) {
            unset( $working[ $table_prefix_line + 1 ] );
        }

        // Save changes to wp-config.php
        file_put_contents( $wp_config_file, implode( "\n", $working ) );
    }

    public function get_site_stats( $request ) {
        $params = $request->get_json_params();
        $site_id = (int) $params['site_id'];
        
        $path = ABSPATH . "content/$site_id/";
        
        if ( file_exists( $path ) ) {
            $bytes = Sites::get_directory_size( $path );
            
            // Calculate relative path for display
            $relative_path = str_replace( ABSPATH, '', $path );

            return [
                'has_dedicated_content' => true,
                'path' => $relative_path, // Sending the relative path now
                'size' => Sites::format_size( $bytes )
            ];
        }

        return [
            'has_dedicated_content' => false,
            'path' => '',
            'size' => 0
        ];
    }

    public function auto_login( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        $site_id = (int) $params['site_id'];

        if ( empty( $site_id ) ) {
            return new \WP_Error( 'invalid_site_id', 'Invalid Site ID', [ 'status' => 400 ] );
        }

        // 1. Check and Inject mu-plugin
        $content_dir = ABSPATH . "content/$site_id";
        $mu_dir      = $content_dir . "/mu-plugins";
        $mu_file     = $mu_dir . "/captaincore-helper.php";

        if ( ! file_exists( $mu_dir ) ) {
            mkdir( $mu_dir, 0777, true );
        }

        if ( ! file_exists( $mu_file ) ) {
            $plugin_content = <<<'EOD'
<?php
/**
 * Plugin Name: CaptainCore Helper
 * Plugin URI: https://captaincore.io
 * Description: Collection of helper functions for CaptainCore
 * Version: 0.2.8
 * Author: CaptainCore
 * Author URI: https://captaincore.io
 * Text Domain: captaincore-helper
 */

function captaincore_quick_login_action_callback() {
    $post = json_decode( file_get_contents( 'php://input' ) );
    if ( ! isset( $post->token ) || $post->token != md5( AUTH_KEY ) ) {
        return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 404 ] );
        wp_die();
    }
    $post->user_login = str_replace( "%20", " ", $post->user_login );
    $user     = get_user_by( 'login', $post->user_login );
    $password = wp_generate_password();
    $token    = sha1( $password );
    update_user_meta( $user->ID, 'captaincore_login_token', $token );
    $query_args = [
            'user_id'                 => $user->ID,
            'captaincore_login_token' => $token,
        ];
    $login_url    = wp_login_url();
    $one_time_url = add_query_arg( $query_args, $login_url );
    echo $one_time_url;
    wp_die();
}
add_action( 'wp_ajax_nopriv_captaincore_quick_login', 'captaincore_quick_login_action_callback' );

function captaincore_login_handle_token() {
    global $pagenow;
    if ( 'wp-login.php' !== $pagenow || empty( $_GET['user_id'] ) || empty( $_GET['captaincore_login_token'] ) ) {
        return;
    }
    if ( is_user_logged_in() ) {
        $error = sprintf( __( 'Invalid one-time login token, but you are logged in as \'%1$s\'. <a href="%2$s">Go to the dashboard instead</a>?', 'captaincore-login' ), wp_get_current_user()->user_login, admin_url() );
    } else {
        $error = sprintf( __( 'Invalid one-time login token. <a href="%s">Try signing in instead</a>?', 'captaincore-login' ), wp_login_url() );
    }
    $user = get_user_by( 'id', (int) $_GET['user_id'] );
    if ( ! $user ) {
        wp_die( $error );
    }
    $token    = get_user_meta( $user->ID, 'captaincore_login_token', true );
    $is_valid = false;
        if ( hash_equals( $token, $_GET['captaincore_login_token'] ) ) {
            $is_valid = true;
        }
    if ( ! $is_valid ) {
        wp_die( $error );
    }
    delete_user_meta( $user->ID, 'captaincore_login_token' );
    wp_set_auth_cookie( $user->ID, 1 );
    wp_safe_redirect( admin_url() );
    exit;
}
add_action( 'init', 'captaincore_login_handle_token' );

add_filter( 'auto_plugin_update_send_email', '__return_false' );
add_filter( 'auto_theme_update_send_email', '__return_false' );
EOD;
            file_put_contents( $mu_file, $plugin_content );
        }

        // 2. Identify Tables
        $prefix     = "stacked_{$site_id}_";
        $meta_table = $prefix . "usermeta";
        $opt_table  = $prefix . "options";

        // 3. Find a random Administrator
        // Note: WP Freighter renames 'wp_capabilities' to 'stacked_X_capabilities' during clone
        $cap_key = $prefix . "capabilities"; 
        
        $sql = "SELECT user_id FROM $meta_table WHERE meta_key = '$cap_key' AND meta_value LIKE '%administrator%' ORDER BY RAND() LIMIT 1";
        $user_id = $wpdb->get_var( $sql );

        if ( ! $user_id ) {
             return new \WP_Error( 'no_admin', 'No administrator found for this site.', [ 'status' => 404 ] );
        }

        // 4. Generate Token
        $password = wp_generate_password();
        $token    = sha1( $password );

        // 5. Save Token to Stacked DB (Manual update_user_meta)
        // Check if meta exists
        $existing_meta = $wpdb->get_var( $wpdb->prepare( "SELECT umeta_id FROM $meta_table WHERE user_id = %d AND meta_key = 'captaincore_login_token'", $user_id ) );

        if ( $existing_meta ) {
            $wpdb->query( $wpdb->prepare( "UPDATE $meta_table SET meta_value = %s WHERE umeta_id = %d", $token, $existing_meta ) );
        } else {
            $wpdb->query( $wpdb->prepare( "INSERT INTO $meta_table (user_id, meta_key, meta_value) VALUES (%d, 'captaincore_login_token', %s)", $user_id, $token ) );
        }

        // 6. Get Site URL
        $site_url = $wpdb->get_var( "SELECT option_value FROM $opt_table WHERE option_name = 'siteurl'" );
        $site_url = rtrim( $site_url, '/' );

        // 7. Construct Link
        $query_args = [
            'user_id'                 => $user_id,
            'captaincore_login_token' => $token,
        ];
        
        $login_url = $site_url . '/wp-login.php';
        // We use our own http_build_query logic here since add_query_arg might use the current site's context
        $final_url = $login_url . '?' . http_build_query( $query_args );

        return [ 'url' => $final_url ];
    }

}