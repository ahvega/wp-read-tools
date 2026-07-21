# WP Read Tools

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-21759B?style=flat-square&logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.2%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-blue?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.2.0-brightgreen?style=flat-square)](https://github.com/ahvega/wp-read-tools/releases)
[![Web Speech API](https://img.shields.io/badge/TTS-Web%20Speech%20API-FF6F00?style=flat-square&logo=google-chrome&logoColor=white)](#text-to-speech)
[![Avada Compatible](https://img.shields.io/badge/Avada-Compatible-E44D26?style=flat-square)](#page-builder-support)
[![Elementor Compatible](https://img.shields.io/badge/Elementor-Compatible-92003B?style=flat-square)](#page-builder-support)
[![Security](https://img.shields.io/badge/Security-Hardened-2EA44F?style=flat-square&logo=shield)](#security)

A WordPress plugin that adds **reading time estimation** and **text-to-speech** capabilities to posts. Uses the browser's native Web Speech API with intelligent Latin American Spanish voice selection. No external APIs required.

## Features

- **Reading Time Estimation** — Word count-based calculation at configurable WPM, locale-aware formatting
- **Text-to-Speech** — Native Web Speech API with pause/resume/stop controls
- **Smart Voice Selection** — Prioritizes es-US Neural voices, falls back through Latin American Spanish variants
- **Page Builder Support** — Reads content from Avada/Fusion Builder and Elementor shortcodes by preserving shortcode inner text
- **Conditional Asset Loading** — Scripts and styles are skipped on archives, home and search pages with no shortcode
- **Security** — Nonce verification, input sanitization, rate limiting, published-posts-only access
- **i18n Ready** — Translation-ready with Spanish (es_ES) included

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 5.0+ |
| PHP | 7.2+ (8.0+ recommended) |
| Browser | Chrome 33+, Firefox 49+, Safari 7+, Edge 14+ |

## Installation

```bash
git clone https://github.com/ahvega/wp-read-tools.git
```

Upload `wp-read-tools/` to `/wp-content/plugins/` and activate via WordPress admin.

## Usage

### Shortcode

```
[readtime]                                    # Reading time only
[readtime read-aloud="yes"]                   # With text-to-speech
[readtime read-aloud="yes" wpm="200"]         # Custom reading speed
```

### Parameters

| Parameter | Default | Description |
|---|---|---|
| `read-aloud` | `"no"` | Enable TTS (`"yes"` / `"no"`) |
| `wpm` | `180` | Words per minute for reading time |
| `class` | `"readtime"` | CSS class for container |
| `link_text` | `"Listen"` | TTS button text |
| `icon_class` | `"fas fa-headphones"` | Font Awesome icon class |
| `content_id` | `""` | **Deprecated no-op.** Accepted for backward compatibility but ignored; its only consumer never executed and was removed in 1.2.0 |

### Theme Integration

```php
// Auto-add to all single posts
add_filter('the_content', function($content) {
    if (is_single() && !is_admin()) {
        return do_shortcode('[readtime read-aloud="yes"]') . $content;
    }
    return $content;
});
```

## Voice Selection Strategy

For Spanish content, the TTS engine selects voices in this priority order:

1. **es-US Neural/Natural** — Bilingual, handles English terms in Spanish text
2. **Any es-US voice**
3. **Latin American Neural** — es-MX, es-CR, es-CO, es-GT, es-HN, es-PA, etc.
4. **Any Latin American voice**
5. **Any es-\* voice** — Including es-ES as last resort

For non-Spanish content, Neural/Natural voices matching the page language are preferred.

## Page Builder Support

The plugin extracts content from page builder shortcodes (Avada/Fusion Builder, Elementor) by stripping shortcode **tags** while preserving the text content within them. This ensures accurate word counts and proper TTS content regardless of the page builder used.

For best results, include content in WordPress's native post editor field.

## Available Filters

```php
// Adjust reading speed per post type
add_filter('wp_read_tools_wpm', function($wpm, $post_id) {
    return get_post_type($post_id) === 'product' ? 150 : $wpm;
}, 10, 2);

// Filter content before speech synthesis
add_filter('wp_read_tools_speech_content', function($content, $post_id) {
    return $content;
}, 10, 2);

// Disable Font Awesome (if theme already loads it)
add_filter('wp_read_tools_load_fontawesome', '__return_false');

// Force load assets on specific pages
add_filter('wp_read_tools_force_load_assets', '__return_true');

// Disable rate limiting
add_filter('wp_read_tools_enable_rate_limiting', '__return_false');
```

## Architecture

```
wp-read-tools/
├── wp-read-tools.php                         # Entry point, constants, init
├── includes/
│   ├── class-wp-read-tools-shortcode.php     # [readtime] shortcode & reading time calc
│   ├── class-wp-read-tools-ajax.php          # AJAX content retrieval for TTS
│   └── class-wp-read-tools-enqueue.php       # Conditional asset loading
├── assets/
│   ├── js/read-aloud.js                      # Speech synthesis & UI controls
│   └── css/read-tools.css                    # Plugin styles
└── languages/                                # i18n (.pot, .po, .mo)
```

## Debugging

```php
// Enable debug logging in wp-config.php
define('WP_READ_TOOLS_DEBUG', true);
```

```javascript
// Check available voices in browser console
speechSynthesis.getVoices().filter(v => v.lang.startsWith('es'));
```

## Changelog

### 1.2.0 — Correctness & performance

- **Fixed**: Font Awesome never loaded on a stock install — the plugin's icons rendered as nothing unless the theme shipped Font Awesome. The conflict check included `dashicons`, which core registers unconditionally, so it always matched. FA6 class names now recognised
- **Fixed**: Reading times inflated ~10–15% in Spanish, French, Portuguese and German — `str_word_count()` is byte-oriented and split every accented word ("canción" = 2 words). Digits were never counted
- **Fixed**: Posts opening with a one-letter word were corrupted — "A mi me gusta" → "Ami me gusta" (A, Y, O, E in Spanish; "I" in English). Drop-caps now matched by name, fixing every occurrence rather than only the first
- **Fixed**: Citation markers `[1]`, `[15]` were deleted from spoken text and word counts
- **Fixed**: `&#039;` apostrophes read aloud character by character; `&nbsp;` leaked into speech
- **Fixed**: latin1-imported content could silently yield empty speech and "0.0 min read"
- **Fixed**: Two read-aloud links on one page — pausing one and clicking the other resumed the first and left the second stuck on "Pause" permanently
- **Fixed**: Chrome could queue and speak an article two or three times (voice-loading handler fired repeatedly, never detached)
- **Fixed**: A database write on every uncached page view, from every anonymous visitor, during shortcode render
- **Fixed**: Links added after page load (infinite scroll, AJAX archives) had no click handler
- **Removed**: 412 lines of unreachable page-builder extraction code
- **Changed**: Very short posts read "0.5 min read" instead of "0.0 min read"

### 1.1.1 — Security release

The `wp_read_tools_get_content` AJAX endpoint is registered for logged-out users and is therefore reachable by anonymous remote callers. **Update recommended for all sites.**

- **Security**: Password-protected posts could be read without the password. The endpoint gated only on `post_status === 'publish'`, but password-protected posts keep that status — protection lives in `post_password`. Access control now combines a post-type allowlist, `is_post_publicly_viewable()` and `post_password_required()`
- **Security**: Non-public post types stored as `publish` (reusable blocks, field groups, private CPTs) were readable by enumerating post IDs
- **Security**: The cache lookup ran before the access check, serving cached bodies without authorization
- **Security**: Rate limiting ran before nonce verification, letting nonce-less requests allocate transients
- **Security**: `X-Forwarded-For` / `CF-Connecting-IP` were trusted unconditionally — rotating the header bypassed the rate limit, and setting it to a third party's address locked that person out. Now gated behind the new `wp_read_tools_trusted_proxies` filter and parsed right-to-left
- **Fixed**: Rate limiting silently disabled itself on containerised/load-balanced installs where the peer address is private
- **Fixed**: The limiter refreshed its window on every request, so a steady low-rate caller could stay blocked indefinitely. Now a fixed window
- **Added**: `wp_read_tools_max_content_length` cap (default 500 KB), truncated on character boundaries

**Upgrade note**: the post-type allowlist defaults to all publicly-registered types. Sites reading aloud a type registered `publicly_queryable => false` must opt in via `wp_read_tools_allowed_post_types`.

### 1.1.0
- **Fixed**: Reading time showing 0.0 for Avada/Fusion Builder posts — shortcode tags are now stripped while preserving inner content instead of using `strip_shortcodes()` which removed content within registered shortcodes
- **Fixed**: TTS reading theme configuration data instead of article text — replaced aggressive database meta-field extraction with standard `post_content` retrieval
- **Fixed**: Speech synthesis errors on previously working articles caused by oversized/malformed content from meta-field concatenation
- **Improved**: Voice selection now prioritizes es-US Neural/Natural voices with Latin American Spanish fallback chain
- **Improved**: Speech rate and pitch set to natural defaults (1.0)

### 1.0.0
- Initial release
- Reading time estimation via `[readtime]` shortcode
- Text-to-speech with Web Speech API
- Avada and Elementor content detection
- Conditional asset loading
- i18n support with Spanish translation

## License

GPL v2 or later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

## Author

**Adalberto H. Vega** — [ahvega](https://github.com/ahvega)
