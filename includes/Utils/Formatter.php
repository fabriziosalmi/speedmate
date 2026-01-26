<?php
/**
 * Formatting utilities for SpeedMate.
 *
 * @package SpeedMate\Utils
 */

declare(strict_types=1);

namespace SpeedMate\Utils;

/**
 * Formatting utility class for common display transformations.
 *
 * Provides centralized formatting functions to avoid duplication:
 * - Byte size formatting (B, KB, MB, GB, TB)
 * - Duration formatting (milliseconds to minutes/hours)
 * - Localized number formatting
 *
 * Previously duplicated in AdminRenderer and used by AdminBar.
 *
 * Usage:
 *   echo Formatter::format_bytes(1048576); // "1.00 MB"
 *   echo Formatter::format_duration(185000); // "3 min"
 *   echo Formatter::format_duration(3725000); // "1 h 2 min"
 *
 * @package SpeedMate\Utils
 * @since 0.4.1
 */
final class Formatter
{
    /**
     * Format bytes to human-readable size.
     *
     * Converts bytes to appropriate unit (B, KB, MB, GB, TB):
     * - Uses 1024 base for calculations
     * - Shows 2 decimals for KB+ units
     * - Shows 0 decimals for bytes
     * - Uses localized number formatting
     *
     * Examples:
     *   512 → "512 B"
     *   1024 → "1.00 KB"
     *   1048576 → "1.00 MB"
     *   5368709120 → "5.00 GB"
     *
     * @param int $bytes Byte count to format.
     *
     * @return string Formatted size string with unit.
     */
    public static function format_bytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        $decimals = $power === 0 ? 0 : 2;
        return number_format_i18n($value, $decimals) . ' ' . $units[$power];
    }

    /**
     * Format milliseconds to human-readable duration.
     *
     * Converts milliseconds to minutes and hours:
     * - < 1 hour: "X min"
     * - ≥ 1 hour: "X h Y min"
     * - Returns "0 min" for zero or negative values
     *
     * Examples:
     *   30000 → "0 min" (30 seconds, rounds down)
     *   185000 → "3 min" (3 minutes 5 seconds)
     *   3725000 → "1 h 2 min" (1 hour 2 minutes)
     *   7380000 → "2 h 3 min" (2 hours 3 minutes)
     *
     * @param int $ms Duration in milliseconds.
     *
     * @return string Formatted duration string.
     */
    public static function format_duration(int $ms): string
    {
        if ($ms <= 0) {
            return '0 min';
        }

        $seconds = (int) floor($ms / 1000);
        $minutes = (int) floor($seconds / SPEEDMATE_SECONDS_PER_MINUTE);
        $hours = (int) floor($minutes / SPEEDMATE_MINUTES_PER_HOUR);
        $minutes = $minutes % SPEEDMATE_MINUTES_PER_HOUR;

        if ($hours > 0) {
            return sprintf('%d h %d min', $hours, $minutes);
        }

        return sprintf('%d min', $minutes);
    }
}
