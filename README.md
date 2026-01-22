# SpeedMate

A lightweight, zero-configuration WordPress performance plugin that delivers static HTML caching, media optimization, and advanced JavaScript deferral without complexity.

## Core Features

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

### Automatic Optimizations
- **Auto-LCP Detection**: Machine learning identifies your Largest Contentful Paint element and automatically injects preload hints
- **Critical CSS**: Automatic stylesheet deferring with critical CSS extraction
- **WebP Conversion**: Automatic WebP image generation with browser fallback support
- **Preload Hints**: DNS prefetch, preconnect, and resource hints for faster loading
- **WooCommerce Integration**: Cart, checkout, and account fragments remain dynamic while product pages are cached
- **Self-Healing Cache**: Monitors plugin and theme updates, purging only affected cache entries

### Management Tools
- **WP-CLI Commands**: Full command-line interface (`flush`, `warm`, `stats`, `gc`, `info`)
- **REST API**: Batch operations and cache management via REST endpoints
- **Import/Export**: JSON-based configuration backup and restore
- **Health Dashboard**: Real-time performance monitoring with traffic light indicators
- **Admin Bar Metrics**: Quick performance stats in WordPress admin bar

## Technical Requirements
- WordPress 5.8 or higher
- PHP 7.4 or higher
- Apache with mod_rewrite OR Nginx with manual configuration
- Write permissions for `.htaccess` (Apache only)

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
SpeedMate operates in two modes, selectable from the admin panel:

**Safe Mode** (recommended for most sites):
- Static HTML caching
- Image lazy-loading with dimension injection
- GZIP compression
- Auto-LCP preloading

**Beast Mode** (advanced performance):
- All Safe Mode features
- JavaScript execution delay
- Configurable script whitelist/blacklist
- Preview mode for admin-only testing before public deployment

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
Advanced security features available in settings:

- **Structured JSON Logging**: Machine-readable logs for SIEM integration
- **CSP Nonce Injection**: Automatic Content Security Policy nonces for inline scripts
- **REST API Protection**: Rate limiting (default: 60 requests/minute) and idempotency keys for state-changing operations

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

### Local Stack
Start a full WordPress development environment with Docker:
```bash
./scripts/stack-up.sh
```
This provisions WordPress, MySQL, and phpMyAdmin accessible at `localhost:8000`.

### Test Execution
Run the complete test suite:
```bash
./scripts/run-tests.sh
```
This executes:
- PHPUnit integration tests (WordPress test framework)
- Playwright end-to-end browser tests

Individual test suites:
```bash
# PHPUnit only
composer test

# Playwright E2E only
cd tests/e2e && npm test
```

### Code Quality
Static analysis and coding standards:
```bash
composer install
composer phpcs      # PHP_CodeSniffer against WordPress standards
composer phpstan    # Static analysis at level 8
```

### Git Hooks
Enable automatic testing before push:
```bash
git config core.hooksPath .githooks
```
The pre-push hook runs PHPUnit and blocks the push if tests fail.

## Architecture

### File Structure
- `/includes/class-speedmate-cache.php` - Core caching engine
- `/includes/class-speedmate-optimizer.php` - Media and asset optimization
- `/includes/class-speedmate-beast-mode.php` - JavaScript delay logic
- `/includes/class-speedmate-lcp.php` - Auto-LCP detection and injection
- `/admin/` - Settings UI and admin functionality

### Caching Strategy
1. Request intercepted via `template_redirect` hook (priority 1)
2. Cache key generated from URL, mobile detection, and logged-in status
3. Cached HTML served with `X-SpeedMate-Cache: HIT` header
4. On cache miss, output buffering captures generated HTML
5. HTML stored in `wp-content/cache/speedmate/` with 24-hour TTL

### Beast Mode Implementation
JavaScript delay uses Mutation Observer and event delegation:
1. All `<script>` tags (except whitelisted) receive `type="speedmate/javascript"`
2. User interaction (click/scroll/keypress) triggers execution queue
3. Scripts execute in original DOM order to preserve dependencies
4. Configurable delay threshold (default: 5 seconds as fallback)

## Performance Metrics

SpeedMate tracks and displays:
- Total pages cached
- Cache hit ratio (hits / total requests)
- Cumulative time saved (avg load time reduction × visitor count)
- Cache size and directory statistics
- LCP improvements before/after optimization
- Real-time admin bar performance indicators

Access metrics in:
- SpeedMate → Dashboard
- WordPress Admin Bar (when logged in)
- WP-CLI: `wp speedmate stats`
- REST API: `/wp-json/speedmate/v1/stats`

## Compatibility

### Known Compatible Plugins
- WooCommerce (with dynamic fragment support)
- Easy Digital Downloads
- MemberPress
- Contact Form 7

### Known Incompatible Plugins
- Other full-page caching plugins (WP Super Cache, W3 Total Cache)
- Plugins that require real-time content on every request without fragment support

## Troubleshooting

### Cache Not Generating
1. Verify `.htaccess` is writable (Apache)
2. Check `wp-content/cache/speedmate/` directory exists and is writable
3. Confirm no conflicting caching plugins are active
4. Review PHP error logs for permission issues

### Beast Mode Breaking Scripts
1. Enable Preview Mode in Beast Mode settings
2. Test as admin to identify problematic scripts
3. Add breaking scripts to whitelist by handle or URL pattern
4. Disable Beast Mode and report issue if whitelisting doesn't resolve

### Dynamic Content Not Updating
1. Verify `[speedmate_dynamic]` shortcode syntax
2. Check that dynamic content is within the shortcode wrapper
3. Flush cache to regenerate pages with updated fragments

## Project Status**v0.3.0**

See [CHANGELOG.md](CHANGELOG.md) for version history and [QUICK-REFERENCE-v0.3.0.md](QUICK-REFERENCE-v0.3.0.md) for quick reference guide
See [CHANGELOG.md](CHANGELOG.md) for version history and [VERSION](VERSION) for semantic versioning details.

## Contributing
Pull requests welcome. Please ensure:
- PHPUnit tests pass (`composer test`)
- Code follows WordPress standards (`composer phpcs`)
- Static analysis passes (`composer phpstan`)

## Security
Report security vulnerabilities privately to fabrizio.salmi@gmail.com. Please allow 48 hours for initial response.

## License
MIT License. See [LICENSE](LICENSE) for full text.

## Links
- Repository: https://github.com/fabriziosalmi/speedmate
- Issues: https://github.com/fabriziosalmi/speedmate/issues
- Releases: https://github.com/fabriziosalmi/speedmate/releases
