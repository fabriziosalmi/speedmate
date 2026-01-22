<?php

declare(strict_types=1);

namespace SpeedMate\Perf;

use SpeedMate\Utils\CspNonce;
use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Container;

final class BeastMode
{
    private static ?BeastMode $instance = null;

    private const SAFE_LIST = [
        'googletagmanager.com/gtag/js',
        'googletagmanager.com/gtm.js',
        'google-analytics.com/analytics.js',
        'js.stripe.com',
        'paypal.com/sdk/js',
        'jquery.js',
        'jquery.min.js',
    ];

    private function __construct()
    {
    }

    public static function instance(): BeastMode
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
        add_action('template_redirect', [$this, 'start_buffer'], 1);
        add_action('wp_head', [$this, 'output_trigger_script'], 1);
        add_filter('speedmate_cache_contents', [$this, 'rewrite_scripts'], 10, 1);
    }

    public function start_buffer(): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        ob_start([$this, 'rewrite_scripts']);
    }

    public function output_trigger_script(): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        $script = "(function(){\n" .
            "var fired=false;\n" .
            "function load(){\n" .
            "if(fired)return;fired=true;\n" .
            "var list=document.querySelectorAll('script[type=\"speedmate/delay\"],script[data-speedmate-src]');\n" .
            "for(var i=0;i<list.length;i++){\n" .
            "var old=list[i];\n" .
            "var s=document.createElement('script');\n" .
            "var src=old.getAttribute('data-speedmate-src');\n" .
            "if(src){s.src=src;}\n" .
            "var t=old.getAttribute('data-speedmate-type');\n" .
            "if(t){s.type=t;}\n" .
            "if(old.async){s.async=true;}\n" .
            "if(old.defer){s.defer=true;}\n" .
            "if(!src){s.text=old.text;}\n" .
            "old.parentNode.replaceChild(s,old);\n" .
            "}\n" .
            "}\n" .
            "var events=['keydown','mousemove','touchmove','wheel'];\n" .
            "for(var i=0;i<events.length;i++){window.addEventListener(events[i],load,{once:true,passive:true});}\n" .
            "})();";

        $nonce_attr = CspNonce::attr();
        echo '<script' . $nonce_attr . '>' . $script . '</script>' . "\n";
    }

    public function rewrite_scripts(string $html): string
    {
        if (!$this->is_enabled()) {
            return $html;
        }

        if ($html === '' || stripos($html, '<script') === false) {
            return $html;
        }

        if (!class_exists('DOMDocument')) {
            return $html;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $options = 0;
        if (defined('LIBXML_HTML_NOIMPLIED')) {
            $options |= LIBXML_HTML_NOIMPLIED;
        }
        if (defined('LIBXML_HTML_NODEFDTD')) {
            $options |= LIBXML_HTML_NODEFDTD;
        }
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, $options);
        libxml_clear_errors();

        if (!$loaded) {
            return $html;
        }

        $scripts = $dom->getElementsByTagName('script');
        if ($scripts->length === 0) {
            return $html;
        }

        $to_process = [];
        foreach ($scripts as $script) {
            $to_process[] = $script;
        }

        foreach ($to_process as $script) {
            if (!$script instanceof \DOMElement) {
                continue;
            }

            if ($script->hasAttribute('data-speedmate-skip')) {
                continue;
            }

            $type = strtolower((string) $script->getAttribute('type'));
            if ($type === 'speedmate/delay') {
                continue;
            }
            if ($type !== '' && !in_array($type, ['text/javascript', 'application/javascript'], true)) {
                continue;
            }

            $src = (string) $script->getAttribute('src');
            if ($src !== '') {
                if ($this->is_blacklisted($src)) {
                    // Force delay.
                } elseif ($this->is_safe_listed($src)) {
                    continue;
                }
            }

            if ($src !== '') {
                $script->setAttribute('data-speedmate-src', $src);
                $script->removeAttribute('src');
            }

            if ($type !== '') {
                $script->setAttribute('data-speedmate-type', $type);
            }

            $script->setAttribute('type', 'speedmate/delay');
        }

        return $dom->saveHTML();
    }

    private function is_safe_listed(string $src): bool
    {
        $rules = array_merge(self::SAFE_LIST, $this->get_whitelist());
        foreach ($rules as $needle) {
            if (stripos($src, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function is_blacklisted(string $src): bool
    {
        foreach ($this->get_blacklist() as $needle) {
            if (stripos($src, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function get_whitelist(): array
    {
        $settings = Settings::get();
        $rules = $settings['beast_whitelist'] ?? [];

        return is_array($rules) ? $rules : [];
    }

    private function get_blacklist(): array
    {
        $settings = Settings::get();
        $rules = $settings['beast_blacklist'] ?? [];

        return is_array($rules) ? $rules : [];
    }

    private function is_enabled(): bool
    {
        if (is_admin() || is_feed() || is_preview()) {
            return false;
        }

        $settings = Settings::get();
        $mode = $settings['mode'] ?? 'disabled';

        if ($mode !== 'beast') {
            return false;
        }

        return $this->is_preview_allowed();
    }

    private function is_preview_allowed(): bool
    {
        $settings = Settings::get();
        $apply_all = (bool) ($settings['beast_apply_all'] ?? false);

        if ($apply_all) {
            return true;
        }

        $cap = (string) apply_filters('speedmate_admin_capability', 'manage_options');
        if (current_user_can($cap)) {
            return true;
        }

        return isset($_GET['speedmate_test']) && $_GET['speedmate_test'] === '1';
    }
}
