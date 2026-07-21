=== WP Read Tools ===
Contributors: ahvega
Donate link: https://github.com/sponsors/ahvega
Tags: reading time, text-to-speech, accessibility, content, posts, shortcode, audio, speech synthesis, reading, wpm
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.1.1
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enhance your WordPress posts with reading time estimation and browser-based text-to-speech functionality. Perfect for accessibility and user experience.

== Description ==

**WP Read Tools** is a modern WordPress plugin that enhances user experience and accessibility by adding reading time estimation and text-to-speech capabilities to your posts. Built with performance, security, and accessibility in mind.

= Why Choose WP Read Tools? =

**🚀 Improves User Experience**
* Shows visitors exactly how long content takes to read
* Provides audio playback for accessibility and multitasking
* Helps users decide whether to read or bookmark content

**♿ Enhances Accessibility**
* WCAG-compliant with proper ARIA attributes
* Supports users with visual impairments or reading difficulties
* Works with screen readers and assistive technologies

**🔧 Developer Friendly**
* Clean, well-documented code following WordPress standards
* Comprehensive hooks and filters for customization
* No external API dependencies - fully self-contained

= Core Features =

* **📊 Smart Reading Time Calculation**: Accurate estimates based on word count and customizable reading speeds (default 180 WPM)
* **🔊 Browser-Based Text-to-Speech**: Uses Web Speech API - no external services or API keys required
* **🎯 Simple Shortcode Integration**: Easy implementation with `[readtime]` shortcode
* **🌐 Multilingual Support**: Automatic language detection for speech synthesis with 40+ supported languages
* **🎨 Highly Customizable**: Flexible styling options, custom icons, and configurable parameters
* **⚡ Performance Optimized**: Lightweight footprint with conditional asset loading
* **🔒 Security Hardened**: CSRF protection, input validation, and secure coding practices

= Perfect For =

* **Bloggers** who want to provide reading time estimates
* **Content creators** looking to improve accessibility
* **Publishers** wanting to enhance user engagement
* **Educational sites** that need audio content support
* **News websites** providing reading time context
* **Accessibility-focused sites** requiring inclusive design

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

= How accurate is the reading time calculation? =

The reading time is calculated using industry-standard methods: word count divided by reading speed (default 180 WPM). The algorithm:
* Strips HTML tags and shortcodes for accurate word counting
* Rounds to the nearest half-minute for user-friendly display
* Can be customized per post type or content category using filters

= Does the text-to-speech feature require internet connection? =

No internet connection is required for the text-to-speech feature. It uses your browser's built-in Web Speech API, which works completely offline once the page is loaded.

= Which browsers and devices support text-to-speech? =

**Desktop Browsers:**
* Chrome 33+ (excellent support)
* Firefox 49+ (good support)
* Safari 7+ (good support)
* Edge 14+ (good support)

**Mobile Devices:**
* iOS Safari 7+ (full support)
* Chrome Mobile 33+ (full support)
* Android Browser (varies by version)

= Can I customize the reading speed? =

Yes! You can customize reading speed in several ways:
1. Use the `wpm` parameter: `[readtime wpm="200"]`
2. Use filters to set different speeds for different content types
3. Default is 180 WPM, but you can set anywhere from 100-400 WPM

= How do I style the reading time display? =

You can customize the appearance using:
1. **Custom CSS classes**: `[readtime class="my-custom-class"]`
2. **Theme stylesheet**: Target `.readtime`, `.read-time-line`, `.read-aloud-line` classes
3. **Custom CSS**: Add styles to your theme's Additional CSS section

Example:
```css
.readtime {
    background: #f0f0f0;
    padding: 10px;
    border-radius: 5px;
}
```

= Does it work with custom post types? =

Yes! WP Read Tools works with any post type including:
* Posts and pages
* Custom post types (products, events, etc.)
* Any content type that supports `the_content()` filter

= Does it work with page builders like Avada and Elementor? =

**Yes, but with important considerations:**

**Content Requirement**: For optimal functionality with theme builders like Avada, Elementor, or similar page builders, ensure your post content is included in WordPress's native post content field (the main editor), not exclusively in page builder modules.

**How it works:**
* **Primary Source**: Native WordPress content field (recommended)
* **Secondary Source**: Page builder meta fields (Avada, Elementor)
* **Fallback**: Frontend content extraction when needed

**Best Practices:**
* Include at least a summary in the native WordPress editor
* The plugin automatically detects and extracts content from page builder meta fields
* For Avada: Content is extracted from `_avada_page_content` and other builder fields
* For Elementor: Content is parsed from JSON data in `_elementor_data`

**Troubleshooting**: If reading time seems inaccurate:
1. Add content to the native WordPress post editor
2. Use the `content_id` parameter: `[readtime content_id="main-content"]`
3. Check that your page builder content includes readable text

= Can I add reading time automatically to all posts? =

Yes, you can automatically add reading time to posts using theme integration:

```php
// Add to functions.php
function auto_add_reading_time($content) {
    if (is_single() && !is_admin()) {
        $reading_time = do_shortcode('[readtime read-aloud="yes"]');
        return $reading_time . $content;
    }
    return $content;
}
add_filter('the_content', 'auto_add_reading_time');
```

= Is the plugin translation ready? =

Yes! The plugin is fully translation-ready with:
* Complete English text domain
* Spanish (es_ES) translation included
* POT template file for easy translation
* Support for RTL (right-to-left) languages

= What about performance impact? =

WP Read Tools is highly optimized with minimal performance impact:
* < 100ms additional page load time
* < 1MB additional memory usage
* Only 2 additional HTTP requests (CSS + JS)
* Compatible with all major caching plugins

= How secure is the plugin? =

Security is a top priority:
* All AJAX requests use WordPress nonces (CSRF protection)
* Input validation and sanitization on all user inputs
* No external API dependencies (reduced attack surface)
* Follows WordPress security best practices
* Regular security audits and updates

== Changelog ==

= 1.1.1 - 2026-07-21 =
**🔒 Security Release — update recommended for all sites**

The `wp_read_tools_get_content` AJAX endpoint is registered for logged-out users,
so it is reachable by anonymous remote callers. This release closes an
unauthenticated content disclosure and hardens the surrounding checks.

* **Fixed (security)**: Password-protected posts could be read without the
  password. The endpoint gated only on `post_status === 'publish'`, but
  password-protected posts keep that status — protection lives in
  `post_password`. Access control now uses a post-type allowlist,
  `is_post_publicly_viewable()` and `post_password_required()`.
* **Fixed (security)**: Non-public post types stored with the `publish` status
  (reusable blocks, field groups, private custom types) were readable by
  enumerating numeric post IDs.
* **Fixed (security)**: The cached-content lookup ran before the access check,
  so cached bodies were served without any authorization test.
* **Fixed (security)**: Rate limiting ran before nonce verification, letting
  requests with no valid nonce allocate transients (database rows).
* **Fixed (security)**: `X-Forwarded-For` / `CF-Connecting-IP` were trusted
  unconditionally. Rotating the header bypassed the rate limit entirely, and
  setting it to a third party's address locked that person out of the feature.
  Proxy headers are now honoured only behind a proxy listed in the new
  `wp_read_tools_trusted_proxies` filter, and `X-Forwarded-For` is parsed
  right-to-left so a client-supplied prefix cannot win.
* **Fixed**: Rate limiting silently disabled itself on containerised and
  load-balanced installs, where the peer address is private and was being
  rejected as invalid.
* **Fixed**: The rate limiter refreshed its window on every request, so a
  steady low-rate caller could stay blocked indefinitely once over the
  threshold. It is now a fixed window.
* **Added**: A per-request content-length cap, filterable via
  `wp_read_tools_max_content_length` (default 500 KB), truncated on character
  boundaries.

= 1.1.0 =
**🐛 Page Builder & Voice Fixes**

* **Fixed**: Reading time showing 0.0 on Avada/Fusion Builder posts. Shortcode
  tags are now stripped while preserving their inner content, instead of
  `strip_shortcodes()` which removed the text inside registered shortcodes.
* **Fixed**: Text-to-speech reading theme configuration data instead of article
  text — replaced database meta-field extraction with standard `post_content`
  retrieval.
* **Fixed**: Speech synthesis errors caused by oversized or malformed content
  from meta-field concatenation.
* **Improved**: Voice selection prioritizes es-US Neural/Natural voices with a
  Latin American Spanish fallback chain.
* **Improved**: Speech rate and pitch set to natural defaults (1.0).

= 1.0.0 - 2024-XX-XX =
**🎉 Initial Release**

* **Core Features**
    * Reading time estimation with customizable WPM (words per minute)
    * Browser-based text-to-speech using Web Speech API
    * Flexible `[readtime]` shortcode with multiple parameters
    * Smart voice selection with language detection
    * Pause/resume controls with visual feedback

* **Accessibility & UX**
    * WCAG-compliant implementation with ARIA attributes
    * Responsive design for all device types
    * Comprehensive keyboard navigation support
    * Visual state management for speech controls

* **Developer Features**
    * Clean, well-documented code following WordPress standards
    * Comprehensive PHPDoc and JSDoc documentation
    * Security-hardened with CSRF protection and input validation
    * Performance-optimized with conditional asset loading
    * Extensive hooks and filters for customization

* **Internationalization**
    * Translation-ready with complete text domain
    * Spanish (es_ES) translation included
    * Locale-aware number formatting
    * RTL language support

* **Technical Specifications**
    * WordPress 5.0+ compatibility
    * PHP 7.2+ support with PHP 8.0+ optimization
    * Modern browser support (Chrome 33+, Firefox 49+, Safari 7+, Edge 14+)
    * Mobile device compatibility (iOS Safari 7+, Chrome Mobile 33+)

== Upgrade Notice ==

= 1.1.1 =
🔒 **SECURITY**: Fixes an unauthenticated content disclosure — password-protected posts could be read without the password via the public AJAX endpoint. Also hardens rate limiting against spoofed proxy headers, which previously allowed both bypass and locking other visitors out. Update recommended for all sites. Note: sites reading aloud a custom post type behind a `publicly_queryable => false` registration will need the new `wp_read_tools_allowed_post_types` filter.

= 1.0.0 =
🚀 **NEW**: WP Read Tools brings reading time estimation and text-to-speech functionality to your WordPress site! Perfect for improving accessibility and user experience. Features browser-based speech synthesis, customizable reading speeds, and comprehensive developer tools. No external APIs required - everything works offline!

== Screenshots ==

1. Reading time display with text-to-speech button
2. Text-to-speech in action with pause/resume controls

== Credits ==

* Font Awesome for icons
* WordPress Plugin Boilerplate for initial structure
