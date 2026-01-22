# Getting Started

Get SpeedMate up and running in under 5 minutes.

## Installation

### Via WordPress Admin

1. Download the latest release from [GitHub Releases](https://github.com/fabriziosalmi/speedmate/releases)
2. Navigate to **Plugins > Add New > Upload Plugin**
3. Upload `speedmate.zip`
4. Click **Install Now** then **Activate**

### Via WP-CLI

```bash
wp plugin install https://github.com/fabriziosalmi/speedmate/releases/latest/download/speedmate.zip --activate
```

### Via Composer

```bash
composer require fabriziosalmi/speedmate
```

## Quick Configuration

### 1. Enable Static Cache

Static cache is enabled by default. Verify it's working:

```bash
wp speedmate info
```

### 2. Enable Beast Mode (Recommended)

Navigate to **Settings > SpeedMate** and:

1. Set **Mode** to `beast`
2. Configure cache TTL (default: 3600 seconds)
3. Save changes

### 3. Verify Cache is Working

Visit your homepage twice:

```bash
# First request - cache miss
curl -I https://yoursite.com | grep X-Cache

# Second request - cache hit
curl -I https://yoursite.com | grep X-Cache
# Should return: X-Cache: HIT
```

## Initial Settings

Recommended settings for most sites:

```php
[
    'mode' => 'beast',              // Enable Beast mode
    'cache_ttl' => 3600,            // 1 hour cache lifetime
    'webp_enabled' => true,         // Enable WebP conversion
    'critical_css_enabled' => true, // Enable Critical CSS
    'lazy_load' => true,            // Enable lazy loading
    'preload_enabled' => true,      // Enable resource hints
    'logging_enabled' => false,     // Disable logging in production
]
```

## Verify Installation

Check that SpeedMate is working:

### 1. Check Cache Directory

```bash
ls -la wp-content/cache/speedmate/
```

Should show cache files after visiting pages.

### 2. Check Cache Stats

```bash
wp speedmate stats
```

Should display cache hit rates and file counts.

### 3. Check Advanced Cache

```bash
cat wp-content/advanced-cache.php
```

Should contain SpeedMate's cache loader.

## Next Steps

- [Configure Beast Mode Rules](/config/beast-mode)
- [Set Up Cache Warming](/features/traffic-warmer)
- [Enable Media Optimization](/features/media-optimization)
- [Configure Multisite](/config/multisite)

## Troubleshooting

### Cache Not Working

1. Check file permissions: `wp-content/cache/speedmate/` must be writable
2. Verify `WP_CACHE` is defined in `wp-config.php`
3. Check for conflicting cache plugins
4. Review logs: `wp-content/speedmate.log`

### Beast Mode Not Activating

1. Visit several pages to generate traffic data
2. Check Beast mode whitelist rules
3. Verify cache TTL is set correctly
4. Check logs for Beast mode decisions

### WebP Not Converting

1. Verify GD or Imagick extension is installed
2. Check PHP memory limit (256MB minimum recommended)
3. Ensure source images are accessible
4. Check file permissions on uploads directory
