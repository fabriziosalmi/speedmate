---
layout: home

hero:
  name: "SpeedMate"
  text: "WordPress Performance Plugin"
  tagline: Free static cache, automation, and Beast mode for WordPress
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/fabriziosalmi/speedmate

features:
  - icon: ⚡
    title: Static Cache
    details: Disk-based static HTML cache with automatic invalidation. Serves cached pages directly without WordPress initialization.
  
  - icon: 🦁
    title: Beast Mode
    details: Automatic static cache with intelligent page selection. Learns from traffic patterns to cache high-traffic pages.
  
  - icon: 🖼️
    title: Media Optimization
    details: WebP conversion with fallback support. Automatic AVIF generation. Lazy loading for images and iframes.
  
  - icon: 🎨
    title: Critical CSS
    details: Extract and inline critical CSS automatically. Async load non-critical styles. Reduce render-blocking resources.
  
  - icon: 🔄
    title: Traffic Warmer
    details: Proactive cache warming based on user navigation patterns. Pre-caches likely next pages before user clicks.
  
  - icon: 🚀
    title: Preload Hints
    details: DNS prefetch, preconnect, and resource hints. Optimizes resource loading priority automatically.
  
  - icon: 📊
    title: Health Widget
    details: Real-time cache statistics in WordPress admin dashboard. Monitor cache hit rates and performance metrics.
  
  - icon: 🌐
    title: Multisite Ready
    details: Full WordPress multisite support with per-site cache isolation. Network-wide or per-site configuration.
  
  - icon: 🔌
    title: REST API
    details: Complete REST API for cache management. Flush, warm, and query cache programmatically.
---

## Quick Start

```bash
# Install via WP-CLI
wp plugin install speedmate --activate

# Or download from GitHub
wget https://github.com/fabriziosalmi/speedmate/releases/latest/download/speedmate.zip
wp plugin install speedmate.zip --activate
```

## Performance Features

SpeedMate provides enterprise-grade performance optimization for WordPress:

- **Static HTML Cache**: Disk-based cache with automatic invalidation
- **Beast Mode**: AI-driven automatic caching based on traffic patterns  
- **WebP/AVIF**: Automatic next-gen image format conversion
- **Critical CSS**: Extract and inline critical rendering path CSS
- **Cache Warming**: Proactive pre-caching of likely navigation paths
- **Resource Hints**: DNS prefetch, preconnect, preload optimization

## Configuration

Configure SpeedMate via:
- WordPress Admin interface
- REST API endpoints
- WP-CLI commands
- Direct settings array

```php
// Example: Programmatic configuration
update_option('speedmate_settings', [
    'mode' => 'beast',
    'cache_ttl' => 3600,
    'webp_enabled' => true,
    'critical_css_enabled' => true,
]);
```

## WP-CLI Commands

```bash
# Flush entire cache
wp speedmate flush

# Warm cache for specific URLs
wp speedmate warm --urls=https://example.com/,https://example.com/about

# View cache statistics
wp speedmate stats

# Plugin information
wp speedmate info

# Garbage collection
wp speedmate gc
```

## REST API

All cache operations available via REST API:

```bash
# Flush cache
curl -X POST https://site.com/wp-json/speedmate/v1/cache/flush \
  -H "X-WP-Nonce: $NONCE"

# Get statistics
curl https://site.com/wp-json/speedmate/v1/stats

# Batch operations
curl -X POST https://site.com/wp-json/speedmate/v1/batch/warm \
  -H "Content-Type: application/json" \
  -d '{"urls":["https://site.com/page1","https://site.com/page2"]}'
```

## Requirements

- **Operating System**: Linux, macOS, or Windows (WSL)
- **PHP**: 7.4 or higher (8.1+ recommended)
- **WordPress**: 6.0 or higher (6.4+ recommended)
- WordPress 6.0 or higher
- Write permissions to `wp-content/cache/speedmate/`

## License

GPL-3.0 License - Free and open source
