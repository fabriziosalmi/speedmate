<?php

declare(strict_types=1);

namespace SpeedMate\Media;

use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Logger;
use SpeedMate\Utils\Container;
use SpeedMate\Utils\Singleton;

/**
 * Media optimization for images and iframes in content.
 *
 * Optimizes media elements by:
 * - Adding lazy loading attributes (loading="lazy")
 * - Injecting width/height dimensions to prevent layout shift
 * - Processing post content and widget text
 *
 * Features:
 * - DOM-based HTML parsing for reliable manipulation
 * - Automatic dimension detection from WordPress media library
 * - Graceful fallback if DOMDocument unavailable
 * - Preserves existing loading/dimension attributes
 *
 * Impact:
 * - Improved Cumulative Layout Shift (CLS) score
 * - Faster initial page load (deferred image loading)
 * - Better Core Web Vitals performance
 *
 * @package SpeedMate\Media
 * @since 0.2.0
 */
final class MediaOptimizer
{
    use Singleton;

    /**
     * Private constructor to enforce Singleton pattern.
     */
    private function __construct()
    {
    }

    /**
     * Register WordPress hooks for media optimization.
     *
     * Hooks:
     * - the_content (priority 999): Optimize images/iframes in post content
     * - widget_text_content (priority 999): Optimize images/iframes in widgets
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_filter('the_content', [$this, 'optimize_content'], 999);
        add_filter('widget_text_content', [$this, 'optimize_content'], 999);
    }

    /**
     * Optimize images and iframes in HTML content.
     *
     * Process:
     * 1. Check if content has media elements
     * 2. Parse HTML with DOMDocument
     * 3. Apply lazy loading to images and iframes
     * 4. Inject dimensions from media library
     * 5. Return optimized HTML
     *
     * Skips optimization if:
     * - Content is empty
     * - Feature is disabled
     * - No media elements present
     * - DOMDocument unavailable
     *
     * @param string $content HTML content to optimize.
     *
     * @return string Optimized HTML with lazy loading and dimensions.
     */
    public function optimize_content(string $content): string
    {
        if ($content === '' || !$this->is_enabled()) {
            return $content;
        }

        if (stripos($content, '<img') === false && stripos($content, '<iframe') === false) {
            return $content;
        }

        if (!class_exists('DOMDocument')) {
            Logger::log('error', 'dom_document_unavailable');
            return $content;
        }

        $wrapped = '<div id="speedmate-wrapper">' . $content . '</div>';
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $options = 0;
        if (defined('LIBXML_HTML_NOIMPLIED')) {
            $options |= LIBXML_HTML_NOIMPLIED;
        }
        if (defined('LIBXML_HTML_NODEFDTD')) {
            $options |= LIBXML_HTML_NODEFDTD;
        }
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8" ?>' . $wrapped,
            $options
        );
        libxml_clear_errors();

        if (!$loaded) {
            Logger::log('warning', 'media_optimizer_parse_failed');
            return $content;
        }

        $wrapper = $dom->getElementById('speedmate-wrapper');
        if (!$wrapper) {
            Logger::log('warning', 'media_optimizer_wrapper_missing');
            return $content;
        }

        $this->apply_lazy_loading($wrapper);
        $this->inject_dimensions($wrapper);

        return $this->get_inner_html($wrapper);
    }

    /**
     * Apply lazy loading attribute to images and iframes.
     *
     * Adds loading="lazy" to all <img> and <iframe> elements
     * that don't already have the attribute.
     *
     * Browser support:
     * - Chrome 76+, Firefox 75+, Edge 79+, Safari 15.4+
     * - Gracefully ignored by older browsers
     *
     * @param \DOMElement $wrapper Wrapper element containing media.
     *
     * @return void
     */
    private function apply_lazy_loading(\DOMElement $wrapper): void
    {
        foreach ($wrapper->getElementsByTagName('img') as $image) {
            if (!$image->hasAttribute('loading')) {
                $image->setAttribute('loading', 'lazy');
            }
        }

        foreach ($wrapper->getElementsByTagName('iframe') as $iframe) {
            if (!$iframe->hasAttribute('loading')) {
                $iframe->setAttribute('loading', 'lazy');
            }
        }
    }

    /**
     * Inject width and height dimensions from media library.
     *
     * Process:
     * 1. Find images without width/height attributes
     * 2. Normalize image src URL
     * 3. Resolve to WordPress attachment ID
     * 4. Get image dimensions from attachment metadata
     * 5. Inject as HTML attributes
     *
     * Benefits:
     * - Prevents Cumulative Layout Shift (CLS)
     * - Browser can allocate space before image loads
     * - Improves Core Web Vitals score
     *
     * @param \DOMElement $wrapper Wrapper element containing images.
     *
     * @return void
     */
    private function inject_dimensions(\DOMElement $wrapper): void
    {
        foreach ($wrapper->getElementsByTagName('img') as $image) {
            if ($image->hasAttribute('width') && $image->hasAttribute('height')) {
                continue;
            }

            $src = $image->getAttribute('src');
            if ($src === '') {
                continue;
            }

            $attachment_id = attachment_url_to_postid($this->normalize_src($src));
            if (!$attachment_id) {
                Logger::log('debug', 'dimension_injection_no_attachment', ['src' => $src]);
                continue;
            }

            $meta = wp_get_attachment_metadata($attachment_id);
            if (!is_array($meta)) {
                continue;
            }

            $width = isset($meta['width']) ? (int) $meta['width'] : 0;
            $height = isset($meta['height']) ? (int) $meta['height'] : 0;

            if ($width > 0 && $height > 0) {
                if (!$image->hasAttribute('width')) {
                    $image->setAttribute('width', (string) $width);
                }
                if (!$image->hasAttribute('height')) {
                    $image->setAttribute('height', (string) $height);
                }
            }
        }
    }

    /**
     * Normalize image src URL for attachment lookup.
     *
     * Removes query parameters and fragments to improve
     * attachment_url_to_postid() matching accuracy.
     *
     * @param string $src Image source URL.
     *
     * @return string Normalized URL (scheme://host/path).
     */
    private function normalize_src(string $src): string
    {
        $parts = wp_parse_url($src);
        if (!is_array($parts)) {
            return $src;
        }

        $normalized = '';
        if (isset($parts['scheme']) && isset($parts['host'])) {
            $normalized .= $parts['scheme'] . '://' . $parts['host'];
        }

        if (isset($parts['path'])) {
            $normalized .= $parts['path'];
        }

        return $normalized !== '' ? $normalized : $src;
    }

    /**
     * Extract inner HTML from DOM element.
     *
     * Returns HTML of child nodes without the wrapper element itself.
     * Used to extract optimized content from temporary wrapper.
     *
     * @param \DOMElement $element Element to extract from.
     *
     * @return string Inner HTML content.
     */
    private function get_inner_html(\DOMElement $element): string
    {
        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }

        return $html;
    }

    /**
     * Check if media optimization is enabled.
     *
     * Requirements:
     * - Mode must be 'safe' or 'beast'
     * - Not admin, feed, or preview
     *
     * @return bool True if enabled, false otherwise.
     */
    private function is_enabled(): bool
    {
        if (is_admin() || is_feed() || is_preview()) {
            return false;
        }

        $settings = Settings::get();
        $mode = $settings['mode'] ?? 'disabled';

        return in_array($mode, ['safe', 'beast'], true);
    }
}
