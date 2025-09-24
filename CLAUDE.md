# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WP Read Tools is a WordPress plugin that adds reading time estimation and text-to-speech functionality to WordPress posts via shortcodes. The plugin uses browser-based speech synthesis (no external APIs) and supports multiple languages.

## Architecture

The plugin follows WordPress coding standards with a modular class-based architecture:

### Core Classes (in `/includes/`)
- **WP_Read_Tools_Shortcode**: Handles the `[readtime]` shortcode rendering and reading time calculation
- **WP_Read_Tools_Enqueue**: Manages frontend asset loading (CSS, JS, Font Awesome CDN)
- **WP_Read_Tools_Ajax**: Handles AJAX endpoint for fetching cleaned post content for text-to-speech

### Main Plugin File
- **wp-read-tools.php**: Entry point that defines constants, loads text domain, and initializes all classes

### Assets Structure
- **assets/js/read-aloud.js**: Frontend JavaScript handling speech synthesis, pause/resume, and AJAX calls
- **assets/css/read-tools.css**: Plugin stylesheet (loaded via enqueue class)

## Key Features Implementation

### Reading Time Calculation
- Uses `str_word_count()` on stripped content (no HTML/shortcodes)
- Default 180 WPM, customizable via shortcode attribute
- Rounds to nearest 0.5 minutes for display
- Supports localized number formatting (special handling for Spanish locales)

### Text-to-Speech
- Browser-based Speech Synthesis API
- AJAX fetches cleaned post content via `wp_read_tools_get_content` action
- Automatic voice selection (prefers female voices, falls back to language-appropriate voices)
- Global state management for pause/resume across multiple instances
- Proper cleanup on navigation/cancellation

## Development Commands

This is a standard WordPress plugin with no build process. No npm, composer, or build commands are required.

### Testing/Linting
No automated testing or linting setup is configured. Manual testing should be done in WordPress environment.

### Plugin Activation
Standard WordPress plugin installation:
1. Upload to `/wp-content/plugins/wp-read-tools/`
2. Activate via WordPress admin

## Shortcode Usage

```php
[readtime] // Basic reading time only
[readtime read-aloud="yes"] // With text-to-speech
[readtime read-aloud="yes" wpm="200" class="custom-class" link_text="Listen Now"]
```

## Security Considerations

- AJAX requests use WordPress nonces for security
- Post IDs are validated and sanitized
- Only published posts are accessible via AJAX
- Content is stripped of HTML/shortcodes before speech synthesis

## Internationalization

- Translation-ready with .pot template
- Spanish translation included (es_ES)
- Text domain: 'wp-read-tools'
- Localized JavaScript strings via wp_localize_script()