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
        if ( ! $current_env_id && isset( $_SERVER['STACKED_SITE_ID'] ) ) {
            $current_env_id = $_SERVER['STACKED_SITE_ID'];
        }
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
     * List all tenant sites.
     *
     * ## EXAMPLES
     *
     * wp freighter list
     *
     * @subcommand list
     */
    public function list_sites( $args, $assoc_args ) {
        $sites   = ( new Sites )->get();
        $configs = ( new Configurations )->get();

        if ( empty( $sites ) ) {
            WP_CLI::line( "No tenant sites found." );
            return;
        }

        // Prepare all possible data points
        $display_data = array_map( function( $site ) {
            return [
                'ID'      => $site['stacked_site_id'],
                'Name'    => $site['name'],
                'Domain'  => $site['domain'],
                'Content' => "content/{$site['stacked_site_id']}",
                'Uploads' => "content/{$site['stacked_site_id']}/uploads",
                'Created' => date( 'Y-m-d H:i:s', $site['created_at'] ),
            ];
        }, $sites );
        // Build columns dynamically based on configurations
        $fields = [ 'ID' ];
        // Toggle Name vs Domain
        if ( $configs->domain_mapping === 'on' ) {
            $fields[] = 'Domain';
        } else {
            $fields[] = 'Name';
        }

        if ( $configs->files === 'dedicated' ) {
            $fields[] = 'Content';
        }

        // Show Uploads path if in Hybrid mode
        if ( $configs->files === 'hybrid' ) {
            $fields[] = 'Uploads';
        }

        $fields[] = 'Created';

        WP_CLI\Utils\format_items( 'table', $display_data, $fields );
    }

    /**
     * Create a new tenant site.
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

        WP_CLI::line( "Creating site..." );
        // Delegate directly to the Site model
        $result = Site::create( $assoc_args );
        if ( is_wp_error( $result ) ) {
            WP_CLI::error( "Failed to create site: " . $result->get_error_message() );
        }

        WP_CLI::success( "Site created successfully." );
        WP_CLI::line( "ID: " . $result['stacked_site_id'] );
        // Only show password if we generated it or user provided it (it's in assoc_args if provided)
        if ( isset( $assoc_args['password'] ) ) {
             WP_CLI::line( "Password: " . $assoc_args['password'] );
        } else {
            WP_CLI::line( "Password: (auto-generated)" );
        }
    }

    /**
     * Delete a tenant site.
     *
     * ## OPTIONS
     *
     * <id>
     * : The Tenant Site ID to delete.
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
        // Delegate directly to the Site model
        $success = Site::delete( $site_id );
        if ( $success ) {
            WP_CLI::success( "Site {$site_id} deleted." );
        } else {
            WP_CLI::error( "Failed to delete site {$site_id}. Site may not exist." );
        }
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
        $clone_args = [
            'name'   => isset( $assoc_args['name'] ) ? $assoc_args['name'] : '',
            'domain' => isset( $assoc_args['domain'] ) ? $assoc_args['domain'] : '',
        ];

        WP_CLI::line( "Cloning site ID '{$source_id}'..." );
        // Delegate directly to the Site model
        $result = Site::clone( $source_id, $clone_args );
        if ( is_wp_error( $result ) ) {
            WP_CLI::error( "Clone failed: " . $result->get_error_message() );
        }

        WP_CLI::success( "Clone complete. New Site ID: " . $result['stacked_site_id'] );
    }

    /**
     * Generate a magic login URL for a specific site.
     *
     * ## OPTIONS
     *
     * <id>
     * : The Tenant Site ID to login to. Use 'main' for the primary site.
     *
     * [--url-only]
     * : Output only the URL (useful for piping to other commands/browsers).
     *
     * ## EXAMPLES
     *
     * wp freighter login 2
     * wp freighter login main
     * open $(wp freighter login 2 --url-only)
     */
    public function login( $args, $assoc_args ) {
        list( $site_id ) = $args;
        $url_only = isset( $assoc_args['url-only'] );

        // Validate Site ID Exists (unless it is 'main')
        if ( 'main' !== $site_id ) {
            $site = Site::get( $site_id );
            if ( empty( $site ) ) {
                WP_CLI::error( "Site ID '{$site_id}' not found." );
            }
        }

        // Delegate to Site Model
        $login_url = Site::login( $site_id );
        if ( is_wp_error( $login_url ) ) {
            WP_CLI::error( "Failed to generate login URL: " . $login_url->get_error_message() );
        }

        if ( $url_only ) {
            WP_CLI::line( $login_url );
        } else {
            WP_CLI::success( "Magic login URL generated:" );
            WP_CLI::line( $login_url );
        }
    }

    /**
     * Regenerate the WP Freighter configuration files.
     *
     * ## EXAMPLES
     *
     * wp freighter regenerate
     */
    public function regenerate( $args, $assoc_args ) {
        ( new Configurations )->refresh_configs();
        WP_CLI::success( "Configuration files regenerated." );
    }

}