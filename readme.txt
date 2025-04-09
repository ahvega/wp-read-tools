=== WP Read Tools ===
Contributors: ahvega
Tags: reading time, text-to-speech, accessibility, content, posts
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add reading time estimates and text-to-speech functionality to your WordPress posts using simple shortcodes.

== Description ==

WP Read Tools enhances your WordPress posts with two valuable features:

1. **Reading Time Estimation**: Shows visitors how long it will take to read your content
2. **Text-to-Speech**: Allows visitors to listen to your content using their browser's built-in speech synthesis

= Key Features =

* Accurate reading time estimation based on word count
* Browser-based text-to-speech functionality (no external services required)
* Language detection for text-to-speech voices
* Customizable reading speed (words per minute)
* Clean, minimal design that works with any theme
* Fully responsive and accessibility-ready
* No configuration required - works out of the box

= Usage =

Basic usage:
`[readtime]`

With text-to-speech enabled:
`[readtime read-aloud="yes"]`

Custom words per minute:
`[readtime wpm="200"]`

= Shortcode Parameters =

* `read-aloud` - Enable text-to-speech feature (yes/no, default: no)
* `class` - Custom CSS class for styling (default: readtime)
* `wpm` - Reading speed in words per minute (default: 180)
* `link_text` - Custom text for the listen button (default: "Listen")
* `icon_class` - Custom Font Awesome icon class (default: "fas fa-headphones" - note the space between classes)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wp-read-tools` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the shortcode `[readtime]` in your posts or pages

== Frequently Asked Questions ==

= How is the reading time calculated? =

Reading time is calculated by counting the words in your post and dividing by the average reading speed (default 180 words per minute). The result is rounded to the nearest half minute for better readability.

= Does the text-to-speech feature require an API key or external service? =

No, WP Read Tools uses the browser's built-in speech synthesis capabilities. No external services or API keys are required.

= Which browsers support the text-to-speech feature? =

Text-to-speech is supported in most modern browsers including:
* Chrome 33+
* Firefox 49+
* Safari 7+
* Edge 14+

= Can I customize the appearance? =

Yes, you can:
1. Use the `class` parameter in the shortcode to add custom CSS classes
2. Override the default CSS styles in your theme
3. Customize the icon using any Font Awesome 5 icon class

= Does it work with my language? =

Reading time estimation works with any language. Text-to-speech functionality depends on your browser's available voices and language support.

== Changelog ==

= 1.0.0 =
* Initial release
* Reading time estimation feature
* Text-to-speech functionality
* Multiple shortcode parameters for customization

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP Read Tools.

== Screenshots ==

1. Reading time display with text-to-speech button
2. Text-to-speech in action with pause/resume controls

== Credits ==

* Font Awesome for icons
* WordPress Plugin Boilerplate for initial structure
