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
- Uses `str_word_count()` on stripped content
- Shortcode tags stripped via regex (`preg_replace`) to preserve inner content (critical for page builders like Avada/Fusion Builder)
- Default 180 WPM, customizable via shortcode attribute
- Rounds **up** to the next 0.5 minute (`ceil($minutes * 2) / 2`) — not nearest
- Supports localized number formatting (special handling for Spanish locales)

### Text-to-Speech
- Browser-based Speech Synthesis API
- AJAX fetches post content via `get_post_field('post_content')` (standard WP field, not database meta extraction)
- Smart voice selection: Spanish prioritizes es-US Neural → LatAm Neural → any es-*; other languages prefer Neural/Natural voices
- Global state management for pause/resume across multiple instances
- Proper cleanup on navigation/cancellation

### Performance & Abuse Controls (in `class-wp-read-tools-ajax.php`)
- Processed content is cached with `wp_cache_set()` under group `wp_read_tools`,
  keyed on post ID + `post_modified` so edits invalidate automatically.
  Caveat: `wp_cache_*` is request-scoped unless a persistent object-cache
  drop-in is installed — do not assume cross-request caching.
- Per-IP rate limiting via transients: 60 requests / 300s by default.
  Client IP comes from `HTTP_CF_CONNECTING_IP` → `HTTP_X_FORWARDED_FOR` →
  `HTTP_X_REAL_IP` → `REMOTE_ADDR`.
- `wp_read_tools_log()` writes debug output only when `WP_DEBUG` is enabled.

### Conditional Asset Loading
CSS/JS load only on pages where the shortcode is detected. Detection is
deliberately broad (post content, widgets, theme templates) because
theme-inserted shortcodes are otherwise missed — see
`BUGFIX_CONDITIONAL_LOADING.md`. Override with the
`wp_read_tools_force_load_assets` filter.

## Filter API

The plugin's entire extension surface. Prefer these over editing core files:

| Filter | Purpose |
|---|---|
| `wp_read_tools_wpm` | Override words-per-minute |
| `wp_read_tools_time_format` | Override the "%s min read" string |
| `wp_read_tools_content_before_count` | Mutate content before word counting |
| `wp_read_tools_speech_content` | Mutate content before speech synthesis |
| `wp_read_tools_cache_duration` | Cache TTL (default `HOUR_IN_SECONDS`) |
| `wp_read_tools_enable_rate_limiting` | Disable rate limiting entirely |
| `wp_read_tools_rate_limit_max_requests` | Request cap per window (default 60) |
| `wp_read_tools_rate_limit_time_window` | Window in seconds (default 300) |
| `wp_read_tools_force_load_assets` | Force asset enqueue regardless of detection |
| `wp_read_tools_load_fontawesome` | Skip the Font Awesome CDN enqueue |

## Known Dead Code — do not build on it

`class-wp-read-tools-ajax.php` contains an unreferenced page-builder
extraction subsystem (~200 lines): `get_post_content_for_speech()`,
`extract_page_builder_content()`, `extract_text_from_elementor_data()`,
`extract_all_content_from_database()`, `extract_text_from_array()`.

`handle_get_content_request()` calls `get_post_field()` directly and never
enters this path, so **Elementor/Avada meta extraction does not actually run**.
Verify call sites before assuming any of it is live; either wire it up
deliberately or delete it — do not extend it in place.

## Development Commands

No build step — no npm, composer, or bundler. The only real commands are
translation regeneration (WP-CLI), required after changing any translatable string:

```bash
wp i18n make-pot . languages/wp-read-tools.pot --domain=wp-read-tools
wp i18n make-mo languages/
```

### Testing/Linting
No automated testing or linting is configured, and there is no CI. Changes must
be verified manually in a WordPress install. Treat this as a reason for extra
care in review, not a reason to skip verification.

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

**The AJAX endpoint is registered with `wp_ajax_nopriv_`, so it is reachable by
unauthenticated remote users on every site running this plugin.** Treat it as a
public attack surface; changes there deserve blocking review findings, not style
comments.

**Check order in `handle_get_content_request()` is load-bearing — preserve it:**

1. nonce verification (before anything that writes state)
2. post ID validation
3. rate limiting (only nonce-verified requests may allocate a transient)
4. `is_post_readable()` access control
5. cache lookup (only once the request is known to be authorized)
6. content fetch + length cap

Each ordering was a real vulnerability before v1.1.1: rate limiting ahead of the
nonce let anonymous callers write `wp_options` rows, and the cache lookup ahead
of the access check served content without any authorization test.

Invariants — regressing any of these reopens a disclosed vulnerability:
- **`post_status === 'publish'` is NOT an access-control check.** Password-protected
  posts keep the `publish` status; protection lives in `post_password`. Always go
  through `is_post_readable()`, which combines a post-type allowlist,
  `is_post_publicly_viewable()` (with a WP 5.0 fallback mirroring
  `is_post_type_viewable()`), and `post_password_required()`.
- **The nonce is not an authorization boundary.** For logged-out users
  `wp_create_nonce()` is bound to user 0, so the value on any public page is
  valid for every anonymous requester. It is CSRF protection only.
- **Never trust proxy headers by default.** `X-Forwarded-For` is *appended* to, so
  its leftmost entry is attacker-controlled even behind a real proxy — parse
  right-to-left, discarding known proxies. Headers are consulted only when the
  peer is listed in `wp_read_tools_trusted_proxies`. Trusting them unconditionally
  allowed both limit bypass and a victim-lockout DoS.
- **Truncate on character boundaries.** The `/u` regexes in
  `process_content_for_speech()` return `null` on malformed UTF-8, so a byte-wise
  cut turns the size cap into an empty response.

Remaining accepted limitations:
- The transient read-modify-write is not atomic; concurrent requests sharing a key
  can slightly overshoot the cap. It is a throttle, not a quota.
- Sites behind an unconfigured proxy share one rate-limit bucket. Deliberate: the
  alternative is trusting spoofable headers.
- Post-auth error responses still carry static `debug` keys. Low risk.

Never commit real site data: no live DB dumps, no client hostnames, no customer
URLs in docs or fixtures. Use generic placeholders.

## Internationalization

- Translation-ready with .pot template
- Spanish translation included (es_ES)
- Text domain: 'wp-read-tools'
- Localized JavaScript strings via wp_localize_script()