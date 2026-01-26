<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

/**
 * Database migration utility for SpeedMate upgrades.
 *
 * v0.2.0 Migration:
 * - Moved stats from wp_options to custom table
 * - Improved performance (direct SQL vs serialized option)
 * - Better scalability for high-traffic sites
 *
 * Features:
 * - Idempotent migrations (safe to run multiple times)
 * - Backup creation before cleanup
 * - Automatic table creation
 *
 * Process:
 * 1. Create speedmate_stats table
 * 2. Migrate data from wp_options
 * 3. Keep backup for safety
 * 4. Clean up after verification
 *
 * Usage:
 *   Migration::migrate_stats_to_table(); // During activation
 *   Migration::cleanup_old_stats(); // After verification
 *
 * @package SpeedMate\Utils
 * @since 0.2.0
 */
final class Migration
{
    /**
     * Migrate statistics from wp_options to custom table.
     *
     * Migration process:
     * 1. Ensure speedmate_stats table exists
     * 2. Check for old data in wp_options
     * 3. Migrate each metric to new table
     * 4. Create backup option for safety
     *
     * Migrated metrics:
     * - warmed_pages: Cache warming count
     * - lcp_preloads: LCP images detected
     * - time_saved_ms: Total time saved
     * - avg_uncached_ms: Average uncached time
     * - avg_cached_ms: Average cached time
     *
     * Safe to run multiple times (uses ON DUPLICATE KEY UPDATE).
     * Returns true if migration successful or nothing to migrate.
     *
     * @return bool True on success, false on failure.
     */
    public static function migrate_stats_to_table(): bool
    {
        // Ensure table exists
        Stats::create_table();

        // Check if old data exists
        $old_stats = get_option(SPEEDMATE_STATS_KEY, false);
        if ($old_stats === false || !is_array($old_stats)) {
            // Nothing to migrate
            return true;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'speedmate_stats';
        $week_key = isset($old_stats['week_key']) ? (string) $old_stats['week_key'] : gmdate('oW');

        // Migrate each stat
        $stats_to_migrate = [
            'warmed_pages',
            'lcp_preloads',
            'time_saved_ms',
            'avg_uncached_ms',
            'avg_cached_ms',
        ];

        foreach ($stats_to_migrate as $metric) {
            if (isset($old_stats[$metric])) {
                $value = (int) $old_stats[$metric];

                $wpdb->query(
                    $wpdb->prepare(
                        "INSERT INTO {$table_name} (metric_name, metric_value, week_key)
                        VALUES (%s, %d, %s)
                        ON DUPLICATE KEY UPDATE metric_value = %d",
                        $metric,
                        $value,
                        $week_key,
                        $value
                    )
                );
            }
        }

        // Keep old option as backup for one more week
        update_option(SPEEDMATE_STATS_KEY . '_backup', $old_stats, false);

        return true;
    }

    /**
     * Remove old stats data from wp_options after successful migration.
     *
     * Deletes:
     * - SPEEDMATE_STATS_KEY: Original stats option
     * - SPEEDMATE_STATS_KEY_backup: Safety backup
     *
     * Call this after verifying:
     * 1. Stats table has data
     * 2. Application working correctly
     * 3. Backup period expired (1+ week)
     *
     * Warning: Permanent deletion! Ensure migration successful first.
     *
     * @return void
     */
    public static function cleanup_old_stats(): void
    {
        delete_option(SPEEDMATE_STATS_KEY);
        delete_option(SPEEDMATE_STATS_KEY . '_backup');
    }
}
