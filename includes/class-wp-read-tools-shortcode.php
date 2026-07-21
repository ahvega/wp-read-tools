<?php
/**
 * Shortcode handler for the WP Read Tools plugin.
 *
 * This file contains the WP_Read_Tools_Shortcode class which handles the
 * registration and rendering of the [readtime] shortcode. It calculates
 * reading time estimates and optionally provides text-to-speech functionality.
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
 * Shortcode handler class for WP Read Tools plugin.
 *
 * This class manages the [readtime] shortcode functionality, including:
 * - Reading time calculation based on word count and reading speed
 * - Text-to-speech integration with customizable parameters
 * - Localization support for different number formats
 * - Accessibility features with proper ARIA attributes
 *
 * @since      1.0.0
 * @package    WP_Read_Tools
 * @subpackage WP_Read_Tools/includes
 * @author     Adalberto H. Vega <contacto@inteldevign.com>
 */
class WP_Read_Tools_Shortcode {

	/**
	 * Initialize shortcode registration.
	 *
	 * Registers the [readtime] shortcode with WordPress to enable
	 * reading time estimation and text-to-speech functionality.
	 *
	 * @since  1.0.0
	 * @access public
	 * @static
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'readtime', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Format numbers according to locale with special handling for Spanish.
	 *
	 * Provides localized number formatting with special consideration for
	 * Latin American Spanish locales, which use different decimal and
	 * thousands separators than other locales.
	 *
	 * @since  1.0.0
	 * @access private
	 * @static
	 *
	 * @param  float $number   The number to format.
	 * @param  int   $decimals Number of decimal places. Default 0.
	 * @return string          Formatted number according to locale.
	 */
	private static function format_number( $number, $decimals = 0 ) {
		$locale = determine_locale();

		// For Spanish locales, use Latin American format (period for decimals, comma for thousands)
		if (strpos($locale, 'es_') === 0) {
			return number_format($number, $decimals, '.', ',');
		}

		// For other locales, use WordPress default formatting
		return number_format_i18n($number, $decimals);
	}

	/**
	 * Enhanced content detection for page builders and custom content areas.
	 *
	 * IMPORTANT NOTE FOR PAGE BUILDER USERS (Avada, Elementor, etc.):
	 * For optimal functionality, ensure your post content is included in WordPress's
	 * native post content field (the main editor), not exclusively in page builder modules.
	 * The plugin will extract content from page builders as a secondary source, but
	 * works best when there's at least a summary in the native content field.
	 *
	 * Attempts to retrieve post content using multiple strategies:
	 * 1. Custom content ID selector (if provided via shortcode parameter)
	 * 2. Avada Builder content detection from meta fields
	 * 3. Elementor content detection from JSON data
	 * 4. Standard post content fallback (always recommended to populate)
	 *
	 * Content Detection Priority:
	 * - Primary: Native WordPress post content field (post_content)
	 * - Secondary: Page builder meta fields (_avada_page_content, _elementor_data, etc.)
	 * - Fallback: Frontend content extraction (marked for JavaScript processing)
	 *
	 * @since  1.0.1
	 * @access private
	 * @static
	 *
	 * @param  int    $post_id    Post ID to retrieve content for.
	 * @param  string $content_id Optional CSS selector ID for custom content container.
	 * @return string             Post content or empty string if not found.
	 */
	private static function get_post_content_enhanced( $post_id, $content_id = '' ) {
		$content = '';

		// NOTE: this method previously called update_post_meta() in four places
		// to record '_wp_read_tools_content_selector' and
		// '_wp_read_tools_needs_frontend_extraction'. Those writes have been
		// removed. They were:
		//
		// - Unread. The only consumers were in the unreferenced page-builder
		//   extraction path, deleted alongside this change.
		// - A database write on every uncached front-end page render, by every
		//   anonymous visitor, from what should be a pure read. The Avada branch
		//   fired on essentially every post, since _avada_page_content is empty
		//   in the common case and the guard tested for content shorter than
		//   100 characters.
		// - Cache-invalidating: each update_post_meta() call also fires
		//   wp_cache_delete( $post_id, 'post_meta' ), discarding that post's
		//   whole meta cache and forcing later meta reads to re-query.
		//
		// Shortcode rendering must have no persistent side effects.

		// Avada/Fusion Builder pages may hold their body copy in a meta field
		// rather than post_content.
		if ( function_exists( 'avada_get_theme_option' ) || class_exists( 'FusionBuilder' ) || get_template() === 'Avada' ) {
			$avada_content = get_post_meta( $post_id, '_avada_page_content', true );
			if ( ! empty( $avada_content ) ) {
				$content = $avada_content;
			}
		}

		// Standard post content fallback.
		if ( empty( $content ) ) {
			$content = get_post_field( 'post_content', $post_id );
		}

		if ( ! is_string( $content ) ) {
			$content = '';
		}

		// Log content detection for debugging
		wp_read_tools_log( sprintf(
			'Content detection for post %d: length=%d',
			$post_id,
			strlen( $content )
		) );

		return $content;
	}

	/**
	 * Renders the HTML output for the [readtime] shortcode.
	 *
	 * Calculates the estimated reading time for the current post and optionally displays
	 * a link to trigger text-to-speech functionality.
	 *
	 * @since 1.0.0
	 *
	 * @param array|string $atts {
	 *     Optional. An array of shortcode attributes. Default empty.
	 *
	 *     @type string $read-aloud Whether to show the read-aloud link ('yes' or 'no'). Default 'no'.
	 *     @type string $class      CSS class for the container div. Default 'readtime'.
	 *     @type int    $wpm        Reading speed in words per minute. Default 180.
	 *     @type string $link_text  Text for the read-aloud link. Default 'Listen'.
	 *     @type string $icon_class Font Awesome icon class for the read-aloud button. Default 'fas fa-headphones'.
	 *     @type string $content_id CSS selector ID for custom content container. Default empty (uses post content).
	 * }
	 * @return string HTML output for the shortcode. Returns empty string if post ID is not found.
	 */
	public static function render_shortcode( $atts ) {
		// Get the current post ID. Return empty if not in a post context.
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return ''; // Cannot calculate reading time outside a post.
		}

		// Define default attributes and merge with user-provided ones.
		$atts = shortcode_atts(
			array(
				'read-aloud' => 'no',    // Option to enable/disable the read-aloud link.
				'class'      => 'readtime', // Default CSS class for the container.
				'wpm'        => 180,     // Average reading speed (words per minute).
				'link_text'  => __( 'Listen', 'wp-read-tools' ), // Translatable link text.
				'icon_class' => 'fas fa-headphones', // Ensure space between classes
				'content_id' => '',      // CSS selector ID for custom content container
			),
			$atts,
			'readtime' // Shortcode tag used for filtering attributes.
		);

		// Sanitize attributes.
		$read_aloud = strtolower( sanitize_text_field( $atts['read-aloud'] ) );
		$class      = sanitize_html_class( $atts['class'] );
		$wpm        = absint( $atts['wpm'] );
		$link_text  = sanitize_text_field( $atts['link_text'] );
		// Use sanitize_text_field instead of sanitize_html_class to preserve spaces
		$icon_class = sanitize_text_field( $atts['icon_class'] );
		$content_id = sanitize_text_field( $atts['content_id'] );

		// Ensure WPM is reasonable.
		if ( $wpm < 1 ) {
			$wpm = 180; // Reset to default if invalid.
		}

		// Allow filtering of WPM based on post context
		$wpm = apply_filters( 'wp_read_tools_wpm', $wpm, $post_id );

		// Get the post content using enhanced detection for page builders
		$content = self::get_post_content_enhanced( $post_id, $content_id );

		// Shared with the speech path so the two can never disagree again.
		$stripped_content = wp_read_tools_clean_content( $content );

		// Allow filtering of content before word count calculation
		$stripped_content = apply_filters( 'wp_read_tools_content_before_count', $stripped_content, $post_id );

		$word_count = wp_read_tools_count_words( $stripped_content );

		// Calculate reading time in minutes.
		$minutes_exact = ( $word_count > 0 && $wpm > 0 ) ? ( $word_count / $wpm ) : 0;
		$minutes_exact = round( $minutes_exact, 2 ); // Round to 2 decimal places for precision.

		// Round up to the nearest 0.5 for display, with a 0.5 floor so that a
		// short post reads "0.5 min read" rather than "0.0 min read".
		$rounded_minutes = ceil( $minutes_exact * 2 ) / 2;
		if ( $rounded_minutes < 0.5 ) {
			$rounded_minutes = 0.5;
		}

		// Prepare the text for the reading time tooltip.
		$read_time_tooltip_text = sprintf(
			/* translators: %s: Estimated reading time in minutes (potentially with decimals). */
			__( 'Estimated reading time: %s minutes', 'wp-read-tools' ),
			self::format_number($minutes_exact, 2) // Use localized number format for tooltip.
		);

		// Prepare the text for the read-aloud link tooltip.
		$read_aloud_tooltip_text = __( 'Listen to this article', 'wp-read-tools' );

		// Start building the HTML output.
		$output = '<div class="' . esc_attr( $class ) . '">';

		// Reading time line.
		$output .= '<span class="read-time-line" title="' . esc_attr( $read_time_tooltip_text ) . '">';
		$output .= '<i class="fas fa-stopwatch" aria-hidden="true"></i> '; // Added aria-hidden for decorative icon.

		// Allow filtering of time format
		$time_format = apply_filters( 'wp_read_tools_time_format', __( '%s min read', 'wp-read-tools' ), $rounded_minutes, $post_id );

		$output .= sprintf( esc_html( $time_format ),
			self::format_number( $rounded_minutes, 1 )
		);
		$output .= '</span>';

		// Create the read-aloud link if enabled.
		if ( 'yes' === $read_aloud ) {
			$output .= '<span class="read-aloud-line read-aloud-link" title="' . esc_attr( $read_aloud_tooltip_text ) . '">';
			$output .= '<a href="#" class="read-aloud-trigger" data-post-id="' . esc_attr( $post_id ) . '">';
			$output .= '<i class="' . esc_attr( str_replace('  ', ' ', $icon_class) ) . '" aria-hidden="true"></i> '; // Added str_replace to ensure single spaces
			$output .= esc_html( $link_text );
			$output .= '</a>';
			$output .= '</span>';
		}

		$output .= '</div>';

		return $output;
	}
}