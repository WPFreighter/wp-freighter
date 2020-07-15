<?php

namespace StackableMode;

class Configurations {

    protected $configurations    = [];
    protected $db_prefix_primary = "";

    public function __construct() {
        global $wpdb;
        $db_prefix         = $wpdb->prefix;
        $db_prefix_primary = ( defined( 'TABLE_PREFIX' ) ? TABLE_PREFIX : $db_prefix );
        if ( $db_prefix_primary == "TABLE_PREFIX" ) { 
            $db_prefix_primary = $db_prefix;
        }
        $this->db_prefix_primary = $db_prefix_primary;
        $configurations          = maybe_unserialize( $wpdb->get_results("select option_value from {$this->db_prefix_primary}options where option_name = 'stacked_configurations'")[0]->option_value );
        if ( empty( $configurations ) ) {
            $configurations = [ "files" => "shared", "domain_mapping" => "off" ];
        }
        $this->configurations    = $configurations;
    }

    public function get() {
        return (object) $this->configurations;
    }

    public function get_json() {
        $configurations = $this->configurations;
        if ( empty( $configurations ) ) {
            $configurations = [ "files" => "shared", "domain_mapping" => "off" ];
        }
        return json_encode( $configurations );
    }

    public function update_config( $key, $value ) {
        global $wpdb;
        $configurations           = $this->configurations;
        $configurations[ $key ]   = $value;
        $configurations_serialize = serialize( $configurations );
        $results                  = $wpdb->get_results("select option_id from {$this->db_prefix_primary}options where option_name = 'stacked_configurations'");
        if ( empty( $results ) ) {
            $wpdb->query("INSERT INTO {$this->db_prefix_primary}options ( option_name, option_value) VALUES ( 'stacked_configurations', '$configurations_serialize' )");
        } else {
            //$wpdb->query("UPDATE {$this->db_prefix_primary}options set option_value = '$configurations_serialize' where option_name = 'stacked_configurations'");
            $wpdb->query( $wpdb->prepare( "UPDATE {$this->db_prefix_primary}options set option_value = %s where option_name = 'stacked_configurations'", $configurations_serialize ) );
        }
    }

    public function update( $configurations ) {
        global $wpdb;
        $this->configurations     = $configurations;
        $configurations_serialize = serialize( $configurations );
        $results                  = $wpdb->get_results("select option_id from {$this->db_prefix_primary}options where option_name = 'stacked_configurations'");
        if ( empty( $results ) ) {
            $wpdb->query("INSERT INTO {$this->db_prefix_primary}options ( option_name, option_value) VALUES ( 'stacked_configurations', '$configurations_serialize' )");
        } else {
            $wpdb->query("UPDATE {$this->db_prefix_primary}options set option_value = '$configurations_serialize' where option_name = 'stacked_configurations'");
        }
        self::refresh_configs();
    }

    public function domain_mapping() {
        $configurations = self::get();
        if ( $configurations->domain_mapping == "on" ) {
            return true;
        }
        return false;
    }

    public function refresh_configs() {
        if ( ! file_exists( ABSPATH . "wp-config.php" ) ) {
            echo "Can not locate wp-config.php file";
            return;
        }
        $wp_config_content = file_get_contents( ABSPATH . "wp-config.php" );
        $working           = explode( "\n", $wp_config_content );
        $lines_to_add      = [ '', '/* Stackable Mode */' ];
        $stacked_sites     = ( new Sites )->get();
        $configurations    = self::get();

        // Remove Stackable configs. Any lines containing '/* Stackable Mode */', 'stacked_site_id' and '$stacked_mappings'.
        foreach( $working as $key => $line ) {
            if ( strpos( $line, '/* Stackable Mode */' ) !== false || strpos( $line, 'stacked_site_id' ) !== false || strpos( $line, '$stacked_mappings' ) !== false ) {
                unset( $working[ $key ] );
            }
        }

        if ( $configurations->domain_mapping == "on" && $configurations->files == "dedicated" ) {
            $domain_mapping = ( new Sites )->domain_mappings();
            foreach ( $domain_mapping as $key => $domain ) {
                $lines_to_add[] = '$stacked_mappings['.$key.'] = \''.$domain.'\';';
            }
            $lines_to_add[] = 'if ( isset( $_SERVER[\'HTTP_HOST\'] ) && in_array( $_SERVER[\'HTTP_HOST\'], $stacked_mappings ) ) { foreach( $stacked_mappings as $key => $stacked_mapping ) { if ( $stacked_mapping == $_SERVER[\'HTTP_HOST\'] ) { $stacked_site_id = $key; continue; } } }';
            $lines_to_add[] = 'if ( defined( \'WP_CLI\' ) && WP_CLI ) { $stacked_site_id = getenv( \'STACKED_SITE_ID\' ); }';
            $lines_to_add[] = 'if ( ! empty( $stacked_site_id ) && ! empty ( $stacked_mappings[ $stacked_site_id ] ) ) { define( \'TABLE_PREFIX\', $table_prefix ); $stacked_home = $stacked_mappings[ $stacked_site_id ]; $table_prefix = "stacked_{$stacked_site_id}_"; define( \'WP_CONTENT_URL\', "//${stacked_home}/content/{$stacked_site_id}" ); define( \'WP_CONTENT_DIR\', dirname(__FILE__) . "/content/{$stacked_site_id}" ); }';
        }

        if ( $configurations->domain_mapping == "off" && $configurations->files == "dedicated" ) {
            $site_url       = get_option( 'siteurl' );
            $lines_to_add[] = '$stacked_site_id = ( isset( $_COOKIE[ "stacked_site_id" ] ) ? $_COOKIE[ "stacked_site_id" ] : "" );';
            $lines_to_add[] = 'if ( defined( \'WP_CLI\' ) && WP_CLI ) { $stacked_site_id = getenv( \'STACKED_SITE_ID\' ); }';
            $lines_to_add[] = 'if ( ! empty( $stacked_site_id ) ) { define( \'TABLE_PREFIX\', $table_prefix ); $table_prefix = "stacked_{$stacked_site_id}_"; define( \'WP_CONTENT_URL\', "//'. $site_url .'/content/{$stacked_site_id}" ); define( \'WP_CONTENT_DIR\', dirname(__FILE__) . "/content/{$stacked_site_id}" ); }';
        }

        if ( $configurations->domain_mapping == "on" && $configurations->files == "shared" ) {
            $domain_mapping = "";
            $lines_to_add[] = '$stacked_mappings = [ "1" => "austinginder.com", "3" => "samplesite.com" ];';
            $lines_to_add[] = 'if ( isset( $_SERVER[\'HTTP_HOST\'] ) && in_array( $_SERVER[\'HTTP_HOST\'], $stacked_mappings ) ) { foreach( $stacked_mappings as $key => $stacked_mapping ) { if ( $stacked_mapping == $_SERVER[\'HTTP_HOST\'] ) { $stacked_site_id = $key; continue; } } }';
            $lines_to_add[] = 'if ( defined( \'WP_CLI\' ) && WP_CLI ) { $stacked_site_id = getenv( \'STACKED_SITE_ID\' ); }';
            $lines_to_add[] = 'if ( ! empty( $stacked_site_id ) && ! empty ( $stacked_mappings[ $stacked_site_id ] ) ) { define( \'TABLE_PREFIX\', $table_prefix ); $table_prefix = "stacked_{$stacked_site_id}_"; }';
        }

        if ( $configurations->domain_mapping == "off" && $configurations->files == "shared" ) {
            $lines_to_add[] = '$stacked_site_id = ( isset( $_COOKIE[ "stacked_site_id" ] ) ? $_COOKIE[ "stacked_site_id" ] : "" );';
            $lines_to_add[] = 'if ( defined( \'WP_CLI\' ) && WP_CLI ) { $stacked_site_id = getenv( \'STACKED_SITE_ID\' ); }';
            $lines_to_add[] = 'if ( ! empty( $stacked_site_id ) ) { define( \'TABLE_PREFIX\', $table_prefix ); $table_prefix = "stacked_{$stacked_site_id}_"; }';
        }

        // Add default stackable configurations right after $table_prefix
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
        $results = file_put_contents( ABSPATH . "wp-config.php", implode( "\n", $updated ) );

        if ( empty( $results ) ) {
            self::update_config( "unable_to_save", $lines_to_add );
        }
    }

}