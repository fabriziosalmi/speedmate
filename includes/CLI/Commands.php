<?php

declare(strict_types=1);

namespace SpeedMate\CLI;

use SpeedMate\Cache\StaticCache;
use SpeedMate\Cache\TrafficWarmer;
use SpeedMate\Utils\Stats;
use SpeedMate\Utils\GarbageCollector;
use SpeedMate\Utils\Settings;

/**
 * WP-CLI commands for SpeedMate cache and performance management.
 *
 * Commands:
 * - flush: Clear all cached pages
 * - warm: Run cache warming process
 * - stats: Display cache statistics
 * - gc: Run garbage collection
 *
 * Usage:
 *   wp speedmate <command> [options]
 *
 * Examples:
 *   wp speedmate flush
 *   wp speedmate warm
 *   wp speedmate stats --format=json
 *   wp speedmate gc
 *
 * @package SpeedMate\CLI
 * @since 0.2.0
 */
final class Commands
{
    /**
     * Flush all cached pages.
     *
     * Removes all cached HTML files and clears cache metadata.
     * Use when:
     * - Deploying new changes
     * - After plugin updates
     * - When cache is stale
     *
     * ## EXAMPLES
     *
     *     wp speedmate flush
     *
     * @when after_wp_load
     *
     * @param array $args Positional arguments (unused).
     * @param array $assoc_args Associative arguments (unused).
     *
     * @return void
     */
    public function flush($args, $assoc_args): void
    {
        StaticCache::instance()->flush_all();
        \WP_CLI::success('Cache flushed successfully.');
    }

    /**
     * Warm the cache by pre-generating pages.
     *
     * Runs the traffic-based cache warming process:
     * 1. Identifies most-visited uncached pages
     * 2. Generates cache for top pages
     * 3. Respects warmer_max_urls setting (default 20)
     *
     * Use when:
     * - After flushing cache
     * - Before high-traffic periods
     * - Proactive cache population
     *
     * ## EXAMPLES
     *
     *     wp speedmate warm
     *
     * @when after_wp_load
     *
     * @param array $args Positional arguments (unused).
     * @param array $assoc_args Associative arguments (unused).
     *
     * @return void
     */
    public function warm($args, $assoc_args): void
    {
        TrafficWarmer::instance()->run();
        \WP_CLI::success('Cache warming completed.');
    }

    /**
     * Display cache and performance statistics.
     *
     * Shows:
     * - Cache hits and misses
     * - Hit rate percentage
     * - Total requests
     * - LCP images detected (if enabled)
     *
     * Supports JSON output for automation:
     *   wp speedmate stats --format=json
     *
     * ## EXAMPLES
     *
     *     wp speedmate stats
     *     wp speedmate stats --format=json
     *
     * @when after_wp_load
     *
     * @param array $args Positional arguments (unused).
     * @param array $assoc_args Associative arguments (unused).
     *
     * @return void
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
     * Run garbage collection to clean database.
     *
     * Cleans:
     * - Spam comments (if gc_spam enabled)
     * - Post revisions (if gc_revisions enabled)
     * - Expired transients (if gc_transients enabled)
     * - Orphaned post metadata (if orphan_meta enabled)
     *
     * Respects user settings for each cleanup type.
     * Use during off-peak hours for optimal performance.
     *
     * ## EXAMPLES
     *
     *     wp speedmate gc
     *
     * @when after_wp_load
     *
     * @param array $args Positional arguments (unused).
     * @param array $assoc_args Associative arguments (unused).
     *
     * @return void
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
