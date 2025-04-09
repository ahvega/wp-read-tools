<?php
/**
 * Plugin Name:       WP Read Tools
 * Plugin URI:        https://github.com/ahvega/wp-read-tools
 * Description:       Provides reading time estimation and a text-to-speech feature for WordPress posts via a shortcode.
 * Version:           1.0.0
 * Author:            Adalberto H. Vega
 * Author URI:        https://inteldevign.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text 'wp-read-tools':       wp-read-tools
 * 'wp-read-tools' Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WP_READ_TOOLS_VERSION', '1.0.0' );
define( 'WP_READ_TOOLS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_READ_TOOLS_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_READ_TOOLS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Loads the plugin's translated strings.
 *
 * @since 1.0.0
 */
function wp_read_tools_load_textdomain() {
	load_plugin_textdomain(
		'wp-read-tools',
		false,
		dirname( WP_READ_TOOLS_BASENAME ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'wp_read_tools_load_textdomain' );

/**
 * Include required files and initialize the plugin.
 *
 * @since 1.0.0
 */
function wp_read_tools_init() {
	// Include class files.
	require_once WP_READ_TOOLS_PATH . 'includes/class-wp-read-tools-enqueue.php';
	require_once WP_READ_TOOLS_PATH . 'includes/class-wp-read-tools-ajax.php';
	require_once WP_READ_TOOLS_PATH . 'includes/class-wp-read-tools-shortcode.php';

	// Instantiate classes or call initialization methods.
	WP_Read_Tools_Enqueue::init();
	WP_Read_Tools_Ajax::init();
	WP_Read_Tools_Shortcode::init();

}
add_action( 'plugins_loaded', 'wp_read_tools_init' );

// Add activation/deactivation hooks if needed in the future.
// register_activation_hook( __FILE__, 'wp_read_tools_activate' );
// register_deactivation_hook( __FILE__, 'wp_read_tools_deactivate' );