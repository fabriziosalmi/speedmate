<?php

declare(strict_types=1);

namespace SpeedMate\Cache;

use SpeedMate\Utils\Settings;

/**
 * Manages cache TTL (time-to-live) based on content type.
 *
 * @package SpeedMate\Cache
 * @since 0.4.0
 */
final class CacheTTLManager
{
    /**
     * Get cache TTL for current request.
     *
     * @return int TTL in seconds.
     */
    public function get_ttl(): int
    {
        $settings = Settings::get();

        // Determine content type and return appropriate TTL
        if (is_front_page()) {
            return (int) ($settings['cache_ttl_homepage'] ?? 3600);
        }

        if (is_singular('post')) {
            return (int) ($settings['cache_ttl_posts'] ?? 7 * DAY_IN_SECONDS);
        }

        if (is_singular('page')) {
            return (int) ($settings['cache_ttl_pages'] ?? 30 * DAY_IN_SECONDS);
        }

        // Default TTL for other content types
        return (int) ($settings['cache_ttl'] ?? 7 * DAY_IN_SECONDS);
    }

    /**
     * Get TTL for specific content type.
     *
     * @param string $content_type Content type ('homepage', 'post', 'page', 'default').
     * @return int TTL in seconds.
     */
    public function get_ttl_for_type(string $content_type): int
    {
        $settings = Settings::get();

        switch ($content_type) {
            case 'homepage':
                return (int) ($settings['cache_ttl_homepage'] ?? 3600);
            case 'post':
                return (int) ($settings['cache_ttl_posts'] ?? 7 * DAY_IN_SECONDS);
            case 'page':
                return (int) ($settings['cache_ttl_pages'] ?? 30 * DAY_IN_SECONDS);
            default:
                return (int) ($settings['cache_ttl'] ?? 7 * DAY_IN_SECONDS);
        }
    }
}
