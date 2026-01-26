<?php

declare(strict_types=1);

namespace SpeedMate\Cache;

use SpeedMate\Utils\Settings;

/**
 * Determines if a request should be cached.
 *
 * @package SpeedMate\Cache
 * @since 0.4.0
 */
final class CachePolicy
{
    /**
     * Check if current request should be cached.
     *
     * @return bool
     */
    public function is_cacheable(): bool
    {
        if (is_admin() || is_user_logged_in()) {
            return false;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET') {
            return false;
        }

        if (!empty($_SERVER['QUERY_STRING']) && !$this->is_warm_request()) {
            return false;
        }

        if (is_feed() || is_trackback() || is_preview() || is_search()) {
            return false;
        }

        // Check URL exclusions
        if ($this->is_excluded_url()) {
            return false;
        }

        // Check cookie exclusions
        if ($this->has_excluded_cookies()) {
            return false;
        }

        $settings = Settings::get();
        $mode = $settings['mode'] ?? 'disabled';

        if ($mode === 'disabled') {
            return false;
        }

        return true;
    }

    /**
     * Check if URL is excluded from caching.
     *
     * @return bool
     */
    public function is_excluded_url(): bool
    {
        $settings = Settings::get();
        $patterns = $settings['cache_exclude_urls'] ?? [];
        
        if (empty($patterns)) {
            return false;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if request has excluded cookies.
     *
     * @return bool
     */
    public function has_excluded_cookies(): bool
    {
        $settings = Settings::get();
        $cookies = $settings['cache_exclude_cookies'] ?? [];
        
        if (empty($cookies)) {
            return false;
        }

        foreach ($cookies as $cookie_name) {
            if (isset($_COOKIE[$cookie_name])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if request is a warming request.
     *
     * @return bool
     */
    public function is_warm_request(): bool
    {
        $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
        return $query === 'speedmate_warm=1';
    }
}
