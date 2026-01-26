<?php

declare(strict_types=1);

namespace SpeedMate\API;

use SpeedMate\Cache\StaticCache;
use SpeedMate\Utils\Stats;
use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Singleton;

/**
 * REST API batch endpoints for SpeedMate operations.
 *
 * Features:
 * - Batch processing (max 10 requests per batch)
 * - Cache management (flush, warm)
 * - Statistics retrieval
 * - DoS protection (MAX_BATCH_SIZE, MIN_MEMORY_MB)
 * - Capability-based authorization
 *
 * Endpoints:
 * - POST /speedmate/v1/batch - Execute multiple operations
 * - POST /speedmate/v1/cache/{flush|warm} - Cache actions
 * - GET /speedmate/v1/stats - Retrieve statistics
 *
 * Security:
 * - Requires manage_options capability for write operations
 * - Automatic nonce verification via WP REST API
 * - Memory availability checks
 * - Request validation
 *
 * @package SpeedMate\API
 * @since 0.4.0
 */
final class BatchEndpoints
{
    use Singleton;

    private const MAX_BATCH_SIZE = 10;
    private const MIN_MEMORY_MB = 32;

    /**
     * Private constructor for Singleton pattern.
     */
    private function __construct()
    {
    }

    /**
     * Register WordPress hooks.
     *
     * Hooks:
     * - rest_api_init: Register REST routes
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes.
     *
     * Routes:
     * - POST /speedmate/v1/batch
     *   - Execute multiple operations in batch
     *   - Requires: requests array
     *   - Max batch size: 10
     *
     * - POST /speedmate/v1/cache/{flush|warm}
     *   - Cache management actions
     *   - flush: Clear cache
     *   - warm: Warm cache
     *
     * - GET /speedmate/v1/stats
     *   - Retrieve cache statistics
     *   - Read-only endpoint
     *
     * @return void
     */
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

    /**
     * Check if user has permission for write operations.
     *
     * Security:
     * - Requires manage_options capability
     * - Automatic nonce verification by WordPress REST API
     * - X-WP-Nonce header checked automatically
     *
     * @param \WP_REST_Request $request REST request object.
     *
     * @return bool True if user can manage options, false otherwise.
     */
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

    /**
     * Check if user has permission for read-only operations.
     *
     * Read-only endpoints (like /stats) require 'read' capability.
     *
     * @return bool True if user can read, false otherwise.
     */
    public function check_read_permissions(): bool
    {
        return current_user_can('read');
    }

    /**
     * Validate batch requests array.
     *
     * Validation:
     * - Must be array
     * - Max batch size: 10 requests (DoS protection)
     * - Each request must have 'method' and 'path'
     *
     * @param mixed $requests Batch requests to validate.
     *
     * @return bool True if valid, false otherwise.
     */
    public function validate_requests($requests): bool
    {
        if (!is_array($requests)) {
            return false;
        }

        // Enforce batch size limit to prevent DoS
        if (count($requests) > self::MAX_BATCH_SIZE) {
            return false;
        }

        foreach ($requests as $request) {
            if (!isset($request['method'], $request['path'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Handle batch operation request.
     *
     * Process:
     * 1. Validate batch size (max 10)
     * 2. Check memory availability (min 32MB)
     * 3. Execute each request sequentially
     * 4. Collect responses
     *
     * Error codes:
     * - 400: Batch size exceeded
     * - 503: Insufficient memory
     *
     * @param \WP_REST_Request $request REST request with 'requests' param.
     *
     * @return \WP_REST_Response Response with results array or error.
     */
    public function handle_batch(\WP_REST_Request $request): \WP_REST_Response
    {
        $requests = $request->get_param('requests');
        
        // Double-check batch size (should already be validated)
        if (count($requests) > self::MAX_BATCH_SIZE) {
            return new \WP_REST_Response([
                'error' => sprintf('Batch size exceeds limit of %d requests', self::MAX_BATCH_SIZE),
            ], 400);
        }

        // Check available memory
        if (!$this->has_sufficient_memory()) {
            return new \WP_REST_Response([
                'error' => 'Insufficient memory available for batch processing',
            ], 503);
        }

        $responses = [];

        foreach ($requests as $single_request) {
            $responses[] = $this->execute_single_request($single_request);
        }

        return new \WP_REST_Response(['responses' => $responses], 200);
    }

    /**
     * Check if sufficient memory is available for batch processing.
     *
     * @return bool True if at least 32MB of memory is available.
     */
    private function has_sufficient_memory(): bool
    {
        $memory_limit = ini_get('memory_limit');
        if ($memory_limit === '-1') {
            // Unlimited memory
            return true;
        }

        // Convert to bytes
        $limit_bytes = $this->convert_to_bytes($memory_limit);
        $used_bytes = memory_get_usage(true);
        $available_bytes = $limit_bytes - $used_bytes;

        // Require at least 32MB available
        return $available_bytes >= (self::MIN_MEMORY_MB * 1024 * 1024);
    }

    /**
     * Convert PHP ini memory value to bytes.
     *
     * @param string $value Memory value (e.g., '128M', '1G').
     * @return int Bytes.
     */
    private function convert_to_bytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1] ?? '');
        $num = (int) $value;

        switch ($last) {
            case 'g':
                $num *= 1024;
                // Fall through
            case 'm':
                $num *= 1024;
                // Fall through
            case 'k':
                $num *= 1024;
        }

        return $num;
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

    /**
     * Handle cache action (flush or warm).
     *
     * Actions:
     * - flush: Clear all cached pages
     * - warm: Start cache warming process
     *
     * @param \WP_REST_Request $request REST request with 'action' param.
     *
     * @return \WP_REST_Response Response with success message or error.
     */
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

    /**
     * Get SpeedMate statistics.
     *
     * Returns:
     * - All Stats data (hits, misses, hit rate, etc.)
     * - Cached pages count
     * - Cache size (bytes and formatted)
     *
     * @param \WP_REST_Request $request REST request.
     *
     * @return \WP_REST_Response Response with statistics data.
     */
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
