<?php

/**
 * The plugin bootstrap file
 *
 * @link              https://austinginder.com
 * @since             1.0.0
 * @package           WP Freighter
 *
 * @wordpress-plugin
 * Plugin Name:       WP Freighter
 * Plugin URI:        https://wpfreighter.com
 * Description:       Efficiently run many WordPress sies from a single WordPress installation.
 * Version:           1.0.2
 * Author:            Austin Ginder
 * Author URI:        https://austinginder.com
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       wp-freighter
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
new WPFreighter\Run();