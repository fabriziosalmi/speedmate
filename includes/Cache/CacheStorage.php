<?php

declare(strict_types=1);

namespace SpeedMate\Cache;

use SpeedMate\Utils\Filesystem;
use SpeedMate\Utils\Logger;

/**
 * Handles file storage operations for static cache.
 *
 * @package SpeedMate\Cache
 * @since 0.4.0
 */
final class CacheStorage
{
    /**
     * Write content to cache file.
     *
     * @param string $path Cache file path.
     * @param string $contents Content to cache.
     * @param int $ttl Time-to-live in seconds.
     * @return bool Success status.
     */
    public function write(string $path, string $contents, int $ttl): bool
    {
        if ($path === '' || $contents === '') {
            return false;
        }

        try {
            if (!Filesystem::put_contents($path, $contents)) {
                Logger::log('warning', 'cache_write_failed', ['path' => $path]);
                return false;
            }

            // Write metadata file with TTL
            $meta = [
                'created' => time(),
                'ttl' => $ttl,
            ];
            $meta_path = $path . '.meta';
            
            if (!Filesystem::put_contents($meta_path, wp_json_encode($meta))) {
                Logger::log('warning', 'cache_meta_write_failed', ['path' => $meta_path]);
                // Don't return false - main cache file was written successfully
            }

            return true;
        } catch (\Exception $e) {
            Logger::log('error', 'cache_write_exception', [
                'path' => $path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Read content from cache file if valid.
     *
     * @param string $path Cache file path.
     * @return string|false Content or false if invalid/expired.
     */
    public function read(string $path)
    {
        if (!$this->exists($path)) {
            return false;
        }

        if (!$this->is_valid($path)) {
            return false;
        }

        return Filesystem::get_contents($path);
    }

    /**
     * Check if cache file exists.
     *
     * @param string $path Cache file path.
     * @return bool
     */
    public function exists(string $path): bool
    {
        return Filesystem::exists($path);
    }

    /**
     * Check if cache file is still valid (not expired).
     *
     * @param string $path Cache file path.
     * @return bool
     */
    public function is_valid(string $path): bool
    {
        $meta_path = $path . '.meta';
        if (!Filesystem::exists($meta_path)) {
            // No metadata means old cache file, consider invalid
            return false;
        }

        $meta_content = Filesystem::get_contents($meta_path);
        if ($meta_content === '') {
            return false;
        }

        $meta = json_decode($meta_content, true);
        if (!is_array($meta) || !isset($meta['created'], $meta['ttl'])) {
            return false;
        }

        $age = time() - (int) $meta['created'];
        return $age < (int) $meta['ttl'];
    }

    /**
     * Delete cache directory and its contents.
     *
     * @param string $dir Directory path.
     * @return void
     */
    public function delete(string $dir): void
    {
        if (!Filesystem::exists($dir)) {
            return;
        }

        if (strpos($dir, SPEEDMATE_CACHE_DIR) !== 0) {
            Logger::log('warning', 'invalid_delete_path', ['dir' => $dir]);
            return;
        }

        Filesystem::delete($dir, true);
    }

    /**
     * Get total size of cache in bytes.
     *
     * @return int Size in bytes.
     */
    public function get_size(): int
    {
        if (!is_dir(SPEEDMATE_CACHE_DIR)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(SPEEDMATE_CACHE_DIR, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Count cached pages (index.html files).
     *
     * @return int Number of cached pages.
     */
    public function count_pages(): int
    {
        if (!is_dir(SPEEDMATE_CACHE_DIR)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(SPEEDMATE_CACHE_DIR, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'index.html') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Ensure cache directory exists.
     * Creates directory by attempting to write a file then removing it.
     *
     * @return void
     */
    public function ensure_cache_dir(): void
    {
        if (!Filesystem::exists(SPEEDMATE_CACHE_DIR)) {
            try {
                $index_path = trailingslashit(SPEEDMATE_CACHE_DIR) . 'index.html';
                Filesystem::put_contents($index_path, "");
                Filesystem::delete($index_path);
            } catch (\Exception $e) {
                Logger::log('error', 'cache_dir_creation_exception', [
                    'dir' => SPEEDMATE_CACHE_DIR,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Flush all cached files.
     *
     * @return void
     */
    public function flush_all(): void
    {
        if (!Filesystem::exists(SPEEDMATE_CACHE_DIR)) {
            return;
        }

        Filesystem::delete(SPEEDMATE_CACHE_DIR, true);
    }
}
