<?php
/**
 * Asset enqueuing handler for the WP Read Tools plugin.
 *
 * This file contains the WP_Read_Tools_Enqueue class which manages all
 * frontend asset loading including stylesheets, JavaScript files, and
 * localized data for AJAX functionality and internationalization.
 *
 * @package    WP_Read_Tools
 * @subpackage WP_Read_Tools/includes
 * @since      1.0.0
 * @author     Adalberto H. Vega <contacto@inteldevign.com>
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset enqueuing class for WP Read Tools plugin.
 *
 * This class manages the registration and enqueuing of frontend assets for the plugin.
 * It handles:
 * - Font Awesome icon library loading from CDN
 * - Plugin-specific CSS stylesheet enqueuing
 * - JavaScript file loading with proper dependencies
 * - Script localization for AJAX endpoints and translations
 * - Security nonce generation for AJAX requests
 *
 * The class follows WordPress coding standards for script and style
 * enqueuing, ensuring proper dependency management and localization.
 *
 * @since      1.0.0
 * @package    WP_Read_Tools
 * @subpackage WP_Read_Tools/includes
 * @author     Adalberto H. Vega <contacto@inteldevign.com>
 */
class WP_Read_Tools_Enqueue {

	/**
	 * Initialize the asset enqueuing system.
	 *
	 * Registers the wp_enqueue_scripts action hook to load frontend assets
	 * when WordPress is preparing to output scripts and styles.
	 *
	 * @since  1.0.0
	 * @access public
	 * @static
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueues all necessary frontend scripts and styles conditionally.
	 *
	 * This method performs conditional asset loading to optimize performance:
	 * 1. Checks if the readtime shortcode is present in current content
	 * 2. Loads Font Awesome icon library from CDN (with conflict detection)
	 * 3. Enqueues plugin's main stylesheet with version control
	 * 4. Enqueues JavaScript file with jQuery dependency
	 * 5. Localizes script with AJAX configuration and translated strings
	 * 6. Generates security nonces for AJAX requests
	 *
	 * Assets are loaded with proper versioning to ensure cache busting
	 * when plugin updates occur. Only loads when shortcode is detected
	 * to minimize performance impact on pages that don't use the plugin.
	 *
	 * @since  1.0.0
	 * @access public
	 * @static
	 *
	 * @global WP_Post $post Current post object.
	 * @global string  $wp_version WordPress version number.
	 *
	 * @return void
	 *
	 * @uses wp_enqueue_style()    Enqueues CSS stylesheets.
	 * @uses wp_enqueue_script()   Enqueues JavaScript files.
	 * @uses wp_localize_script()  Localizes scripts with data and translations.
	 * @uses wp_create_nonce()     Creates security nonces for AJAX calls.
	 * @uses admin_url()           Gets admin URL for AJAX endpoint.
	 * @uses has_shortcode()       Checks if shortcode exists in content.
	 */
	public static function enqueue_scripts() {
		global $post;

		// Check if we need to load assets
		$should_load_assets = self::should_load_assets();

		if ( ! $should_load_assets ) {
			return; // Skip loading assets if shortcode not detected
		}

		// Load Font Awesome with conflict detection
		self::enqueue_font_awesome();

        // Enqueue the plugin's main stylesheet.
        wp_enqueue_style(
            'wp-read-tools-styles',
            WP_READ_TOOLS_URL . 'assets/css/read-tools.css',
            array(),
            WP_READ_TOOLS_VERSION
        );

        // Enqueue the plugin's JavaScript file.
        wp_enqueue_script(
            'wp-read-tools-script',
            WP_READ_TOOLS_URL . 'assets/js/read-aloud.js',
            array('jquery'), // Dependency: jQuery
            WP_READ_TOOLS_VERSION,
            true // Load in footer
        );

        // Localize script with necessary data.
        wp_localize_script(
            'wp-read-tools-script',
            'readAloudSettings', // Object name in JavaScript
            array(
                'ajax_url'    => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce('read_aloud_nonce'), // Nonce for security
                'readingText' => __('Reading...', 'wp-read-tools'),
                'pauseText'   => __('Pause', 'wp-read-tools'),
                'resumeText'  => __('Resume', 'wp-read-tools'),
                'errorText'   => __('Error fetching content.', 'wp-read-tools'), // Added generic error text
                'ajaxAction'  => 'wp_read_tools_get_content', // Define AJAX action name
            )
		);
	}

	/**
	 * Determines whether plugin assets should be loaded.
	 *
	 * Checks various conditions to determine if the readtime shortcode is present
	 * or likely to be present on the current page, optimizing performance by only
	 * loading assets when needed.
	 *
	 * @since  1.0.0
	 * @access private
	 * @static
	 *
	 * @global WP_Post $post Current post object.
	 *
	 * @return bool True if assets should be loaded, false otherwise.
	 */
	private static function should_load_assets() {
		global $post, $wp_query;

		// Always load in admin or when customizing
		if ( is_admin() || is_customize_preview() ) {
			return true;
		}

		// Allow force loading via filter (for theme integration)
		if ( apply_filters( 'wp_read_tools_force_load_assets', false ) ) {
			return true;
		}

		// For now, be more permissive to avoid breaking functionality
		// Load on singular pages (posts, pages, custom post types)
		if ( is_singular() ) {
			// Check current post content if available
			if ( is_a( $post, 'WP_Post' ) && ! empty( $post->post_content ) ) {
				if ( has_shortcode( $post->post_content, 'readtime' ) ) {
					return true;
				}
			}

			// Also check the queried object in case $post is not set yet
			$queried_object = get_queried_object();
			if ( $queried_object instanceof WP_Post && ! empty( $queried_object->post_content ) ) {
				if ( has_shortcode( $queried_object->post_content, 'readtime' ) ) {
					return true;
				}
			}

			// For singular pages, load assets to be safe (theme might add shortcode)
			// This maintains functionality while still optimizing for non-singular pages
			return true;
		}

		// Check for shortcode in queried posts (for archive pages, search results, etc.)
		if ( is_home() || is_archive() || is_search() ) {
			if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
				foreach ( $wp_query->posts as $queried_post ) {
					if ( has_shortcode( $queried_post->post_content, 'readtime' ) ) {
						return true;
					}
				}
			}
		}

		// Don't load on other pages (like 404, etc.) unless forced
		return false;
	}

	/**
	 * Enqueues Font Awesome with conflict detection.
	 *
	 * Checks if Font Awesome is already loaded by theme or other plugins
	 * to avoid conflicts and duplicate loading. Provides filters to allow
	 * themes to disable Font Awesome loading entirely.
	 *
	 * @since  1.0.0
	 * @access private
	 * @static
	 *
	 * @return void
	 *
	 * @uses wp_enqueue_style()    Enqueues Font Awesome stylesheet.
	 * @uses wp_style_is()         Checks if style is already enqueued.
	 */
	private static function enqueue_font_awesome() {
		// Allow themes/plugins to disable Font Awesome loading
		if ( ! apply_filters( 'wp_read_tools_load_fontawesome', true ) ) {
			return;
		}

		// Check if Font Awesome is already enqueued by theme or other plugins
		$fontawesome_handles = array(
			'font-awesome',
			'fontawesome',
			'font-awesome-5',
			'fontawesome-5',
			'fa',
			'fa5',
			'dashicons' // WordPress includes some icons we could use as fallback
		);

		foreach ( $fontawesome_handles as $handle ) {
			if ( wp_style_is( $handle, 'enqueued' ) || wp_style_is( $handle, 'registered' ) ) {
				// Font Awesome already available, don't load another copy
				return;
			}
		}

		// Load Font Awesome from CDN
		wp_enqueue_style(
			'wp-read-tools-fontawesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
			array(),
			'5.15.4'
		);
	}
}
