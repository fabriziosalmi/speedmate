<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

final class Settings
{
    private static ?array $settings = null;

    public static function get(): array
    {
        if (self::$settings === null) {
            $value = get_option(SPEEDMATE_OPTION_KEY, []);
            self::$settings = is_array($value) ? $value : [];
        }

        return self::$settings;
    }

    public static function refresh(): void
    {
        self::$settings = null;
        self::get();
    }

    public static function get_value(string $key, $default = null)
    {
        $settings = self::get();

        return $settings[$key] ?? $default;
    }
}
