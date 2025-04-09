<?php
/**
 * Handles AJAX requests for the WP Read Tools plugin.
 *
 * @package WP_Read_Tools
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Read_Tools_Ajax
 *
 * Manages AJAX endpoints for the plugin.
 */
class WP_Read_Tools_Ajax {

	/**
	 * Initialize hooks for AJAX actions.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Hook for logged-in users.
		add_action( 'wp_ajax_wp_read_tools_get_content', array( __CLASS__, 'handle_get_content_request' ) );
		// Hook for non-logged-in users.
		add_action( 'wp_ajax_nopriv_wp_read_tools_get_content', array( __CLASS__, 'handle_get_content_request' ) );
	}

	/**
	 * Handles the AJAX request to fetch cleaned post content for text-to-speech.
	 *
	 * Verifies the nonce, retrieves and sanitizes the post ID, fetches the post content,
	 * cleans it by removing shortcodes and HTML tags, and returns it as a JSON response.
	 *
	 * @since 1.0.0
	 */
	public static function handle_get_content_request() {
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
		$stripped_content = strip_shortcodes( $content );
		$stripped_content = wp_strip_all_tags( $stripped_content );
		// Optionally, decode HTML entities that might remain after stripping tags.
		$stripped_content = html_entity_decode( $stripped_content );
		// Optionally, normalize whitespace.
		$stripped_content = preg_replace( '/\s+/', ' ', $stripped_content );
		$stripped_content = trim( $stripped_content );

		// Send successful response with cleaned content.
		wp_send_json_success( array( 'content' => $stripped_content ) );

		// wp_die() is called automatically by wp_send_json_success / wp_send_json_error.
	}
}