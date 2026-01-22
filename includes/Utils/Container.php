<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

final class Container
{
    /** @var array<string, object> */
    private static array $instances = [];

    public static function set(string $id, object $instance): void
    {
        self::$instances[$id] = $instance;
    }

    public static function get(string $id): ?object
    {
        return self::$instances[$id] ?? null;
    }
}
