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

    public static function instance(): self
    {
        static $instance = null;
        if (null === $instance) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Flush cache for all sites in network.
     * Uses direct filesystem operations to avoid expensive switch_to_blog() calls.
     *
     * @return void
     */
    public static function flush_network_cache(): void
    {
        if (!self::is_multisite()) {
            return;
        }

        $sites = get_sites(['number' => 999]);
        $flushed_count = 0;
        
        foreach ($sites as $site) {
            $site_cache_dir = trailingslashit(SPEEDMATE_CACHE_DIR) . 'site-' . $site->blog_id;
            
            // Direct filesystem flush without context switching
            if (file_exists($site_cache_dir)) {
                \SpeedMate\Utils\Filesystem::delete_directory($site_cache_dir);
                $flushed_count++;
            }
        }
        
        // Clear transients for all sites in one pass
        if ($flushed_count > 0) {
            self::clear_site_transients($sites);
        }
    }

    /**
     * Clear site-specific transients without context switching.
     *
     * @param array $sites Array of site objects.
     * @return void
     */
    private static function clear_site_transients(array $sites): void
    {
        global $wpdb;
        
        foreach ($sites as $site) {
            $prefix = $wpdb->get_blog_prefix($site->blog_id);
            
            // Delete stats transients for this site
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$prefix}options WHERE option_name IN (%s, %s)",
                    '_transient_speedmate_cache_size',
                    '_transient_speedmate_cache_count'
                )
            );
        }
    }
}
