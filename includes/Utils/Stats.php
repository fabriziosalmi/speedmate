<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

/**
 * Cache and performance statistics tracking.
 *
 * Features:
 * - Weekly metrics (rolling week basis)
 * - Atomic increments (ON DUPLICATE KEY UPDATE)
 * - Rolling averages for response times
 * - Static caching to avoid repeated table checks
 * - Auto-table creation on first use
 *
 * Metrics tracked:
 * - cache_hits: Total cache hits
 * - cache_misses: Total cache misses
 * - avg_uncached_ms: Average uncached response time
 * - lcp_images_detected: LCP images found (if enabled)
 *
 * Storage:
 * - Table: wp_speedmate_stats
 * - Week key format: ISO 8601 week (YYYYWW, e.g., 202604)
 * - Unique index: (metric_name, week_key)
 *
 * Usage:
 *   Stats::increment('cache_hits');
 *   Stats::record_uncached_time(350);
 *   $stats = Stats::get();
 *
 * @package SpeedMate\Utils
 * @since 0.1.0
 */
final class Stats
{
    private const TABLE_NAME = 'speedmate_stats';
    
    /** @var bool|null Cache for table existence check within request */
    private static $table_exists = null;

    /**
     * Create stats table if it doesn't exist.
     *
     * Table structure:
     * - id: Auto-increment primary key
     * - metric_name: Statistic identifier (varchar 50)
     * - metric_value: Numeric value (bigint)
     * - week_key: ISO week (varchar 10, format: YYYYWW)
     * - updated_at: Last update timestamp
     *
     * Indexes:
     * - UNIQUE: (metric_name, week_key) - Prevents duplicates
     * - KEY: metric_name - Fast metric lookups
     * - KEY: week_key - Fast week filtering
     *
     * Uses dbDelta for safe schema updates.
     *
     * @return void
     */
    public static function create_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            metric_name varchar(50) NOT NULL,
            metric_value bigint(20) NOT NULL DEFAULT 0,
            week_key varchar(10) NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY metric_week (metric_name, week_key),
            KEY metric_name (metric_name),
            KEY week_key (week_key)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        // Mark as existing after creation
        self::$table_exists = true;
    }

    /**
     * Check if stats table exists with static caching.
     * Cached within request to avoid repeated DB queries.
     *
     * @return bool True if table exists.
     */
    private static function table_exists(): bool
    {
        if (self::$table_exists !== null) {
            return self::$table_exists;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $escaped_table = $wpdb->esc_like($table_name);
        
        self::$table_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $escaped_table)) === $table_name);
        
        return self::$table_exists;
    }

    /**
     * Get current week statistics.
     *
     * Returns:
     * - cache_hits: Total hits this week
     * - cache_misses: Total misses this week
     * - hit_rate: Calculated percentage (0-100)
     * - avg_uncached_ms: Average uncached response time
     * - lcp_images_detected: LCP images found (if enabled)
     * - week_key: Current ISO week (YYYYWW)
     *
     * If table doesn't exist, returns defaults (all zeros).
     * Metrics reset automatically at week boundary.
     *
     * @return array Weekly statistics with calculated hit_rate.
     */
    public static function get(): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $week_key = gmdate('oW');

        // Use cached table existence check
        if (!self::table_exists()) {
            return self::get_defaults();
        }

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT metric_name, metric_value FROM {$table_name} WHERE week_key = %s",
                $week_key
            ),
            ARRAY_A
        );

        $stats = self::get_defaults();
        $stats['week_key'] = $week_key;

        if (is_array($results)) {
            foreach ($results as $row) {
                if (isset($row['metric_name'], $row['metric_value'])) {
                    $stats[$row['metric_name']] = (int) $row['metric_value'];
                }
            }
        }

        return $stats;
    }

    /**
     * Atomically increment a metric counter.
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for atomic increments.
     * Creates table if it doesn't exist.
     *
     * Common metrics:
     * - 'cache_hits': Successful cache retrievals
     * - 'cache_misses': Cache not found or expired
     * - 'lcp_images_detected': LCP images identified
     *
     * Step value is clamped to minimum of 1.
     *
     * @param string $key  Metric name to increment.
     * @param int    $step Increment amount (default 1, min 1).
     *
     * @return void
     */
    public static function increment(string $key, int $step = 1): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $week_key = gmdate('oW');

        // Use cached table existence check, create if needed
        if (!self::table_exists()) {
            self::create_table();
        }

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table_name} (metric_name, metric_value, week_key)
                VALUES (%s, %d, %s)
                ON DUPLICATE KEY UPDATE metric_value = metric_value + %d",
                $key,
                max(1, $step),
                $week_key,
                max(1, $step)
            )
        );
    }

    /**
     * Record uncached response time with rolling average.
     *
     * Maintains a rolling average of uncached page generation times:
     * - First value: Direct assignment
     * - Subsequent: (old_avg * 9 + new_value) / 10
     *
     * This gives more weight to historical data while incorporating new samples.
     * Also ensures avg_cached_ms exists with default value for consistency.
     *
     * Used to calculate cache performance benefit:
     *   speedup = (avg_uncached_ms - avg_cached_ms) / avg_uncached_ms * 100%
     *
     * @param int $ms Uncached page generation time in milliseconds.
     *
     * @return void
     */
    public static function record_uncached_time(int $ms): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $week_key = gmdate('oW');

        // Check if table exists, create if not
        $escaped_table = $wpdb->esc_like($table_name);
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $escaped_table)) !== $table_name) {
            self::create_table();
        }

        // Get current average
        $current = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT metric_value FROM {$table_name} WHERE metric_name = %s AND week_key = %s",
                'avg_uncached_ms',
                $week_key
            )
        );

        if ($current === null || (int) $current <= 0) {
            $new_value = $ms;
        } else {
            // Rolling average: (old * 9 + new) / 10
            $new_value = (int) round(((int) $current * 9 + $ms) / 10);
        }

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table_name} (metric_name, metric_value, week_key)
                VALUES (%s, %d, %s)
                ON DUPLICATE KEY UPDATE metric_value = %d",
                'avg_uncached_ms',
                $new_value,
                $week_key,
                $new_value
            )
        );

        // Ensure avg_cached_ms has a default value
        $cached_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT metric_value FROM {$table_name} WHERE metric_name = %s AND week_key = %s",
                'avg_cached_ms',
                $week_key
            )
        );

        if ($cached_exists === null) {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table_name} (metric_name, metric_value, week_key)
                    VALUES (%s, %d, %s)
                    ON DUPLICATE KEY UPDATE metric_value = %d",
                    'avg_cached_ms',
                    50,
                    $week_key,
                    50
                )
            );
        }
    }
    /**
     * Calculate and accumulate time saved by caching.
     *
     * Calculates time saved for a single cache hit:
     *   time_saved = avg_uncached_ms - avg_cached_ms
     *
     * Accumulates total time saved this week.
     * Only increments if uncached time > cached time (sanity check).
     *
     * Defaults:
     * - avg_uncached_ms: 0 (if not recorded)
     * - avg_cached_ms: 50ms (default cache read time)
     *
     * Used to show users tangible performance benefit:
     *   \"Cache saved X seconds this week\"
     *
     * @return void
     */    public static function add_time_saved_from_hit(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $week_key = gmdate('oW');

        // Check if table exists, create if not
        $escaped_table = $wpdb->esc_like($table_name);
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $escaped_table)) !== $table_name) {
            self::create_table();
        }

        // Get both averages
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT metric_name, metric_value FROM {$table_name}
                WHERE metric_name IN (%s, %s) AND week_key = %s",
                'avg_uncached_ms',
                'avg_cached_ms',
                $week_key
            ),
            ARRAY_A
        );

        $uncached = 0;
        $cached = 50;

        if (is_array($results)) {
            foreach ($results as $row) {
                if ($row['metric_name'] === 'avg_uncached_ms') {
                    $uncached = (int) $row['metric_value'];
                } elseif ($row['metric_name'] === 'avg_cached_ms') {
                    $cached = (int) $row['metric_value'];
                }
            }
        }

        if ($uncached <= $cached) {
            return;
        }

        $time_saved = $uncached - $cached;

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table_name} (metric_name, metric_value, week_key)
                VALUES (%s, %d, %s)
                ON DUPLICATE KEY UPDATE metric_value = metric_value + %d",
                'time_saved_ms',
                $time_saved,
                $week_key,
                $time_saved
            )
        );
    }

    /**
     * Get default statistics values.
     *
     * Returns baseline statistics when table doesn't exist
     * or no data recorded for current week.
     *
     * Defaults:
     * - warmed_pages: 0 - Pages warmed by TrafficWarmer
     * - lcp_preloads: 0 - LCP images preloaded by AutoLCP
     * - time_saved_ms: 0 - Cumulative time saved by caching
     * - avg_uncached_ms: 0 - Average uncached response time
     * - avg_cached_ms: 50 - Estimated cache read time
     * - week_key: '' - Current ISO week (set by get())
     *
     * @return array Default statistics structure.
     */
    private static function get_defaults(): array
    {
        return [
            'warmed_pages' => 0,
            'lcp_preloads' => 0,
            'time_saved_ms' => 0,
            'avg_uncached_ms' => 0,
            'avg_cached_ms' => 50,
            'week_key' => '',
        ];
    }
}
