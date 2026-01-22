# What is SpeedMate?

SpeedMate is a WordPress performance plugin that provides static HTML caching, automated optimization, and intelligent traffic management.

## Core Philosophy

- **Zero Configuration**: Works out of the box with sensible defaults
- **Automation First**: Beast mode learns from traffic patterns automatically
- **Performance by Default**: Static cache enabled from installation
- **Developer Friendly**: Complete REST API and WP-CLI support

## Architecture

SpeedMate uses a multi-layered caching strategy:

1. **Static HTML Cache**: Disk-based cache served directly by advanced-cache.php
2. **Dynamic Fragments**: Cache dynamic components separately
3. **Object Cache Integration**: Works with Redis/Memcached when available
4. **Traffic Warmer**: Proactive pre-caching of likely navigation paths

## Key Features

### Static Cache Engine
- Disk-based HTML caching with automatic invalidation
- Supports query strings, user agents, cookies
- Multisite-aware with per-site isolation
- Gzip compression for reduced bandwidth

### Beast Mode
- Automatically identifies high-traffic pages
- Machine learning-based page selection
- Configurable whitelist/blacklist rules
- Real-time traffic analysis

### Media Optimization
- WebP conversion with automatic fallback
- AVIF support for browsers that support it
- Lazy loading for images and iframes
- Responsive image srcset generation

### Critical CSS
- Extracts critical rendering path CSS
- Inlines critical styles in `<head>`
- Async loads non-critical stylesheets
- Per-template CSS optimization

### Developer Tools
- Complete REST API for cache management
- WP-CLI commands for automation
- Hooks and filters for customization
- Logging with structured JSON format

## When to Use SpeedMate

SpeedMate is ideal for:

- **Content Sites**: Blogs, news sites, magazines
- **WooCommerce**: Product pages, category archives
- **Membership Sites**: Protected content with user-aware caching
- **Multisite Networks**: Per-site cache isolation
- **High Traffic Sites**: Automatic cache warming and Beast mode

## When NOT to Use SpeedMate

Consider alternatives if you need:

- **Full-Page CDN**: Use Cloudflare or similar
- **Database Query Optimization**: Use Query Monitor
- **Server-Level Optimization**: Use Nginx FastCGI cache

SpeedMate focuses on WordPress-level optimization and works alongside other performance solutions.
