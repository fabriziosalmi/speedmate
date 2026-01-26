<?php

declare(strict_types=1);

namespace SpeedMate\Cache;

use SpeedMate\Utils\RateLimiter;
use SpeedMate\Utils\Logger;
use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Container;
use SpeedMate\Utils\CspNonce;
use SpeedMate\Utils\Singleton;

/**
 * Dynamic fragment caching with JavaScript replacement.
 *
 * Allows caching of pages with dynamic content (user-specific, cart, etc.)
 * by replacing dynamic sections with JavaScript-loaded fragments.
 *
 * Features:
 * - [speedmate_dynamic] shortcode for wrapping dynamic content
 * - Client-side fragment loading via REST API
 * - WooCommerce cart hash integration for cache busting
 * - Transient storage with 1-hour TTL
 * - Rate limiting per IP (120 requests/minute)
 * - Automatic fallback on fetch failures
 *
 * Use cases:
 * - User greeting in cached pages
 * - Cart widget in cached shop pages
 * - Dynamic pricing for logged-in users
 * - Personalized recommendations
 *
 * Example:
 * [speedmate_dynamic]Hello <?php echo wp_get_current_user()->display_name; ?>![/speedmate_dynamic]
 *
 * @package SpeedMate\Cache
 * @since 0.2.0
 */
final class DynamicFragments
{
    use Singleton;

    /**
     * Registry of fragments for current request.
     *
     * @var array<string, string>
     */
    private static array $fragments = [];

    /**
     * Counter for generating unique fragment IDs.
     *
     * @var int
     */
    private static int $counter = 0;

    /**
     * Private constructor to enforce Singleton pattern.
     */
    private function __construct()
    {
    }

    /**
     * Register WordPress hooks for dynamic fragments.
     *
     * Hooks:
     * - speedmate_dynamic shortcode: Wrap dynamic content
     * - wp_footer (priority 20): Output loader JavaScript
     * - rest_api_init: Register REST API endpoints
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_shortcode('speedmate_dynamic', [$this, 'render_shortcode']);
        add_action('wp_footer', [$this, 'output_loader_script'], 20);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Render [speedmate_dynamic] shortcode as placeholder.
     *
     * Process:
     * 1. Generate unique fragment ID from content
     * 2. Store fragment in static registry and transient (1h)
     * 3. Return invisible span with data-speedmate-fragment attribute
     * 4. JavaScript will replace span with actual content
     *
     * If feature disabled, executes shortcodes immediately.
     *
     * @param array|string $atts Shortcode attributes (unused).
     * @param string|null $content Shortcode content (the dynamic HTML).
     *
     * @return string Placeholder span or rendered content.
     */
    public function render_shortcode($atts, $content = null): string
    {
        if ($content === null) {
            return '';
        }

        if (!$this->is_enabled()) {
            return do_shortcode($content);
        }

        $id = $this->make_fragment_id((string) $content);
        self::$fragments[$id] = (string) $content;

        set_transient($this->transient_key($id), (string) $content, HOUR_IN_SECONDS);

        return '<span data-speedmate-fragment="' . esc_attr($id) . '" style="display:none"></span>';
    }

    /**
     * Output JavaScript loader script in footer.
     *
     * Injects non-blocking JavaScript that:
     * - Finds all [data-speedmate-fragment] placeholders
     * - Detects WooCommerce cart changes via cookie
     * - Fetches bust token if cart exists
     * - Loads each fragment via REST API
     * - Replaces placeholder with actual HTML
     * - Shows placeholder on fetch failure
     *
     * Script includes CSP nonce for inline execution.
     * Only outputs if fragments were registered.
     *
     * @return void
     */
    public function output_loader_script(): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        if (self::$fragments === []) {
            return;
        }

        $endpoint = esc_url_raw(rest_url('speedmate/v1/fragment/'));
        if ($endpoint === '') {
            return;
        }

        $bust_endpoint = esc_url_raw(rest_url('speedmate/v1/fragment-bust'));
        $script = "(function(){\n" .
            "function getCookie(name){\n" .
            "var v=('; '+document.cookie).split('; '+name+'=');\n" .
            "if(v.length===2)return v.pop().split(';').shift();\n" .
            "return '';\n" .
            "}\n" .
            "var items=document.querySelectorAll('[data-speedmate-fragment]');\n" .
            "if(!items.length)return;\n" .
            "var cartHash=getCookie('woocommerce_cart_hash');\n" .
            "var bustPromise=Promise.resolve('');\n" .
            "if(cartHash){\n" .
            "bustPromise=fetch(" . wp_json_encode($bust_endpoint) . ",{credentials:'same-origin'})\n" .
            ".then(function(r){return r.json();})\n" .
            ".then(function(data){return data&&data.token?data.token:'';})\n" .
            ".catch(function(){return '';});\n" .
            "}\n" .
            "bustPromise.then(function(token){\n" .
            "var suffix=token?('?v='+encodeURIComponent(token)):'';\n" .
            "for(var i=0;i<items.length;i++){\n" .
            "var el=items[i];\n" .
            "var id=el.getAttribute('data-speedmate-fragment');\n" .
            "if(!id)continue;\n" .
            "fetch(" . wp_json_encode($endpoint) . "+id+suffix,{credentials:'same-origin'})\n" .
            ".then(function(r){return r.text();})\n" .
            ".then(function(html){if(html){el.outerHTML=html;}})\n" .
            ".catch(function(){el.style.display='';});\n" .
            "}\n" .
            "});\n" .
            "})();";

        $nonce_attr = CspNonce::attr();
        echo '<script' . $nonce_attr . '>' . $script . '</script>' . "\n";
    }

    /**
     * Register REST API routes for fragment loading.
     *
     * Endpoints:
     * - GET /speedmate/v1/fragment/{id}: Fetch fragment HTML
     * - GET /speedmate/v1/fragment-bust: Get cache bust token
     *
     * Both endpoints are publicly accessible (no auth required).
     *
     * @return void
     */
    public function register_routes(): void
    {
        register_rest_route('speedmate/v1', '/fragment/(?P<id>[a-f0-9]{12,64})', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_fragment'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('speedmate/v1', '/fragment-bust', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_fragment_bust'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle REST API request to fetch fragment HTML.
     *
     * Process:
     * 1. Rate limit check (120 req/min per IP)
     * 2. Validate fragment ID
     * 3. Load from static registry or transient
     * 4. Execute shortcodes
     * 5. Return HTML with proper Content-Type
     *
     * @param \WP_REST_Request $request REST API request with 'id' parameter.
     *
     * @return \WP_REST_Response HTML content or 404/429 error.
     */
    public function handle_fragment(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->allow_request('fragment')) {
            Logger::log('warning', 'rate_limited', ['endpoint' => 'fragment']);
            return new \WP_REST_Response('Rate limited', 429);
        }

        $id = (string) $request->get_param('id');
        if ($id === '') {
            Logger::log('warning', 'invalid_payload', ['endpoint' => 'fragment']);
            return new \WP_REST_Response('Not found', 404);
        }

        $content = self::$fragments[$id] ?? get_transient($this->transient_key($id));
        if (!is_string($content) || $content === '') {
            Logger::log('info', 'fragment_not_found', ['endpoint' => 'fragment']);
            return new \WP_REST_Response('Not found', 404);
        }

        $html = do_shortcode($content);

        return new \WP_REST_Response($html, 200, [
            'Content-Type' => 'text/html; charset=' . get_option('blog_charset'),
        ]);
    }

    /**
     * Handle REST API request to get cache bust token.
     *
     * Generates unique token based on:
     * - WooCommerce cart hash (woocommerce_cart_hash cookie)
     * - WooCommerce session ID
     * - Current user ID
     *
     * Token changes when cart or session changes, ensuring
     * dynamic fragments reflect current state.
     *
     * @param \WP_REST_Request $request REST API request.
     *
     * @return \WP_REST_Response JSON with 'token' or 429 error.
     */
    public function handle_fragment_bust(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->allow_request('fragment_bust')) {
            Logger::log('warning', 'rate_limited', ['endpoint' => 'fragment_bust']);
            return new \WP_REST_Response(['error' => 'rate_limited'], 429);
        }

        $cart_hash = isset($_COOKIE['woocommerce_cart_hash']) ? sanitize_text_field((string) $_COOKIE['woocommerce_cart_hash']) : '';
        $session_key = 'wp_woocommerce_session_' . COOKIEHASH;
        $session = isset($_COOKIE[$session_key]) ? sanitize_text_field((string) $_COOKIE[$session_key]) : '';

        $token = wp_hash($cart_hash . '|' . $session . '|' . get_current_user_id());

        return new \WP_REST_Response([
            'token' => $token,
        ], 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * Generate unique fragment ID from content.
     *
     * Uses:
     * - Fragment content
     * - Current post ID
     * - Incrementing counter
     *
     * Returns 32-character SHA-1 hash prefix.
     *
     * @param string $content Fragment content.
     *
     * @return string Unique fragment identifier.
     */
    private function make_fragment_id(string $content): string
    {
        self::$counter++;
        $seed = $content . '|' . (int) get_the_ID() . '|' . self::$counter;

        return substr(sha1($seed), 0, 32);
    }

    /**
     * Generate transient key for fragment storage.
     *
     * @param string $id Fragment ID.
     *
     * @return string WordPress transient key.
     */
    private function transient_key(string $id): string
    {
        return 'speedmate_fragment_' . $id;
    }

    /**
     * Check if dynamic fragments feature is enabled.
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

    /**
     * Check rate limit for fragment requests.
     *
     * Enforces 120 requests per minute per IP address.
     * Uses RateLimiter utility with scope-specific keys.
     *
     * @param string $scope Rate limit scope ('fragment' or 'fragment_bust').
     *
     * @return bool True if request allowed, false if rate limited.
     */
    private function allow_request(string $scope): bool
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = 'speedmate_rl_' . $scope . '_' . md5($ip);

        return RateLimiter::allow($key, SPEEDMATE_FRAGMENT_RATE_LIMIT, SPEEDMATE_FRAGMENT_RATE_WINDOW);
    }
}
