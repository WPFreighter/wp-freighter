<?php

namespace WPFreighter;

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
        $configurations = $wpdb->get_results("select option_value from {$this->db_prefix_primary}options where option_name = 'stacked_configurations'");
        $configurations = empty ( $configurations ) ? "" : maybe_unserialize( $configurations[0]->option_value );
        if ( empty( $configurations ) ) {
            $configurations      = [ 
                "files"          => "shared", 
                "domain_mapping" => "off",
            ];
        }
        $this->configurations    = $configurations;
    }

    public function get() {
        $configs = (array) $this->configurations;
        $configs['errors'] = [];

        // Use absolute path to ensure we look in root wp-content, not the tenant site's content dir
        $bootstrap_path = ABSPATH . 'wp-content/freighter.php';

        // Lazy Init
        if ( ! file_exists( $bootstrap_path ) ) {
            $this->refresh_configs();
        }

        // Error 1 Check
        if ( ! file_exists( $bootstrap_path ) ) {
            $configs['errors']['manual_bootstrap_required'] = $this->get_bootstrap_content();
        }

        // Error 2 Check
        if ( empty( $configs['errors']['manual_bootstrap_required'] ) ) {
             if ( file_exists( ABSPATH . "wp-config.php" ) ) {
                $wp_config_file = ABSPATH . "wp-config.php";
            } elseif ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
                $wp_config_file = dirname( ABSPATH ) . '/wp-config.php';
            }

            $wp_config_content = ( isset( $wp_config_file ) && file_exists( $wp_config_file ) ) 
                ? file_get_contents( $wp_config_file ) 
                : '';
            // Look for the unique filename
            if ( strpos( $wp_config_content, 'wp-content/freighter.php' ) === false ) {
                // Return as array for Vue compatibility
                $configs['errors']['manual_config_required'] = [
                    "if ( file_exists( dirname( __FILE__ ) . '/wp-content/freighter.php' ) ) { require_once( dirname( __FILE__ ) . '/wp-content/freighter.php' ); }"
                ];
            }
        }

        return (object) $configs;
    }

    public function get_json() {
        $configurations     = $this->configurations;
        if ( empty( $configurations ) ) {
            $configurations = [ 
                "files"          => "shared", 
                "domain_mapping" => "off",
            ];
        }
        return json_encode( $configurations );
    }

    public function update_config( $key, $value ) {
        global $wpdb;
        $this->configurations[ $key ] = $value;
        $configurations_serialize     = serialize( $this->configurations );
        $exists = $wpdb->get_var( $wpdb->prepare( 
            "SELECT option_id from {$this->db_prefix_primary}options where option_name = %s", 
            'stacked_configurations' 
        ) );
        if ( ! $exists ) {
            $wpdb->query( $wpdb->prepare( 
                "INSERT INTO {$this->db_prefix_primary}options ( option_name, option_value) VALUES ( %s, %s )", 
                'stacked_configurations', 
                $configurations_serialize 
            ) );
        } else {
            $wpdb->query( $wpdb->prepare( 
                "UPDATE {$this->db_prefix_primary}options set option_value = %s where option_name = %s", 
                $configurations_serialize, 
                'stacked_configurations' 
            ) );
        }
    }

    public function update( $configurations ) {
        global $wpdb;
        // 1. Sanitize: Remove 'errors' so we don't save dynamic checks to DB
        $data = (array) $configurations;
        if ( isset( $data['errors'] ) ) {
            unset( $data['errors'] );
        }

        $this->configurations     = $data;
        $configurations_serialize = serialize( $this->configurations );
        $exists = $wpdb->get_var( $wpdb->prepare( 
            "SELECT option_id from {$this->db_prefix_primary}options where option_name = %s", 
            'stacked_configurations' 
        ) );
        if ( ! $exists ) {
            $wpdb->query( $wpdb->prepare( 
                "INSERT INTO {$this->db_prefix_primary}options ( option_name, option_value) VALUES ( %s, %s )", 
                'stacked_configurations', 
                $configurations_serialize 
            ) );
        } else {
            $wpdb->query( $wpdb->prepare( 
                "UPDATE {$this->db_prefix_primary}options set option_value = %s where option_name = %s", 
                $configurations_serialize, 
                'stacked_configurations' 
            ) );
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
        global $wpdb;
        // 1. Generate & Write Bootstrap File
        $bootstrap_content = $this->get_bootstrap_content();
        $bootstrap_path    = ABSPATH . 'wp-content/freighter.php';
        
        // Attempt write
        $bootstrap_written = @file_put_contents( $bootstrap_path, $bootstrap_content );
        // Fallback check: maybe it already exists and is valid?
        if ( ! $bootstrap_written ) {
            if ( file_exists( $bootstrap_path ) && md5_file( $bootstrap_path ) === md5( $bootstrap_content ) ) {
                $bootstrap_written = true;
            }
        }

        if ( ! $bootstrap_written ) {
            return;
        }

        // 2. The Clean One-Liner (No Comment)
        $lines_to_add = [
            "if ( file_exists( dirname( __FILE__ ) . '/wp-content/freighter.php' ) ) { require_once( dirname( __FILE__ ) . '/wp-content/freighter.php' ); }"
        ];
        // 3. Update wp-config.php
        if ( file_exists( ABSPATH . "wp-config.php" ) ) {
            $wp_config_file = ABSPATH . "wp-config.php";
        } elseif ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
            $wp_config_file = dirname( ABSPATH ) . '/wp-config.php';
        } else {
            return;
        }

        if ( is_writable( $wp_config_file ) ) {
            $wp_config_content = file_get_contents( $wp_config_file );
            $working           = preg_split( '/\R/', $wp_config_content );
            // Clean OLD logic and NEW logic
            $working = $this->clean_wp_config_lines( $working );
            // Find insertion point ($table_prefix)
            $table_prefix_line = 0;
            foreach( $working as $key => $line ) {
                if ( strpos( $line, '$table_prefix' ) !== false ) {
                    $table_prefix_line = $key;
                    break;
                }
            }

            // Insert new one-liner
            $updated = array_merge( 
                array_slice( $working, 0, $table_prefix_line + 1, true ), 
                $lines_to_add, 
                array_slice( $working, $table_prefix_line + 1, count( $working ), true ) 
            );
            file_put_contents( $wp_config_file, implode( PHP_EOL, $updated ) );
        }
    }

    /**
     * Generates the dynamic PHP code for wp-content/freighter.php
     */
    private function get_bootstrap_content() {
        global $wpdb;
        $configurations = (object) $this->configurations; 
        
        // Fetch URL cleanly directly from DB
        $site_url = $wpdb->get_var( "SELECT option_value FROM {$this->db_prefix_primary}options WHERE option_name = 'siteurl'" );
        $site_url = str_replace( ["https://", "http://"], "", $site_url );

        // Prepare Mappings Array
        $mapping_php = '$stacked_mappings = [];';
        if ( $configurations->domain_mapping == "on" ) {
            $domain_mappings = ( new Sites )->domain_mappings();
            // Using var_export ensures the array is written as valid PHP code
            $export = var_export( $domain_mappings, true );
            $mapping_php = "\$stacked_mappings = $export;";
        }

        // Logic Blocks based on File Mode
        $mode_logic = "";
        // --- DEDICATED MODE ---
        if ( $configurations->files == 'dedicated' ) {
            $mode_logic = <<<PHP
    if ( ! empty( \$stacked_site_id ) ) {
        \$table_prefix = "stacked_{\$stacked_site_id}_";
        define( 'WP_CONTENT_URL', "https://" . ( isset(\$stacked_home) ? \$stacked_home : '$site_url' ) . "/content/{\$stacked_site_id}" );
        define( 'WP_CONTENT_DIR', ABSPATH . "content/{\$stacked_site_id}" );
        // Define URLs for non-mapped sites (cookie based)
        if ( empty( \$stacked_home ) ) {
             define( 'WP_HOME', "https://$site_url" );
             define( 'WP_SITEURL', "https://$site_url" );
        }
    }
PHP;
        }

        // --- HYBRID MODE ---
        if ( $configurations->files == 'hybrid' ) {
            $mode_logic = <<<PHP
    if ( ! empty( \$stacked_site_id ) ) {
        \$table_prefix = "stacked_{\$stacked_site_id}_";
        define( 'UPLOADS', "content/{\$stacked_site_id}/uploads" );
        
        if ( empty( \$stacked_home ) ) {
             define( 'WP_HOME', "https://$site_url" );
             define( 'WP_SITEURL', "https://$site_url" );
        }
    }
PHP;
        }

        // --- SHARED MODE ---
        if ( $configurations->files == 'shared' ) {
            $mode_logic = <<<PHP
    if ( ! empty( \$stacked_site_id ) ) {
        \$table_prefix = "stacked_{\$stacked_site_id}_";
        if ( empty( \$stacked_home ) ) {
             define( 'WP_HOME', "https://$site_url" );
             define( 'WP_SITEURL', "https://$site_url" );
        }
    }
PHP;
        }

        // Return the Full File Content
        return <<<EOD
<?php
/**
 * WP Freighter Bootstrap
 *
 * Auto-generated file. Do not edit directly. 
 * Settings are managed via WP Admin -> Tools -> WP Freighter.
 */

// 1. Define Mappings
$mapping_php

// 2. Identify Tenant Site ID
\$stacked_site_id = ( isset( \$_COOKIE[ "stacked_site_id" ] ) ? \$_COOKIE[ "stacked_site_id" ] : "" );
// [GATEKEEPER] Enforce strict access control for cookie-based access
if ( ! empty( \$stacked_site_id ) && isset( \$_COOKIE['stacked_site_id'] ) ) {
    
    // Whitelist login page (so Magic Login can function)
    \$is_login = ( isset( \$_SERVER['SCRIPT_NAME'] ) && strpos( \$_SERVER['SCRIPT_NAME'], 'wp-login.php' ) !== false );
    // Check for WordPress Auth Cookie (Raw Check)
    // We check if *any* cookie starts with 'wordpress_logged_in_' because we can't validate the hash yet.
    \$has_auth_cookie = false;
    foreach ( \$_COOKIE as \$key => \$value ) {
        if ( strpos( \$key, 'wordpress_logged_in_' ) === 0 ) {
            \$has_auth_cookie = true;
            break;
        }
    }

    // If not logging in, and no auth cookie is present, REVOKE ACCESS immediately.
    if ( ! \$is_login && ! \$has_auth_cookie ) {
        setcookie( 'stacked_site_id', '', time() - 3600, '/' );
        unset( \$_COOKIE['stacked_site_id'] );
        \$stacked_site_id = ""; // Revert to Main Site context for this request
    }
}

// CLI Support
if ( defined( 'WP_CLI' ) && WP_CLI ) { 
    \$env_id = getenv( 'STACKED_SITE_ID' );
    if ( ! \$env_id && isset( \$_SERVER['STACKED_SITE_ID'] ) ) { \$env_id = \$_SERVER['STACKED_SITE_ID']; }
    if ( ! \$env_id && isset( \$_ENV['STACKED_SITE_ID'] ) ) { \$env_id = \$_ENV['STACKED_SITE_ID']; }
    if ( \$env_id ) { \$stacked_site_id = \$env_id; }
}

// Domain Mapping Detection
if ( isset( \$_SERVER['HTTP_HOST'] ) && in_array( \$_SERVER['HTTP_HOST'], \$stacked_mappings ) ) {
    \$found_id = array_search( \$_SERVER['HTTP_HOST'], \$stacked_mappings );
    if ( \$found_id ) {
        \$stacked_site_id = \$found_id;
        \$stacked_home    = \$stacked_mappings[ \$found_id ];
    }
}

if ( ! empty( \$stacked_site_id ) && empty( \$stacked_home ) && isset( \$stacked_mappings[ \$stacked_site_id ] ) ) {
    \$stacked_home = \$stacked_mappings[ \$stacked_site_id ];
}

// 3. Apply Configuration Logic
if ( ! empty( \$stacked_site_id ) ) {
    // Save original prefix if needed later
    if ( ! defined( 'TABLE_PREFIX' ) && isset( \$table_prefix ) ) {
        define( 'TABLE_PREFIX', \$table_prefix );
    }
    
$mode_logic

    // Ensure Object Caching is unique per site
    if ( ! defined( 'WP_CACHE_KEY_SALT' ) ) {
        define( 'WP_CACHE_KEY_SALT', \$stacked_site_id );
    }
}
EOD;
    }

    /**
     * Cleaning Logic
     */
    private function clean_wp_config_lines( $lines ) {
        $is_legacy_block = false;
        foreach( $lines as $key => $line ) {
            
            // 1. Remove the One-Liner (Matches by unique filename)
            if ( strpos( $line, 'wp-content/freighter.php' ) !== false ) {
                unset( $lines[ $key ] );
                continue;
            }

            // 2. Remove Legacy Multi-line Blocks (Keep this to clean old versions)
            if ( strpos( $line, '/* WP Freighter */' ) !== false ) {
                if ( strpos( $line, 'require_once' ) === false ) {
                    $is_legacy_block = true;
                }
                unset( $lines[ $key ] );
                continue;
            }

            if ( $is_legacy_block || strpos( $line, '$stacked_site_id' ) !== false || strpos( $line, '$stacked_mappings' ) !== false ) {
                
                unset( $lines[ $key ] );
                if ( trim( $line ) === '}' || trim( $line ) === '' ) {
                     $is_legacy_block = false;
                }
            }
        }
        return $lines;
    }

}