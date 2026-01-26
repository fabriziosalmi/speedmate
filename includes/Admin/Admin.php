<?php

declare(strict_types=1);

namespace SpeedMate\Admin;

use SpeedMate\Cache\StaticCache;
use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Singleton;

/**
 * Main admin orchestrator.
 * Delegates to specialized classes for menu, settings, rendering, and admin bar.
 *
 * @package SpeedMate\Admin
 * @since 0.4.0
 */
final class Admin
{
    use Singleton;

    private AdminMenu $menu;
    private AdminSettings $settings;
    private AdminRenderer $renderer;
    private AdminBar $admin_bar;

    private function __construct()
    {
        $this->menu = new AdminMenu();
        $this->settings = new AdminSettings();
        $this->renderer = new AdminRenderer();
        $this->admin_bar = new AdminBar();
    }

    private function register_hooks(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_bar_menu', [$this, 'register_admin_bar'], 100);
        add_action('admin_post_speedmate_flush_cache', [$this, 'handle_flush_cache']);
        add_action('admin_post_speedmate_apply_beast_all', [$this, 'handle_apply_beast_all']);
    }

    public function register_menu(): void
    {
        $this->menu->register([$this, 'render_page']);
    }

    public function register_settings(): void
    {
        $this->settings->register();
    }

    public function render_page(): void
    {
        $this->renderer->render_page(
            $this->menu->get_capability(),
            $this->menu->get_flush_url(),
            $this->menu->get_apply_beast_all_url()
        );
    }

    public function register_admin_bar($wp_admin_bar): void
    {
        $this->admin_bar->register(
            $wp_admin_bar,
            $this->menu->get_capability(),
            $this->menu->get_flush_url()
        );
    }

    public function handle_flush_cache(): void
    {
        if (!current_user_can($this->menu->get_capability())) {
            wp_die(__('Unauthorized.', 'speedmate'));
        }

        check_admin_referer('speedmate_flush_cache');

        StaticCache::instance()->flush_all();

        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=speedmate'));
        exit;
    }

    public function handle_apply_beast_all(): void
    {
        if (!current_user_can($this->menu->get_capability())) {
            wp_die(__('Unauthorized.', 'speedmate'));
        }

        check_admin_referer('speedmate_apply_beast_all');

        $settings = Settings::get();
        $settings['beast_apply_all'] = true;
        update_option(SPEEDMATE_OPTION_KEY, $settings, false);
        Settings::refresh();

        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=speedmate'));
        exit;
    }
}
