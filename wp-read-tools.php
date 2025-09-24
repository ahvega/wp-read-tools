<?php
/**
 * WP Read Tools - WordPress Plugin
 *
 * @package           WP_Read_Tools
 * @author            Adalberto H. Vega
 * @copyright         2024 Adalberto H. Vega
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WP Read Tools
 * Plugin URI:        https://github.com/ahvega/wp-read-tools
 * Description:       Provides reading time estimation and text-to-speech functionality for WordPress posts via a shortcode. Enhances accessibility and user experience with browser-based speech synthesis.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * Author:            Adalberto H. Vega
 * Author URI:        https://inteldevign.com
 * Text Domain:       wp-read-tools
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Network:           false
 * Update URI:        https://github.com/ahvega/wp-read-tools
 *
 * WP Read Tools is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * WP Read Tools is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WP Read Tools. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WP_READ_TOOLS_VERSION', '1.0.0' );

/**
 * Debug mode flag.
 * Set to true to enable debug logging. Can also be enabled via wp-config.php.
 */
if ( ! defined( 'WP_READ_TOOLS_DEBUG' ) ) {
	define( 'WP_READ_TOOLS_DEBUG', false );
}

/**
 * Plugin directory path.
 * Used for including files and templates.
 */
define( 'WP_READ_TOOLS_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 * Used for enqueueing assets (CSS, JS, images).
 */
define( 'WP_READ_TOOLS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 * Used for plugin identification and hooks.
 */
define( 'WP_READ_TOOLS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Loads the plugin's translated strings.
 *
 * This function loads the text domain for internationalization support,
 * allowing the plugin to be translated into different languages.
 *
 * @since 1.0.0
 *
 * @return void
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
 * Debug logging helper function.
 *
 * Logs debug messages when WP_READ_TOOLS_DEBUG is enabled.
 * Messages are logged to WordPress debug log if WP_DEBUG_LOG is enabled.
 *
 * @since 1.0.0
 *
 * @param string $message Debug message to log.
 * @param string $level   Log level (info, warning, error). Default 'info'.
 * @return void
 */
function wp_read_tools_log( $message, $level = 'info' ) {
	if ( ! WP_READ_TOOLS_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
		return;
	}

	$timestamp = current_time( 'Y-m-d H:i:s' );
	$log_message = sprintf(
		'[%s] WP Read Tools [%s]: %s',
		$timestamp,
		strtoupper( $level ),
		$message
	);

	error_log( $log_message );
}

/**
 * Initialize the plugin by including required files and classes.
 *
 * This function loads all necessary class files and initializes the plugin
 * components including asset enqueuing, AJAX handlers, and shortcode registration.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wp_read_tools_init() {
	wp_read_tools_log( 'Plugin initialization started' );

	// Include class files.
	require_once WP_READ_TOOLS_PATH . 'includes/class-wp-read-tools-enqueue.php';
	require_once WP_READ_TOOLS_PATH . 'includes/class-wp-read-tools-ajax.php';
	require_once WP_READ_TOOLS_PATH . 'includes/class-wp-read-tools-shortcode.php';

	// Initialize plugin components.
	WP_Read_Tools_Enqueue::init();
	WP_Read_Tools_Ajax::init();
	WP_Read_Tools_Shortcode::init();

	wp_read_tools_log( 'Plugin initialization completed' );
}
add_action( 'plugins_loaded', 'wp_read_tools_init' );

/**
 * Plugin activation hook.
 *
 * Runs when the plugin is activated. Reserved for future use
 * if activation procedures are needed (database creation, option setup, etc.).
 *
 * @since 1.0.0
 *
 * @return void
 */
function wp_read_tools_activate() {
	// Future activation procedures can be added here.
	// Examples: create database tables, set default options, check requirements.
}

/**
 * Plugin deactivation hook.
 *
 * Runs when the plugin is deactivated. Reserved for future use
 * if cleanup procedures are needed (temporary data cleanup, etc.).
 * Note: This should NOT remove user data or settings.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wp_read_tools_deactivate() {
	// Future deactivation cleanup can be added here.
	// Note: Do NOT remove user data or settings here.
}

// Register activation and deactivation hooks.
// register_activation_hook( __FILE__, 'wp_read_tools_activate' );
// register_deactivation_hook( __FILE__, 'wp_read_tools_deactivate' );