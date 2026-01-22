<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

/**
 * Multisite helper for SpeedMate
 * Handles network-wide and per-site cache management
 */
final class Multisite
{
    /**
     * Check if multisite is enabled
     */
    public static function is_multisite(): bool
    {
        return is_multisite();
    }

    /**
     * Get site-specific cache directory
     */
    public static function get_site_cache_dir(): string
    {
        if (!self::is_multisite()) {
            return SPEEDMATE_CACHE_DIR;
        }

        $site_id = get_current_blog_id();
        return trailingslashit(SPEEDMATE_CACHE_DIR) . 'site-' . $site_id;
    }

    /**
     * Get network-wide cache directory
     */
    public static function get_network_cache_dir(): string
    {
        if (!self::is_multisite()) {
            return SPEEDMATE_CACHE_DIR;
        }

        return WP_CONTENT_DIR . '/cache/speedmate-network';
    }

    /**
     * Get settings with network fallback
     */
    public static function get_settings(): array
    {
        if (!self::is_multisite()) {
            return Settings::get();
        }

        // Get network settings
        $network_settings = get_site_option(SPEEDMATE_OPTION_KEY . '_network', []);
        
        // Get site-specific settings
        $site_settings = Settings::get();

        // Merge with network settings as defaults
        return wp_parse_args($site_settings, $network_settings);
    }

    /**
     * Check if current user can manage network settings
     */
    public static function can_manage_network(): bool
    {
        return self::is_multisite() && current_user_can('manage_network_options');
    }

    /**
     * Flush cache for all sites in network
     */
    public static function flush_network_cache(): void
    {
        if (!self::is_multisite()) {
            return;
        }

        $sites = get_sites(['number' => 999]);
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            \SpeedMate\Cache\StaticCache::instance()->flush_all();
            restore_current_blog();
        }
    }
}
