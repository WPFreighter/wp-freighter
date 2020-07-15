<?php

/**
 * The plugin bootstrap file
 *
 * @link              https://austinginder.com
 * @since             1.0.0
 * @package           Stackable
 *
 * @wordpress-plugin
 * Plugin Name:       Stackable
 * Plugin URI:        https://stackablewp.com
 * Description:       Lighting fast ⚡ duplicate copies of your WordPress site.
 * Version:           1.0.0
 * Author:            Austin Ginder
 * Author URI:        https://austinginder.com
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       stackable
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
new StackableMode\Run();