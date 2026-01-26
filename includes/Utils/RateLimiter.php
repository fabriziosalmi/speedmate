<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

final class RateLimiter
{
    /**
     * Check if an action is allowed based on rate limiting.
     *
     * @param string $key Unique identifier for the rate limit bucket
     * @param int $limit Maximum number of actions allowed in the window
     * @param int $window_seconds Time window in seconds
     * @return bool True if action is allowed, false if rate limit exceeded
     */
    public static function allow(string $key, int $limit, int $window_seconds): bool
    {
        // No limit means always allow
        if ($limit <= 0) {
            return true;
        }

        // Validate input
        if (empty($key) || $window_seconds <= 0) {
            return false;
        }

        // Sanitize key to prevent cache poisoning
        $key = 'speedmate_rl_' . md5($key);

        $bucket = get_transient($key);
        $current_time = time();

        // Initialize or reset bucket
        if (!is_array($bucket) || !isset($bucket['count'], $bucket['reset'])) {
            $bucket = [
                'count' => 0,
                'reset' => $current_time + $window_seconds,
            ];
        }

        // Reset if window expired
        if ($current_time > (int) $bucket['reset']) {
            $bucket = [
                'count' => 0,
                'reset' => $current_time + $window_seconds,
            ];
        }

        // Check limit
        if ((int) $bucket['count'] >= $limit) {
            return false;
        }

        // Increment and save
        $bucket['count'] = (int) $bucket['count'] + 1;
        
        // Use longer expiration to handle clock skew
        set_transient($key, $bucket, $window_seconds + 60);

        return true;
    }

    /**
     * Clear rate limit for a specific key.
     *
     * @param string $key The rate limit key to clear
     * @return bool True on success
     */
    public static function clear(string $key): bool
    {
        $key = 'speedmate_rl_' . md5($key);
        return delete_transient($key);
    }
}
