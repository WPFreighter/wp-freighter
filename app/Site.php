<?php

namespace WPFreighter;

class Site {

    /**
     * Get a specific site by ID.
     */
    public static function get( $site_id ) {
        return Sites::fetch( $site_id );
    }

    /**
     * Create a new stacked site.
     */
    public static function create( $args ) {
        global $wpdb, $table_prefix;
        $defaults = [
            'title'    => 'New Site',
            'name'     => 'New Site',
            'domain'   => '',
            'username' => 'admin',
            'email'    => 'admin@example.com',
            'password' => wp_generate_password(),
        ];
        
        $data = (object) array_merge( $defaults, $args );

        // 1. Calculate New ID
        $stacked_sites       = ( new Sites )->get();
        $current_stacked_ids = array_column( $stacked_sites, "stacked_site_id" );
        $site_id             = ( empty( $current_stacked_ids ) ? 1 : (int) max( $current_stacked_ids ) + 1 );

        // 2. Prepare Database
        $original_prefix  = $table_prefix;
        $primary_prefix   = self::get_primary_prefix();
        $new_table_prefix = "stacked_{$site_id}_";
        
        // Cleanup existing tables
        $tables = array_column( $wpdb->get_results("show tables"), "Tables_in_". DB_NAME );
        foreach ( $tables as $table ) {
            if ( strpos( $table, $new_table_prefix ) === 0 ) {
                $wpdb->query( "DROP TABLE IF EXISTS $table" );
            }
        }

        // 3. Prepare Site Data
        // NOTE: We queue the data here but DO NOT save it yet to avoid "Table doesn't exist" errors.
        $stacked_sites[] = [
            "stacked_site_id" => $site_id,
            "created_at"      => time(),
            "name"            => sanitize_text_field( $data->name ),
            "domain"          => sanitize_text_field( $data->domain )
        ];

        // 4. Handle Filesystem (Dedicated/Hybrid)
        $files_mode = ( new Configurations )->get()->files;
        if ( in_array( $files_mode, [ 'dedicated', 'hybrid' ] ) ) {
            $content_path = ABSPATH . "content/$site_id";
            
            if ( ! file_exists( "$content_path/uploads/" ) ) {
                mkdir( "$content_path/uploads/", 0777, true );
            }

            if ( $files_mode == 'dedicated' ) {
                if ( ! file_exists( "$content_path/themes/" ) ) mkdir( "$content_path/themes/", 0777, true );
                if ( ! file_exists( "$content_path/plugins/" ) ) mkdir( "$content_path/plugins/", 0777, true );
                // Kinsta Compatibility
                self::copy_kinsta_assets( $site_id );
            }
        }

        // 5. Install WordPress
        try {
            // Context Switch
            $table_prefix = $new_table_prefix;
            wp_set_wpdb_vars();

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            
            ob_start();
            wp_install( $data->title, $data->username, $data->email, true, '', wp_slash( $data->password ), "en" );
            ob_end_clean();

            // Update Site URLs if domain provided
            if ( ! empty ( $data->domain ) ) {
                $url = 'https://' . sanitize_text_field( $data->domain );
                update_option( 'siteurl', $url );
                update_option( 'home', $url );
            }

            // Activate WP Freighter on the new site
            update_option( 'active_plugins', [ 'wp-freighter/wp-freighter.php' ] );
            
            // Fix Permissions (Copy roles from main site)
            $wpdb->query( "UPDATE {$new_table_prefix}options set `option_name` = 'stacked_{$site_id}_user_roles' WHERE `option_name` = '{$primary_prefix}user_roles'" );
            
            // Install Default Theme if Dedicated
            if ( $files_mode == "dedicated" ) {
                $default_theme_path = ABSPATH . "content/$site_id/themes/" . WP_DEFAULT_THEME ."/";
                
                if ( ! file_exists( $default_theme_path ) ) {
                    include_once ABSPATH . 'wp-admin/includes/theme.php';
                    
                    // Prevent updates during install
                    remove_action( 'upgrader_process_complete', [ 'Language_Pack_Upgrader', 'async_upgrade' ], 20 );
                    remove_action( 'upgrader_process_complete', 'wp_version_check', 10 );
                    remove_action( 'upgrader_process_complete', 'wp_update_plugins', 10 );
                    remove_action( 'upgrader_process_complete', 'wp_update_themes', 10 );
                    $skin     = self::get_silent_skin();
                    $upgrader = new \Theme_Upgrader( $skin );
                    $api = themes_api( 'theme_information', [ 'slug'  => WP_DEFAULT_THEME, 'fields' => [ 'sections' => false ] ] );
                    if ( ! is_wp_error( $api ) ) {
                        $upgrader->run( [
                            'package'           => $api->download_link,
                            'destination'       => $default_theme_path,
                            'clear_destination' => false,
                            'clear_working'     => true,
                            'hook_extra'        => [ 'type' => 'theme', 'action' => 'install' ],
                        ] );
                    }
                }
            }

        } catch ( \Exception $e ) {
            return new \WP_Error( 'install_failed', $e->getMessage() );
        } finally {
            // Restore Context
            $table_prefix = $original_prefix;
            wp_set_wpdb_vars();
        }

        ( new Sites )->update( $stacked_sites );
        ( new Configurations )->refresh_configs();

        return self::get( $site_id );
    }

    /**
     * Delete a stacked site.
     */
    public static function delete( $site_id ) {
        global $wpdb;

        $site_id = (int) $site_id;
        $sites   = ( new Sites )->get();
        $found   = false;

        foreach( $sites as $key => $site ) {
            if ( $site['stacked_site_id'] == $site_id ) {
                unset( $sites[$key] );
                $found = true;
            }
        }

        if ( ! $found ) return false;

        ( new Sites )->update( array_values( $sites ) );

        $prefix = "stacked_{$site_id}_";
        $tables = array_column( $wpdb->get_results("show tables"), "Tables_in_". DB_NAME );
        foreach ( $tables as $table ) {
            if ( strpos( $table, $prefix ) === 0 ) {
                $wpdb->query( "DROP TABLE IF EXISTS $table" );
            }
        }

        $content_path = ABSPATH . "content/$site_id/";
        if ( file_exists( $content_path ) ) {
            Sites::delete_directory( $content_path );
        }

        ( new Configurations )->refresh_configs();
        
        return true;
    }

    /**
     * Clone a site.
     */
    public static function clone( $source_id, $args = [] ) {
        global $wpdb;

        // 1. Determine Paths and Prefixes
        if ( $source_id && $source_id !== 'main' ) {
            $source_path = ABSPATH . "content/$source_id";
            $source_prefix = "stacked_{$source_id}_";
            $source_site = self::get( $source_id );
            $source_name = $source_site['name'];
        } else {
            $source_path   = ABSPATH . 'wp-content';
            $source_prefix = self::get_primary_prefix();
            $source_name   = "Main Site";
        }

        $new_name   = ! empty( $args['name'] ) ? $args['name'] : "$source_name (Clone)";
        $new_domain = ! empty( $args['domain'] ) ? $args['domain'] : "";

        // 2. Get New ID
        $sites   = ( new Sites )->get();
        $ids     = array_column( $sites, "stacked_site_id" );
        $new_id  = ( empty( $ids ) ? 1 : (int) max( $ids ) + 1 );
        
        // 3. Duplicate Tables
        $new_prefix = "stacked_{$new_id}_";
        $tables     = array_column( $wpdb->get_results("show tables"), "Tables_in_". DB_NAME );

        foreach ( $tables as $table ) {
            if ( strpos( $table, $source_prefix ) !== 0 ) continue;
            
            $suffix = substr( $table, strlen( $source_prefix ) );
            $new_table_name = $new_prefix . $suffix;

            $wpdb->query( "DROP TABLE IF EXISTS $new_table_name" );
            $wpdb->query( "CREATE TABLE $new_table_name LIKE $table" );
            $wpdb->query( "INSERT INTO $new_table_name SELECT * FROM $table" );

            if ( $suffix == "options" ) {
                $wpdb->query( "UPDATE $new_table_name set `option_name` = 'stacked_{$new_id}_user_roles' WHERE `option_name` = '{$source_prefix}user_roles'" );
            }
            if ( $suffix == "usermeta" ) {
                $wpdb->query( "UPDATE $new_table_name set `meta_key` = 'stacked_{$new_id}_capabilities' WHERE `meta_key` = '{$source_prefix}capabilities'" );
                $wpdb->query( "UPDATE $new_table_name set `meta_key` = 'stacked_{$new_id}_user_level' WHERE `meta_key` = '{$source_prefix}user_level'" );
            }
        }

        // 4. Register Site
        $sites[] = [
            "stacked_site_id" => $new_id,
            "created_at"      => time(),
            "name"            => $new_name,
            "domain"          => $new_domain
        ];
        ( new Sites )->update( $sites );

        // 5. Duplicate Files (If Dedicated)
        if ( ( new Configurations )->get()->files == "dedicated" ) {
             Sites::copy_recursive( $source_path, ABSPATH . "content/$new_id" );
             self::copy_kinsta_assets( $new_id );
        }

        // 6. Update Domain in DB
        if ( ! empty ( $new_domain ) ) {
            $url = 'https://' . $new_domain;
            $wpdb->query( $wpdb->prepare("UPDATE stacked_{$new_id}_options set option_value = %s where option_name = 'siteurl'", $url ) );
            $wpdb->query( $wpdb->prepare("UPDATE stacked_{$new_id}_options set option_value = %s where option_name = 'home'", $url ) );
        }

        ( new Configurations )->refresh_configs();

        return self::get( $new_id );
    }

    /**
     * Update site details.
     */
    public static function update( $site_id, $args ) {
        global $wpdb;
        $sites = ( new Sites )->get();
        $found = false;

        foreach( $sites as &$site ) {
            if ( $site['stacked_site_id'] == $site_id ) {
                if ( isset( $args['name'] ) )   $site['name'] = $args['name'];
                if ( isset( $args['domain'] ) ) $site['domain'] = $args['domain'];
                
                if ( isset( $args['domain'] ) ) {
                    $url = 'https://' . $args['domain'];
                    $wpdb->query( $wpdb->prepare("UPDATE stacked_{$site_id}_options set option_value = %s where option_name = 'siteurl'", $url ) );
                    $wpdb->query( $wpdb->prepare("UPDATE stacked_{$site_id}_options set option_value = %s where option_name = 'home'", $url ) );
                }

                $found = true;
                break;
            }
        }

        if ( $found ) {
            ( new Sites )->update( $sites );
            return self::get( $site_id );
        }
        
        return false;
    }

    /**
     * Generate an auto-login URL.
     */
    public static function login( $site_id, $redirect_to = '' ) {
        global $wpdb;

        // 1. Determine Target DB Prefix
        if ( 'main' === $site_id ) {
            $target_prefix = self::get_primary_prefix();
        } else {
            $site_id = (int) $site_id;
            $target_prefix  = "stacked_{$site_id}_";
        }
        
        $meta_table = $target_prefix . "usermeta";
        $cap_key    = $target_prefix . "capabilities";
        
        // 2. Find Admin (IN TARGET DB)
        $user_id = $wpdb->get_var( "SELECT user_id FROM $meta_table WHERE meta_key = '$cap_key' AND meta_value LIKE '%administrator%' LIMIT 1" );
        if ( ! $user_id ) return new \WP_Error( 'no_admin', 'No administrator found.' );

        // 3. Generate Token (IN TARGET DB)
        $token = sha1( wp_generate_password() );
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT umeta_id FROM $meta_table WHERE user_id = %d AND meta_key = 'captaincore_login_token'", $user_id ) );

        if ( $existing ) {
            $wpdb->query( $wpdb->prepare( "UPDATE $meta_table SET meta_value = %s WHERE umeta_id = %d", $token, $existing ) );
        } else {
            $wpdb->query( $wpdb->prepare( "INSERT INTO $meta_table (user_id, meta_key, meta_value) VALUES (%d, 'captaincore_login_token', %s)", $user_id, $token ) );
        }

        // 4. Ensure Helper Plugin Exists
        if ( 'main' !== $site_id ) {
            self::ensure_helper_plugin( $site_id );
        }

        // 5. Build URL
        $configurations = ( new Configurations )->get();
        $domain_mapping_on = ( isset($configurations->domain_mapping) && $configurations->domain_mapping === 'on' );

        // Get Base URL
        if ( 'main' === $site_id || ! $domain_mapping_on ) {
            $url_prefix = self::get_primary_prefix();
        } else {
            $url_prefix = $target_prefix;
        }
        
        $site_url = $wpdb->get_var( "SELECT option_value FROM {$url_prefix}options WHERE option_name = 'siteurl'" );
        $site_url = rtrim( $site_url, '/' );

        // [CRITICAL CHANGE] Logic to bypass Helper Plugin on first hit
        if ( 'main' !== $site_id && ! $domain_mapping_on ) {
            // Use special params that the Helper Plugin DOES NOT recognize
            $query_args = [
                'freighter_switch' => $site_id,
                'freighter_user'   => $user_id,
                'freighter_token'  => $token
            ];
        } else {
            // Standard Params for direct login
            $query_args = [
                'user_id'                 => $user_id,
                'captaincore_login_token' => $token
            ];
        }

        if ( ! empty( $redirect_to ) ) {
            $query_args['redirect_to'] = $redirect_to;
        }

        return $site_url . '/wp-login.php?' . http_build_query( $query_args );
    }

    /**
     * HELPER: Ensure the auth helper plugin exists.
     */
    private static function ensure_helper_plugin( $site_id ) {
        $mu_dir  = ABSPATH . "content/$site_id/mu-plugins";
        if ( ! file_exists( $mu_dir ) ) mkdir( $mu_dir, 0777, true );
        
        $mu_file = "$mu_dir/captaincore-helper.php";
        if ( ! file_exists( $mu_file ) ) {
            // Simplified helper plugin content
            $plugin_content = <<<'EOD'
<?php
/**
 * Plugin Name: CaptainCore Helper
 */
function captaincore_login_handle_token() {
    global $pagenow;
    if ( 'wp-login.php' !== $pagenow || empty( $_GET['user_id'] ) || empty( $_GET['captaincore_login_token'] ) ) return;
    
    $user = get_user_by( 'id', (int) $_GET['user_id'] );
    if ( ! $user ) wp_die( 'Invalid User' );
    
    $token = get_user_meta( $user->ID, 'captaincore_login_token', true );
    if ( ! hash_equals( $token, $_GET['captaincore_login_token'] ) ) wp_die( 'Invalid Token' );
    
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
    }

    /**
     * HELPER: Kinsta Assets Copy
     */
    private static function copy_kinsta_assets( $stacked_site_id ) {
        // Only run if using dedicated files
        if ( ( new Configurations )->get()->files != "dedicated" ) {
            return;
        }

        $mu_plugins_source = ABSPATH . 'wp-content/mu-plugins';
        $mu_plugins_dest   = ABSPATH . "content/$stacked_site_id/mu-plugins";

        if ( file_exists( "$mu_plugins_source/kinsta-mu-plugins.php" ) ) {
            if ( ! file_exists( $mu_plugins_dest ) ) mkdir( $mu_plugins_dest, 0777, true );
            copy( "$mu_plugins_source/kinsta-mu-plugins.php", "$mu_plugins_dest/kinsta-mu-plugins.php" );
        }

        if ( file_exists( "$mu_plugins_source/kinsta-mu-plugins" ) ) {
            if ( ! file_exists( $mu_plugins_dest ) ) mkdir( $mu_plugins_dest, 0777, true );
            Sites::copy_recursive( "$mu_plugins_source/kinsta-mu-plugins", "$mu_plugins_dest/kinsta-mu-plugins" );
        }
    }

    /**
     * HELPER: Get Primary Prefix
     */
    private static function get_primary_prefix() {
        global $wpdb;
        $db_prefix = $wpdb->prefix;
        $db_prefix_primary = ( defined( 'TABLE_PREFIX' ) ? TABLE_PREFIX : $db_prefix );
        if ( $db_prefix_primary == "TABLE_PREFIX" ) { 
            $db_prefix_primary = $db_prefix;
        }
        return $db_prefix_primary;
    }

    /**
     * HELPER: Get Silent Upgrader Skin
     */
    private static function get_silent_skin() {
        if ( ! class_exists( 'WP_Upgrader_Skin' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
        return new class extends \WP_Upgrader_Skin {
            public function feedback( $string, ...$args ) { /* Silence */ }
            public function header() { /* Silence */ }
            public function footer() { /* Silence */ }
            public function error( $errors ) { /* Silence */ }
        };
    }
}