<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

/**
 * Singleton trait for ensuring single instance of a class.
 *
 * Usage:
 * ```php
 * final class MyClass {
 *     use Singleton;
 *
 *     private function __construct() {
 *         // Initialization code
 *     }
 * }
 *
 * $instance = MyClass::instance();
 * ```
 *
 * @package SpeedMate\Utils
 * @since 0.4.0
 */
trait Singleton
{
    /**
     * Single instance of the class.
     *
     * @var static|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * Checks Container for test injection before creating instance.
     * Automatically calls register_hooks() if method exists.
     *
     * @return static
     */
    public static function instance()
    {
        // For test injection - only available when Container is loaded
        if (class_exists('\SpeedMate\Utils\Container')) {
            $override = Container::get(static::class);
            if ($override instanceof static) {
                return $override;
            }
        }

        if (self::$instance === null) {
            self::$instance = new static();

            // Auto-register hooks if method exists
            if (method_exists(self::$instance, 'register_hooks')) {
                self::$instance->register_hooks();
            }
        }

        return self::$instance;
    }

    /**
     * Prevent cloning of singleton instance.
     *
     * @throws \Exception
     */
    private function __clone()
    {
        throw new \Exception('Cannot clone singleton instance of ' . static::class);
    }

    /**
     * Prevent unserialization of singleton instance.
     *
     * @throws \Exception
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton instance of ' . static::class);
    }

    /**
     * Reset instance (for testing purposes only).
     *
     * @internal
     * @return void
     */
    public static function reset_instance(): void
    {
        self::$instance = null;
    }
}
