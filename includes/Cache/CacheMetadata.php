<?php

declare(strict_types=1);

namespace SpeedMate\Cache;

use SpeedMate\Utils\Logger;

/**
 * Manages cache path generation and validation.
 *
 * @package SpeedMate\Cache
 * @since 0.4.0
 */
final class CacheMetadata
{
    /**
     * Get cache file path for current request.
     *
     * @return string Cache file path or empty string if invalid.
     */
    public function get_cache_path(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        // Security: Remove query string and sanitize path
        $uri = strtok($uri, '?') ?: '/';
        // Remove any potential directory traversal attempts
        $uri = str_replace(['../', '..\\'], '', $uri);
        // Sanitize and normalize the path
        $uri = trim($uri, '/');

        // Additional security: ensure no absolute paths or null bytes
        if (strpos($uri, chr(0)) !== false || strpos($uri, DIRECTORY_SEPARATOR) === 0) {
            Logger::log('warning', 'invalid_cache_path_attempt', ['uri' => $uri]);
            return '';
        }

        // Security: Reject paths starting with dot (hidden files/directories)
        if (strpos($uri, '.') === 0) {
            Logger::log('warning', 'dotfile_cache_path_attempt', ['uri' => $uri]);
            return '';
        }

        // Security: Validate path contains only safe characters
        if (preg_match('/[^a-zA-Z0-9\/\-_]/', $uri) && $uri !== '') {
            Logger::log('warning', 'invalid_characters_in_path', ['uri' => $uri]);
            return '';
        }

        $path = trailingslashit(SPEEDMATE_CACHE_DIR . '/' . $host . '/' . $uri);

        return $path . 'index.html';
    }

    /**
     * Get cache file path for specific URL.
     *
     * @param string $url URL to get cache path for.
     * @return string Cache file path or empty string if invalid.
     */
    public function get_cache_path_for_url(string $url): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!is_string($path)) {
            return '';
        }

        // Security: Remove directory traversal attempts
        $path = str_replace(['../', '..\\'], '', $path);
        $path = trim($path, '/');

        // Additional security: ensure no null bytes
        if (strpos($path, chr(0)) !== false) {
            Logger::log('warning', 'invalid_cache_path_for_url', ['url' => $url]);
            return '';
        }

        // Security: Reject paths starting with dot
        if (strpos($path, '.') === 0) {
            Logger::log('warning', 'dotfile_cache_path_for_url', ['url' => $url]);
            return '';
        }

        // Security: Validate path contains only safe characters
        if (preg_match('/[^a-zA-Z0-9\/\-_]/', $path) && $path !== '') {
            Logger::log('warning', 'invalid_characters_in_url_path', ['url' => $url]);
            return '';
        }

        $cache_path = trailingslashit(SPEEDMATE_CACHE_DIR . '/' . $host . '/' . $path);

        return $cache_path . 'index.html';
    }

    /**
     * Get cache directory path for URL (for deletion).
     *
     * @param string $url URL to get directory for.
     * @return string Directory path.
     */
    public function get_cache_dir_for_url(string $url): string
    {
        $path = $this->get_cache_path_for_url($url);
        if ($path === '') {
            return '';
        }

        return dirname($path);
    }
}
