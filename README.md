# SpeedMate

WordPress performance plugin with static HTML caching, media optimization, and JavaScript deferral.

## Features

### Caching System
- **Static HTML Generation**: Full-page cache with intelligent purge on content updates
- **TTL & Expiration**: Configurable cache lifetime per content type (homepage, posts, pages)
- **URL Exclusion Patterns**: Wildcard-based cache exclusions for dynamic pages
- **Server Rule Automation**: Apache `.htaccess` rules generated automatically; Nginx configuration provided for manual setup
- **Cache Warming**: Configurable traffic-pattern analysis with scheduling options (hourly/twice-daily/daily)
- **Dynamic Fragment Support**: Cache entire pages while keeping user-specific content dynamic using `[speedmate_dynamic]` shortcode
- **Multisite Ready**: Per-site cache directories with network-level settings fallback

### Performance Modes
- **Safe Mode**: Production-ready optimizations including lazy-loading, automatic image dimensions, and GZIP compression
- **Beast Mode**: Aggressive JavaScript delay strategy that defers execution until first user interaction (click, scroll, or keypress)

### Optimizations
- **LCP Detection**: Identifies Largest Contentful Paint elements and injects preload hints
- **Critical CSS**: Stylesheet deferring with media print onload strategy
- **WebP Conversion**: Image conversion on upload with browser fallback via picture tags
- **Preload Hints**: DNS prefetch, preconnect, and resource prefetch
- **WooCommerce Support**: Dynamic fragments for cart/checkout, cached product pages
- **Selective Purging**: Cache invalidation on plugin/theme updates

### Management Tools
- **WP-CLI Commands**: Full command-line interface (`flush`, `warm`, `stats`, `gc`, `info`)
- **REST API**: Batch operations and cache management via REST endpoints
- **Import/Export**: JSON-based configuration backup and restore
- **Health Dashboard**: Real-time performance monitoring with traffic light indicators
- **Admin Bar Metrics**: Quick performance stats in WordPress admin bar

## Technical Requirements
- **Operating System**: Linux (Ubuntu, Debian, CentOS, Rocky Linux), macOS, or Windows with WSL
- **WordPress**: 6.0 or higher (6.4+ recommended)
- **PHP**: 7.4 or higher (8.1+ recommended)
- **Web Server**: Apache with mod_rewrite OR Nginx
- **Permissions**: Write access to `wp-content/` and `.htaccess` (Apache)

## Installation

### Development Installation
```bash
cd wp-content/plugins
git clone https://github.com/fabriziosalmi/speedmate.git
```
Then activate via WordPress admin panel and select your performance mode.

### Production Installation
1. Download the latest release ZIP from [GitHub Releases](https://github.com/fabriziosalmi/speedmate/releases)
2. Navigate to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the ZIP file and activate
4. Configure your preferred mode in SpeedMate settings

## Configuration

### Performance Modes

**Safe Mode**:
- Static HTML caching
- Image lazy-loading with dimension injection
- GZIP compression
- LCP preloading

**Beast Mode**:
- All Safe Mode features
- JavaScript execution delay until user interaction
- Configurable script whitelist/blacklist
- Preview mode for testing

### Beast Mode Configuration
The Beast Mode panel allows you to:
- **Whitelist**: Scripts that should execute immediately (e.g., `jquery-core`, `critical-analytics`)
- **Blacklist**: Scripts to forcibly delay (overrides automatic detection)
- **Preview Mode**: Test Beast Mode as logged-in admin without affecting visitors

### Dynamic Content Handling
For user-specific or frequently-changing content within cached pages:

```php
[speedmate_dynamic]
<?php echo get_current_user_name(); ?>
[/speedmate_dynamic]
```

Dynamic fragments are rendered on every request while the surrounding page remains cached.

### Security Hardening (Optional)
Advanced secuFeatures

- **Structured JSON Logging**: Machine-readable logs
- **CSP Nonce Injection**: Content Security Policy nonces for inline scripts
- **REST API Rate Limiting**: 60 requests/minute default, idempotency keys for state change
## Cache Management

### Manual Cache Clearing
- Admin bar: Click "Flush SpeedMate Cache"
- Settings page: "Clear All Cache" button
- WP-CLI: `wp speedmate flush`
- REST API: POST to `/wp-json/speedmate/v1/cache/flush`

### Automatic Cache Purging
Cache is automatically invalidated when:
- Posts or pages are published, updated, or deleted
- Plugins or themes are activated, deactivated, or updated
- SpeedMate settings are modified
- Manual purge is triggered

## Development Setup

### Local Stac

### Docker Stack
```bash
./scripts/stack-up.sh
```
Starts WordPress, MySQL, and phpMyAdmin on
Run the complete test suite:
```bash
./scripts/run-tests.sh  # PHPUnit + Playwright E2E
```

Individual

# Playwright E2E only
cd tests/e2e && npm test


### Code Quality
Static analysis and coding standards:

```bash
composer install
composer phpcs      # PHP_CodeSniffer against WordPress standards
composer phpstan    # Static analysis at level 8
```

```bash
composer phpcs      # WordPress coding standards
composer phpstan    # Static analysis
```

### Git Hooks
```bash
git config core.hooksPath .githooks  # Pre-push PHPUnit tests
```

- `/includes/class-speedmate-lcp.php` - Auto-LCP detection and injection
- `/admin/` - Settings UI and admin functionality

### Caching Strategy
1. Request intercepted via `template_redirect` hook
2. Implementation

### Caching
1. `template_redirect` hook intercepts requests
2. Cache key: URL + mobile detection + login status
3. Cached HTML served with `X-SpeedMate-Cache: HIT` header
4. Output buffering captures HTML on miss
5. Files stored in `wp-content/cache/speedmate/` with configurable TTL

### JavaScript Delay (Beast Mode)
1. Script tags receive `type="speedmate/javascript"` (except whitelist)
2. User interaction (click/scroll/keypress) triggers execution
3. Scripts execute in DOM order
4. 5-second fallback timeout
Access metrics in:
- SpeedMate → Dashboard
- WordPress Admin Bar (when logged in)
- WP-CLI: `wp speedmate stats`
- REST API: `/wp-json/speedmate/v1/stats`

## Compatibility

### Known Compatible Plugins
- WMetrics

Tracked data:
- Pages cached
- Hit ratio
- Time saved estimate
- Cache size
- LCP statistics

Access via:
- Dashboard widget
- Admin barche
2. Check `wp-content/cache/speedmate/` directory exists and is writable
3. Confirm no conflicting caching plugins are active
4. Review PHP error logs for permission issues

### Beast Mode Breaking Scripts
1. Enable Preview Mode in Beast Mode settings
2. Test as admin to identify problematic scripts
3. Add breaking scripts to whitelist by handle or URL pattern
4. Disable Beast Mode and report issue if whitelisting doesn't resolve

### Dynamic Content Not Updating
1. Verify [speedmate_dynamic] shortcode syntax
2. Check that dynamic content is within the shortcode wrapper
3. Flush cache to regenerate pages with updated fragments

## Contributing
Pull requests welcome. Please ensure:
- PHPUnit tests pass (`composer test`)
- Code follows WordPress standards (`composer phpcs`)
- Static analysis passes (`composer phpstan`)

## License
MIT License. See [LICENSE](LICENSE) for full text.

## Links
- Repository: https://github.com/fabriziosalmi/speedmate
- Issues: https://github.com/fabriziosalmi/speedmate/issues
- Releases: https://github.com/fabriziosalmi/speedmate/releases
require:
- PHPUnit tests passing
- WordPress coding standards (`composer phpcs`)
- PHPStan passing

## Security
Report vulnerabilities to fabrizio.salmi@gmail.com
