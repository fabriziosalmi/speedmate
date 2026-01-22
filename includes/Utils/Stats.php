<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

final class Stats
{
    public static function get(): array
    {
        $stats = get_option(SPEEDMATE_STATS_KEY, []);

        if (!is_array($stats)) {
            return [
                'warmed_pages' => 0,
                'lcp_preloads' => 0,
                'time_saved_ms' => 0,
                'avg_uncached_ms' => 0,
                'avg_cached_ms' => 50,
                'week_key' => '',
            ];
        }

        return wp_parse_args($stats, [
            'warmed_pages' => 0,
            'lcp_preloads' => 0,
            'time_saved_ms' => 0,
            'avg_uncached_ms' => 0,
            'avg_cached_ms' => 50,
            'week_key' => '',
        ]);
    }

    public static function increment(string $key, int $step = 1): void
    {
        $stats = self::maybe_reset_week(self::get());
        $current = isset($stats[$key]) ? (int) $stats[$key] : 0;
        $stats[$key] = $current + max(1, $step);
        update_option(SPEEDMATE_STATS_KEY, $stats, false);
    }

    public static function record_uncached_time(int $ms): void
    {
        $stats = self::maybe_reset_week(self::get());
        $current = isset($stats['avg_uncached_ms']) ? (int) $stats['avg_uncached_ms'] : 0;
        if ($current <= 0) {
            $stats['avg_uncached_ms'] = $ms;
        } else {
            $stats['avg_uncached_ms'] = (int) round(($current * 9 + $ms) / 10);
        }

        if (!isset($stats['avg_cached_ms']) || (int) $stats['avg_cached_ms'] <= 0) {
            $stats['avg_cached_ms'] = 50;
        }

        update_option(SPEEDMATE_STATS_KEY, $stats, false);
    }

    public static function add_time_saved_from_hit(): void
    {
        $stats = self::maybe_reset_week(self::get());
        $uncached = isset($stats['avg_uncached_ms']) ? (int) $stats['avg_uncached_ms'] : 0;
        $cached = isset($stats['avg_cached_ms']) ? (int) $stats['avg_cached_ms'] : 50;

        if ($uncached <= $cached) {
            return;
        }

        $stats['time_saved_ms'] = (int) ($stats['time_saved_ms'] ?? 0) + ($uncached - $cached);
        update_option(SPEEDMATE_STATS_KEY, $stats, false);
    }

    private static function maybe_reset_week(array $stats): array
    {
        $current = gmdate('oW');
        $week_key = isset($stats['week_key']) ? (string) $stats['week_key'] : '';

        if ($week_key !== $current) {
            $stats['time_saved_ms'] = 0;
            $stats['week_key'] = $current;
        }

        return $stats;
    }
}
