<?php
/**
 * Plugin Name: SpeedMate
 * Description: Free WordPress performance with static cache, automation and Beast mode.
 * Version: 0.3.0
 * Author: Fabrizio Salmi
 * Text Domain: speedmate
 * Requires PHP: 7.4
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('SPEEDMATE_VERSION', '0.3.0');
define('SPEEDMATE_PATH', plugin_dir_path(__FILE__));
define('SPEEDMATE_URL', plugin_dir_url(__FILE__));

define('SPEEDMATE_OPTION_KEY', 'speedmate_settings');
define('SPEEDMATE_STATS_KEY', 'speedmate_stats');

define('SPEEDMATE_CACHE_DIR', WP_CONTENT_DIR . '/cache/speedmate');

define('SPEEDMATE_MIN_PHP', '7.4');

require_once SPEEDMATE_PATH . 'includes/Plugin.php';
require_once SPEEDMATE_PATH . 'includes/Utils/Filesystem.php';
require_once SPEEDMATE_PATH . 'includes/Utils/GarbageCollector.php';
require_once SPEEDMATE_PATH . 'includes/Cache/StaticCache.php';
require_once SPEEDMATE_PATH . 'includes/Cache/TrafficWarmer.php';

function speedmate(): SpeedMate\Plugin
{
    return SpeedMate\Plugin::instance();
}

add_action('plugins_loaded', 'speedmate');

register_activation_hook(__FILE__, ['SpeedMate\\Cache\\StaticCache', 'activate']);
register_deactivation_hook(__FILE__, ['SpeedMate\\Cache\\StaticCache', 'deactivate']);
register_activation_hook(__FILE__, ['SpeedMate\\Cache\\TrafficWarmer', 'activate']);
register_deactivation_hook(__FILE__, ['SpeedMate\\Cache\\TrafficWarmer', 'deactivate']);
register_activation_hook(__FILE__, ['SpeedMate\\Utils\\GarbageCollector', 'activate']);
register_deactivation_hook(__FILE__, ['SpeedMate\\Utils\\GarbageCollector', 'deactivate']);
