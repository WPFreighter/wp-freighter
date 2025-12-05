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

    public static function fetch( $stacked_site_id = "" ) {
        global $wpdb;
        $db_prefix         = $wpdb->prefix;
        $db_prefix_primary = ( defined( 'TABLE_PREFIX' ) ? TABLE_PREFIX : $db_prefix );

        if ( $db_prefix_primary == "TABLE_PREFIX" ) { 
            $db_prefix_primary = $db_prefix;
        }

        $stacked_sites           = $wpdb->get_results("select option_value from {$db_prefix_primary}options where option_name = 'stacked_sites'");
        $stacked_sites           = empty( $stacked_sites ) ? "" : maybe_unserialize( $stacked_sites[0]->option_value );
        if ( empty( $stacked_sites ) ) {
            $stacked_sites = [];
        }
        if ( empty( $stacked_site_id ) ) { return array_values( $stacked_sites ); }
        foreach( $stacked_sites as $site ) { if ( $site['stacked_site_id'] == $stacked_site_id ) { return $site; } }
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

    public static function get_directory_size( $path ) {
        $bytestotal = 0;
        $path = realpath( $path );
        if ( $path !== false && $path != '' && file_exists( $path ) ) {
            foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ) ) as $object ) {
                $bytestotal += $object->getSize();
            }
        }
        return $bytestotal;
    }

    public static function format_size( $bytes ) {
        $units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
        $bytes = max( $bytes, 0 );
        $pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow   = min( $pow, count( $units ) - 1 );
        $bytes /= pow( 1024, $pow );
        return round( $bytes, 2 ) . ' ' . $units[ $pow ];
    }

    public static function delete_directory( $dir ) {
        if ( ! file_exists( $dir ) ) {
            return true;
        }

        if ( is_link( $dir ) ) {
            return unlink( $dir );
        }

        if ( ! is_dir( $dir ) ) {
            return unlink( $dir );
        }

        foreach ( scandir( $dir ) as $item ) {
            if ( $item == '.' || $item == '..' ) {
                continue;
            }

            if ( ! self::delete_directory( $dir . DIRECTORY_SEPARATOR . $item ) ) {
                return false;
            }
        }

        return rmdir( $dir );
    }

    public static function copy_recursive( $source, $dest ) {
        if ( ! file_exists( $source ) ) {
            return;
        }
        if ( ! file_exists( $dest ) ) {
            mkdir( $dest, 0777, true );
        }
        
        foreach (
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( $source, \RecursiveDirectoryIterator::SKIP_DOTS ),
                \RecursiveIteratorIterator::SELF_FIRST
            ) as $item
        ) {
            $subPathName      = $iterator->getSubPathname();
            $destination_file = $dest . DIRECTORY_SEPARATOR . $subPathName;

            if ( $item->isLink() ) {
                $target = readlink( $item->getPathname() );
                @symlink( $target, $destination_file );
            } elseif ( $item->isDir() ) {
                if ( ! file_exists( $destination_file ) ) {
                    mkdir( $destination_file );
                }
            } else {
                copy( $item, $destination_file );
            }
        }
    }

}