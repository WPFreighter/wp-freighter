<?php

namespace WPFreighter;

class Run {

    public function __construct() {
        if ( defined( 'WP_FREIGHTER_DEV_MODE' ) ) {
            add_filter('https_ssl_verify', '__return_false');
            add_filter('https_local_ssl_verify', '__return_false');
            add_filter('http_request_host_is_external', '__return_true');
        }
        add_action( 'wp_ajax_stacked_ajax', [ $this, 'ajax_actions' ] );
        add_action( 'admin_bar_menu', [ $this, 'admin_toolbar' ], 100 );
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        register_activation_hook( plugin_dir_path( __DIR__ ) . "wp-freighter.php", [ $this, 'activate' ] );
        register_deactivation_hook( plugin_dir_path( __DIR__ ) . "wp-freighter.php", [ $this, 'deactivate' ] );
        $plugin_file = dirname ( plugin_basename( __DIR__ ) ) . "/wp-freighter.php" ;
        add_filter( "plugin_action_links_{$plugin_file}", [ $this, 'settings_link' ] );
    }

    public function settings_link( $links ) {
        $settings_link = "<a href='/wp-admin/tools.php?page=wp-freighter'>" . __( 'Settings' ) . "</a>";
        // Adds the link to the end of the array.
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function ajax_actions() {
        global $wpdb;
        $db_prefix          = $wpdb->prefix;
        $db_prefix_primary  = $db_prefix_primary = ( defined( 'TABLE_PREFIX' ) ? TABLE_PREFIX : $db_prefix );
        if ( $db_prefix_primary == "TABLE_PREFIX" ) { 
            $db_prefix_primary = $db_prefix;
        }
        $command       = empty( $_POST['command'] ) ? "" : $_POST['command'];
        $value         = empty( $_POST['value'] ) ? "" : $_POST['value'];
        $stacked_sites = ( new Sites )->get();

        if ( isset( $_GET['command'] ) && $_GET['command'] == "exitWPFreighter" ) {
            setcookie( 'stacked_site_id', null, -1, '/');
            unset( $_COOKIE[ "stacked_site_id" ] );
            wp_redirect( $_SERVER['HTTP_REFERER'] );
            exit;
        }

        if ( $command == "fetchSites" ) {
            if ( empty( $stacked_sites ) ) {
                $stacked_sites = [];
            }
            echo json_encode( $stacked_sites );
        }

        if ( $command == "saveConfigurations" ) {
            $value = (object) $value;
            ( new Sites )->update( $value->sites );
            ( new Configurations )->update( $value->configurations );
            echo json_encode( ( new Configurations )->get() );
        }

        if ( $command == "switchTo" ) {
            setcookie( 'stacked_site_id', $value, time() + 31536000, '/' );
            $_COOKIE[ "stacked_site_id" ] = $value;
        }

        if ( $command == "deleteSite" ) {
            foreach( $stacked_sites as $key => $item ) {
                if ( $value == $item['stacked_site_id'] ) {
                    unset( $stacked_sites[$key] );  
                }
            }

            $stacked_sites_serialize = serialize( $stacked_sites );
            $wpdb->query("UPDATE ${db_prefix_primary}options set option_value = '$stacked_sites_serialize' where option_name = 'stacked_sites'");

            if ( ! empty ( $value ) ) {
                $site_table_prefix = "stacked_{$value}_";
                $tables            = array_column( $wpdb->get_results("show tables"), "Tables_in_". DB_NAME );
                foreach ( $tables as $table ) {
                    if ( substr( $table, 0, strlen( $site_table_prefix ) ) != $site_table_prefix ) {
                        continue;
                    }
                    $wpdb->query( "DROP TABLE IF EXISTS $table" );
                }
            }
            echo ( new Sites )->get_json();
        }

        if ( $command == "newSite" ) {

            global $wpdb, $table_prefix;
            $db_prefix         = $wpdb->prefix;
            $db_prefix_primary = ( defined( 'TABLE_PREFIX' ) ? TABLE_PREFIX : $db_prefix );
            if ( $db_prefix_primary == "TABLE_PREFIX" ) { 
                $db_prefix_primary = $db_prefix;
            }

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

            $new_site            = (object) $value;
            $stacked_sites       = ( new Sites )->get();
            $current_stacked_ids = array_column( $stacked_sites, "stacked_site_id" );
            $stacked_site_id     = ( empty( $current_stacked_ids ) ? 1 : (int) max( $current_stacked_ids ) + 1 );
            $new_table_prefix    = "stacked_{$stacked_site_id}_";
            $tables              = array_column( $wpdb->get_results("show tables"), "Tables_in_". DB_NAME );
            foreach ( $tables as $table ) {
                if ( substr( $table, 0, strlen( $new_table_prefix ) ) != $new_table_prefix ) {
                    continue;
                }
                $wpdb->query( "DROP TABLE IF EXISTS $table" );
            }
            $stacked_sites[] = [
                "stacked_site_id" => $stacked_site_id,
                "created_at"      => strtotime("now"),
                "name"            => $new_site->name,
                "domain"          => $new_site->domain
            ];

            $stacked_sites_serialize = serialize( $stacked_sites );
            $results                 = $wpdb->get_results("select option_id from ${db_prefix_primary}options where option_name = 'stacked_sites'");
            if ( empty( $results ) ) {
                $wpdb->query("INSERT INTO {$db_prefix_primary}options ( option_name, option_value) VALUES ( 'stacked_sites', '$stacked_sites_serialize' )");
            } else {
                $wpdb->query("UPDATE ${db_prefix_primary}options set option_value = '$stacked_sites_serialize' where option_name = 'stacked_sites'");
            }
            echo ( new Sites )->get_json();

            ( new Configurations )->refresh_configs();

            // Prepare new content folder if needed
            if ( ( new Configurations )->get()->files == "dedicated" ) {
                if ( ! file_exists( ABSPATH . "content/$stacked_site_id/" ) ) {
                    mkdir( ABSPATH . "content/$stacked_site_id/themes/", 0777, true );
                    mkdir( ABSPATH . "content/$stacked_site_id/plugins/", 0777, true );
                    mkdir( ABSPATH . "content/$stacked_site_id/uploads/", 0777, true );
                }
            }

            // Install WordPress to new table prefix
            $table_prefix = $new_table_prefix;
            wp_set_wpdb_vars();
            wp_install( $new_site->title, $new_site->username, $new_site->email, true, '', wp_slash( $new_site->password ), "en" );

            if ( ! empty ( $new_site->domain ) ) {
                $wpdb->query("UPDATE stacked_{$stacked_site_id}_options set option_value = 'https://{$new_site->domain}' where option_name = 'siteurl'");
                $wpdb->query("UPDATE stacked_{$stacked_site_id}_options set option_value = 'https://{$new_site->domain}' where option_name = 'home'");
            }

            // Activate WP Freighter
            $wpdb->query( "UPDATE {$new_table_prefix}options set `option_value` = 'a:1:{i:0;s:23:\"wp-freighter/wp-freighter.php\";}' WHERE `option_name` = 'active_plugins'" );

            // Fix permissions
            $wpdb->query( "UPDATE {$new_table_prefix}options set `option_name` = 'stacked_{$stacked_site_id}_user_roles' WHERE `option_name` = '{$db_prefix}user_roles'" );

            // Check if default theme is installed on new site
            $default_theme_path = ABSPATH . "content/$stacked_site_id/themes/" . WP_DEFAULT_THEME ."/";
            if ( ! file_exists( $default_theme_path ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
                include_once ABSPATH . 'wp-admin/includes/theme.php';
                $skin     = new \WP_Ajax_Upgrader_Skin();
                $upgrader = new \Theme_Upgrader( $skin );
                $api      = themes_api( 'theme_information', [ 'slug'  => WP_DEFAULT_THEME, 'fields' => [ 'sections' => false ] ] );
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

        if ( $command == "cloneExisting" ) {
            global $wpdb, $table_prefix;
            $db_prefix         = $wpdb->prefix;
            $db_prefix_primary = ( defined( 'TABLE_PREFIX' ) ? TABLE_PREFIX : $db_prefix );
            if ( $db_prefix_primary == "TABLE_PREFIX" ) { 
                $db_prefix_primary = $db_prefix;
            }
            $stacked_sites       = ( new Sites )->get();
            $current_stacked_ids = array_column( $stacked_sites, "stacked_site_id" );
            $stacked_site_id     = ( empty( $current_stacked_ids ) ? 1 : (int) max( $current_stacked_ids ) + 1 );
            $tables              = array_column( $wpdb->get_results("show tables"), "Tables_in_". DB_NAME );
            // Scan through all table tables
            foreach ( $tables as $table ) {
                // Duplicate only required tables
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
            $stacked_sites[] = [
                "stacked_site_id" => $stacked_site_id,
                "created_at"      => strtotime("now"),
                "name"            => "",
                "domain"          => ""
            ];

            $stacked_sites_serialize = serialize( $stacked_sites );
            $results                 = $wpdb->get_results("select option_id from ${db_prefix_primary}options where option_name = 'stacked_sites'");
            if ( empty( $results ) ) {
                $wpdb->query("INSERT INTO {$db_prefix_primary}options ( option_name, option_value) VALUES ( 'stacked_sites', '$stacked_sites_serialize' )");
            } else {
                $wpdb->query("UPDATE ${db_prefix_primary}options set option_value = '$stacked_sites_serialize' where option_name = 'stacked_sites'");
            }

            echo ( new Sites )->get_json();
            // Prepare new content folder if needed
            if ( ( new Configurations )->get()->files == "dedicated" ) {
                if ( ! file_exists( ABSPATH . "content/$stacked_site_id/" ) ) {
                    mkdir( ABSPATH . "content/$stacked_site_id/", 0777, true );
                    $source = ABSPATH . "wp-content/";
                    $dest   = ABSPATH . "content/$stacked_site_id/";
                    foreach (
                        $iterator = new \RecursiveIteratorIterator(
                         new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                         \RecursiveIteratorIterator::SELF_FIRST) as $item
                       ) {
                         if ($item->isDir()) {
                           mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
                         } else {
                           copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
                         }
                       }
                }
            }
        }
        wp_die();
        return;
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
                'href'  => '/wp-admin/admin-ajax.php?action=stacked_ajax&command=exitWPFreighter',
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

}