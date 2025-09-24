<?php
/**
 * Handles AJAX requests for the WP Read Tools plugin.
 *
 * This file contains the WP_Read_Tools_Ajax class which manages all AJAX-related
 * functionality for the plugin, specifically handling requests to fetch cleaned
 * post content for text-to-speech functionality.
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
 * AJAX handler class for WP Read Tools plugin.
 *
 * This class manages AJAX endpoints for the plugin, specifically handling
 * requests to fetch and clean post content for text-to-speech functionality.
 * It implements proper security checks, content validation, and error handling.
 *
 * @since      1.0.0
 * @package    WP_Read_Tools
 * @subpackage WP_Read_Tools/includes
 * @author     Adalberto H. Vega <contacto@inteldevign.com>
 */
class WP_Read_Tools_Ajax {

	/**
	 * Initialize AJAX hooks and actions.
	 *
	 * Registers the AJAX endpoints for both logged-in and non-logged-in users
	 * to handle post content retrieval for text-to-speech functionality.
	 *
	 * @since  1.0.0
	 * @access public
	 * @static
	 *
	 * @return void
	 */
	public static function init() {
		// Hook for logged-in users.
		add_action( 'wp_ajax_wp_read_tools_get_content', array( __CLASS__, 'handle_get_content_request' ) );
		// Hook for non-logged-in users.
		add_action( 'wp_ajax_nopriv_wp_read_tools_get_content', array( __CLASS__, 'handle_get_content_request' ) );
	}

	/**
	 * Handles AJAX requests to fetch cleaned post content for text-to-speech.
	 *
	 * This method performs the following operations:
	 * 1. Verifies security nonce to prevent CSRF attacks
	 * 2. Validates and sanitizes the post ID parameter
	 * 3. Checks post existence and publication status
	 * 4. Retrieves and cleans post content (removes HTML, shortcodes)
	 * 5. Returns cleaned content as JSON response
	 *
	 * Security measures implemented:
	 * - Nonce verification for CSRF protection
	 * - Input validation and sanitization
	 * - Post status verification (only published posts)
	 * - Error handling with appropriate HTTP status codes
	 *
	 * @since  1.0.0
	 * @access public
	 * @static
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void Outputs JSON response and terminates execution.
	 */
	public static function handle_get_content_request() {
		wp_read_tools_log( 'AJAX request received for content retrieval' );

		// Check rate limiting first
		if ( ! self::check_rate_limit() ) {
			wp_read_tools_log( 'AJAX request blocked due to rate limiting', 'warning' );
			wp_send_json_error(
				array( 'message' => __( 'Too many requests. Please try again later.', 'wp-read-tools' ) ),
				429 // Too Many Requests
			);
			wp_die();
		}

		// Verify the security nonce.
		// The nonce name 'read_aloud_nonce' should match the one created in WP_Read_Tools_Enqueue.
		// The key 'nonce' should match the key sent in the AJAX data from read-aloud.js.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'read_aloud_nonce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed.', 'wp-read-tools' ) ),
				403 // Forbidden
			);
			wp_die();
		}

		// Check if the post ID is provided.
		if ( ! isset( $_POST['post_id'] ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Error: Post ID not provided.', 'wp-read-tools' ) ),
				400 // Bad Request
			);
			wp_die();
		}

		// Sanitize and validate the post ID.
		$post_id = intval( $_POST['post_id'] );
		if ( $post_id <= 0 ) {
			wp_send_json_error(
				array( 'message' => __( 'Error: Invalid post ID.', 'wp-read-tools' ) ),
				400 // Bad Request
			);
			wp_die();
		}

		// Check cache first
		$cached_content = self::get_cached_content( $post_id );
		if ( $cached_content !== false ) {
			wp_read_tools_log( "Serving cached content for post ID: {$post_id}" );
			wp_send_json_success( array( 'content' => $cached_content ) );
			wp_die();
		}

		// Check if the post exists and is published (or user has permission to read).
		$post_status = get_post_status( $post_id );
		if ( ! $post_status || 'publish' !== $post_status ) {
			// Add more sophisticated checks if needed (e.g., for private posts and user capabilities).
			wp_send_json_error(
				array( 'message' => __( 'Error: Post not found or not accessible.', 'wp-read-tools' ) ),
				404 // Not Found
			);
			wp_die();
		}

		// Get the raw post content.
		$content = get_post_field( 'post_content', $post_id );

		if ( is_wp_error( $content ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Error retrieving post content.', 'wp-read-tools' ) ),
				500 // Internal Server Error
			);
			wp_die();
		}

		// Clean up the content for reading: remove shortcodes and HTML tags.
		$stripped_content = self::process_content_for_speech( $content, $post_id );

		// Cache the processed content
		self::cache_content( $post_id, $stripped_content );

		wp_read_tools_log( "Successfully processed and cached content for post ID: {$post_id}" );

		// Send successful response with cleaned content.
		wp_send_json_success( array( 'content' => $stripped_content ) );

		// wp_die() is called automatically by wp_send_json_success / wp_send_json_error.
	}

	/**
	 * Processes post content for text-to-speech functionality.
	 *
	 * Cleans post content by removing HTML tags, shortcodes, and normalizing
	 * whitespace to create speech-friendly text. Applies filters to allow
	 * customization of the content processing.
	 *
	 * @since  1.0.0
	 * @access private
	 * @static
	 *
	 * @param  string $content Raw post content.
	 * @param  int    $post_id Post ID for context.
	 * @return string          Processed content ready for speech synthesis.
	 */
	private static function process_content_for_speech( $content, $post_id ) {
		// Remove shortcodes and HTML tags
		$stripped_content = strip_shortcodes( $content );
		$stripped_content = wp_strip_all_tags( $stripped_content );

		// Decode HTML entities that might remain after stripping tags
		$stripped_content = html_entity_decode( $stripped_content );

		// Allow filtering of content before speech synthesis
		$stripped_content = apply_filters( 'wp_read_tools_speech_content', $stripped_content, $post_id );

		// Normalize whitespace
		$stripped_content = preg_replace( '/\s+/', ' ', $stripped_content );
		$stripped_content = trim( $stripped_content );

		return $stripped_content;
	}

	/**
	 * Retrieves cached content for a specific post.
	 *
	 * Attempts to retrieve processed content from WordPress cache to avoid
	 * repeated processing of the same content. Cache keys are based on
	 * post ID and last modified time to ensure freshness.
	 *
	 * @since  1.0.0
	 * @access private
	 * @static
	 *
	 * @param  int $post_id Post ID to retrieve cached content for.
	 * @return string|false Cached content on success, false on failure.
	 */
	private static function get_cached_content( $post_id ) {
		$cache_key = self::get_cache_key( $post_id );
		return wp_cache_get( $cache_key, 'wp_read_tools' );
	}

	/**
	 * Caches processed content for a specific post.
	 *
	 * Stores processed content in WordPress cache with a reasonable expiration
	 * time to balance performance and memory usage.
	 *
	 * @since  1.0.0
	 * @access private
	 * @static
	 *
	 * @param int    $post_id Post ID to cache content for.
	 * @param string $content Processed content to cache.
	 * @return bool           True on success, false on failure.
	 */
	private static function cache_content( $post_id, $content ) {
		$cache_key = self::get_cache_key( $post_id );
		// Cache for 1 hour by default, allow filtering
		$cache_duration = apply_filters( 'wp_read_tools_cache_duration', HOUR_IN_SECONDS );
		return wp_cache_set( $cache_key, $content, 'wp_read_tools', $cache_duration );
	}

	/**
	 * Generates cache key for post content.
	 *
	 * Creates a unique cache key based on post ID and last modified time
	 * to ensure cache invalidation when content is updated.
	 *
	 * @since  1.0.0
	 * @access private
	 * @static
	 *
	 * @param  int $post_id Post ID to generate cache key for.
	 * @return string       Generated cache key.
	 */
	private static function get_cache_key( $post_id ) {
		$post_modified = get_post_modified_time( 'U', true, $post_id );
		return "content_{$post_id}_{$post_modified}";
	}

	/**
	 * Implements basic rate limiting for AJAX requests.
	 *
	 * Prevents abuse by limiting the number of requests per IP address
	 * within a specified time window. Uses WordPress transients for
	 * temporary storage of request counts.
	 *
	 * @since  1.0.0
	 * @access private
	 * @static
	 *
	 * @return bool True if request is allowed, false if rate limit exceeded.
	 */
	private static function check_rate_limit() {
		// Allow disabling rate limiting via filter
		if ( ! apply_filters( 'wp_read_tools_enable_rate_limiting', true ) ) {
			return true;
		}

		// Get client IP address
		$client_ip = self::get_client_ip();
		if ( empty( $client_ip ) ) {
			return true; // Allow if we can't determine IP
		}

		// Create rate limit key
		$rate_limit_key = 'wp_read_tools_rate_limit_' . md5( $client_ip );

		// Get current request count
		$request_count = get_transient( $rate_limit_key );
		if ( $request_count === false ) {
			$request_count = 0;
		}

		// Rate limit settings (filterable)
		$max_requests = apply_filters( 'wp_read_tools_rate_limit_max_requests', 30 ); // 30 requests
		$time_window = apply_filters( 'wp_read_tools_rate_limit_time_window', 300 ); // 5 minutes

		// Check if limit exceeded
		if ( $request_count >= $max_requests ) {
			return false;
		}

		// Increment request count
		$request_count++;
		set_transient( $rate_limit_key, $request_count, $time_window );

		return true;
	}

	/**
	 * Gets the client IP address with proxy support.
	 *
	 * Attempts to determine the real client IP address, accounting for
	 * common proxy headers while maintaining security.
	 *
	 * @since  1.0.0
	 * @access private
	 * @static
	 *
	 * @return string Client IP address or empty string if unavailable.
	 */
	private static function get_client_ip() {
		// Check for various proxy headers
		$ip_headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_FORWARDED_FOR', // Standard proxy header
			'HTTP_X_REAL_IP', // Nginx proxy
			'REMOTE_ADDR' // Direct connection
		);

		foreach ( $ip_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

				// Handle comma-separated IPs (X-Forwarded-For can have multiple IPs)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip_list = explode( ',', $ip );
					$ip = trim( $ip_list[0] ); // Use the first IP
				}

				// Validate IP address
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return '';
	}
}