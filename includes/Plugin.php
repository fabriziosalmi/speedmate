<?php

declare(strict_types=1);

namespace SpeedMate;

use SpeedMate\Admin\Admin;
use SpeedMate\Cache\StaticCache;

final class Plugin
{
    private static ?Plugin $instance = null;

    private function __construct()
    {
    }

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->boot();
        }

        return self::$instance;
    }

    private function boot(): void
    {
        $this->load_autoloader();
        $this->register_hooks();
    }

    private function load_autoloader(): void
    {
        $autoload_path = SPEEDMATE_PATH . 'vendor/autoload.php';
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
        }
    }

    private function register_hooks(): void
    {
        add_action('init', [$this, 'register_settings']);

        if (is_admin()) {
            Admin::instance();
            \SpeedMate\Admin\HealthWidget::instance();
            \SpeedMate\Admin\ImportExport::instance();
        }

        StaticCache::instance();
        \SpeedMate\Cache\TrafficWarmer::instance();
        \SpeedMate\Media\MediaOptimizer::instance();
        \SpeedMate\Media\WebPConverter::instance();
        \SpeedMate\Perf\AutoLCP::instance();
        \SpeedMate\Perf\BeastMode::instance();
        \SpeedMate\Perf\CriticalCSS::instance();
        \SpeedMate\Perf\PreloadHints::instance();
        \SpeedMate\Cache\DynamicFragments::instance();
        \SpeedMate\Utils\GarbageCollector::instance();
        \SpeedMate\API\BatchEndpoints::instance();
        
        // Register WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('speedmate', \SpeedMate\CLI\Commands::class);
        }
        
        // Initialize multisite support
        if (is_multisite()) {
            \SpeedMate\Utils\Multisite::instance();
        }
    }

    public function register_settings(): void
    {
        if (get_option(SPEEDMATE_OPTION_KEY) === false) {
            add_option(SPEEDMATE_OPTION_KEY, [
                'mode' => 'disabled',
                'beast_whitelist' => [],
                'beast_blacklist' => [],
                'beast_apply_all' => false,
                'logging_enabled' => false,
                'csp_nonce' => false,
                'cache_ttl' => 7 * DAY_IN_SECONDS,
                'cache_ttl_homepage' => 3600,
                'cache_ttl_posts' => 7 * DAY_IN_SECONDS,
                'cache_ttl_pages' => 30 * DAY_IN_SECONDS,
                'cache_exclude_urls' => [
                    '/checkout/',
                    '/cart/',
                    '/my-account/',
                ],
                'cache_exclude_cookies' => [
                    'woocommerce_items_in_cart',
                ],
                'cache_exclude_query_params' => [
                    'utm_*',
                    'fb_*',
                ],
                'warmer_enabled' => true,
                'warmer_frequency' => 'hourly',
                'warmer_max_urls' => 20,
                'warmer_concurrent' => 3,
                'webp_enabled' => false,
                'critical_css_enabled' => false,
                'preload_hints_enabled' => true,
            ], '', false);
        }

        if (get_option(SPEEDMATE_STATS_KEY) === false) {
            add_option(SPEEDMATE_STATS_KEY, [
                'warmed_pages' => 0,
                'lcp_preloads' => 0,
                'time_saved_ms' => 0,
                'avg_uncached_ms' => 0,
                'avg_cached_ms' => 50,
                'week_key' => '',
            ], '', false);
        }
    }
}
