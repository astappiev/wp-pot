# WP Performance Optimization Tactics (P-o-T)

A modular WordPress plugin for performance optimization and site enhancements with a simple on/off toggle interface.

## Features

- **Performance**: Clean head tags, disable pingbacks/embeds/emoji, lazy load iframes, defer scripts
- **Media**: Local avatars, media meta columns, replace media files
- **SEO**: Enhanced Yoast sitemap with images (requires Yoast SEO)
- **Admin**: Language switcher, custom flags (requires Polylang)
- **Content**: Cyrillic to Latin slug conversion
- **Security**: Disable author archives

All modules can be toggled on/off via **Settings → P-o-T**.

## WP-CLI Commands

```bash
# Replace site URL in database
wp pot-sync siteurl [--yes]

# Flush Nginx cache
wp pot-purge nginx

# Clean unregistered files from uploads
wp pot-clean unregistered [--delete]
```

Commands are located in `includes/cli/` and follow WP-CLI best practices with proper class structure and documentation.

## Creating a Module

Create `includes/modules/class-my-module.php`:

```php
<?php
namespace Pot\Modules;

class My_Module extends \Pot\Module {
    public function get_metadata(): array {
        return [
            'name' => 'My Module',
            'description' => 'Module description',
            'category' => 'performance',
            'default' => true,
            'dependencies' => [],
        ];
    }

    public function load(): void {
        add_action('init', [$this, 'init']);
    }

    public function init(): void {
        // Your code here
    }
}
```

Modules in `includes/modules/` are auto-discovered. Categories: `performance`, `security`, `media`, `seo`, `admin`, `content`, `general`.

## License

GPLv3
