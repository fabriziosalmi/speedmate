<?php

declare(strict_types=1);

namespace SpeedMate\CLI;

use SpeedMate\Cache\StaticCache;
use SpeedMate\Cache\TrafficWarmer;
use SpeedMate\Utils\Stats;
use SpeedMate\Utils\GarbageCollector;
use SpeedMate\Utils\Settings;

/**
 * WP-CLI commands for SpeedMate
 */
final class Commands
{
    /**
     * Flush all cached pages
     *
     * ## EXAMPLES
     *
     *     wp speedmate flush
     *
     * @when after_wp_load
     */
    public function flush($args, $assoc_args): void
    {
        StaticCache::instance()->flush_all();
        \WP_CLI::success('Cache flushed successfully.');
    }

    /**
     * Warm the cache
     *
     * ## EXAMPLES
     *
     *     wp speedmate warm
     *
     * @when after_wp_load
     */
    public function warm($args, $assoc_args): void
    {
        TrafficWarmer::instance()->run();
        \WP_CLI::success('Cache warming completed.');
    }

    /**
     * Display statistics
     *
     * ## EXAMPLES
     *
     *     wp speedmate stats
     *
     * @when after_wp_load
     */
    public function stats($args, $assoc_args): void
    {
        $stats = Stats::get();
        
        $items = [];
        foreach ($stats as $key => $value) {
            $items[] = [
                'Metric' => $key,
                'Value' => is_numeric($value) ? number_format((float) $value) : $value,
            ];
        }

        \WP_CLI\Utils\format_items('table', $items, ['Metric', 'Value']);
    }

    /**
     * Run garbage collector
     *
     * ## EXAMPLES
     *
     *     wp speedmate gc
     *
     * @when after_wp_load
     */
    public function gc($args, $assoc_args): void
    {
        GarbageCollector::instance()->cleanup();
        \WP_CLI::success('Garbage collection completed.');
    }

    /**
     * Show configuration information
     *
     * ## EXAMPLES
     *
     *     wp speedmate info
     *
     * @when after_wp_load
     */
    public function info($args, $assoc_args): void
    {
        $settings = Settings::get();
        $cache = StaticCache::instance();
        
        \WP_CLI::line('SpeedMate Configuration:');
        \WP_CLI::line('');
        \WP_CLI::line('Mode: ' . ($settings['mode'] ?? 'disabled'));
        \WP_CLI::line('Cache TTL: ' . ($settings['cache_ttl'] ?? 0) . ' seconds');
        \WP_CLI::line('Homepage TTL: ' . ($settings['cache_ttl_homepage'] ?? 0) . ' seconds');
        \WP_CLI::line('Cached Pages: ' . $cache->get_cached_pages_count());
        \WP_CLI::line('Cache Size: ' . size_format($cache->get_cache_size_bytes()));
        \WP_CLI::line('Logging: ' . (($settings['logging_enabled'] ?? false) ? 'enabled' : 'disabled'));
    }
}
