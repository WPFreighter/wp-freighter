<?php

namespace WPFreighter;

class Sites {

    protected $sites             = [];
    protected $db_prefix_primary = "";

    public function __construct() {
        global $wpdb;
        $db_prefix         = $wpdb->prefix;
        $db_prefix_primary = ( defined( 'TABLE_PREFIX' ) ? TABLE_PREFIX : $db_prefix );

        if ( $db_prefix_primary == "TABLE_PREFIX" ) { 
            $db_prefix_primary = $db_prefix;
        }

        $this->db_prefix_primary = $db_prefix_primary;
        $stacked_sites           = $wpdb->get_results("select option_value from {$db_prefix_primary}options where option_name = 'stacked_sites'");
        $stacked_sites           = empty( $stacked_sites ) ? "" : maybe_unserialize( $stacked_sites[0]->option_value );
        if ( empty( $stacked_sites ) ) {
            $stacked_sites = [];
        }
        $this->sites             = $stacked_sites;
    }

    public function get() {
        return $this->sites;
    }

    public function domain_mappings() {
        $sites           = (object) $this->sites;
        $domain_mappings = [];
        foreach( $sites as $site ) {
            $domain_mappings[ $site['stacked_site_id'] ] = $site['domain'];
        }
        return $domain_mappings;
    }

    public function get_json() {
        $stacked_sites = $this->sites;
        if ( empty( $stacked_sites ) ) {
            $stacked_sites = [];
        }
        return json_encode( array_values( $stacked_sites ) );
    }

    public function update( $sites ) {
        global $wpdb;

        $configurations = ( new Configurations )->get();
        // Refresh domain mappings when enabled
        if ( $configurations->domain_mapping == "on" ) {
            foreach( $sites as $site ) {
                if ( $site["domain"] != "" ) {
                    $wpdb->query("UPDATE stacked_{$site["stacked_site_id"]}_options set option_value = 'https://{$site["domain"]}' where option_name = 'siteurl'");
                    $wpdb->query("UPDATE stacked_{$site["stacked_site_id"]}_options set option_value = 'https://{$site["domain"]}' where option_name = 'home'");
                }
            }
        }
        // Turn domain mappings off when not used
        if ( $configurations->domain_mapping == "off" ) {
            $primary_site_url = $wpdb->get_results("SELECT option_value from {$this->db_prefix_primary}options where option_name = 'siteurl'")[0]->option_value;
            $primary_home     = $wpdb->get_results("SELECT option_value from {$this->db_prefix_primary}options where option_name = 'home'")[0]->option_value;
            foreach( $sites as $site ) {
                $wpdb->query("UPDATE stacked_{$site["stacked_site_id"]}_options set option_value = '{$primary_site_url}' where option_name = 'siteurl'");
                $wpdb->query("UPDATE stacked_{$site["stacked_site_id"]}_options set option_value = '{$primary_home}' where option_name = 'home'");
            }
        }

        $sites_serialize = serialize( $sites );
        $results         = $wpdb->get_results("select option_id from {$this->db_prefix_primary}options where option_name = 'stacked_sites'");
        if ( empty( $results ) ) {
            $wpdb->query("INSERT INTO {$this->db_prefix_primary}options ( option_name, option_value) VALUES ( 'stacked_sites', '$sites_serialize' )");
        } else {
            $wpdb->query("UPDATE {$this->db_prefix_primary}options set option_value = '$sites_serialize' where option_name = 'stacked_sites'");
        }
    }


}