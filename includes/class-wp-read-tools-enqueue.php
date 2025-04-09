<?php

/**
 * Handles the enqueuing of scripts and styles for the WP Read Tools plugin.
 *
 * This class is responsible for registering and enqueuing all frontend assets
 * including stylesheets, JavaScript files, and localized data needed for the
 * read-aloud functionality. It handles:
 * - Loading Font Awesome from CDN for icons
 * - Loading plugin-specific CSS
 * - Loading plugin JavaScript with localized data
 * - Setting up AJAX endpoints configuration
 *
 * @package WP_Read_Tools
 * @since   1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class WP_Read_Tools_Enqueue
 *
 * Manages the registration and enqueuing of frontend assets for the plugin.
 * This class follows the WordPress coding standards for script and style
 * enqueuing, ensuring proper dependency management and localization.
 *
 * @package WP_Read_Tools
 * @since   1.0.0
 */
class WP_Read_Tools_Enqueue
{
    /**
     * Initialize the enqueuing system.
     *
     * Hooks into WordPress's script enqueuing system by registering
     * the necessary action hook for frontend asset loading.
     *
     * @since  1.0.0
     * @access public
     * @static
     *
     * @return void
     */
    public static function init()
    {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
    }

    /**
     * Enqueues all necessary scripts and styles for the frontend.
     *
     * This method handles:
     * 1. Loading Font Awesome from CDN for icons
     * 2. Loading plugin's main stylesheet
     * 3. Loading plugin's JavaScript with jQuery dependency
     * 4. Localizing JavaScript with translated strings and AJAX configuration
     *
     * @since  1.0.0
     * @access public
     * @static
     *
     * @return void
     *
     * @uses wp_enqueue_style()    To enqueue stylesheets
     * @uses wp_enqueue_script()   To enqueue JavaScript files
     * @uses wp_localize_script()  To add translated strings to JavaScript
     * @uses wp_create_nonce()     To create security nonce for AJAX calls
     */
    public static function enqueue_scripts()
    {
        // Enqueue Font Awesome from CDN.
        // Consider making this optional or checking if already enqueued by theme/other plugins in a future version.
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
            array(),
            '5.15.4'
        );

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
}
