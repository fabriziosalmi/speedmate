<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

final class Logger
{
    public static function enabled(): bool
    {
        $enabled = (bool) Settings::get_value('logging_enabled', false);

        return (bool) apply_filters('speedmate_logging_enabled', $enabled);
    }

    public static function log(string $level, string $event, array $context = []): void
    {
        if (!self::enabled()) {
            return;
        }

        $payload = [
            'level' => $level,
            'event' => $event,
            'context' => $context,
            'ts' => gmdate('c'),
        ];

        error_log(wp_json_encode($payload));
    }
}
