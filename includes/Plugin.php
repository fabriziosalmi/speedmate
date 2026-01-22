<?php

declare(strict_types=1);

namespace SpeedMate;

use SpeedMate\Admin\Admin;
use SpeedMate\Cache\StaticCache;

final class Plugin
{
    private static ?Plugin $instance = null;

    private function __construct()
    {
    }

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->boot();
        }

        return self::$instance;
    }

    private function boot(): void
    {
        $this->load_dependencies();
        $this->register_hooks();
    }

    private function load_dependencies(): void
    {
        require_once SPEEDMATE_PATH . 'includes/Utils/Filesystem.php';
        require_once SPEEDMATE_PATH . 'includes/Utils/Stats.php';
        require_once SPEEDMATE_PATH . 'includes/Utils/GarbageCollector.php';
        require_once SPEEDMATE_PATH . 'includes/Cache/StaticCache.php';
        require_once SPEEDMATE_PATH . 'includes/Cache/TrafficWarmer.php';
        require_once SPEEDMATE_PATH . 'includes/Media/MediaOptimizer.php';
        require_once SPEEDMATE_PATH . 'includes/Perf/AutoLCP.php';
        require_once SPEEDMATE_PATH . 'includes/Perf/BeastMode.php';
        require_once SPEEDMATE_PATH . 'includes/Cache/DynamicFragments.php';
        require_once SPEEDMATE_PATH . 'includes/Admin/Admin.php';
    }

    private function register_hooks(): void
    {
        add_action('init', [$this, 'register_settings']);

        if (is_admin()) {
            Admin::instance();
        }

        StaticCache::instance();
        \SpeedMate\Cache\TrafficWarmer::instance();
        \SpeedMate\Media\MediaOptimizer::instance();
        \SpeedMate\Perf\AutoLCP::instance();
        \SpeedMate\Perf\BeastMode::instance();
        \SpeedMate\Cache\DynamicFragments::instance();
        \SpeedMate\Utils\GarbageCollector::instance();
    }

    public function register_settings(): void
    {
        if (get_option(SPEEDMATE_OPTION_KEY) === false) {
            add_option(SPEEDMATE_OPTION_KEY, [
                'mode' => 'disabled',
                'beast_whitelist' => [],
                'beast_blacklist' => [],
                'beast_apply_all' => false,
            ], '', false);
        }

        if (get_option(SPEEDMATE_STATS_KEY) === false) {
            add_option(SPEEDMATE_STATS_KEY, [
                'warmed_pages' => 0,
                'lcp_preloads' => 0,
                'time_saved_ms' => 0,
                'avg_uncached_ms' => 0,
                'avg_cached_ms' => 50,
                'week_key' => '',
            ], '', false);
        }
    }
}
