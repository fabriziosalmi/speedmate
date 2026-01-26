<?php

declare(strict_types=1);

namespace SpeedMate\Admin;

use SpeedMate\Cache\StaticCache;
use SpeedMate\Utils\Settings;

/**
 * Handles WordPress admin bar integration.
 *
 * @package SpeedMate\Admin
 * @since 0.4.0
 */
final class AdminBar
{
    private AdminRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new AdminRenderer();
    }

    /**
     * Register admin bar nodes.
     *
     * @param \WP_Admin_Bar $wp_admin_bar WordPress admin bar object.
     * @param string $capability Required capability.
     * @param string $flush_url URL for cache flush.
     * @return void
     */
    public function register($wp_admin_bar, string $capability, string $flush_url): void
    {
        if (!current_user_can($capability)) {
            return;
        }

        $cache = StaticCache::instance();
        $stats = \SpeedMate\Utils\Stats::get();
        
        $wp_admin_bar->add_node([
            'id' => 'speedmate',
            'title' => $this->get_title($stats),
            'href' => admin_url('admin.php?page=speedmate'),
        ]);

        $wp_admin_bar->add_node([
            'parent' => 'speedmate',
            'id' => 'speedmate-stats',
            'title' => sprintf(
                'Cache: %s | Pages: %d | LCP: %d',
                $this->renderer->format_bytes($cache->get_cache_size_bytes()),
                $cache->get_cached_pages_count(),
                $stats['lcp_preloads'] ?? 0
            ),
        ]);

        $wp_admin_bar->add_node([
            'parent' => 'speedmate',
            'id' => 'speedmate-timing',
            'title' => sprintf(
                'Avg: %dms cached | %dms uncached',
                $stats['avg_cached_ms'] ?? 50,
                $stats['avg_uncached_ms'] ?? 0
            ),
        ]);

        $wp_admin_bar->add_node([
            'parent' => 'speedmate',
            'id' => 'speedmate-flush-cache',
            'title' => __('Flush Cache', 'speedmate'),
            'href' => $flush_url,
        ]);
    }

    /**
     * Get admin bar title with icon and stats.
     *
     * @param array $stats Statistics array.
     * @return string Formatted title.
     */
    private function get_title(array $stats): string
    {
        $mode = Settings::get_value('mode', 'disabled');
        $icon = $mode === 'beast' ? '⚡' : ($mode === 'safe' ? '🔒' : '💤');
        
        return sprintf(
            '%s SpeedMate | Time Saved: %s',
            $icon,
            gmdate('H:i:s', (int) (($stats['time_saved_ms'] ?? 0) / 1000))
        );
    }
}
