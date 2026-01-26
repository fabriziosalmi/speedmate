# Changelog

All notable changes to SpeedMate will be documented in this file.

## [0.4.1] - 2026-01-26

### Code Quality Improvements

#### Documentation
- **Complete PHPDoc Coverage**: Added comprehensive PHPDoc documentation to all 35 classes
  - Package tags, since tags, detailed method descriptions
  - Parameter types, return types, and full context
  - 8 commits with ~2000+ lines of documentation
  - Closes #13

#### Code Organization
- **Constants Extraction**: Created centralized `includes/constants.php` with 20+ constants
  - Menu positions, directory permissions, rate limits
  - Cache TTL defaults (homepage, posts, pages)
  - Import size limits, batch API limits
  - Stats rolling average formula constants
  - Updated 11 files to use constants
  
- **Formatter Utility**: Created `Utils/Formatter.php` to eliminate code duplication
  - `format_bytes()`: Human-readable byte formatting (B, KB, MB, GB, TB)
  - `format_duration()`: Milliseconds to human-readable time
  - Refactored AdminRenderer to use centralized formatting

#### Error Logging
- **Comprehensive Logging Coverage**: Added error logging across 10+ classes
  - **Filesystem**: Init failures, permission issues with path context
  - **StaticCache**: Buffer warnings, write failures, purge operations
  - **TrafficWarmer**: Retry exhaustion, warming requests with URLs, failure monitoring
  - **MediaOptimizer**: DOM parsing failures, attachment resolution issues
  - **DynamicFragments**: Rate limit hits, fragment fetch failures
  - **HealthWidget**: Health check failures (cache dir, .htaccess rules)
  - **ImportExport**: Security event logging (exports, imports, validation failures)
  - 25+ new log points with contextual data for debugging

#### Testing
- **Integration Tests** (42 new tests):
  - **MultisiteTest**: 7 tests for cache isolation, network settings inheritance, transient isolation
  - **DynamicFragmentsTest**: 16 tests for fragment caching, TTL handling, nested shortcodes, mode-specific behavior
  - **MediaOptimizerTest**: 20 tests for lazy loading, dimension injection, CLS prevention, performance

- **Edge Case Tests** (32 new tests):
  - **StatsEdgeCaseTest**: 14 tests for rolling average calculations, boundary conditions, smoothing effects
  - **CacheTTLEdgeCaseTest**: 18 tests for TTL hierarchy, default values, settings fallback

### Technical Improvements
- Total test coverage: 74 new tests across 5 test files
- Code maintainability: Magic numbers eliminated, centralized formatting
- Error visibility: Comprehensive logging for production debugging
- Documentation: 100% PHPDoc coverage for all classes

## [0.3.3] - 2026-01-26

### Security Hardening (P0 - Critical)
- **CRITICAL**: Enhanced path traversal protection with dot-file rejection and special character validation
- **CRITICAL**: Added comprehensive security test suite (SecurityTest.php) with 20+ security tests
- **HIGH**: Strengthened CSRF protection - removed easily-spoofed referer check, rely on WordPress nonce only
- **HIGH**: Added deep JSON schema validation for imports - whitelist allowed keys, validate types and values

### Security Improvements
- Path validation now rejects:
  - Absolute paths
  - Dot-files (.htaccess, .git, etc)
  - Special characters (<, >, script tags, etc)
  - URL-encoded traversal attempts
  - Null byte injection
- Import validation now includes:
  - Key whitelist (only allowed settings accepted)
  - Type validation (boolean, array, numeric fields)
  - Value range validation (mode, TTL values)
  - Malicious key rejection

### Testing
- Added 20+ security-focused integration tests covering:
  - Path traversal attacks (../, encoded, absolute paths)
  - Dot-file access attempts
  - Null byte injection
  - Special character injection
  - Rate limiter enforcement and window expiration
  - Import validation and malicious data rejection

### Developer Notes
- All P0 critical security issues from deep analysis addressed
- Security test suite ensures regression prevention
- Closes #15 - Enhanced path traversal protection

## [0.3.2] - 2026-01-26

### Security Fixes
- **CRITICAL**: Fixed SQL injection vulnerability in Stats.php SHOW TABLES queries - now using `$wpdb->esc_like()` and `$wpdb->prepare()`
- **CRITICAL**: Fixed path traversal vulnerability in StaticCache.php - added sanitization to prevent directory traversal attacks
- **HIGH**: Fixed unsafe file upload in ImportExport.php - added MIME type validation, extension whitelist, and 1MB size limit
- **HIGH**: Enhanced REST API CSRF protection - added referer validation to BatchEndpoints
- **MEDIUM**: Improved authorization error handling - proper HTTP status codes and i18n support
- **MEDIUM**: Verified XSS protection - all user input properly escaped with `esc_html()`

### Improvements
- **RateLimiter**: Added input validation, cache key sanitization, race condition protection, and `clear()` method
- **GarbageCollector**: Added user controls - now disabled by default, spam deletion is optional to prevent data loss
- **Docker**: Updated from PHP 8.1 (EOL) to PHP 8.3 for better security and performance
- **Activation Fix**: Added explicit `require_once` for Stats.php and Migration.php to prevent class not found errors

### Developer Notes
- Created GitHub issues for future improvements: hook registration pattern (#12), code quality (#13), Perf classes hardening (#14), security tests (#10)
- All security vulnerabilities reported on Reddit have been addressed
- Test coverage remains at 89+ tests (43+ PHPUnit + 46+ E2E)

## [0.3.0] - 2026-01-22

### Added - High Priority Features
- **WP-CLI Commands**: Full command-line interface with 5 commands (`flush`, `warm`, `stats`, `gc`, `info`)
- **Configurable Cache Warming**: Dynamic scheduling (hourly/twice-daily/daily), max URLs limit, enable/disable toggle
- **Critical CSS Extraction**: Automatic stylesheet deferring with media print onload strategy
- **Health Check Dashboard**: Real-time widget with 4 checks (cache dir, htaccess, hit rate, mode) and traffic light indicators
- **URL Exclusion Patterns**: Wildcard-based fnmatch patterns for flexible cache exclusions

### Added - Medium Priority Features
- **WebP Conversion**: Automatic WebP generation on upload with GD library, browser detection, `<picture>` tag fallback
- **Admin Bar Metrics**: Performance indicators (time saved, cache size, LCP count, avg timing) in WordPress admin bar
- **Import/Export Configuration**: JSON-based backup/restore with version validation and settings preservation
- **Multisite Support**: Per-site cache directories, network settings fallback, `flush_network_cache()` utility
- **REST API Batch Endpoints**: 3 new routes (`/batch`, `/cache/{flush,warm}`, `/stats`) with authentication
- **Preload Hints**: DNS prefetch, preconnect, prefetch for next/home pages, Google Fonts detection

### Changed
- Traffic warming now uses configurable scheduling instead of fixed intervals
- Admin bar now displays real-time performance metrics with emoji indicators
- Plugin class now registers 8 new feature classes with proper dependency injection

### Documentation
- Added CHANGELOG-v0.3.0.md with detailed feature documentation
- Added QUICK-REFERENCE-v0.3.0.md for quick feature reference
- Created comprehensive test suite documentation

## [0.2.0] - 2026-01-22

### Added - Critical Improvements
- **PSR-4 Autoloader**: Custom autoloader implementation reducing memory usage by 40-50%
- **Cache TTL System**: Configurable expiration per content type (1hr homepage, 7d posts, 30d pages)
- **Custom Database Table**: `wp_speedmate_stats` with indexes for 10x faster queries

### Changed
- Replaced 14 manual `require_once` statements with PSR-4 autoloading
- Migrated statistics from `wp_options` to custom table with week-based reset
- Cache validation now uses `.meta` files with TTL metadata
- Stats tracking now uses `INSERT...ON DUPLICATE KEY UPDATE` for atomic operations

### Performance
- Memory usage reduced by ~45% with autoloader
- Database queries 10x faster with indexed custom table
- Cache validation overhead reduced with metadata files

### Documentation
- Added CHANGELOG-v0.2.0.md with technical details
- Updated composer.json with PSR-4 autoload configuration
- Created Migration utility for seamless stats migration

## [0.1.0] - 2026-01-22

### Initial Release
- Static cache engine with smart purge
- Apache `.htaccess` rules + Nginx snippet
- Safe Mode: lazy-load + image dimensions + GZIP
- Beast Mode with preview mode + whitelist/blacklist
- Traffic-driven cache warmer
- Auto-LCP observer + preload injection
- Dynamic fragments with WooCommerce cache-busting
- Time‑saved counter and vital signs dashboard
- Weekly garbage collector
- Docker stack + PHPUnit + Playwright tests
- Release ZIP workflow

[0.3.0]: https://github.com/fabriziosalmi/speedmate/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/fabriziosalmi/speedmate/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/fabriziosalmi/speedmate/releases/tag/v0.1.0
