<?php

namespace StackableMode;

class Run {

    public function __construct() {
        if ( defined( 'STACKABLE_DEV_MODE' ) ) {
            add_filter('edd_sl_api_request_verify_ssl', '__return_false');
            add_filter('https_ssl_verify', '__return_false');
            add_filter('https_local_ssl_verify', '__return_false');
            add_filter('http_request_host_is_external', '__return_true');
            define( 'STACKABLE_EDD_SL_STORE_URL', 'https://stackable.test' );
            define( 'STACKABLE_EDD_SL_ITEM_ID', 44 );
        } else {
            define( 'STACKABLE_EDD_SL_STORE_URL', 'https://stackablewp.com' );
            define( 'STACKABLE_EDD_SL_ITEM_ID', 74 );
        }
        add_action( 'wp_ajax_stacked_ajax', [ $this, 'ajax_actions' ] );
        add_action( 'admin_bar_menu', [ $this, 'admin_toolbar' ], 100 );
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        register_activation_hook( plugin_dir_path( __DIR__ ) . "stackable.php", [ $this, 'activate' ] );
        register_deactivation_hook( plugin_dir_path( __DIR__ ) . "stackable.php", [ $this, 'deactivate' ] );
        $license_key = ( new Configurations() )->license_key();
        $plugin_file = plugin_dir_path( __DIR__ ) . "stackable.php";
        new Updater( STACKABLE_EDD_SL_STORE_URL, $plugin_file, [
            'version' => '1.0.0',
            'license' => $license_key,
            'item_id' => STACKABLE_EDD_SL_ITEM_ID,
            'author'  => 'Austin Ginder',
            'url'     => home_url(),
            'beta'    => false
         ] );
    }

    public function ajax_actions() {
        global $wpdb;
        $db_prefix          = $wpdb->prefix;
        $db_prefix_primary  = $db_prefix_primary = ( defined( 'TABLE_PREFIX' ) ? TABLE_PREFIX : $db_prefix );
        if ( $db_prefix_primary == "TABLE_PREFIX" ) { 
            $db_prefix_primary = $db_prefix;
        }
        $command       = $_POST['command'];
        $value         = $_POST['value'];
        $stacked_sites = ( new Sites )->get();

        if ( $_GET['command'] == "exitStackable" ) {
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

        if ( $command == "activateLicense" ) {
            ( new Configurations )->activate_license( $value );
            echo json_encode( ( new Configurations )->get() );
        }

        if ( $command == "verifyLicense" ) {
            $key = ( new Configurations )->license_key();
            if ( $key != $value ) {
                ( new Configurations )->activate_license( $value );
            } else {
                ( new Configurations )->verify_license();
            }
            echo json_encode( ( new Configurations )->get() );
        }

        if ( $command == "saveConfigurations" ) {
            $value = (object) $value;
            ( new Configurations )->update( $value->configurations );
            ( new Sites )->update( $value->sites );
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
            echo json_encode( $stacked_sites );
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
            $stacked_site_id     = ( is_array( $current_stacked_ids ) ? max( $current_stacked_ids ) + 1 : 1 );
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
            echo json_encode( $stacked_sites );

            // Install WordPress to new table prefix
            $table_prefix = $new_table_prefix;
            wp_set_wpdb_vars();
            wp_install( $new_site->title, $new_site->username, $new_site->email, true, '', wp_slash( $new_site->password ), "en" );

            // Activate Stackable
            $wpdb->query( "UPDATE {$new_table_prefix}options set `option_value` = 'a:1:{i:0;s:23:\"stackable/stackable.php\";}' WHERE `option_name` = 'active_plugins'" );

            // Fix permissions
            $wpdb->query( "UPDATE {$new_table_prefix}options set `option_name` = 'stacked_{$stacked_site_id}_user_roles' WHERE `option_name` = '{$db_prefix}user_roles'" );
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
            $stacked_site_id     = ( is_array( $current_stacked_ids ) ? max( $current_stacked_ids ) + 1 : 1 );
            $tables              = array_column( $wpdb->get_results("show tables"), "Tables_in_". DB_NAME );
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
            $stacked_sites[] = [
                "stacked_site_id" => $stacked_site_id,
                "created_at"      => strtotime("now"),
                "name"            => "",
                "domain"          => ""
            ];

            foreach ( $tables as $table ) {
                if ( strpos( $table, $string ) !== FALSE) {
                    $wpdb->query( "UPDATE $table set `option_name` = 'stacked_sites' WHERE `option_name` = '{$db_prefix}user_roles'" );
                }
            }
            $stacked_sites_serialize = serialize( $stacked_sites );
            $results                 = $wpdb->get_results("select option_id from ${db_prefix_primary}options where option_name = 'stacked_sites'");
            if ( empty( $results ) ) {
                $wpdb->query("INSERT INTO {$db_prefix_primary}options ( option_name, option_value) VALUES ( 'stacked_sites', '$stacked_sites_serialize' )");
            } else {
                $wpdb->query("UPDATE ${db_prefix_primary}options set option_value = '$stacked_sites_serialize' where option_name = 'stacked_sites'");
            }

            echo json_encode( $stacked_sites );
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
        $stacked_site_id = $_COOKIE[ "stacked_site_id" ];
        foreach( $stacked_sites as $stacked_site ) {
            if ( $stacked_site_id == $stacked_site['stacked_site_id'] ) {
                $item = $stacked_site;
                break;
            }
        }
        $label = ( $item['name'] ? "{$item['name']} - " : "" ) . wp_date( "M j, Y, g:i a", $item['created_at'] );
        if ( ! empty( $stacked_site_id ) ) {
            $admin_bar->add_menu( [
                'id'    => 'stackable-mode',
                'title' => '<span class="ab-icon dashicons dashicons-welcome-view-site"></span> <span style="font-size: 0.8em !important;background-color: #fff;color: #000;padding: 1px 4px;border-radius: 2px;margin-left: 2px;position:relative;top:-2px">' . $label .'</span>',
                'href'  => '/wp-admin/tools.php?page=stackable-mode',
            ] );
            $admin_bar->add_menu( [
                'id'    => 'stackable-mode-exit',
                'title' => '<span class="ab-icon dashicons dashicons-backup"></span>Exit Stackable Mode',
                'href'  => '/wp-admin/admin-ajax.php?action=stacked_ajax&command=exitStackable',
            ] );
        }
        if ( empty( $stacked_site_id ) ) {
            $admin_bar->add_menu( [
                'id'    => 'stackable-mode-enter',
                'title' => '<span class="ab-icon dashicons dashicons-welcome-view-site"></span>View Stacked Sites',
                'href'  => '/wp-admin/tools.php?page=stackable-mode',
            ] );
        }
    }

    public function admin_menu() {
        if ( current_user_can( 'manage_options' ) ) {
            add_management_page( "Stackable Mode", "Stackable Mode", "manage_options", "stackable-mode", array( $this, 'admin_view' ) );
        }
    }

    public function admin_view() {
        if ( false === ( $stackable_verify_license = get_transient( 'stackable_verify_license' ) ) ) {
            set_transient( 'stackable_verify_license', "Verified Stackable license key.", 12 * HOUR_IN_SECONDS );
            ( new Configurations )->verify_license();
        }
        require_once plugin_dir_path( __DIR__ ) . '/templates/admin-stackable.php';
    }
    
    public function activate() {
        $license_file = plugin_dir_path( __DIR__ ) . "purchased_license.php";
        if ( file_exists ( $license_file ) ) {
            $license_key = file_get_contents ( $license_file );
            ( new Configurations )->activate_license( $license_key );
            unlink ( $license_file );
        }
        if ( ! file_exists( ABSPATH . "wp-config.php" ) ) {
            return;
        }
        $wp_config_content = file_get_contents( ABSPATH . "wp-config.php" );
        $working           = explode( "\n", $wp_config_content );

        // Remove Stackable configs. Any lines containing '/* Stackable Mode */', 'stacked_site_id' and '$stacked_mappings'.
        foreach( $working as $key => $line ) {
            if ( strpos( $line, '/* Stackable Mode */' ) !== false || strpos( $line, 'stacked_site_id' ) !== false || strpos( $line, '$stacked_mappings' ) !== false ) {
                unset( $working[ $key ] );
            }
        }

        // Add default stackable configurations right after $table_prefix
        $lines_to_add = [
            '',
            '/* Stackable Mode */',
            '$stacked_site_id = ( isset( $_COOKIE[ "stacked_site_id" ] ) ? $_COOKIE[ "stacked_site_id" ] : "" );',
            'if ( defined( \'WP_CLI\' ) && WP_CLI ) { $stacked_site_id = getenv( \'STACKED_SITE_ID\' ); }',
            'if ( ! empty( $stacked_site_id ) ) { define( \'TABLE_PREFIX\', $table_prefix ); $table_prefix = "stacked_{$stacked_site_id}_"; }',
        ];
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
        $results = @file_put_contents( ABSPATH . "wp-config.php", implode( "\n", $updated ) );

        if ( empty( $results ) ) {
            ( new Configurations )->update_config( "unable_to_save", $lines_to_add );
        }
    }

    public function deactivate() {
        if ( ! file_exists( ABSPATH . "wp-config.php" ) ) {
            echo "Can not locate wp-config.php file";
            return;
        }
        $wp_config_content = file_get_contents( ABSPATH . "wp-config.php" );
        $working           = explode( "\n", $wp_config_content );

        // Remove Stackable configs. Any lines containing '/* Stackable Mode */', 'stacked_site_id' and '$stacked_mappings'.
        foreach( $working as $key => $line ) {
            if ( strpos( $line, '/* Stackable Mode */' ) !== false || strpos( $line, 'stacked_site_id' ) !== false || strpos( $line, '$stacked_mappings' ) !== false ) {
                unset( $working[ $key ] );
            }
        }

        // Remove extra line space if found.
        if ( $working[ $table_prefix_line + 1 ] == "" && $working[ $table_prefix_line + 2 ] == "" ) {
            unset( $working[ $table_prefix_line + 1 ] );
        }

        // Save changes to wp-config.php
        file_put_contents( ABSPATH . "wp-config.php", implode( "\n", $working ) );
    }

}