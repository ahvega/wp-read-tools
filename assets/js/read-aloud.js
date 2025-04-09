jQuery(document).ready(function($) {
    // Add global state tracking
    window.speechState = {
        isPaused: false,
        currentUtterance: null,
        resumePoint: 0
    };

    $('.read-aloud-trigger').on('click', function(e) {
        e.preventDefault();
        const link = $(this);
        const postId = link.data('post-id');
        const icon = link.find('.fas');
        const originalLinkText = link.contents().filter(function() { return this.nodeType === 3; }).text().trim(); // More robust way to get text node
        let isPlaying = false; // Track the playing state
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
             isPlaying = false;


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

     // Function to find and set the voice
     function findAndSetVoice(utterance, langCode) {
         const voices = window.speechSynthesis.getVoices();
         let femaleVoice = voices.find(v => v.lang.startsWith(langCode) && (v.name.includes('Female') || v.gender === 'female'));
         let anyVoice = voices.find(v => v.lang.startsWith(langCode));

         utterance.voice = femaleVoice || anyVoice || voices.find(v => v.default) || voices[0]; // Fallback chain
     }


     // Function to handle speaking and event listeners
     function speakUtterance(utterance, link, icon, originalLinkText) {
         icon.removeClass('fa-spinner fa-spin').addClass('fa-pause');
         updateLinkText(link, readAloudSettings.pauseText);
         link.removeClass('read-aloud-loading');
         speechSynthesis.speak(utterance);
         isPlaying = true;

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


    // Helper function to update link text (targets only the text node)
    function updateLinkText(link, newText) {
        link.contents().filter(function() {
            return this.nodeType === 3; // Node.TEXT_NODE
        }).replaceWith(' ' + newText); // Add space for separation from icon
    }

     // Helper function to reset link state
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
         isPlaying = false;
     }


     // Ensure speech is stopped if the user navigates away
     $(window).on('beforeunload', function() {
         if (speechSynthesis.speaking || speechSynthesis.pending) {
             speechSynthesis.cancel();
         }
     });
});