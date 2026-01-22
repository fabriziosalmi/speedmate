# Settings

Complete configuration reference for SpeedMate.

## Settings Overview

SpeedMate settings are stored in WordPress options table under `speedmate_settings` key.

## Access Methods

### 1. WordPress Admin

Navigate to **Settings > SpeedMate** to configure via GUI.

### 2. Programmatic

```php
// Get all settings
$settings = get_option('speedmate_settings', []);

// Update settings
update_option('speedmate_settings', [
    'mode' => 'beast',
    'cache_ttl' => 3600,
]);
```

### 3. WP-CLI

```bash
# View settings
wp option get speedmate_settings --format=json

# Update setting
wp option update speedmate_settings --format=json < settings.json
```

### 4. REST API

```bash
# Get settings
curl https://site.com/wp-json/speedmate/v1/settings

# Update settings (requires nonce)
curl -X POST https://site.com/wp-json/speedmate/v1/settings \
  -H "X-WP-Nonce: $NONCE" \
  -d '{"mode":"beast","cache_ttl":3600}'
```

## Core Settings

### Mode

Cache operation mode.

**Type**: `string`  
**Options**: `disabled`, `static`, `beast`  
**Default**: `disabled`

```php
'mode' => 'beast'
```

- `disabled`: No caching
- `static`: Manual static cache only
- `beast`: Automatic intelligent caching

### Cache TTL

Time-to-live for cached pages in seconds.

**Type**: `int`  
**Default**: `3600` (1 hour)

```php
'cache_ttl' => 3600
```

Common values:
- `1800` - 30 minutes (dynamic content)
- `3600` - 1 hour (standard)
- `86400` - 24 hours (static content)

### Gzip Compression

Enable gzip compression for cached files.

**Type**: `bool`  
**Default**: `true`

```php
'gzip_enabled' => true
```

Reduces file size by 60-80%.

## Beast Mode Settings

### Beast Threshold

Minimum score required for auto-caching.

**Type**: `int`  
**Default**: `50`

```php
'beast_threshold' => 50
```

Lower = more aggressive caching

### Beast Time Window

Time window for traffic analysis in seconds.

**Type**: `int`  
**Default**: `3600` (1 hour)

```php
'beast_time_window' => 3600
```

### Beast Whitelist

URL patterns to always cache.

**Type**: `array`  
**Default**: `[]`

```php
'beast_whitelist' => [
    '/blog/*',
    '/category/*',
    '/tag/*',
]
```

Supports wildcards: `*`, `?`

### Beast Blacklist

URL patterns to never cache.

**Type**: `array`  
**Default**: `[]`

```php
'beast_blacklist' => [
    '/checkout/*',
    '/cart/*',
    '/my-account/*',
]
```

## Media Settings

### WebP Enabled

Enable WebP conversion.

**Type**: `bool`  
**Default**: `true`

```php
'webp_enabled' => true
```

### AVIF Enabled

Enable AVIF conversion.

**Type**: `bool`  
**Default**: `true`

```php
'avif_enabled' => true
```

### WebP Quality

WebP compression quality (0-100).

**Type**: `int`  
**Default**: `85`

```php
'webp_quality' => 85
```

### Lazy Load

Enable lazy loading for images.

**Type**: `bool`  
**Default**: `true`

```php
'lazy_load' => true
```

### Lazy Threshold

Distance in pixels before loading.

**Type**: `int`  
**Default**: `200`

```php
'lazy_threshold' => 200
```

## Performance Settings

### Critical CSS Enabled

Enable critical CSS extraction.

**Type**: `bool`  
**Default**: `true`

```php
'critical_css_enabled' => true
```

### Critical Viewport Height

Viewport height for critical CSS extraction.

**Type**: `int`  
**Default**: `1080`

```php
'critical_viewport_height' => 1080
```

### Preload Enabled

Enable resource hints.

**Type**: `bool`  
**Default**: `true`

```php
'preload_enabled' => true
```

### DNS Prefetch

Domains for DNS prefetch.

**Type**: `array`  
**Default**: `[]`

```php
'dns_prefetch' => [
    'fonts.googleapis.com',
    'cdn.example.com',
]
```

### Preconnect

Domains for preconnect.

**Type**: `array`  
**Default**: `[]`

```php
'preconnect' => [
    'https://fonts.googleapis.com',
    'https://fonts.gstatic.com',
]
```

## Traffic Warmer Settings

### Traffic Warmer Enabled

Enable traffic warmer.

**Type**: `bool`  
**Default**: `false`

```php
'traffic_warmer_enabled' => true
```

### Warm Strategy

Warming strategy.

**Type**: `string`  
**Options**: `on-visit`, `scheduled`, `idle`  
**Default**: `on-visit`

```php
'warm_strategy' => 'on-visit'
```

### Warm Concurrent

Concurrent warm requests.

**Type**: `int`  
**Default**: `3`  
**Range**: 1-10

```php
'warm_concurrent' => 3
```

### Warm Delay

Delay between requests in milliseconds.

**Type**: `int`  
**Default**: `500`

```php
'warm_delay' => 500
```

## Logging Settings

### Logging Enabled

Enable structured logging.

**Type**: `bool`  
**Default**: `false`

```php
'logging_enabled' => true
```

**Note**: Disable in production for performance.

### Log Level

Logging level.

**Type**: `string`  
**Options**: `debug`, `info`, `warning`, `error`  
**Default**: `info`

```php
'log_level' => 'info'
```

### Log File

Custom log file path.

**Type**: `string`  
**Default**: `wp-content/speedmate.log`

```php
'log_file' => WP_CONTENT_DIR . '/logs/speedmate.log'
```

## Advanced Settings

### Cache Logged In

Cache pages for logged-in users.

**Type**: `bool`  
**Default**: `false`

```php
'cache_logged_in' => false
```

**Warning**: May leak user-specific content.

### Cache Query Strings

Cache pages with query strings.

**Type**: `bool`  
**Default**: `true`

```php
'cache_query_strings' => true
```

### Exclude Patterns

URL patterns to exclude from cache.

**Type**: `array`  
**Default**: `[]`

```php
'exclude_patterns' => [
    '/wp-admin/*',
    '/wp-login.php',
    '/*preview=*',
]
```

### User Agent Groups

Group user agents for separate caches.

**Type**: `array`  
**Default**: `['mobile', 'desktop']`

```php
'user_agent_groups' => [
    'mobile' => ['iPhone', 'Android'],
    'desktop' => [],
]
```

## Multisite Settings

### Network Settings

Network-wide defaults:

```php
// In wp-config.php
define('SPEEDMATE_NETWORK_MODE', 'beast');
define('SPEEDMATE_NETWORK_TTL', 3600);
```

### Per-Site Override

```php
// Site-specific settings
switch_to_blog(2);
update_option('speedmate_settings', [
    'mode' => 'static',  // Override network default
]);
restore_current_blog();
```

## Import/Export

### Export Settings

```bash
# Via WP-CLI
wp option get speedmate_settings --format=json > speedmate-settings.json

# Via REST API
curl https://site.com/wp-json/speedmate/v1/settings > settings.json
```

### Import Settings

```bash
# Via WP-CLI
wp option update speedmate_settings --format=json < speedmate-settings.json

# Via WordPress Admin
# Settings > SpeedMate > Import/Export > Choose File
```

## Configuration Examples

### High-Traffic Blog

```php
update_option('speedmate_settings', [
    'mode' => 'beast',
    'cache_ttl' => 3600,
    'beast_threshold' => 30,
    'beast_whitelist' => ['/', '/blog/*', '/category/*'],
    'webp_enabled' => true,
    'critical_css_enabled' => true,
    'traffic_warmer_enabled' => true,
    'warm_strategy' => 'on-visit',
]);
```

### E-Commerce Site

```php
update_option('speedmate_settings', [
    'mode' => 'beast',
    'cache_ttl' => 1800,
    'beast_blacklist' => ['/checkout/*', '/cart/*', '/my-account/*'],
    'cache_logged_in' => false,
    'webp_enabled' => true,
    'lazy_load' => true,
]);
```

### Corporate Site

```php
update_option('speedmate_settings', [
    'mode' => 'static',
    'cache_ttl' => 86400,
    'webp_enabled' => true,
    'critical_css_enabled' => true,
    'preload_enabled' => true,
    'dns_prefetch' => ['fonts.googleapis.com'],
]);
```

## Validation

Settings are validated on save:

```php
add_filter('speedmate_validate_settings', function($settings) {
    // Ensure TTL is positive
    if (isset($settings['cache_ttl']) && $settings['cache_ttl'] < 0) {
        $settings['cache_ttl'] = 3600;
    }
    
    // Ensure quality is 0-100
    if (isset($settings['webp_quality'])) {
        $settings['webp_quality'] = max(0, min(100, $settings['webp_quality']));
    }
    
    return $settings;
});
```

## Next Steps

- [Beast Mode Configuration](/config/beast-mode)
- [Cache Control](/config/cache-control)
- [Multisite Setup](/config/multisite)
