<?php

declare(strict_types=1);

namespace SpeedMate;

use SpeedMate\Admin\Admin;
use SpeedMate\Cache\StaticCache;
use SpeedMate\Utils\Singleton;

/**
 * Main plugin class.
 * Orchestrates all plugin components and hooks.
 *
 * @package SpeedMate
 * @since 0.4.0
 */
final class Plugin
{
    use Singleton;

    private function __construct()
    {
    }

    /**
     * Initialize plugin after instance creation.
     * Called automatically by Singleton trait after first instantiation.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        $this->load_autoloader();
        
        add_action('init', [$this, 'register_settings'], 10, 0);

        // Initialize core components
        $this->init_admin_components();
        $this->init_cache_components();
        $this->init_media_components();
        $this->init_performance_components();
        $this->init_api_components();
        $this->init_cli_commands();
        $this->init_multisite();
    }

    /**
     * Load Composer autoloader.
     *
     * @return void
     */
    private function load_autoloader(): void
    {
        $autoload_path = SPEEDMATE_PATH . 'vendor/autoload.php';
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
        }
    }

    /**
     * Initialize admin components.
     *
     * @return void
     */
    private function init_admin_components(): void
    {
        if (!is_admin()) {
            return;
        }

        Admin::instance();
        \SpeedMate\Admin\HealthWidget::instance();
        \SpeedMate\Admin\ImportExport::instance();
    }

    /**
     * Initialize cache components.
     *
     * @return void
     */
    private function init_cache_components(): void
    {
        StaticCache::instance();
        \SpeedMate\Cache\TrafficWarmer::instance();
        \SpeedMate\Cache\DynamicFragments::instance();
    }

    /**
     * Initialize media optimization components.
     *
     * @return void
     */
    private function init_media_components(): void
    {
        \SpeedMate\Media\MediaOptimizer::instance();
        \SpeedMate\Media\WebPConverter::instance();
    }

    /**
     * Initialize performance optimization components.
     *
     * @return void
     */
    private function init_performance_components(): void
    {
        \SpeedMate\Perf\AutoLCP::instance();
        \SpeedMate\Perf\BeastMode::instance();
        \SpeedMate\Perf\CriticalCSS::instance();
        \SpeedMate\Perf\PreloadHints::instance();
    }

    /**
     * Initialize API components.
     *
     * @return void
     */
    private function init_api_components(): void
    {
        \SpeedMate\Utils\GarbageCollector::instance();
        \SpeedMate\API\BatchEndpoints::instance();
    }

    /**
     * Register WP-CLI commands.
     *
     * @return void
     */
    private function init_cli_commands(): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('speedmate', \SpeedMate\CLI\Commands::class);
        }
    }

    /**
     * Initialize multisite support.
     *
     * @return void
     */
    private function init_multisite(): void
    {
        if (is_multisite()) {
            \SpeedMate\Utils\Multisite::instance();
        }
    }

    /**
     * Register plugin settings on init.
     * Creates default options if they don't exist.
     *
     * @return void
     */
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
                'cache_ttl_homepage' => SPEEDMATE_DEFAULT_TTL_HOMEPAGE,
                'cache_ttl_posts' => SPEEDMATE_DEFAULT_TTL_POSTS,
                'cache_ttl_pages' => SPEEDMATE_DEFAULT_TTL_PAGES,
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
                'garbage_collector_enabled' => false,
                'garbage_collector_delete_spam' => false,
            ], '', false);
        }

        if (get_option(SPEEDMATE_STATS_KEY) === false) {
            add_option(SPEEDMATE_STATS_KEY, [
                'warmed_pages' => 0,
                'lcp_preloads' => 0,
                'time_saved_ms' => 0,
                'avg_uncached_ms' => 0,
                'avg_cached_ms' => SPEEDMATE_STATS_DEFAULT_CACHED_MS,
                'week_key' => '',
            ], '', false);
        }
    }
}