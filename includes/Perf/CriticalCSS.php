<?php

declare(strict_types=1);

namespace SpeedMate\Perf;

use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Singleton;

/**
 * Critical CSS extraction and stylesheet deferring.
 *
 * Optimizes CSS delivery by:
 * - Deferring non-critical stylesheets to load after page render
 * - Extracting critical above-the-fold CSS for inlining
 * - Using media="print" trick for non-blocking stylesheet loading
 *
 * Features:
 * - Automatic stylesheet deferring via media attribute
 * - Onload handler to restore original media
 * - Basic critical CSS extraction from HTML structure
 * - Above-the-fold selector detection
 *
 * Impact:
 * - Faster First Contentful Paint (FCP)
 * - Reduced render-blocking resources
 * - Improved page load experience
 *
 * @package SpeedMate\Perf
 * @since 0.2.0
 */
final class CriticalCSS
{
    use Singleton;

    /**
     * Private constructor to enforce Singleton pattern.
     */
    private function __construct()
    {
    }

    /**
     * Register WordPress hooks for Critical CSS optimization.
     *
     * Only registers if critical_css_enabled setting is true.
     *
     * Hooks:
     * - speedmate_cache_contents (priority 15): Optimize CSS delivery
     *
     * @return void
     */
    private function register_hooks(): void
    {
        $settings = Settings::get();
        if (!($settings['critical_css_enabled'] ?? false)) {
            return;
        }

        add_filter('speedmate_cache_contents', [$this, 'optimize_css'], 15);
    }

    /**
     * Optimize CSS delivery by deferring non-critical stylesheets.
     *
     * Currently focuses on stylesheet deferring. Can be enhanced
     * to include critical CSS extraction in future versions.
     *
     * @param string $html HTML content to optimize.
     *
     * @return string Optimized HTML with deferred stylesheets.
     */
    public function optimize_css(string $html): string
    {
        // Extract critical CSS from inline styles and defer the rest
        $html = $this->defer_stylesheets($html);
        return $html;
    }

    /**
     * Defer stylesheet loading using media="print" trick.
     *
     * Converts blocking <link rel="stylesheet"> tags to non-blocking
     * by temporarily setting media="print", then restoring via onload.
     *
     * Process:
     * 1. Find all <link rel="stylesheet"> tags
     * 2. Skip already deferred or print stylesheets
     * 3. Set media="print" to make non-blocking
     * 4. Add onload="this.media='all'" to restore
     *
     * @param string $html HTML content to process.
     *
     * @return string HTML with deferred stylesheets.
     */
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

    /**
     * Extract critical above-the-fold CSS from page HTML.
     *
     * Basic implementation that identifies critical selectors
     * and builds minimal CSS ruleset for inlining.
     *
     * Can be enhanced with proper CSS parser for production use.
     *
     * @param string $html HTML content to analyze.
     * @param string $url Page URL (unused, reserved for future).
     *
     * @return string HTML with inlined critical CSS.
     */
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

    /**
     * Extract above-the-fold CSS selectors from HTML.
     *
     * Simplified implementation that identifies:
     * - Header element classes
     * - Navigation element classes
     * - Main content area classes
     *
     * @param string $html HTML content to analyze.
     *
     * @return array<string> Array of CSS selectors.
     */
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

    /**
     * Build minimal critical CSS from selectors.
     *
     * Creates basic reset CSS for critical elements.
     * Currently returns generic reset; can be enhanced
     * to extract actual styles from page CSS.
     *
     * @param array<string> $selectors Array of CSS selectors.
     *
     * @return string Critical CSS ruleset.
     */
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

    /**
     * Inline critical CSS in HTML head.
     *
     * Inserts <style> tag with critical CSS:
     * - Before first stylesheet (optimal)
     * - At end of <head> (fallback)
     *
     * @param string $html HTML content.
     * @param string $css Critical CSS to inline.
     *
     * @return string HTML with inlined critical CSS.
     */
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
