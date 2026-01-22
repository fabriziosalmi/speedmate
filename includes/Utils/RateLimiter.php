<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

final class RateLimiter
{
    public static function allow(string $key, int $limit, int $window_seconds): bool
    {
        if ($limit <= 0) {
            return true;
        }

        $bucket = get_transient($key);
        if (!is_array($bucket)) {
            $bucket = [
                'count' => 0,
                'reset' => time() + $window_seconds,
            ];
        }

        if (time() > (int) $bucket['reset']) {
            $bucket = [
                'count' => 0,
                'reset' => time() + $window_seconds,
            ];
        }

        if ((int) $bucket['count'] >= $limit) {
            return false;
        }

        $bucket['count'] = (int) $bucket['count'] + 1;
        set_transient($key, $bucket, $window_seconds);

        return true;
    }
}
