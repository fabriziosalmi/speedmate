<?php

declare(strict_types=1);

namespace SpeedMate\Admin;

/**
 * Handles WordPress admin menu registration.
 *
 * @package SpeedMate\Admin
 * @since 0.4.0
 */
final class AdminMenu
{
    /**
     * Register SpeedMate admin menu page.
     *
     * @param callable $render_callback Callback to render the page.
     * @return void
     */
    public function register(callable $render_callback): void
    {
        add_menu_page(
            __('SpeedMate', 'speedmate'),
            __('SpeedMate', 'speedmate'),
            $this->get_capability(),
            'speedmate',
            $render_callback,
            'dashicons-dashboard',
            SPEEDMATE_MENU_POSITION
        );
    }

    /**
     * Get required capability for admin access.
     *
     * @return string Capability name.
     */
    public function get_capability(): string
    {
        return (string) apply_filters('speedmate_admin_capability', 'manage_options');
    }

    /**
     * Generate flush cache URL with nonce.
     *
     * @return string URL for cache flush action.
     */
    public function get_flush_url(): string
    {
        $url = admin_url('admin-post.php?action=speedmate_flush_cache');
        return wp_nonce_url($url, 'speedmate_flush_cache');
    }

    /**
     * Generate apply beast mode URL with nonce.
     *
     * @return string URL for beast mode activation.
     */
    public function get_apply_beast_all_url(): string
    {
        $url = admin_url('admin-post.php?action=speedmate_apply_beast_all');
        return wp_nonce_url($url, 'speedmate_apply_beast_all');
    }
}
