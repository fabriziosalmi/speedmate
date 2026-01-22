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
            ];
        }

        return wp_parse_args($stats, [
            'warmed_pages' => 0,
            'lcp_preloads' => 0,
        ]);
    }

    public static function increment(string $key, int $step = 1): void
    {
        $stats = self::get();
        $current = isset($stats[$key]) ? (int) $stats[$key] : 0;
        $stats[$key] = $current + max(1, $step);
        update_option(SPEEDMATE_STATS_KEY, $stats, false);
    }
}
