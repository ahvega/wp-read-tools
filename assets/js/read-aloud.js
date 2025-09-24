/**
 * WP Read Tools - Frontend Text-to-Speech Functionality
 *
 * This script handles the text-to-speech functionality for the WP Read Tools plugin.
 * It manages speech synthesis, pause/resume controls, voice selection, and AJAX
 * communication with the WordPress backend.
 *
 * @package    WP_Read_Tools
 * @subpackage assets/js
 * @since      1.0.0
 * @author     Adalberto H. Vega <contacto@inteldevign.com>
 *
 * @requires   jQuery
 * @requires   readAloudSettings (localized from PHP)
 */

/**
 * Global speech state management object.
 * Tracks the current state of speech synthesis across all instances.
 *
 * @namespace
 * @global
 * @since 1.0.0
 *
 * @property {boolean}                isPaused         Whether speech is currently paused
 * @property {SpeechSynthesisUtterance|null} currentUtterance Current speech utterance object
 * @property {number}                 resumePoint      Position to resume from (future use)
 */

jQuery(document).ready(function($) {
    /**
     * Initialize global speech state tracking.
     * This object maintains the state of speech synthesis across the application.
     */
    window.speechState = {
        isPaused: false,
        currentUtterance: null,
        resumePoint: 0
    };

    /**
     * Main click event handler for read-aloud trigger links.
     *
     * Handles all text-to-speech functionality including:
     * - Pause/resume controls for active speech
     * - Stopping current speech when switching between posts
     * - AJAX requests to fetch post content
     * - Speech synthesis with voice selection
     * - UI state management and visual feedback
     *
     * @since 1.0.0
     *
     * @param {Event} e - The click event object
     */
    $('.read-aloud-trigger').on('click', function(e) {
        e.preventDefault();
        const link = $(this);
        const postId = link.data('post-id');
        const icon = link.find('.fas');
        const originalLinkText = link.contents().filter(function() { return this.nodeType === 3; }).text().trim(); // More robust way to get text node
        let utterance = null; // Store the utterance object
        let currentLink = link; // Keep track of the link being processed

        // Handle pause/resume if already speaking
        if (speechSynthesis.speaking) {
            if (!window.speechState.isPaused) {
                // Pause the narration
                speechSynthesis.pause();
                window.speechState.isPaused = true;
                icon.removeClass('fa-pause').addClass('fa-play');
                updateLinkText(link, readAloudSettings.resumeText);
                return;
            } else {
                // Resume the narration
                speechSynthesis.resume();
                window.speechState.isPaused = false;
                icon.removeClass('fa-play').addClass('fa-pause');
                updateLinkText(link, readAloudSettings.pauseText);
                return;
            }
        }

        // Stop any currently playing speech before starting new or stopping
        if (speechSynthesis.speaking || speechSynthesis.pending) {
            const wasSpeakingThisLink = window.activeReadAloudLink && currentLink.is(window.activeReadAloudLink); // Check if the click is on the currently active link
            speechSynthesis.cancel(); // Stop speech synthesis

            // Reset the icon and text of the link that was playing/loading
            if (window.activeReadAloudLink && window.activeReadAloudIcon && window.originalReadAloudText) {
                 window.activeReadAloudIcon.removeClass('fa-pause fa-play fa-spinner fa-spin').addClass(' fa-headphones');
                 updateLinkText(window.activeReadAloudLink, window.originalReadAloudText);
                 window.activeReadAloudLink.removeClass('read-aloud-loading');
            }

             // Clear global state
             window.activeReadAloudLink = null;
             window.activeReadAloudIcon = null;
             window.originalReadAloudText = null;
             utterance = null;


            // If the click was on the link that was already playing, just stop and return
            if (wasSpeakingThisLink) {
                return;
            }
        }

        // Reset state if starting new narration
        window.speechState = {
            isPaused: false,
            currentUtterance: null,
            resumePoint: 0
        };

        if ('speechSynthesis' in window) {
            // Store current link state globally to reset if another link is clicked
            window.activeReadAloudLink = link;
            window.activeReadAloudIcon = icon;
            window.originalReadAloudText = originalLinkText;


            link.addClass('read-aloud-loading');
            icon.removeClass('fa-headphones').addClass('fa-spinner fa-spin');
            updateLinkText(link, readAloudSettings.readingText);

            $.ajax({
                url: readAloudSettings.ajax_url,
                type: 'POST',
                data: {
                    action: readAloudSettings.ajaxAction, // Use localized action name
                    post_id: postId,
                    nonce: readAloudSettings.nonce
                },
                success: function(response) {
                    // Check if the current link is still the one being processed
                     if (!window.activeReadAloudLink || !link.is(window.activeReadAloudLink)) {
                         return; // Another link was clicked, abort this one
                     }

                    if (response.success) {
                        const content = response.data.content;
                        if (!content) {
                             alert(readAloudSettings.errorText || 'Error: Empty content received.');
                             resetLinkState(link, icon, originalLinkText);
                             return;
                        }

                        utterance = new SpeechSynthesisUtterance(content);
                        window.speechState.currentUtterance = utterance;

                        // Add pause/resume handling
                        utterance.onstart = function() {
                            icon.removeClass('fa-spinner fa-spin fa-play').addClass('fa-pause');
                            updateLinkText(link, readAloudSettings.pauseText);
                            link.removeClass('read-aloud-loading');
                        };

                        utterance.onend = function() {
                            if (link.is(window.activeReadAloudLink)) {
                                resetLinkState(link, icon, originalLinkText);
                                window.speechState = {
                                    isPaused: false,
                                    currentUtterance: null,
                                    resumePoint: 0
                                };
                            }
                        };

                        // --- Voice Selection Logic ---
                        const pageLang = document.documentElement.lang || navigator.language || 'en-US';
                        const langCode = pageLang.substring(0, 2);
                        let selectedVoice = null;

                        // Wait for voices to be loaded (important for some browsers)
                        const voices = window.speechSynthesis.getVoices();
                        if (voices.length === 0) {
                            window.speechSynthesis.onvoiceschanged = function() {
                                findAndSetVoice(utterance, langCode);
                                // Check again if the link is still active before speaking
                                if (window.activeReadAloudLink && link.is(window.activeReadAloudLink)) {
                                    speakUtterance(utterance, link, icon, originalLinkText);
                                }
                            };
                        } else {
                            findAndSetVoice(utterance, langCode);
                             // Check if the link is still active before speaking
                             if (window.activeReadAloudLink && link.is(window.activeReadAloudLink)) {
                                 speakUtterance(utterance, link, icon, originalLinkText);
                             }
                        }
                        // --- End Voice Selection Logic ---

                    } else {
                        alert(response.data.message || readAloudSettings.errorText); // Use localized error text
                        resetLinkState(link, icon, originalLinkText);
                    }
                },
                error: function(xhr, status, error) {
                     // Check if the current link is still the one being processed
                     if (!window.activeReadAloudLink || !link.is(window.activeReadAloudLink)) {
                         return; // Another link was clicked, abort this one
                     }
                    console.error("AJAX error:", status, error, xhr);
                    alert('Error communicating with the server.'); // Keep generic or localize if needed
                    resetLinkState(link, icon, originalLinkText);
                }
            });
        } else {
            alert('Your browser does not support text-to-speech.');
             resetLinkState(link, icon, originalLinkText); // Reset state if TTS not supported
        }
    });

    /**
     * Finds and sets the optimal voice for speech synthesis.
     *
     * Attempts to select the best available voice for the given language code
     * with preference for female voices, then any voice matching the language,
     * then default voice, and finally the first available voice.
     *
     * @since 1.0.0
     *
     * @param {SpeechSynthesisUtterance} utterance - The speech utterance object to configure
     * @param {string} langCode - Two-letter language code (e.g., 'en', 'es', 'fr')
     *
     * @return {void}
     */
    function findAndSetVoice(utterance, langCode) {
        const voices = window.speechSynthesis.getVoices();

        // Prefer female voices for better user experience
        let femaleVoice = voices.find(v =>
            v.lang.startsWith(langCode) &&
            (v.name.includes('Female') || v.gender === 'female')
        );

        // Fallback to any voice in the target language
        let anyVoice = voices.find(v => v.lang.startsWith(langCode));

        // Fallback chain: female voice -> language match -> default -> first available
        utterance.voice = femaleVoice || anyVoice || voices.find(v => v.default) || voices[0];
    }

    /**
     * Initiates speech synthesis and sets up event listeners.
     *
     * This function handles the actual speech synthesis process including:
     * - Starting the speech with the configured utterance
     * - Setting up UI state changes (loading to pause button)
     * - Configuring event handlers for speech end and error states
     * - Managing visual feedback during speech playback
     *
     * @since 1.0.0
     *
     * @param {SpeechSynthesisUtterance} utterance - The configured speech utterance
     * @param {jQuery} link - The jQuery object for the trigger link
     * @param {jQuery} icon - The jQuery object for the icon element
     * @param {string} originalLinkText - Original text of the link for reset purposes
     *
     * @return {void}
     */
    function speakUtterance(utterance, link, icon, originalLinkText) {
         icon.removeClass('fa-spinner fa-spin').addClass('fa-pause');
         updateLinkText(link, readAloudSettings.pauseText);
         link.removeClass('read-aloud-loading');
         speechSynthesis.speak(utterance);

         utterance.onend = function() {
             if (link.is(window.activeReadAloudLink)) { // Only reset if this is the active link
                 resetLinkState(link, icon, originalLinkText);
             }
         };

         utterance.onerror = function(event) {
             console.error('Speech synthesis error:', event.error);
              if (link.is(window.activeReadAloudLink)) { // Only reset if this is the active link
                 alert('An error occurred during speech synthesis.');
                 resetLinkState(link, icon, originalLinkText);
             }
         };

         // Note: onpause and onresume are less reliable across browsers for user actions
         // It's often better to handle pause/resume via the main click handler logic
     }


    /**
     * Updates the text content of a link while preserving icon elements.
     *
     * This function specifically targets text nodes within the link element,
     * leaving icon elements intact. It's used to change button text between
     * states like "Listen", "Pause", "Resume", etc.
     *
     * @since 1.0.0
     *
     * @param {jQuery} link - The jQuery object for the link element
     * @param {string} newText - New text content to display
     *
     * @return {void}
     */
    function updateLinkText(link, newText) {
        link.contents().filter(function() {
            return this.nodeType === 3; // Node.TEXT_NODE
        }).replaceWith(' ' + newText); // Add space for separation from icon
    }

    /**
     * Resets the visual and functional state of a read-aloud link.
     *
     * This function performs complete cleanup of a read-aloud link including:
     * - Restoring original icon (headphones)
     * - Resetting link text to original state
     * - Clearing CSS classes for loading states
     * - Cleaning up global state variables
     * - Resetting speech synthesis state
     *
     * @since 1.0.0
     *
     * @param {jQuery} link - The jQuery object for the link element
     * @param {jQuery} icon - The jQuery object for the icon element
     * @param {string} originalText - Original link text to restore
     *
     * @return {void}
     */
    function resetLinkState(link, icon, originalText) {
         // Remove all possible states and add headphones icon with proper spacing
         icon.removeClass('fa-pause fa-play fa-spinner fa-spin')
             .removeClass('fas') // Remove the base class
             .addClass('fas fa-headphones'); // Re-add with proper spacing
         updateLinkText(link, originalText);
         link.removeClass('read-aloud-loading');
         // Clear global state tracking
         window.activeReadAloudLink = null;
         window.activeReadAloudIcon = null;
         window.originalReadAloudText = null;
         window.speechState = {
             isPaused: false,
             currentUtterance: null,
             resumePoint: 0
         };
         utterance = null;
     }


    /**
     * Cleanup handler for page navigation.
     *
     * Ensures that any active speech synthesis is properly cancelled when
     * the user navigates away from the page. This prevents speech from
     * continuing to play after the page has changed.
     *
     * @since 1.0.0
     *
     * @listens window:beforeunload
     */
    $(window).on('beforeunload', function() {
        if (speechSynthesis.speaking || speechSynthesis.pending) {
            speechSynthesis.cancel();
        }
    });
});