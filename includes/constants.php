<?php
/**
 * SpeedMate Constants
 *
 * Centralized constants for magic numbers used throughout the plugin.
 * Extracting these improves maintainability and reduces duplication.
 *
 * @package SpeedMate
 * @since 0.4.1
 */

declare(strict_types=1);

// Admin Menu
if (!defined('SPEEDMATE_MENU_POSITION')) {
    define('SPEEDMATE_MENU_POSITION', 58);
}

// Filesystem Permissions
if (!defined('SPEEDMATE_DIR_PERMISSIONS')) {
    define('SPEEDMATE_DIR_PERMISSIONS', 0755);
}

// Rate Limiting
if (!defined('SPEEDMATE_LCP_RATE_LIMIT')) {
    define('SPEEDMATE_LCP_RATE_LIMIT', 60); // requests per minute
}

if (!defined('SPEEDMATE_LCP_RATE_WINDOW')) {
    define('SPEEDMATE_LCP_RATE_WINDOW', 60); // seconds
}

if (!defined('SPEEDMATE_FRAGMENT_RATE_LIMIT')) {
    define('SPEEDMATE_FRAGMENT_RATE_LIMIT', 120); // requests per minute
}

if (!defined('SPEEDMATE_FRAGMENT_RATE_WINDOW')) {
    define('SPEEDMATE_FRAGMENT_RATE_WINDOW', 60); // seconds
}

// Import/Export
if (!defined('SPEEDMATE_MAX_IMPORT_SIZE')) {
    define('SPEEDMATE_MAX_IMPORT_SIZE', 1048576); // 1MB in bytes
}

// Batch API
if (!defined('SPEEDMATE_MAX_BATCH_SIZE')) {
    define('SPEEDMATE_MAX_BATCH_SIZE', 10); // max requests per batch
}

if (!defined('SPEEDMATE_MIN_MEMORY_MB')) {
    define('SPEEDMATE_MIN_MEMORY_MB', 32); // minimum memory required for batch processing
}

// Cache TTL Defaults
if (!defined('SPEEDMATE_DEFAULT_TTL_HOMEPAGE')) {
    define('SPEEDMATE_DEFAULT_TTL_HOMEPAGE', 3600); // 1 hour
}

if (!defined('SPEEDMATE_DEFAULT_TTL_POSTS')) {
    define('SPEEDMATE_DEFAULT_TTL_POSTS', 7 * DAY_IN_SECONDS); // 1 week
}

if (!defined('SPEEDMATE_DEFAULT_TTL_PAGES')) {
    define('SPEEDMATE_DEFAULT_TTL_PAGES', 30 * DAY_IN_SECONDS); // 1 month
}

// Idempotency
if (!defined('SPEEDMATE_IDEMPOTENCY_DURATION')) {
    define('SPEEDMATE_IDEMPOTENCY_DURATION', 10 * MINUTE_IN_SECONDS); // 10 minutes
}

// WordPress Hooks Priority
if (!defined('SPEEDMATE_HOOK_PRIORITY_DEFAULT')) {
    define('SPEEDMATE_HOOK_PRIORITY_DEFAULT', 10);
}

// Time Calculations (for readability in formatDuration methods)
if (!defined('SPEEDMATE_SECONDS_PER_MINUTE')) {
    define('SPEEDMATE_SECONDS_PER_MINUTE', 60);
}

if (!defined('SPEEDMATE_MINUTES_PER_HOUR')) {
    define('SPEEDMATE_MINUTES_PER_HOUR', 60);
}

// Stats Average Calculation
if (!defined('SPEEDMATE_STATS_ROLLING_WEIGHT')) {
    define('SPEEDMATE_STATS_ROLLING_WEIGHT', 9); // Weight for old value in rolling average: (old * 9 + new) / 10
}

if (!defined('SPEEDMATE_STATS_ROLLING_DIVISOR')) {
    define('SPEEDMATE_STATS_ROLLING_DIVISOR', 10); // Divisor for rolling average
}

if (!defined('SPEEDMATE_STATS_DEFAULT_CACHED_MS')) {
    define('SPEEDMATE_STATS_DEFAULT_CACHED_MS', 50); // Default cached page read time in milliseconds
}
