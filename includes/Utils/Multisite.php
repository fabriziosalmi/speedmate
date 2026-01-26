<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

/**
 * WordPress Multisite support for SpeedMate.
 *
 * Features:
 * - Per-site cache isolation
 * - Network-wide settings inheritance
 * - Site-specific overrides
 * - Network admin capabilities
 *
 * Cache structure:
 * - Single site: /cache/speedmate/
 * - Multisite: /cache/speedmate/site-{ID}/
 * - Network cache: /cache/speedmate-network/
 *
 * Settings hierarchy:
 * 1. Network settings (shared defaults)
 * 2. Site-specific overrides
 * 3. wp_parse_args() merges both
 *
 * Use cases:
 * - Enterprise WordPress networks
 * - Multiple brands on one install
 * - Shared hosting with multiple sites
 *
 * @package SpeedMate\Utils
 * @since 0.1.0
 */
final class Multisite
{
    /**
     * Check if WordPress Multisite is enabled.
     *
     * Wrapper around is_multisite() for consistency.
     *
     * @return bool True if multisite enabled, false otherwise.
     */
    public static function is_multisite(): bool
    {
        return is_multisite();
    }

    /**
     * Get cache directory for current site.
     *
     * Returns:
     * - Single site: SPEEDMATE_CACHE_DIR (e.g., /wp-content/cache/speedmate)
     * - Multisite: SPEEDMATE_CACHE_DIR/site-{ID} (e.g., /wp-content/cache/speedmate/site-2)
     *
     * Ensures cache isolation between sites in network.
     * Each site's cache is independent and can be flushed separately.
     *
     * @return string Site-specific cache directory path.
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
     * Get network-wide cache directory.
     *
     * Returns:
     * - Single site: SPEEDMATE_CACHE_DIR (same as site cache)
     * - Multisite: /wp-content/cache/speedmate-network
     *
     * Use for:
     * - Shared resources across sites
     * - Network-wide configurations
     * - Global caching (e.g., external API responses)
     *
     * @return string Network cache directory path.
     */
    public static function get_network_cache_dir(): string
    {
        if (!self::is_multisite()) {
            return SPEEDMATE_CACHE_DIR;
        }

        return WP_CONTENT_DIR . '/cache/speedmate-network';
    }

    /**
     * Get settings with network inheritance.
     *
     * Settings hierarchy:
     * 1. Network settings (defaults for all sites)
     * 2. Site-specific overrides
     * 3. Merged result (site settings override network)
     *
     * Single site: Returns Settings::get()
     * Multisite: Merges site + network settings
     *
     * Use case:
     * - Network admin sets cache_ttl = 3600 (1 hour)
     * - Site 2 overrides to 7200 (2 hours)
     * - Result: Site 2 gets 7200, others get 3600
     *
     * @return array Merged settings array.
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
     * Check if current user can manage network-wide settings.
     *
     * Returns true if:
     * - Multisite is enabled AND
     * - User has manage_network_options capability
     *
     * Returns false for:
     * - Single site installations
     * - Non-network-admin users
     *
     * Use for:
     * - Network settings page access
     * - Network-wide cache operations
     * - Global configuration changes
     *
     * @return bool True if user can manage network, false otherwise.
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
                \SpeedMate\Utils\Filesystem::delete($site_cache_dir, true);
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
