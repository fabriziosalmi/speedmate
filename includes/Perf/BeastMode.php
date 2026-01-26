<?php

declare(strict_types=1);

namespace SpeedMate\Perf;

use SpeedMate\Utils\CspNonce;
use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Container;
use SpeedMate\Utils\Singleton;
use SpeedMate\Utils\Logger;

/**
 * Beast Mode - Aggressive JavaScript delay optimization.
 *
 * Delays all JavaScript execution until user interaction (keydown, mousemove,
 * touchmove, wheel) to dramatically improve initial page load metrics.
 *
 * Features:
 * - Rewrites <script> tags to type="speedmate/delay"
 * - Injects trigger script to load on user interaction
 * - Whitelist/blacklist support for granular control
 * - Safe-list for critical scripts (analytics, payment, jQuery)
 * - Admin preview capability with speedmate_test=1
 * - DOM-based script parsing with error handling
 *
 * Impact:
 * - Faster First Contentful Paint (FCP)
 * - Faster Largest Contentful Paint (LCP)
 * - Reduced Total Blocking Time (TBT)
 * - Lower Time to Interactive (TTI)
 *
 * @package SpeedMate\Perf
 * @since 0.2.0
 */
final class BeastMode
{
    use Singleton;

    /**
     * Built-in safe list of scripts that should not be delayed.
     *
     * Includes:
     * - Google Analytics & Tag Manager
     * - Stripe payment SDK
     * - PayPal SDK
     * - jQuery core
     *
     * @var array<string>
     */
    private const SAFE_LIST = [
        'googletagmanager.com/gtag/js',
        'googletagmanager.com/gtm.js',
        'google-analytics.com/analytics.js',
        'js.stripe.com',
        'paypal.com/sdk/js',
        'jquery.js',
        'jquery.min.js',
    ];

    /**
     * Private constructor to enforce Singleton pattern.
     */
    private function __construct()
    {
    }

    /**
     * Register WordPress hooks for Beast Mode.
     *
     * Hooks:
     * - template_redirect (priority 1): Start output buffering
     * - wp_head (priority 1): Output trigger script
     * - speedmate_cache_contents (priority 10): Rewrite script tags
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_action('template_redirect', [$this, 'start_buffer'], 1);
        add_action('wp_head', [$this, 'output_trigger_script'], 1);
        add_filter('speedmate_cache_contents', [$this, 'rewrite_scripts'], 10, 1);
    }

    /**
     * Start output buffering to capture HTML for script rewriting.
     *
     * Hooks into template_redirect with priority 1 to ensure all
     * output is captured. Buffer is processed by rewrite_scripts().
     *
     * Only runs when Beast Mode is enabled.
     *
     * @return void
     */
    public function start_buffer(): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        ob_start([$this, 'rewrite_scripts']);
    }

    /**
     * Output JavaScript trigger script to load delayed scripts on user interaction.
     *
     * Injects non-blocking JavaScript that:
     * - Listens for user interaction events (keydown, mousemove, touchmove, wheel)
     * - Finds all delayed scripts (type="speedmate/delay")
     * - Restores original script attributes and executes
     * - Fires only once per page load
     * - Uses {once:true,passive:true} for optimal performance
     *
     * Script includes CSP nonce for inline execution.
     *
     * @return void
     */
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

    /**
     * Rewrite script tags to delay execution until user interaction.
     *
     * Process:
     * 1. Parse HTML with DOMDocument
     * 2. Find all <script> tags
     * 3. Check whitelist/blacklist rules
     * 4. Move src to data-speedmate-src
     * 5. Change type to "speedmate/delay"
     * 6. Preserve async/defer attributes
     *
     * Skips:
     * - Scripts with data-speedmate-skip attribute
     * - Scripts with non-JS MIME types
     * - Scripts in whitelist (unless forced by blacklist)
     *
     * Error handling:
     * - Returns original HTML on DOM parse failure
     * - Logs parse errors via Logger
     * - Graceful degradation if DOMDocument unavailable
     *
     * @param string $html HTML content to process.
     *
     * @return string Processed HTML with delayed scripts.
     */
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

        try {
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
            
            if (!$loaded) {
                $errors = libxml_get_errors();
                if (!empty($errors)) {
                    Logger::log('warning', 'beast_mode_dom_parse_failed', [
                        'errors' => array_map(function($error) {
                            return sprintf('%s (line %d)', trim($error->message), $error->line);
                        }, $errors),
                    ]);
                }
                libxml_clear_errors();
                return $html;
            }
            
            libxml_clear_errors();
        } catch (\Exception $e) {
            Logger::log('error', 'beast_mode_dom_exception', [
                'error' => $e->getMessage(),
            ]);
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

    /**
     * Check if script URL is in safe list (should not be delayed).
     *
     * Combines built-in SAFE_LIST with user-defined whitelist.
     * Uses case-insensitive substring matching.
     *
     * @param string $src Script URL to check.
     *
     * @return bool True if safe-listed, false otherwise.
     */
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

    /**
     * Check if script URL is in blacklist (must be delayed).
     *
     * Blacklist overrides whitelist for forced delay.
     * Uses case-insensitive substring matching.
     *
     * @param string $src Script URL to check.
     *
     * @return bool True if blacklisted (force delay), false otherwise.
     */
    private function is_blacklisted(string $src): bool
    {
        foreach ($this->get_blacklist() as $needle) {
            if (stripos($src, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user-defined whitelist of scripts that should not be delayed.
     *
     * Reads from Settings 'beast_whitelist' array.
     *
     * @return array<string> Array of URL patterns.
     */
    private function get_whitelist(): array
    {
        $settings = Settings::get();
        $rules = $settings['beast_whitelist'] ?? [];

        return is_array($rules) ? $rules : [];
    }

    /**
     * Get user-defined blacklist of scripts that must be delayed.
     *
     * Blacklist overrides whitelist. Reads from Settings 'beast_blacklist' array.
     *
     * @return array<string> Array of URL patterns.
     */
    private function get_blacklist(): array
    {
        $settings = Settings::get();
        $rules = $settings['beast_blacklist'] ?? [];

        return is_array($rules) ? $rules : [];
    }

    /**
     * Check if Beast Mode is enabled for current request.
     *
     * Requirements:
     * - Mode must be 'beast'
     * - Not admin, feed, or preview
     * - Preview allowed for testing
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

        if ($mode !== 'beast') {
            return false;
        }

        return $this->is_preview_allowed();
    }

    /**
     * Check if Beast Mode preview is allowed for current user.
     *
     * Preview allowed when:
     * - beast_apply_all is enabled (affects all users)
     * - User has admin capability (filtered via speedmate_admin_capability)
     * - speedmate_test=1 query parameter is present
     *
     * @return bool True if preview allowed, false otherwise.
     */
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
