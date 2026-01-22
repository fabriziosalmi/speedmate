<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

final class Stats
{
    private const TABLE_NAME = 'speedmate_stats';

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
    }

    public static function get(): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $week_key = gmdate('oW');

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
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

    public static function increment(string $key, int $step = 1): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $week_key = gmdate('oW');

        // Check if table exists, create if not
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
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

    public static function record_uncached_time(int $ms): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $week_key = gmdate('oW');

        // Check if table exists, create if not
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
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

    public static function add_time_saved_from_hit(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $week_key = gmdate('oW');

        // Check if table exists, create if not
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
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
