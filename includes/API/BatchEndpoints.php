<?php

declare(strict_types=1);

namespace SpeedMate\API;

use SpeedMate\Cache\StaticCache;
use SpeedMate\Utils\Stats;
use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Singleton;

final class BatchEndpoints
{
    use Singleton;

    private function __construct()
    {
    }

    private function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('speedmate/v1', '/batch', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_batch'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'requests' => [
                    'required' => true,
                    'type' => 'array',
                    'validate_callback' => [$this, 'validate_requests'],
                ],
            ],
        ]);

        register_rest_route('speedmate/v1', '/cache/(?P<action>flush|warm)', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_cache_action'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('speedmate/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => [$this, 'check_read_permissions'],
        ]);
    }

    public function check_permissions(\WP_REST_Request $request): bool
    {
        // WordPress REST API automatically handles nonce verification via cookies
        // for authenticated requests. The wp_rest nonce is checked automatically
        // by WordPress core when the request includes X-WP-Nonce header.
        
        // Verify user has proper capabilities
        if (!current_user_can('manage_options')) {
            return false;
        }

        // Note: Referer checking removed as it's easily spoofed and provides
        // false sense of security. WordPress REST API nonce is sufficient.
        // Clients must include X-WP-Nonce header with valid nonce.

        return true;
    }

    public function check_read_permissions(): bool
    {
        return current_user_can('read');
    }

    public function validate_requests($requests): bool
    {
        if (!is_array($requests)) {
            return false;
        }

        foreach ($requests as $request) {
            if (!isset($request['method'], $request['path'])) {
                return false;
            }
        }

        return true;
    }

    public function handle_batch(\WP_REST_Request $request): \WP_REST_Response
    {
        $requests = $request->get_param('requests');
        $responses = [];

        foreach ($requests as $single_request) {
            $responses[] = $this->execute_single_request($single_request);
        }

        return new \WP_REST_Response(['responses' => $responses], 200);
    }

    private function execute_single_request(array $request): array
    {
        $method = strtoupper($request['method'] ?? 'GET');
        $path = $request['path'] ?? '';

        // Simple routing
        if (strpos($path, '/speedmate/v1/stats') === 0 && $method === 'GET') {
            $stats = Stats::get();
            return ['status' => 200, 'body' => $stats];
        }

        return ['status' => 404, 'body' => ['error' => 'Not found']];
    }

    public function handle_cache_action(\WP_REST_Request $request): \WP_REST_Response
    {
        $action = $request->get_param('action');

        switch ($action) {
            case 'flush':
                StaticCache::instance()->flush_all();
                return new \WP_REST_Response(['success' => true, 'message' => 'Cache flushed'], 200);

            case 'warm':
                \SpeedMate\Cache\TrafficWarmer::instance()->run();
                return new \WP_REST_Response(['success' => true, 'message' => 'Cache warming started'], 200);

            default:
                return new \WP_REST_Response(['error' => 'Invalid action'], 400);
        }
    }

    public function get_stats(\WP_REST_Request $request): \WP_REST_Response
    {
        $stats = Stats::get();
        $cache = StaticCache::instance();

        $data = array_merge($stats, [
            'cached_pages' => $cache->get_cached_pages_count(),
            'cache_size_bytes' => $cache->get_cache_size_bytes(),
            'cache_size_formatted' => size_format($cache->get_cache_size_bytes()),
        ]);

        return new \WP_REST_Response($data, 200);
    }
}
