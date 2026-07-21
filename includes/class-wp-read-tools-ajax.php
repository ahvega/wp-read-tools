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

		// Verify the security nonce BEFORE any state-creating work. The rate
		// limiter writes transients, so running it first let unauthenticated
		// callers create rows in wp_options without presenting a nonce at all.
		// The nonce name 'read_aloud_nonce' should match the one created in WP_Read_Tools_Enqueue.
		// The key 'nonce' should match the key sent in the AJAX data from read-aloud.js.
		// is_string() guards against nonce[]=x, which would otherwise reach
		// sanitize_key() as an array and raise a TypeError on PHP 8 -- turning a
		// clean 403 into a 500.
		if ( ! isset( $_POST['nonce'] ) || ! is_string( $_POST['nonce'] )
			|| ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'read_aloud_nonce' ) ) {
			wp_read_tools_log( 'AJAX request failed nonce verification', 'error' );
			wp_send_json_error(
				array( 'message' => __( 'Security check failed.', 'wp-read-tools' ) ),
				403 // Forbidden
			);
		}

		wp_read_tools_log( 'AJAX nonce verification passed' );

		// Check if the post ID is provided.
		if ( ! isset( $_POST['post_id'] ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Error: Post ID not provided.', 'wp-read-tools' ) ),
				400 // Bad Request
			);
		}

		// Sanitize and validate the post ID. is_scalar() rejects post_id[]=x,
		// which intval() would otherwise silently coerce to 1.
		$post_id = is_scalar( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( $post_id <= 0 ) {
			wp_send_json_error(
				array( 'message' => __( 'Error: Invalid post ID.', 'wp-read-tools' ) ),
				400 // Bad Request
			);
		}

		// Rate limiting runs after the nonce check so that only requests which
		// already passed CSRF verification can allocate a transient.
		if ( ! self::check_rate_limit() ) {
			wp_read_tools_log( 'AJAX request blocked due to rate limiting', 'warning' );
			wp_send_json_error(
				array( 'message' => __( 'Too many requests. Please try again later.', 'wp-read-tools' ) ),
				429 // Too Many Requests
			);
		}

		// Access control. This MUST run before the cache lookup: serving a cached
		// body first would bypass the check entirely whenever a post's visibility
		// changed without bumping post_modified.
		if ( ! self::is_post_readable( $post_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Error: Post not found or not accessible.', 'wp-read-tools' ) ),
				404 // Not Found
			);
		}

		// Check cache only after the request is known to be authorized.
		$cached_content = self::get_cached_content( $post_id );
		if ( $cached_content !== false ) {
			wp_read_tools_log( "Serving cached content for post ID: {$post_id}" );
			wp_send_json_success( array( 'content' => $cached_content ) );
		}

		// Get the post content - use standard post_content field which contains
		// the actual article text (including page builder shortcodes with content)
		$content = get_post_field( 'post_content', $post_id );

		// Cap the amount of content processed per request. Without this an
		// unbounded post is read, regex-processed and cached on every call,
		// which is a cheap CPU/memory amplification vector on a public endpoint.
		// get_post_field() can return a non-string for an invalid field/post;
		// normalise before any string function runs (strlen( null ) is deprecated
		// in PHP 8.1 and fatal for an object).
		if ( ! is_string( $content ) ) {
			$content = '';
		}

		$max_length = (int) apply_filters( 'wp_read_tools_max_content_length', 500000, $post_id );
		if ( $max_length > 0 && strlen( $content ) > $max_length ) {
			wp_read_tools_log(
				sprintf( 'Content for post %d truncated from %d to %d bytes', $post_id, strlen( $content ), $max_length ),
				'warning'
			);

			// Truncate on a character boundary. A byte-wise substr() can split a
			// multibyte sequence, and the downstream /u regexes in
			// process_content_for_speech() return null on malformed UTF-8 --
			// turning a size cap into an empty response.
			if ( function_exists( 'mb_strcut' ) ) {
				$content = mb_strcut( $content, 0, $max_length, 'UTF-8' );
			} else {
				$content = substr( $content, 0, $max_length );
				// Drop a trailing partial sequence left by the byte-wise cut.
				$content = preg_replace( '/(?:[\xC0-\xFF][\x80-\xBF]*)$/', '', $content );
			}
		}

		wp_read_tools_log( "Retrieved content for post {$post_id}, length: " . strlen($content) );

		if ( is_wp_error( $content ) ) {
			wp_read_tools_log( 'Error retrieving post content: ' . $content->get_error_message(), 'error' );
			wp_send_json_error(
				array(
					'message' => __( 'Error retrieving post content.', 'wp-read-tools' ),
					'debug' => 'content_retrieval_failed'
				),
				500 // Internal Server Error
			);
			wp_die();
		}

		if ( empty( $content ) ) {
			wp_read_tools_log( "Empty content retrieved for post {$post_id}", 'warning' );
			wp_send_json_error(
				array(
					'message' => __( 'Post content is empty.', 'wp-read-tools' ),
					'debug' => 'empty_content'
				),
				400 // Bad Request
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
	 * Determines whether a post may be read aloud by the current requester.
	 *
	 * This is the endpoint's access-control gate. A bare `post_status === 'publish'`
	 * test is NOT sufficient:
	 *
	 * - Password-protected posts keep the `publish` status (protection lives in
	 *   `post_password`), so a status-only check hands their plaintext to any
	 *   anonymous caller.
	 * - Non-public post types (`wp_block`, field groups, private CPTs) are also
	 *   commonly stored with the `publish` status and would be readable by
	 *   enumerating numeric IDs.
	 *
	 * @since  1.1.1
	 * @access private
	 * @static
	 *
	 * @param  int $post_id Post ID to authorize.
	 * @return bool         True if the post may be returned to this requester.
	 */
	private static function is_post_readable( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		// Default to every publicly-registered type, so custom post types that
		// worked before this change keep working. This still excludes the
		// internal types that motivated the check (wp_block, ACF field groups
		// and similar are registered with 'public' => false). Sites can narrow
		// or widen it deliberately via the filter.
		$allowed_types = apply_filters(
			'wp_read_tools_allowed_post_types',
			get_post_types( array( 'public' => true ) ),
			$post_id
		);
		if ( ! in_array( $post->post_type, (array) $allowed_types, true ) ) {
			return false;
		}

		// is_post_publicly_viewable() is WP 5.7+; this plugin supports 5.0.
		if ( function_exists( 'is_post_publicly_viewable' ) ) {
			if ( ! is_post_publicly_viewable( $post ) ) {
				return false;
			}
		} else {
			// Mirror is_post_type_viewable(): publicly_queryable, or a builtin
			// public type. Testing ->public alone would admit types registered
			// public but not publicly_queryable.
			$type_object = get_post_type_object( $post->post_type );
			if ( ! $type_object || 'publish' !== $post->post_status ) {
				return false;
			}
			$type_viewable = ! empty( $type_object->publicly_queryable )
				|| ( ! empty( $type_object->_builtin ) && ! empty( $type_object->public ) );
			if ( ! $type_viewable ) {
				return false;
			}
		}

		// Password-protected content requires the password, which this endpoint
		// does not accept. Refuse rather than leak.
		if ( post_password_required( $post ) ) {
			return false;
		}

		return true;
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
		// Remove shortcode tags but preserve inner content (critical for page builders
		// like Avada/Fusion Builder whose registered shortcodes would be removed entirely
		// by strip_shortcodes(), including the text content within them).
		$stripped_content = preg_replace( '/\[\/?\w[^\]]*\]/', '', $content );
		$stripped_content = wp_strip_all_tags( $stripped_content );

		// Fix drop-cap artifact: when a drop-cap shortcode wraps a single letter,
		// stripping tags leaves a space between the letter and the rest of the word
		// (e.g. [fusion_dropcap]O[/fusion_dropcap] rando → "O rando" instead of "Orando").
		$stripped_content = trim( $stripped_content );
		$stripped_content = preg_replace( '/^(\pL)\s+(\pL)/u', '$1$2', $stripped_content );

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

		// Rate limit settings (filterable) - more lenient defaults
		$max_requests = apply_filters( 'wp_read_tools_rate_limit_max_requests', 60 ); // 60 requests (doubled)
		$time_window = apply_filters( 'wp_read_tools_rate_limit_time_window', 300 ); // 5 minutes

		// Fixed window. The stored value carries its own start time so that the
		// TTL is not refreshed on every hit; refreshing it turned this into a
		// sliding window that could keep a steady low-rate caller blocked
		// indefinitely once they crossed the threshold.
		$bucket = get_transient( $rate_limit_key );
		if ( ! is_array( $bucket ) || ! isset( $bucket['count'], $bucket['start'] ) ) {
			$bucket = array(
				'count' => 0,
				'start' => time(),
			);
		}

		$elapsed = time() - (int) $bucket['start'];
		if ( $elapsed >= $time_window ) {
			// Previous window expired; start a fresh one.
			$bucket = array(
				'count' => 0,
				'start' => time(),
			);
			$elapsed = 0;
		}

		// Check if limit exceeded
		if ( $bucket['count'] >= $max_requests ) {
			return false;
		}

		// Increment request count.
		// NOTE: read-modify-write via transients is not atomic, so highly
		// concurrent requests from one key can slightly overshoot the cap. This
		// is a throttle, not a hard quota; the security boundary is the nonce
		// and the access-control check, not this counter.
		$bucket['count']++;
		set_transient( $rate_limit_key, $bucket, $time_window - $elapsed );

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
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		// Proxy headers are attacker-controlled unless a proxy in front of the
		// site overwrites them. Trusting them unconditionally allowed both a
		// trivial limit bypass (rotate the header) and a denial-of-service
		// primitive (set the header to a victim's IP to exhaust their bucket).
		//
		// Sites genuinely behind a proxy opt in by returning the proxy's own
		// address(es) from this filter; the header is honoured only when the
		// immediate peer is one of them.
		$trusted_proxies = (array) apply_filters( 'wp_read_tools_trusted_proxies', array() );

		if ( ! empty( $trusted_proxies ) && in_array( $remote_addr, $trusted_proxies, true ) ) {
			// Single-value headers written by the edge itself. Cloudflare and
			// nginx overwrite these rather than appending, so the value cannot
			// carry an attacker-supplied prefix.
			foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP' ) as $header ) {
				if ( empty( $_SERVER[ $header ] ) ) {
					continue;
				}
				$ip = trim( sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}

			// X-Forwarded-For is APPENDED to, so the leftmost entry is whatever
			// the client sent and is fully attacker-controlled even behind a
			// trusted proxy. Walk right-to-left, discarding known proxies; the
			// first non-proxy address is the real peer.
			if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
				$parts     = array_map( 'trim', explode( ',', $forwarded ) );

				for ( $i = count( $parts ) - 1; $i >= 0; $i-- ) {
					if ( in_array( $parts[ $i ], $trusted_proxies, true ) ) {
						continue;
					}
					// Private/reserved ranges are legitimate keys here; rejecting
					// them made the limiter disable itself entirely on
					// containerised installs where the peer is often 10.x.
					if ( filter_var( $parts[ $i ], FILTER_VALIDATE_IP ) ) {
						return $parts[ $i ];
					}
					break; // First non-proxy entry was malformed; do not trust further left.
				}
			}
		}

		return filter_var( $remote_addr, FILTER_VALIDATE_IP ) ? $remote_addr : '';
	}

}