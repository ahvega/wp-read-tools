# WP Read Tools - Troubleshooting Guide

## 🚨 Common Issues & Solutions

### Issue: "Error al obtener el contenido" (Error fetching content)

**Symptoms:**
- Button switches to "Pausar" but no sound plays
- Error message appears in Spanish: "Error al obtener el contenido"
- Browser console shows AJAX errors

**Most Common Causes & Solutions:**

#### 1. 🎨 Theme Conflicts (Avada, Elementor, etc.)

**Problem:** Page builders and complex themes can interfere with AJAX requests.

**Solutions:**
```php
// Add to functions.php to force asset loading
add_filter('wp_read_tools_force_load_assets', '__return_true');

// Disable rate limiting if too restrictive
add_filter('wp_read_tools_enable_rate_limiting', '__return_false');

// Increase rate limits
add_filter('wp_read_tools_rate_limit_max_requests', function() {
    return 100; // Allow more requests
});
```

#### 2. 🔒 Security Plugin Interference

**Problem:** Security plugins block AJAX requests.

**Solutions:**
- Whitelist `/wp-admin/admin-ajax.php` in security plugins
- Check firewall logs for blocked requests
- Temporarily disable security plugins to test

#### 3. 🌐 CDN/Caching Issues

**Problem:** CDN or caching plugins interfere with AJAX.

**Solutions:**
- Exclude `/wp-admin/admin-ajax.php` from caching
- Clear all caches (plugin, CDN, browser)
- Test with caching disabled

#### 4. 📄 Empty Content Detection (Page Builder Content)

**Problem:** Post content is empty or not accessible, especially with Avada/Elementor.

**⚠️ IMPORTANT NOTE FOR PAGE BUILDER USERS:**
The plugin works best when content is included in WordPress's native post content field (the main editor). Page builders that store content only in custom modules/widgets may not be fully detected.

**Recommended Solution: Native Content Field**
```
1. Edit your post/page in WordPress admin
2. In the main WordPress editor (not the page builder):
   - Add at least a summary or excerpt of your content
   - Include key paragraphs from your page builder content
   - This ensures accurate reading time and text-to-speech functionality
3. The plugin will use this as primary content source
4. Page builder content will be used as secondary/fallback
```

**Alternative Solutions:**

**Option A: Use Custom Content Selector**
```php
// Add this shortcode with content_id parameter
[readtime read-aloud="yes" content_id="#main"]
```

**Option B: Enable Debug Mode and Check Logs**
```php
// Check if content exists
$post = get_post(123); // Replace with actual post ID
echo "Content: " . strlen($post->post_content) . " characters";

// Enable debug mode to see content extraction details
define('WP_READ_TOOLS_DEBUG', true);
```

**Option C: Manual Content Detection Test**
```javascript
// Test in browser console to find your content selector
document.querySelector('.fusion-builder-column').innerText;
document.querySelector('.entry-content').innerText;
```

## 🛠️ Debug Mode Setup

### Step 1: Enable Debug Logging
```php
// Add to wp-config.php
define('WP_READ_TOOLS_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Step 2: Check Debug Log
```bash
# View recent logs
tail -f /wp-content/debug.log | grep "WP Read Tools"
```

### Step 3: Browser Console
1. Open Developer Tools (F12)
2. Go to Console tab
3. Click the text-to-speech button
4. Look for "WP Read Tools AJAX error" messages

## 🔍 Specific Error Diagnosis

### Error: "Security check failed"
**Cause:** Nonce verification failed
**Solution:**
```php
// Clear all caches
// Check if user is logged in properly
// Verify AJAX URL is correct
```

### Error: "Too many requests"
**Cause:** Rate limiting triggered
**Solution:**
```php
// Increase rate limits or disable
add_filter('wp_read_tools_enable_rate_limiting', '__return_false');
```

### Error: "Post content is empty"
**Cause:** No content found for the post
**Solutions:**
- Verify post has content
- Check if content is in custom fields
- Ensure post status is "published"

### Error: "Post not found or not accessible"
**Cause:** Post ID invalid or not published
**Solutions:**
- Verify correct post ID
- Check post status
- Verify user permissions

## 🔧 Content Selector Solutions

### Custom Content ID Parameter

For sites using page builders like Avada or Elementor where content isn't detected automatically:

```php
// Basic usage with custom content selector
[readtime read-aloud="yes" content_id="#main"]

// Common content selectors for different themes:

// Avada Theme - Deep nested content
[readtime read-aloud="yes" content_id="#contenido .fusion-text-5"]
[readtime read-aloud="yes" content_id="#contenido .fusion-text"]
[readtime read-aloud="yes" content_id=".fusion-text-5"]
[readtime read-aloud="yes" content_id=".fusion-builder-column"]

// Elementor
[readtime read-aloud="yes" content_id=".elementor-widget-text-editor"]

// Generic WordPress
[readtime read-aloud="yes" content_id=".entry-content"]
[readtime read-aloud="yes" content_id="#content"]
```

### Finding Your Content Selector

1. **Open browser Developer Tools (F12)**
2. **Go to Elements/Inspector tab**
3. **Find the main content area of your page**
4. **Right-click and copy selector**
5. **Use that selector in the shortcode**

## 🎨 Theme-Specific Solutions

### Avada Theme
```php
// Add to functions.php
add_action('wp_enqueue_scripts', function() {
    // Force load on all pages with Avada
    if (function_exists('avada_get_theme_option')) {
        add_filter('wp_read_tools_force_load_assets', '__return_true');
    }
}, 5);
```

### Elementor
```php
// For Elementor-built pages
add_filter('wp_read_tools_force_load_assets', function($force) {
    if (defined('ELEMENTOR_VERSION') && \Elementor\Plugin::$instance->db->is_built_with_elementor(get_the_ID())) {
        return true;
    }
    return $force;
});
```

### Divi Theme
```php
// For Divi Builder pages
add_filter('wp_read_tools_force_load_assets', function($force) {
    if (function_exists('et_pb_is_pagebuilder_used') && et_pb_is_pagebuilder_used(get_the_ID())) {
        return true;
    }
    return $force;
});
```

## 🚀 Performance vs Compatibility

### High Compatibility Mode
```php
// functions.php - Always load assets (max compatibility)
add_filter('wp_read_tools_force_load_assets', '__return_true');
add_filter('wp_read_tools_enable_rate_limiting', '__return_false');
```

### Balanced Mode (Recommended)
```php
// functions.php - Load on content pages only
add_filter('wp_read_tools_force_load_assets', function($force) {
    return is_singular(); // Load on all posts/pages
});
```

### Performance Mode
```php
// functions.php - Minimal loading (max performance)
// Use default settings, only loads when shortcode detected
```

## 📞 Getting Help

### Information to Provide:

1. **WordPress Environment:**
   - WordPress version
   - PHP version
   - Active theme and version
   - Active plugins list

2. **Error Details:**
   - Exact error message
   - Browser used
   - Console error logs
   - Debug log entries

3. **Plugin Settings:**
   - Shortcode used
   - Page/post where issue occurs
   - Theme customizations

### Debug Information Command:
```php
// Add temporarily to functions.php for debugging
add_action('wp_footer', function() {
    if (current_user_can('manage_options')) {
        echo '<div style="display:none;" id="wp-read-tools-debug">';
        echo 'Theme: ' . get_template();
        echo ' | WP: ' . get_bloginfo('version');
        echo ' | PHP: ' . PHP_VERSION;
        echo ' | Post ID: ' . get_the_ID();
        echo '</div>';
    }
});
```

## ✅ Quick Fix Checklist

- [ ] Clear all caches (plugin, CDN, browser)
- [ ] Disable security plugins temporarily
- [ ] Enable debug mode
- [ ] Check browser console for errors
- [ ] Test with default theme
- [ ] Verify post has content
- [ ] Check server error logs
- [ ] Update plugin to latest version

---

**Need More Help?**
- Check the debug logs first
- Test with a default theme
- Report issues with full debug information