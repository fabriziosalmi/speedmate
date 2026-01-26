<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

use SpeedMate\Utils\Logger;

/**
 * WordPress Filesystem API wrapper with automatic initialization.
 *
 * Provides simplified access to WP_Filesystem functions:
 * - Automatic WP_Filesystem initialization
 * - Type-safe returns
 * - Graceful error handling
 * - Recursive directory creation
 *
 * Benefits:
 * - Proper file permissions via WordPress standards
 * - Compatible with alternative filesystem methods (FTP, SSH)
 * - Respects WordPress filesystem abstraction
 *
 * Usage:
 *   Filesystem::put_contents('/path/to/file.html', '<html>...');
 *   $content = Filesystem::get_contents('/path/to/file.html');
 *   Filesystem::delete('/path/to/cache', true);
 *
 * @package SpeedMate\Utils
 * @since 0.1.0
 */
final class Filesystem
{
    private static bool $initialized = false;

    /**
     * Initialize WordPress Filesystem API.
     *
     * Lazy initialization with caching:
     * - Loads WP_Filesystem() if not already loaded
     * - Caches initialization state to avoid repeated calls
     * - Logs warning on failure
     *
     * WordPress will use best available method:
     * - Direct filesystem access (if permissions allow)
     * - FTP (if configured)
     * - SSH (if configured)
     *
     * @return bool True if filesystem initialized successfully, false otherwise.
     */
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

    /**
     * Write contents to file with automatic directory creation.
     *
     * Features:
     * - Creates parent directories recursively (0755 permissions)
     * - Uses FS_CHMOD_FILE for proper file permissions
     * - Atomic write operation
     *
     * Use cases:
     * - Writing cache files
     * - Saving generated HTML
     * - Exporting configuration
     *
     * @param string $path     Absolute file path.
     * @param string $contents File contents to write.
     *
     * @return bool True on success, false on failure.
     */
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

    /**
     * Read file contents safely.
     *
     * Returns empty string if:
     * - Filesystem initialization fails
     * - File doesn't exist
     * - Read operation fails
     * - Non-string result (edge case)
     *
     * Use cases:
     * - Reading cache files
     * - Loading templates
     * - Importing configuration
     *
     * @param string $path Absolute file path.
     *
     * @return string File contents or empty string on failure.
     */
    public static function get_contents(string $path): string
    {
        global $wp_filesystem;

        if (!self::init()) {
            return '';
        }

        if (!$wp_filesystem->exists($path)) {
            return '';
        }

        $contents = $wp_filesystem->get_contents($path);
        return is_string($contents) ? $contents : '';
    }

    /**
     * Check if file or directory exists.
     *
     * Checks via WordPress Filesystem API.
     * Returns false if initialization fails.
     *
     * Use cases:
     * - Validate cache file exists before reading
     * - Check directory before operations
     * - Conditional file creation
     *
     * @param string $path Absolute path to check.
     *
     * @return bool True if exists, false otherwise.
     */
    public static function exists(string $path): bool
    {
        global $wp_filesystem;

        if (!self::init()) {
            return false;
        }

        return (bool) $wp_filesystem->exists($path);
    }

    /**
     * Delete file or directory.
     *
     * Options:
     * - $recursive=false: Delete single file
     * - $recursive=true: Delete directory and all contents
     *
     * Use cases:
     * - Clearing cache files
     * - Removing expired data
     * - Cleanup operations
     *
     * Warning: Recursive deletion is permanent!
     *
     * @param string $path      Absolute path to delete.
     * @param bool   $recursive Whether to delete directories recursively.
     *
     * @return bool True on success, false on failure.
     */
    public static function delete(string $path, bool $recursive = false): bool
    {
        global $wp_filesystem;

        if (!self::init()) {
            return false;
        }

        return (bool) $wp_filesystem->delete($path, $recursive);
    }
}
