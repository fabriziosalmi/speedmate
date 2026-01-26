<?php

declare(strict_types=1);

namespace SpeedMate\Perf;

use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Singleton;

final class PreloadHints
{
    use Singleton;

    private function __construct()
    {
    }

    private function register_hooks(): void
    {
        $settings = Settings::get();
        if (!($settings['preload_hints_enabled'] ?? false)) {
            return;
        }

        add_action('wp_head', [$this, 'output_hints'], 1);
    }

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

    private function get_preconnect_urls(): array
    {
        $urls = [];

        if ($this->site_uses_google_fonts()) {
            $urls[] = 'https://fonts.googleapis.com';
            $urls[] = 'https://fonts.gstatic.com';
        }

        return apply_filters('speedmate_preconnect_urls', $urls);
    }

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
