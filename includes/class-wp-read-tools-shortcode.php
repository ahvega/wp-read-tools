<?php
/**
 * Handles the [readtime] shortcode for the WP Read Tools plugin.
 *
 * @package WP_Read_Tools
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Read_Tools_Shortcode
 *
 * Registers and renders the [readtime] shortcode.
 */
class WP_Read_Tools_Shortcode {

	/**
	 * Initialize hooks. Registers the shortcode.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_shortcode( 'readtime', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Format numbers according to locale, with special handling for Latin American Spanish.
	 *
	 * @param float $number    The number to format
	 * @param int   $decimals  Number of decimal points
	 * @return string          Formatted number
	 */
	private static function format_number($number, $decimals = 0) {
		$locale = determine_locale();

		// For Spanish locales, use Latin American format (period for decimals, comma for thousands)
		if (strpos($locale, 'es_') === 0) {
			return number_format($number, $decimals, '.', ',');
		}

		// For other locales, use WordPress default formatting
		return number_format_i18n($number, $decimals);
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

		// Ensure WPM is reasonable.
		if ( $wpm < 1 ) {
			$wpm = 180; // Reset to default if invalid.
		}

		// Get the post content.
		$content = get_post_field( 'post_content', $post_id );

		// Remove shortcodes and HTML tags to get a clean word count.
		$stripped_content = strip_shortcodes( $content );
		$stripped_content = wp_strip_all_tags( $stripped_content );
		$word_count       = str_word_count( $stripped_content );

		// Calculate reading time in minutes.
		$minutes_exact = ( $word_count > 0 && $wpm > 0 ) ? ( $word_count / $wpm ) : 0;
		$minutes_exact = round( $minutes_exact, 2 ); // Round to 2 decimal places for precision.

		// Round up to the nearest 0.5 for display.
		$rounded_minutes = ceil( $minutes_exact * 2 ) / 2;

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
		/* translators: %s: Reading time rounded to nearest half minute. */
		$output .= sprintf( esc_html__( '%s min read', 'wp-read-tools' ),
			self::format_number($rounded_minutes, 1)
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