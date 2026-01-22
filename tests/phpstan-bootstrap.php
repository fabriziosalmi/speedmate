<?php
/**
 * PHPStan bootstrap file
 * Defines constants and stubs for PHPStan analysis
 */

// Define missing WordPress constants
if (!defined('SPEEDMATE_PATH')) {
    define('SPEEDMATE_PATH', __DIR__ . '/../');
}

if (!defined('COOKIEHASH')) {
    define('COOKIEHASH', 'test_hash');
}

if (!defined('SPEEDMATE_CACHE_DIR')) {
    define('SPEEDMATE_CACHE_DIR', '/tmp/speedmate-cache');
}

if (!defined('SPEEDMATE_OPTION_KEY')) {
    define('SPEEDMATE_OPTION_KEY', 'speedmate_settings');
}
