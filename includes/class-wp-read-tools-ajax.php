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

		// Check rate limiting first (but be more lenient for debugging)
		if ( ! self::check_rate_limit() ) {
			wp_read_tools_log( 'AJAX request blocked due to rate limiting', 'warning' );
			// Send a more specific error for debugging
			wp_send_json_error(
				array(
					'message' => __( 'Too many requests. Please try again later.', 'wp-read-tools' ),
					'debug' => 'rate_limit_exceeded'
				),
				429 // Too Many Requests
			);
			wp_die();
		}

		// Verify the security nonce.
		// The nonce name 'read_aloud_nonce' should match the one created in WP_Read_Tools_Enqueue.
		// The key 'nonce' should match the key sent in the AJAX data from read-aloud.js.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'read_aloud_nonce' ) ) {
			wp_read_tools_log( 'AJAX request failed nonce verification', 'error' );
			wp_send_json_error(
				array(
					'message' => __( 'Security check failed.', 'wp-read-tools' ),
					'debug' => 'nonce_verification_failed'
				),
				403 // Forbidden
			);
			wp_die();
		}

		wp_read_tools_log( 'AJAX nonce verification passed' );

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

		// Get the raw post content using direct database extraction
		$content = self::extract_all_content_from_database( $post_id );

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

		// Rate limit settings (filterable) - more lenient defaults
		$max_requests = apply_filters( 'wp_read_tools_rate_limit_max_requests', 60 ); // 60 requests (doubled)
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
	 * Enhanced content retrieval for page builders and custom content areas.
	 *
	 * IMPORTANT FOR PAGE BUILDER USERS (Avada, Elementor, etc.):
	 * This plugin works best when content is included in WordPress's native post
	 * content field. While the plugin can extract content from page builders,
	 * including at least a summary in the main WordPress editor ensures optimal
	 * reading time calculation and text-to-speech functionality.
	 *
	 * Uses multiple strategies to retrieve content for speech synthesis:
	 * 1. Check if frontend extraction is needed (for page builders without backend content)
	 * 2. Use custom content selector if specified via shortcode
	 * 3. Extract from page builder meta fields (Avada, Elementor)
	 * 4. Fallback to standard post content (always recommended to populate)
	 *
	 * Content Sources Priority:
	 * - Native WordPress content field (best compatibility)
	 * - Page builder meta fields (secondary extraction)
	 * - Frontend content extraction (last resort)
	 *
	 * @since  1.0.1
	 * @access private
	 * @static
	 *
	 * @param  int $post_id Post ID to retrieve content for.
	 * @return string       Post content for speech synthesis.
	 */
	private static function get_post_content_for_speech( $post_id ) {
		// Check if this post needs frontend content extraction
		$needs_frontend = get_post_meta( $post_id, '_wp_read_tools_needs_frontend_extraction', true );
		$custom_selector = get_post_meta( $post_id, '_wp_read_tools_content_selector', true );

		wp_read_tools_log( sprintf(
			'Content extraction for post %d: needs_frontend=%s, custom_selector=%s',
			$post_id,
			$needs_frontend,
			$custom_selector ?: 'none'
		) );

		if ( $needs_frontend === 'yes' || ! empty( $custom_selector ) ) {
			// For page builders, we need to get the rendered content
			// This requires a different approach - we'll get what we can from the backend
			// and let the frontend JavaScript extract additional content if needed

			$content = get_post_field( 'post_content', $post_id );

			// Try to get more content from page builder meta
			if ( empty( trim( $content ) ) || $needs_frontend === 'yes' ) {
				$content = self::extract_page_builder_content( $post_id );
			}

			// If still empty, set a flag for frontend extraction
			if ( empty( trim( strip_tags( $content ) ) ) ) {
				wp_read_tools_log( "Post {$post_id} requires frontend content extraction", 'warning' );
				// We'll return a special marker that the frontend can detect
				$content = '<!-- WP_READ_TOOLS_FRONTEND_EXTRACTION_NEEDED -->' . $content;
			}

			return $content;
		}

		// Standard content retrieval
		return get_post_field( 'post_content', $post_id );
	}

	/**
	 * Extract content from page builder meta data.
	 *
	 * Attempts to retrieve content from various page builder meta fields
	 * and combines them into readable text.
	 *
	 * @since  1.0.1
	 * @access private
	 * @static
	 *
	 * @param  int $post_id Post ID to extract content from.
	 * @return string       Extracted content or empty string.
	 */
	private static function extract_page_builder_content( $post_id ) {
		$content = '';

		// Avada/Fusion Builder content extraction
		if ( function_exists( 'avada_get_theme_option' ) || class_exists( 'FusionBuilder' ) ) {
			// Try various Avada meta fields
			$avada_fields = array(
				'_avada_page_content',
				'_fusion_builder_content',
				'fusion_builder_content_backup',
			);

			foreach ( $avada_fields as $field ) {
				$avada_content = get_post_meta( $post_id, $field, true );
				if ( ! empty( $avada_content ) ) {
					$content .= ' ' . $avada_content;
				}
			}
		}

		// Elementor content extraction
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
			if ( ! empty( $elementor_data ) ) {
				// Elementor data is JSON, extract text content
				$elementor_content = self::extract_text_from_elementor_data( $elementor_data );
				if ( ! empty( $elementor_content ) ) {
					$content .= ' ' . $elementor_content;
				}
			}
		}

		// Clean up and return
		$content = trim( $content );
		if ( ! empty( $content ) ) {
			wp_read_tools_log( "Extracted page builder content for post {$post_id}, length: " . strlen( $content ) );
		}

		return $content;
	}

	/**
	 * Extract text content from Elementor JSON data.
	 *
	 * Recursively parses Elementor JSON data to extract readable text content.
	 *
	 * @since  1.0.1
	 * @access private
	 * @static
	 *
	 * @param  string $elementor_data JSON string containing Elementor data.
	 * @return string                 Extracted text content.
	 */
	private static function extract_text_from_elementor_data( $elementor_data ) {
		$text_content = '';

		if ( is_string( $elementor_data ) ) {
			$data = json_decode( $elementor_data, true );
		} else {
			$data = $elementor_data;
		}

		if ( ! is_array( $data ) ) {
			return '';
		}

		foreach ( $data as $element ) {
			if ( is_array( $element ) ) {
				// Check for text content in settings
				if ( isset( $element['settings'] ) ) {
					$settings = $element['settings'];

					// Common text fields in Elementor
					$text_fields = array( 'text', 'content', 'title', 'description', 'html' );
					foreach ( $text_fields as $field ) {
						if ( isset( $settings[ $field ] ) && ! empty( $settings[ $field ] ) ) {
							$text_content .= ' ' . $settings[ $field ];
						}
					}
				}

				// Recursively check elements array
				if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
					$text_content .= ' ' . self::extract_text_from_elementor_data( $element['elements'] );
				}
			}
		}

		return trim( $text_content );
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

	/**
	 * Extract all possible content directly from the database.
	 *
	 * This method queries the database directly to find all content associated
	 * with a post, including meta fields from page builders like Avada/Elementor.
	 *
	 * @since  1.0.1
	 * @access private
	 * @static
	 *
	 * @param  int $post_id Post ID to extract content for.
	 * @return string       Combined content from all sources.
	 */
	private static function extract_all_content_from_database( $post_id ) {
		global $wpdb;

		wp_read_tools_log( "Starting direct database content extraction for post {$post_id}" );

		$all_content = '';

		// 1. Get the standard post content
		$post_content = get_post_field( 'post_content', $post_id );
		if ( ! empty( trim( $post_content ) ) ) {
			$all_content .= $post_content . ' ';
			wp_read_tools_log( "Found standard post content, length: " . strlen( $post_content ) );
		}

		// 2. Get ALL meta values for this post that might contain content
		$meta_query = $wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND (
				meta_key LIKE %s OR
				meta_key LIKE %s OR
				meta_key LIKE %s OR
				meta_key LIKE %s OR
				meta_key LIKE %s OR
				meta_key LIKE %s OR
				meta_key LIKE %s OR
				meta_key LIKE %s
			) AND CHAR_LENGTH(meta_value) > %d",
			$post_id,
			'%content%',
			'%text%',
			'%description%',
			'%body%',
			'%fusion%',
			'%elementor%',
			'%avada%',
			'%builder%',
			50
		);

		$meta_results = $wpdb->get_results( $meta_query );

		if ( $meta_results ) {
			wp_read_tools_log( "Found " . count( $meta_results ) . " meta fields with potential content" );

			foreach ( $meta_results as $meta ) {
				$meta_content = $meta->meta_value;

				// Skip serialized data that's too complex, but try to extract strings
				if ( is_serialized( $meta_content ) ) {
					$unserialized = maybe_unserialize( $meta_content );
					if ( is_string( $unserialized ) && strlen( $unserialized ) > 50 ) {
						$all_content .= ' ' . $unserialized;
						wp_read_tools_log( "Added content from meta '{$meta->meta_key}' (unserialized), length: " . strlen( $unserialized ) );
					} elseif ( is_array( $unserialized ) ) {
						// Try to extract text from array elements
						$array_text = self::extract_text_from_array( $unserialized );
						if ( ! empty( $array_text ) ) {
							$all_content .= ' ' . $array_text;
							wp_read_tools_log( "Added content from meta '{$meta->meta_key}' (array extraction), length: " . strlen( $array_text ) );
						}
					}
				} elseif ( is_string( $meta_content ) && strlen( $meta_content ) > 50 ) {
					$all_content .= ' ' . $meta_content;
					wp_read_tools_log( "Added content from meta '{$meta->meta_key}', length: " . strlen( $meta_content ) );
				}
			}
		}

		// Clean up the combined content
		$all_content = trim( $all_content );

		wp_read_tools_log( "Total combined content length: " . strlen( $all_content ) );

		// Return what we found, even if minimal
		return $all_content;
	}

	/**
	 * Extract text content from nested arrays recursively.
	 *
	 * Used to extract readable content from complex data structures
	 * that page builders often store in meta fields.
	 *
	 * @since  1.0.1
	 * @access private
	 * @static
	 *
	 * @param  mixed $data Array or other data structure to extract text from.
	 * @return string      Extracted text content.
	 */
	private static function extract_text_from_array( $data ) {
		$text = '';

		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				if ( is_string( $value ) && strlen( $value ) > 20 && ! is_serialized( $value ) ) {
					// Only include values that look like readable text
					if ( preg_match( '/[a-zA-Z\s]{20,}/', $value ) ) {
						$text .= ' ' . $value;
					}
				} elseif ( is_array( $value ) ) {
					$text .= ' ' . self::extract_text_from_array( $value );
				}
			}
		}

		return trim( $text );
	}
}