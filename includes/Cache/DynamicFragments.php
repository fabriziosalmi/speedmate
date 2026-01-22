<?php

declare(strict_types=1);

namespace SpeedMate\Cache;

use SpeedMate\Utils\RateLimiter;
use SpeedMate\Utils\Logger;
use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Container;
use SpeedMate\Utils\CspNonce;

final class DynamicFragments
{
    private static ?DynamicFragments $instance = null;
    private static array $fragments = [];
    private static int $counter = 0;

    private function __construct()
    {
    }

    public static function instance(): DynamicFragments
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
        add_shortcode('speedmate_dynamic', [$this, 'render_shortcode']);
        add_action('wp_footer', [$this, 'output_loader_script'], 20);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

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

    private function make_fragment_id(string $content): string
    {
        self::$counter++;
        $seed = $content . '|' . (int) get_the_ID() . '|' . self::$counter;

        return substr(sha1($seed), 0, 32);
    }

    private function transient_key(string $id): string
    {
        return 'speedmate_fragment_' . $id;
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

    private function allow_request(string $scope): bool
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = 'speedmate_rl_' . $scope . '_' . md5($ip);

        return RateLimiter::allow($key, 120, 60);
    }
}
