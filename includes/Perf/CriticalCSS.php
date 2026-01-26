<?php

declare(strict_types=1);

namespace SpeedMate\Perf;

use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Singleton;

final class CriticalCSS
{
    use Singleton;

    private function __construct()
    {
    }

    private function register_hooks(): void
    {
        $settings = Settings::get();
        if (!($settings['critical_css_enabled'] ?? false)) {
            return;
        }

        add_filter('speedmate_cache_contents', [$this, 'optimize_css'], 15);
    }

    public function optimize_css(string $html): string
    {
        // Extract critical CSS from inline styles and defer the rest
        $html = $this->defer_stylesheets($html);
        return $html;
    }

    private function defer_stylesheets(string $html): string
    {
        // Convert link tags to load via JS (non-blocking)
        return preg_replace_callback(
            '/<link([^>]*rel=["\']stylesheet["\'][^>]*)>/i',
            function ($matches) {
                $link = $matches[1];
                
                // Skip already deferred or media print
                if (strpos($link, 'media=') !== false && strpos($link, 'media="print"') === false) {
                    return $matches[0];
                }

                // Make stylesheet non-blocking
                if (strpos($link, 'media=') === false) {
                    $link .= ' media="print"';
                } else {
                    $link = str_replace('media="all"', 'media="print"', $link);
                }

                // Add onload to switch media back
                if (strpos($link, 'onload=') === false) {
                    $link .= ' onload="this.media=\'all\'"';
                }

                return '<link' . $link . '>';
            },
            $html
        );
    }

    public function extract_critical(string $html, string $url): string
    {
        // Basic implementation - can be enhanced with proper CSS parser
        $critical_selectors = $this->get_atf_selectors($html);
        $critical_css = $this->build_minimal_css($critical_selectors);

        if ($critical_css !== '') {
            $html = $this->inline_critical($html, $critical_css);
        }

        return $html;
    }

    private function get_atf_selectors(string $html): array
    {
        // Extract classes and IDs from above-the-fold content
        // This is a simplified version - can be enhanced
        $selectors = [];

        // Get header elements
        if (preg_match('/<header[^>]*class=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $selectors[] = $matches[1];
        }

        // Get nav elements
        if (preg_match('/<nav[^>]*class=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $selectors[] = $matches[1];
        }

        // Get main content start
        if (preg_match('/<main[^>]*class=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $selectors[] = $matches[1];
        }

        return $selectors;
    }

    private function build_minimal_css(array $selectors): string
    {
        if (empty($selectors)) {
            return '';
        }

        // Basic critical CSS
        $css = 'body{margin:0;padding:0;}';
        $css .= 'img{max-width:100%;height:auto;}';

        return $css;
    }

    private function inline_critical(string $html, string $css): string
    {
        $style_tag = '<style id="speedmate-critical-css">' . $css . '</style>';

        // Insert before first stylesheet or in head
        if (preg_match('/<link[^>]*rel=["\']stylesheet["\'][^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1];
            return substr_replace($html, $style_tag, $pos, 0);
        }

        // Fallback: insert at end of head
        $html = str_replace('</head>', $style_tag . '</head>', $html);

        return $html;
    }
}
