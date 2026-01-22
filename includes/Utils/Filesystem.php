<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

use SpeedMate\Utils\Logger;

final class Filesystem
{
    private static bool $initialized = false;

    public static function init(): bool
    {
        if (self::$initialized) {
            return true;
        }

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $result = WP_Filesystem();
        if ($result) {
            self::$initialized = true;
        }

        if (!$result) {
            Logger::log('warning', 'filesystem_init_failed');
        }

        return $result;
    }

    public static function put_contents(string $path, string $contents): bool
    {
        global $wp_filesystem;

        if (!self::init()) {
            return false;
        }

        $dir = dirname($path);
        if (!$wp_filesystem->is_dir($dir)) {
            $wp_filesystem->mkdir($dir, 0755, true);
        }

        return (bool) $wp_filesystem->put_contents($path, $contents, FS_CHMOD_FILE);
    }

    public static function exists(string $path): bool
    {
        global $wp_filesystem;

        if (!self::init()) {
            return false;
        }

        return (bool) $wp_filesystem->exists($path);
    }

    public static function delete(string $path, bool $recursive = false): bool
    {
        global $wp_filesystem;

        if (!self::init()) {
            return false;
        }

        return (bool) $wp_filesystem->delete($path, $recursive);
    }
}
