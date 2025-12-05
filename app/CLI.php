<?php

namespace WPFreighter;

use WP_CLI;
use WP_CLI_Command;

/**
 * Manage WP Freighter via WP-CLI.
 */
class CLI extends WP_CLI_Command {

    /**
     * Display status information about WP Freighter.
     *
     * ## EXAMPLES
     *
     * wp freighter info
     */
    public function info( $args, $assoc_args ) {
        global $wpdb;

        // 1. Get Configurations
        $configs = ( new Configurations )->get();

        // 2. Get Site List & Count
        $sites   = ( new Sites )->get();
        $count   = count( $sites );

        // 3. Get Main Site URL safely
        $db_prefix         = $wpdb->prefix;
        $db_prefix_primary = ( defined( 'TABLE_PREFIX' ) ? TABLE_PREFIX : $db_prefix );
        if ( $db_prefix_primary == "TABLE_PREFIX" ) { 
            $db_prefix_primary = $db_prefix;
        }

        $main_site_url = $wpdb->get_var( "SELECT option_value FROM {$db_prefix_primary}options WHERE option_name = 'home'" );

        // 4. Output Details
        WP_CLI::line( "Main site: " . $main_site_url );

        // 5. Check for Environment Variable
        $current_env_id = getenv( 'STACKED_SITE_ID' );

        if ( $current_env_id !== false && $current_env_id !== '' ) {
            $site_details = "{$current_env_id} (Not found)";
            
            foreach ( $sites as $site ) {
                if ( $site['stacked_site_id'] == $current_env_id ) {
                    // Build label dynamically
                    $parts = [];
                    if ( ! empty( $site['name'] ) ) {
                        $parts[] = "name: " . $site['name'];
                    }
                    if ( ! empty( $site['domain'] ) ) {
                        $parts[] = "domain: " . $site['domain'];
                    }

                    $site_details = $site['stacked_site_id'];
                    
                    if ( ! empty( $parts ) ) {
                        $site_details .= " (" . implode( ', ', $parts ) . ")";
                    }
                    break;
                }
            }
            WP_CLI::line( "Current Site: " . $site_details );
        }

        WP_CLI::line( "Current Domain Mapping: " . WP_CLI::colorize( "%G" . $configs->domain_mapping . "%n" ) );
        WP_CLI::line( "Current Files Mode: " . WP_CLI::colorize( "%G" . $configs->files . "%n" ) );
        WP_CLI::line( "Site Count: " . $count );
    }

    /**
     * Get or set the files mode.
     *
     * ## OPTIONS
     *
     * <action>
     * : The action to perform (get|set).
     *
     * [<mode>]
     * : The mode to set (shared|hybrid|dedicated). Required for 'set'.
     *
     * ## EXAMPLES
     *
     * wp freighter files get
     * wp freighter files set dedicated
     */
    public function files( $args, $assoc_args ) {
        list( $action ) = $args;
        $mode = isset( $args[1] ) ? $args[1] : null;

        // Manual validation to prevent parsing errors
        if ( ! in_array( $action, [ 'get', 'set' ] ) ) {
            WP_CLI::error( "Invalid action. Use 'get' or 'set'." );
        }

        $configs = ( new Configurations )->get();

        if ( 'get' === $action ) {
            WP_CLI::line( "Current Files Mode: " . WP_CLI::colorize( "%G" . $configs->files . "%n" ) );
            return;
        }

        if ( 'set' === $action ) {
            $valid_modes = [ 'shared', 'hybrid', 'dedicated' ];
            if ( ! in_array( $mode, $valid_modes ) ) {
                WP_CLI::error( "Invalid mode. Available options: " . implode( ', ', $valid_modes ) );
            }
            
            $data = (array) $configs;
            $data['files'] = $mode;
            
            ( new Configurations )->update( $data );
            WP_CLI::success( "Files mode updated to '{$mode}' and wp-config.php refreshed." );
        }
    }

    /**
     * Get or set the domain mapping mode.
     *
     * ## OPTIONS
     *
     * <action>
     * : The action to perform (get|set).
     *
     * [<status>]
     * : The status to set (on|off). Required for 'set'.
     *
     * ## EXAMPLES
     *
     * wp freighter domain get
     * wp freighter domain set on
     */
    public function domain( $args, $assoc_args ) {
        list( $action ) = $args;
        $status = isset( $args[1] ) ? $args[1] : null;

        if ( ! in_array( $action, [ 'get', 'set' ] ) ) {
            WP_CLI::error( "Invalid action. Use 'get' or 'set'." );
        }

        $configs = ( new Configurations )->get();

        if ( 'get' === $action ) {
            WP_CLI::line( "Current Domain Mapping: " . WP_CLI::colorize( "%G" . $configs->domain_mapping . "%n" ) );
            return;
        }

        if ( 'set' === $action ) {
            $valid_status = [ 'on', 'off' ];
            if ( ! in_array( $status, $valid_status ) ) {
                WP_CLI::error( "Invalid status. Available options: on, off" );
            }

            $data = (array) $configs;
            $data['domain_mapping'] = $status;
            
            ( new Configurations )->update( $data );
            WP_CLI::success( "Domain mapping updated to '{$status}'." );
        }
    }

    /**
     * List all stacked sites.
     *
     * ## EXAMPLES
     *
     * wp freighter list
     *
     * @subcommand list
     */
    public function list_sites( $args, $assoc_args ) {
        $sites = ( new Sites )->get();

        if ( empty( $sites ) ) {
            WP_CLI::line( "No stacked sites found." );
            return;
        }

        // Flatten object for display
        $display_data = array_map( function( $site ) {
            return [
                'ID'      => $site['stacked_site_id'],
                'Name'    => $site['name'],
                'Domain'  => $site['domain'],
                'Created' => date( 'Y-m-d H:i:s', $site['created_at'] ),
            ];
        }, $sites );

        WP_CLI\Utils\format_items( 'table', $display_data, [ 'ID', 'Name', 'Domain', 'Created' ] );
    }

    /**
     * Create a new stacked site.
     *
     * ## OPTIONS
     *
     * [--title=<title>]
     * : Site title. Default: "New Site"
     *
     * [--name=<name>]
     * : Label for the site.
     *
     * [--domain=<domain>]
     * : Domain for the site.
     *
     * [--username=<username>]
     * : Admin username. Default: "admin"
     *
     * [--email=<email>]
     * : Admin email. Default: "admin@example.com"
     *
     * [--password=<password>]
     * : Admin password. If not set, one will be generated.
     *
     * ## EXAMPLES
     *
     * wp freighter add --title="My Sandbox" --name="Sandbox"
     *
     * @alias create
     */
    public function add( $args, $assoc_args ) {
        
        // Fix: Suppress "Undefined array key HTTP_HOST" warnings in wp_install
        if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
            $_SERVER['HTTP_HOST'] = 'cli.wpfreighter.localhost';
        }

        $defaults = [
            'title'    => 'New Site',
            'name'     => 'New Site',
            'domain'   => '',
            'username' => 'admin',
            'email'    => 'admin@example.com',
            'password' => wp_generate_password(),
        ];

        $data = (object) array_merge( $defaults, $assoc_args );

        WP_CLI::line( "Creating site '{$data->title}'..." );

        // Mock request object
        $request_mock = new class($data) {
            private $data;
            public function __construct($data) { $this->data = (array)$data; }
            public function get_json_params() { return $this->data; }
        };

        // Run the creation
        $run = new Run();
        $result = $run->new_site( $request_mock );

        if ( ! empty( $result ) ) {
            WP_CLI::success( "Site created successfully." );
            // Get the last ID
            $last = end($result);
            WP_CLI::line( "ID: " . $last['stacked_site_id'] );
            WP_CLI::line( "Password: " . $data->password );
        } else {
            WP_CLI::error( "Failed to create site." );
        }
    }

    /**
     * Delete a stacked site.
     *
     * ## OPTIONS
     *
     * <id>
     * : The Stacked Site ID to delete.
     *
     * [--yes]
     * : Skip confirmation.
     *
     * ## EXAMPLES
     *
     * wp freighter delete 2
     */
    public function delete( $args, $assoc_args ) {
        list( $site_id ) = $args;

        WP_CLI::confirm( "Are you sure you want to delete Site ID {$site_id}? This will drop tables and delete files.", $assoc_args );

        $request_mock = new class(['site_id' => $site_id]) {
            private $data;
            public function __construct($data) { $this->data = $data; }
            public function get_json_params() { return $this->data; }
        };

        $run = new Run();
        $run->delete_site( $request_mock );

        WP_CLI::success( "Site {$site_id} deleted." );
    }

    /**
     * Clone an existing site (or main site).
     *
     * ## OPTIONS
     *
     * <source-id>
     * : The Source ID to clone. Use 'main' for the primary site.
     *
     * [--name=<name>]
     * : New site label.
     *
     * [--domain=<domain>]
     * : New site domain.
     *
     * ## EXAMPLES
     *
     * wp freighter clone main --name="Staging"
     * wp freighter clone 2 --name="Dev Copy"
     */
    public function clone_site( $args, $assoc_args ) {
        list( $source_id ) = $args;
        
        $name = isset( $assoc_args['name'] ) ? $assoc_args['name'] : '';
        $domain = isset( $assoc_args['domain'] ) ? $assoc_args['domain'] : '';

        $params = [
            'source_id' => $source_id,
            'name'      => $name,
            'domain'    => $domain,
        ];

        $request_mock = new class($params) {
            private $data;
            public function __construct($data) { $this->data = $data; }
            public function get_json_params() { return $this->data; }
        };

        WP_CLI::line( "Cloning site ID '{$source_id}'..." );

        $run = new Run();
        $result = $run->clone_existing( $request_mock );

        WP_CLI::success( "Clone complete." );
    }

}