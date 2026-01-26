# Installation

Complete installation guide for SpeedMate.

## Requirements

### Minimum Requirements

- **Operating System**: Linux (Ubuntu, Debian, CentOS, Rocky Linux), macOS, or Windows with WSL
- **PHP**: 7.4 or higher (8.1+ recommended)
- **WordPress**: 6.0 or higher (6.4+ recommended)
- **Disk Space**: 100MB free in `wp-content/cache/`
- **File Permissions**: Write access to `wp-content/` directory

### Recommended

- **PHP Extensions**: GD or Imagick for WebP conversion
- **Memory Limit**: 256MB or higher
- **Object Cache**: Redis or Memcached for optimal performance
- **Server**: Nginx or Apache with mod_rewrite

## Installation Methods

### Method 1: WordPress Admin (Recommended)

1. Download `speedmate.zip` from [GitHub Releases](https://github.com/fabriziosalmi/speedmate/releases/latest)
2. Go to **Plugins > Add New > Upload Plugin**
3. Choose the downloaded ZIP file
4. Click **Install Now**
5. Click **Activate Plugin**

### Method 2: WP-CLI

```bash
# Install from GitHub
wp plugin install https://github.com/fabriziosalmi/speedmate/releases/latest/download/speedmate.zip --activate

# Or from WordPress.org (when available)
wp plugin install speedmate --activate
```

### Method 3: Manual Installation

1. Download and extract `speedmate.zip`
2. Upload the `speedmate` folder to `wp-content/plugins/`
3. Activate via WordPress admin or WP-CLI:

```bash
wp plugin activate speedmate
```

### Method 4: Git Clone (Development)

```bash
cd wp-content/plugins/
git clone https://github.com/fabriziosalmi/speedmate.git
cd speedmate
composer install --no-dev
```

## Post-Installation

### 1. Verify Installation

```bash
wp speedmate info
```

Expected output:
```
SpeedMate v0.3.1
Status: Active
Cache Directory: /path/to/wp-content/cache/speedmate
Advanced Cache: Enabled
Mode: disabled (configure to enable)
```

### 2. Configure wp-config.php

SpeedMate automatically adds `WP_CACHE` constant. Verify it exists:

```php
// wp-config.php
define('WP_CACHE', true);
```

### 3. Set File Permissions

```bash
# Cache directory must be writable
chmod 755 wp-content/cache/
chmod 755 wp-content/cache/speedmate/

# On some servers, you may need 775 or 777
# chmod 775 wp-content/cache/speedmate/
```

### 4. Create Advanced Cache Drop-in

SpeedMate automatically creates `wp-content/advanced-cache.php`. Verify:

```bash
ls -la wp-content/advanced-cache.php
```

If missing, manually create it with:

```php
<?php
// wp-content/advanced-cache.php
defined('ABSPATH') || exit;

$cache_file = WP_CONTENT_DIR . '/plugins/speedmate/includes/Cache/advanced-cache.php';
if (file_exists($cache_file)) {
    require_once $cache_file;
}
```

## Multisite Installation

### Network Activation

```bash
wp plugin activate speedmate --network
```

### Per-Site Activation

Activate normally on each site. SpeedMate automatically isolates cache per site.

### Network-Wide Settings

Configure default settings in `wp-config.php`:

```php
// Network-wide SpeedMate defaults
define('SPEEDMATE_DEFAULT_MODE', 'beast');
define('SPEEDMATE_DEFAULT_TTL', 3600);
```

## Docker Installation

### Using Official WordPress Image

```dockerfile
FROM wordpress:latest

# Install SpeedMate
RUN wp plugin install https://github.com/fabriziosalmi/speedmate/releases/latest/download/speedmate.zip --activate --allow-root

# Configure cache directory
RUN mkdir -p /var/www/html/wp-content/cache/speedmate && \
    chown -R www-data:www-data /var/www/html/wp-content/cache
```

### Docker Compose

```yaml
version: '3.8'
services:
  wordpress:
    image: wordpress:latest
    volumes:
      - ./speedmate:/var/www/html/wp-content/plugins/speedmate
      - cache:/var/www/html/wp-content/cache
    environment:
      WORDPRESS_CONFIG_EXTRA: |
        define('WP_CACHE', true);

volumes:
  cache:
```

## Troubleshooting Installation

### Plugin Won't Activate

**Error**: "The plugin does not have a valid header"

**Solution**: Verify all files extracted correctly:
```bash
ls -la wp-content/plugins/speedmate/speedmate.php
```

### Cache Directory Not Created

**Error**: "Cache directory is not writable"

**Solution**: Create manually and set permissions:
```bash
mkdir -p wp-content/cache/speedmate
chmod 755 wp-content/cache/speedmate
chown www-data:www-data wp-content/cache/speedmate
```

### Advanced Cache Not Loading

**Error**: Cache headers not showing

**Solution**: 
1. Verify `WP_CACHE` is defined in `wp-config.php`
2. Check `advanced-cache.php` exists in `wp-content/`
3. Ensure no other cache plugins are active

### Composer Dependencies Missing

**Error**: "Class 'SpeedMate\Utils\Settings' not found"

**Solution**: Install Composer dependencies:
```bash
cd wp-content/plugins/speedmate
composer install --no-dev --optimize-autoloader
```

## Uninstallation

### Via WordPress Admin

1. Deactivate plugin
2. Delete plugin
3. SpeedMate automatically cleans up cache files

### Via WP-CLI

```bash
wp plugin deactivate speedmate
wp plugin delete speedmate

# Manually remove cache (optional)
rm -rf wp-content/cache/speedmate/
```

### Clean Uninstall

```bash
# Remove plugin
wp plugin delete speedmate

# Remove cache directory
rm -rf wp-content/cache/speedmate/

# Remove advanced-cache.php (if only SpeedMate)
rm wp-content/advanced-cache.php

# Remove WP_CACHE constant from wp-config.php
# Edit manually or use:
wp config delete WP_CACHE
```

## Next Steps

- [Getting Started Guide](/guide/getting-started)
- [Configuration Options](/config/settings)
- [Enable Beast Mode](/config/beast-mode)
