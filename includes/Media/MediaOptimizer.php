<?php

declare(strict_types=1);

namespace SpeedMate\Media;

use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Container;

final class MediaOptimizer
{
    private static ?MediaOptimizer $instance = null;

    private function __construct()
    {
    }

    public static function instance(): MediaOptimizer
    {
        $override = Container::get(self::class);
        if ($override instanceof self) {
            return $override;
        }

        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->register_hooks();
        }

        return self::$instance;
    }

    private function register_hooks(): void
    {
        add_filter('the_content', [$this, 'optimize_content'], 999);
        add_filter('widget_text_content', [$this, 'optimize_content'], 999);
    }

    public function optimize_content(string $content): string
    {
        if ($content === '' || !$this->is_enabled()) {
            return $content;
        }

        if (stripos($content, '<img') === false && stripos($content, '<iframe') === false) {
            return $content;
        }

        if (!class_exists('DOMDocument')) {
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
            return $content;
        }

        $wrapper = $dom->getElementById('speedmate-wrapper');
        if (!$wrapper) {
            return $content;
        }

        $this->apply_lazy_loading($wrapper);
        $this->inject_dimensions($wrapper);

        return $this->get_inner_html($wrapper);
    }

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

    private function get_inner_html(\DOMElement $element): string
    {
        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }

        return $html;
    }

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
