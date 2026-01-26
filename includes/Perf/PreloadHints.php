<?php

declare(strict_types=1);

namespace SpeedMate\Perf;

use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Singleton;

/**
 * Resource hints for optimizing external resource loading.
 *
 * Outputs HTML resource hints to optimize network requests:
 * - DNS prefetch: Early DNS resolution for external domains
 * - Preconnect: Full connection setup (DNS+TCP+TLS) for critical origins
 * - Prefetch: Low-priority fetch of likely-needed resources
 *
 * Features:
 * - Automatic detection of Google Fonts usage
 * - Next post prefetching for single posts
 * - Homepage prefetching for deep pages
 * - Extensible via filters for custom domains
 *
 * Impact:
 * - Faster external resource loading
 * - Reduced connection latency
 * - Improved perceived performance
 *
 * @package SpeedMate\Perf
 * @since 0.2.0
 */
final class PreloadHints
{
    use Singleton;

    /**
     * Private constructor to enforce Singleton pattern.
     */
    private function __construct()
    {
    }

    /**
     * Register WordPress hooks for resource hints.
     *
     * Only registers if preload_hints_enabled setting is true.
     *
     * Hooks:
     * - wp_head (priority 1): Output resource hint link tags
     *
     * @return void
     */
    private function register_hooks(): void
    {
        $settings = Settings::get();
        if (!($settings['preload_hints_enabled'] ?? false)) {
            return;
        }

        add_action('wp_head', [$this, 'output_hints'], 1);
    }

    /**
     * Output resource hint link tags in HTML head.
     *
     * Outputs:
     * - dns-prefetch for external domains
     * - preconnect for critical resources
     * - prefetch for next post (on single posts)
     * - prefetch for homepage (on deep pages)
     *
     * All URLs are escaped with esc_url().
     *
     * @return void
     */
    public function output_hints(): void
    {
        // DNS prefetch for external domains
        $domains = $this->get_external_domains();
        foreach ($domains as $domain) {
            echo '<link rel="dns-prefetch" href="' . esc_url($domain) . '">' . "\n";
        }

        // Preconnect for critical resources
        $preconnect = $this->get_preconnect_urls();
        foreach ($preconnect as $url) {
            echo '<link rel="preconnect" href="' . esc_url($url) . '" crossorigin>' . "\n";
        }

        // Prefetch next page for single posts
        if (is_single()) {
            $next = get_next_post();
            if ($next instanceof \WP_Post) {
                $next_url = get_permalink($next);
                if (is_string($next_url)) {
                    echo '<link rel="prefetch" href="' . esc_url($next_url) . '">' . "\n";
                }
            }
        }

        // Prefetch home for deep pages
        if (!is_front_page() && !is_home()) {
            echo '<link rel="prefetch" href="' . esc_url(home_url('/')) . '">' . "\n";
        }
    }

    /**
     * Get list of external domains for DNS prefetch.
     *
     * Automatically includes:
     * - Google Fonts (if detected)
     * - jsDelivr CDN
     *
     * Extensible via 'speedmate_dns_prefetch_domains' filter.
     *
     * @return array<string> Array of domain URLs.
     */
    private function get_external_domains(): array
    {
        $domains = [];

        // Google Fonts
        if ($this->site_uses_google_fonts()) {
            $domains[] = 'https://fonts.googleapis.com';
            $domains[] = 'https://fonts.gstatic.com';
        }

        // CDNs commonly used by plugins
        $domains[] = 'https://cdn.jsdelivr.net';

        return apply_filters('speedmate_dns_prefetch_domains', $domains);
    }

    /**
     * Get list of URLs for preconnect hints.
     *
     * Preconnect performs full connection (DNS+TCP+TLS) setup.
     * Only use for critical external resources.
     *
     * Automatically includes:
     * - Google Fonts (if detected)
     *
     * Extensible via 'speedmate_preconnect_urls' filter.
     *
     * @return array<string> Array of resource URLs.
     */
    private function get_preconnect_urls(): array
    {
        $urls = [];

        if ($this->site_uses_google_fonts()) {
            $urls[] = 'https://fonts.googleapis.com';
            $urls[] = 'https://fonts.gstatic.com';
        }

        return apply_filters('speedmate_preconnect_urls', $urls);
    }

    /**
     * Check if site uses Google Fonts.
     *
     * Scans registered stylesheets for Google Fonts URL.
     * Used to conditionally add Google Fonts hints.
     *
     * @return bool True if Google Fonts detected, false otherwise.
     */
    private function site_uses_google_fonts(): bool
    {
        // Check if any enqueued styles reference Google Fonts
        global $wp_styles;

        if (!isset($wp_styles->registered)) {
            return false;
        }

        foreach ($wp_styles->registered as $style) {
            if (isset($style->src) && strpos($style->src, 'fonts.googleapis.com') !== false) {
                return true;
            }
        }

        return false;
    }
}
