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
 * Version:           1.2.0
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
define( 'WP_READ_TOOLS_VERSION', '1.2.0' );

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
 * Strips markup and shortcodes from post content, producing plain readable text.
 *
 * Shared by the reading-time count and the text-to-speech path. These two used
 * byte-identical copies of this pipeline, which is why every defect in it
 * existed twice and had to be fixed twice.
 *
 * Shortcode tags are removed while their inner text is PRESERVED. This is
 * deliberate and load-bearing for page builders: strip_shortcodes() deletes the
 * entire registered shortcode including its body, which is where Avada/Fusion
 * Builder keeps the article copy.
 *
 * @since 1.2.0
 *
 * @param  string $content Raw post content.
 * @return string          Plain text, whitespace-normalized.
 */
function wp_read_tools_clean_content( $content ) {
	if ( ! is_string( $content ) ) {
		return '';
	}

	// Resolve drop-cap shortcodes by name rather than by position. The previous
	// heuristic anchored on the start of the document with
	// '/^(\pL)\s+(\pL)/u', which both over- and under-triggered: it joined the
	// first two words of any text opening with a one-letter word -- "A mi me
	// gusta" became "Ami me gusta", and likewise for Y, O, E and English "I" --
	// while fixing only the FIRST drop-cap in a document that had several.
	//
	// Trailing whitespace is deliberately NOT consumed. We operate on raw
	// post_content, where the author's spacing is authoritative: eating it
	// would turn "[dropcap]A[/dropcap] post about X" into "Apost about X".
	// The no-space form "[dropcap]O[/dropcap]rando" already yields "Orando".
	$text = preg_replace( '/\[(fusion_)?dropcap[^\]]*\](.*?)\[\/(fusion_)?dropcap\]/isu', '$2', $content );

	// Remove remaining shortcode tags, keeping inner text.
	//
	// Requiring a letter as the first character means numeric citation markers
	// -- [1], [15] -- now survive into both the word count and the spoken text;
	// the previous '/\[\/?\w[^\]]*\]/' deleted them, since \w matches digits.
	// The pattern also tolerates the [[escaped]] form WordPress uses to display
	// a shortcode literally.
	//
	// Known limitations, all pre-existing and all with the same root cause --
	// this matches by shape, not against the registered $shortcode_tags table:
	//   - "[sic]" and other single-word editorial brackets are removed.
	//   - "[our guide](https://...)" loses its markdown anchor text.
	//   - "[Note some remark]" is removed, though "[Note: some remark]" now
	//     survives, since a colon is not the whitespace the pattern requires.
	// Matching $shortcode_tags would fix all three, at the cost of leaking raw
	// tags whenever the registering plugin is inactive -- exactly the page
	// builder case this function exists to handle. Not changed here.
	$text = preg_replace( '/\[\[?\/?[a-z][a-z0-9_-]*(?:\s[^\]]*)?\]?\]/iu', '', $text );

	$text = wp_strip_all_tags( $text );

	// Decode entities AFTER stripping tags, not before.
	//
	// Decoding first looks safer but destroys content: strip_tags() treats a
	// bare "<" as the start of a tag and eats everything to the end of the
	// string, so "We have &lt; 5 minutes" would decode to "We have < 5 minutes"
	// and then strip down to "We have ". Prose about HTML ("the &lt;video&gt;
	// tag") loses the tag name the same way.
	//
	// ENT_QUOTES | ENT_HTML5 is the actual fix that was needed here: the PHP
	// 7.2-8.0 default (ENT_COMPAT) leaves &#039; undecoded, so the speech
	// engine reads apostrophes out character by character.
	//
	// Consequence to be aware of: an escaped "&lt;script&gt;" in the source
	// survives as the literal text "<script>". That is correct for speech
	// synthesis, which is this function's only consumer, but anything hooking
	// wp_read_tools_speech_content and RENDERING the result as HTML must
	// escape it first.
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	// Normalize whitespace. The /u modifier plus an explicit non-breaking space
	// matters: &nbsp; (U+00A0) is pervasive in page-builder output and is not
	// matched by \s without it.
	$text = preg_replace( '/[\s\x{00A0}]+/u', ' ', $text );

	// preg_replace() returns null on malformed UTF-8 (common with content
	// imported from latin1 installs). Fall back rather than propagate null.
	if ( null === $text ) {
		$text = wp_strip_all_tags( (string) $content );
	}

	return trim( $text );
}

/**
 * Counts words in a UTF-8 string.
 *
 * str_word_count() is byte-oriented and treats only [A-Za-z'-] as word
 * characters, so it SPLITS every accented word in two: "canción" counts as
 * "canci" + "n". Spanish is this plugin's documented primary locale and carries
 * an accent or ñ on roughly 10-15% of words, so reading times were inflated by
 * about that margin -- and the same applies to French, Portuguese and German.
 * Digits were never counted at all.
 *
 * @since 1.2.0
 *
 * @param  string $text Plain text to count.
 * @return int          Word count.
 */
function wp_read_tools_count_words( $text ) {
	if ( ! is_string( $text ) || '' === trim( $text ) ) {
		return 0;
	}

	// Letters and digits, allowing internal apostrophes and hyphens so that
	// "don't" and "well-known" each count once.
	$count = preg_match_all( '/[\p{L}\p{N}]+(?:[\'’\-][\p{L}\p{N}]+)*/u', $text );

	// preg_match_all() returns false on malformed UTF-8.
	if ( false === $count ) {
		return str_word_count( $text );
	}

	return $count;
}

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