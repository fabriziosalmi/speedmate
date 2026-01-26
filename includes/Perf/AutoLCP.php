<?php

declare(strict_types=1);

namespace SpeedMate\Perf;

use SpeedMate\Utils\Stats;
use SpeedMate\Utils\RateLimiter;
use SpeedMate\Utils\Logger;
use SpeedMate\Utils\Settings;
use SpeedMate\Utils\CspNonce;
use SpeedMate\Utils\Container;
use SpeedMate\Utils\Singleton;

final class AutoLCP
{
    use Singleton;

    private const META_KEY = '_speedmate_lcp_image';

    private function __construct()
    {
    }

    private function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_head', [$this, 'output_preload'], 1);
        add_action('wp_head', [$this, 'output_observer_script'], 99);
    }

    public function register_routes(): void
    {
        register_rest_route('speedmate/v1', '/lcp', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_report'],
            'permission_callback' => '__return_true',
            'args' => [
                'image_url' => [
                    'required' => true,
                    'sanitize_callback' => 'esc_url_raw',
                ],
                'page_url' => [
                    'required' => true,
                    'sanitize_callback' => 'esc_url_raw',
                ],
            ],
        ]);
    }

    public function handle_report(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->is_enabled()) {
            return new \WP_REST_Response(['status' => 'disabled'], 200);
        }

        if (!$this->allow_request('lcp')) {
            Logger::log('warning', 'rate_limited', ['endpoint' => 'lcp']);
            return new \WP_REST_Response(['status' => 'rate_limited'], 429);
        }

        $idempotency = (string) $request->get_header('X-Idempotency-Key');
        if ($idempotency !== '' && $this->is_duplicate($idempotency)) {
            Logger::log('info', 'idempotent_replay', ['endpoint' => 'lcp']);
            return new \WP_REST_Response(['status' => 'ok'], 200);
        }

        $image_url = (string) $request->get_param('image_url');
        $page_url = (string) $request->get_param('page_url');

        if ($image_url === '' || $page_url === '') {
            Logger::log('warning', 'invalid_payload', ['endpoint' => 'lcp']);
            return new \WP_REST_Response(['status' => 'invalid'], 400);
        }

        if (!$this->is_same_host($page_url)) {
            Logger::log('warning', 'forbidden_host', ['endpoint' => 'lcp']);
            return new \WP_REST_Response(['status' => 'forbidden'], 403);
        }

        $post_id = url_to_postid($page_url);
        if (!$post_id) {
            Logger::log('info', 'post_not_found', ['endpoint' => 'lcp']);
            return new \WP_REST_Response(['status' => 'not_found'], 404);
        }

        $current = (string) get_post_meta($post_id, self::META_KEY, true);
        if ($current !== $image_url) {
            update_post_meta($post_id, self::META_KEY, $image_url);
            Stats::increment('lcp_preloads');
        }

        return new \WP_REST_Response(['status' => 'ok'], 200);
    }

    public function output_preload(): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        $image_url = (string) get_post_meta($post_id, self::META_KEY, true);
        if ($image_url === '') {
            return;
        }

        echo '<link rel="preload" as="image" href="' . esc_url($image_url) . '">' . "\n";
    }

    public function output_observer_script(): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        if (!is_singular()) {
            return;
        }

        $endpoint = esc_url_raw(rest_url('speedmate/v1/lcp'));
        $page_url = esc_url_raw(get_permalink());

        if ($endpoint === '' || $page_url === '') {
            return;
        }

        $script = "(function(){\n" .
            "if(!('PerformanceObserver'in window))return;\n" .
            "var sent=false;\n" .
            "var endpoint=" . wp_json_encode($endpoint) . ";\n" .
            "var pageUrl=" . wp_json_encode($page_url) . ";\n" .
            "try{\n" .
            "var observer=new PerformanceObserver(function(list){\n" .
            "var entries=list.getEntries();\n" .
            "var last=entries[entries.length-1];\n" .
            "if(!last||!last.element||sent)return;\n" .
            "var el=last.element;\n" .
            "if(el.tagName&&el.tagName.toLowerCase()==='img'&&el.currentSrc){\n" .
            "sent=true;\n" .
            "var payload=JSON.stringify({image_url:el.currentSrc,page_url:pageUrl});\n" .
            "if(navigator.sendBeacon){\n" .
            "var blob=new Blob([payload],{type:'application/json'});\n" .
            "navigator.sendBeacon(endpoint,blob);\n" .
            "}else{\n" .
            "fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:payload,keepalive:true});\n" .
            "}\n" .
            "}\n" .
            "});\n" .
            "observer.observe({type:'largest-contentful-paint',buffered:true});\n" .
            "}catch(e){}\n" .
            "})();";

        $nonce_attr = CspNonce::attr();
        echo '<script' . $nonce_attr . '>' . $script . '</script>' . "\n";
    }

    private function is_enabled(): bool
    {
        $settings = Settings::get();
        $mode = $settings['mode'] ?? 'disabled';

        return in_array($mode, ['safe', 'beast'], true);
    }

    private function allow_request(string $scope): bool
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = 'speedmate_rl_' . $scope . '_' . md5($ip);

        return RateLimiter::allow($key, 60, 60);
    }

    private function is_duplicate(string $idempotency_key): bool
    {
        $key = 'speedmate_idem_' . md5($idempotency_key);
        if (get_transient($key)) {
            return true;
        }

        set_transient($key, 1, 10 * MINUTE_IN_SECONDS);

        return false;
    }

    private function is_same_host(string $url): bool
    {
        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $url_host = wp_parse_url($url, PHP_URL_HOST);

        return $home_host && $url_host && strtolower((string) $home_host) === strtolower((string) $url_host);
    }
}
