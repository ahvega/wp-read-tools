# WP Read Tools

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.2%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.0-green.svg)](https://github.com/ahvega/wp-read-tools/releases)

A WordPress plugin that enhances your posts with reading time estimation and text-to-speech capabilities.

## 🚀 Features

- 📊 **Reading Time Estimation**: Automatically calculate and display estimated reading time for posts
- 🔊 **Text-to-Speech**: Convert your posts to speech using browser's built-in speech synthesis
- 🎯 **Shortcode Support**: Easy integration with `[readtime]` shortcode
- 🌐 **Multilingual Support**: Works with any language for reading time, speech synthesis based on browser capabilities
- 🎨 **Customizable**: Flexible styling options and configurable parameters

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Modern browser supporting Speech Synthesis API (for text-to-speech feature)

## 🔧 Installation

1. Clone this repository or download the ZIP file:

```bash
git clone https://github.com/ahvega/wp-read-tools.git
```

2. Upload to your WordPress installation:
   - Upload the entire `wp-read-tools` directory to `/wp-content/plugins/`
   - Or upload via WordPress Admin → Plugins → Add New → Upload Plugin

3. Activate the plugin through WordPress Admin → Plugins

## 💻 Usage

### Basic Usage

Add reading time estimation to any post or page:

```php
[readtime]
```

### With Text-to-Speech

Enable both reading time and text-to-speech:

```php
[readtime read-aloud="yes"]
```

### Advanced Options

Customize the display with various parameters:

```php
[readtime read-aloud="yes" wpm="200" class="custom-class" link_text="Listen Now"]
```

### Available Parameters

| Parameter    | Description                           | Default     | Type    |
|-------------|---------------------------------------|-------------|---------|
| read-aloud  | Enable text-to-speech                 | "no"        | string  |
| class       | Custom CSS class                      | "readtime"  | string  |
| wpm         | Words per minute reading speed        | 180         | integer |
| link_text   | Custom text for listen button         | "Listen"    | string  |
| icon_class  | Custom Font Awesome icon class        | "fas fa-headphones" | string |

## 🎨 Styling

### Custom CSS

You can style the output using these CSS classes:

```css
.readtime {
    /* Container styles */
}

.read-time-line {
    /* Reading time display styles */
}

.read-aloud-line {
    /* Text-to-speech button styles */
}
```

## 🌐 Internationalization

The plugin is translation-ready and includes:

- Translation template file (.pot)
- Spanish translation (es_ES)
- Easy addition of new translations

## 📚 Developer Documentation

### Filters

Add custom functionality using these filters:

```php
// Modify reading time calculation
add_filter('wp_read_tools_wpm', function($wpm) {
    return 200; // Custom WPM
});

// Modify content before speech synthesis
add_filter('wp_read_tools_speech_content', function($content) {
    return $content; // Modified content
});
```

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 👥 Authors

- **Adalberto H. Vega** - *Initial work* - [ahvega](https://github.com/ahvega)

## 🙏 Acknowledgments

- [WordPress Plugin Boilerplate](https://github.com/DevinVinson/WordPress-Plugin-Boilerplate)
- [Font Awesome](https://fontawesome.com/) for icons

## 📧 Support

For support, please [open an issue](https://github.com/ahvega/wp-read-tools/issues) on GitHub or visit our [WordPress.org plugin page](https://wordpress.org/plugins/wp-read-tools/).
