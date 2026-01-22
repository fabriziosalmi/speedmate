<?php

declare(strict_types=1);

namespace SpeedMate\Cache;

use SpeedMate\Utils\Filesystem;
use SpeedMate\Utils\Stats;
use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Logger;

final class StaticCache
{
    private static ?StaticCache $instance = null;
    private float $request_start = 0.0;

    private function __construct()
    {
    }

    public static function instance(): StaticCache
    {
        // For test injection - only available when Container is loaded
        if (class_exists('\SpeedMate\Utils\Container')) {
            $override = \SpeedMate\Utils\Container::get(self::class);
            if ($override instanceof self) {
                return $override;
            }
        }

        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->register_hooks();
        }

        return self::$instance;
    }

    private function register_hooks(): void
    {
        add_action('template_redirect', [$this, 'mark_request_start'], -1);
        add_action('template_redirect', [$this, 'start_buffer'], 0);
        add_action('shutdown', [$this, 'write_cache'], 0);
        add_action('shutdown', [$this, 'record_timing'], 1);
        add_action('save_post', [$this, 'purge_post_cache'], 10, 2);
        add_action('delete_post', [$this, 'purge_post_cache'], 10, 2);
    }

    public static function activate(): void
    {
        self::instance()->ensure_cache_dir();
        self::instance()->write_htaccess_rules();
        Stats::create_table();
        \SpeedMate\Utils\Migration::migrate_stats_to_table();
    }

    public static function deactivate(): void
    {
        self::instance()->remove_htaccess_rules();
    }

    public function start_buffer(): void
    {
        if (!$this->is_cacheable_request()) {
            return;
        }

        if (ob_get_level() === 0) {
            ob_start();
        }
    }

    public function write_cache(): void
    {
        if (!$this->is_cacheable_request()) {
            return;
        }

        if (ob_get_level() === 0) {
            return;
        }

        $contents = ob_get_contents();
        if ($contents === false || $contents === '') {
            return;
        }

        $path = $this->get_cache_path();
        if ($path === '') {
            Logger::log('warning', 'cache_path_empty');
            return;
        }

        $contents = apply_filters('speedmate_cache_contents', $contents);
        if (!Filesystem::put_contents($path, $contents)) {
            Logger::log('warning', 'cache_write_failed', ['path' => $path]);
            return;
        }

        // Write metadata file with TTL
        $meta = [
            'created' => time(),
            'ttl' => $this->get_cache_ttl(),
        ];
        $meta_path = $path . '.meta';
        Filesystem::put_contents($meta_path, wp_json_encode($meta));

        if ($this->is_warm_request()) {
            Stats::increment('warmed_pages');
        }
    }

    public function mark_request_start(): void
    {
        if ($this->request_start === 0.0) {
            $this->request_start = microtime(true);
        }
    }

    public function record_timing(): void
    {
        if ($this->request_start <= 0) {
            return;
        }

        if (!$this->is_cacheable_request()) {
            return;
        }

        $elapsed_ms = (int) round((microtime(true) - $this->request_start) * 1000);
        if ($elapsed_ms <= 0) {
            return;
        }

        Stats::record_uncached_time($elapsed_ms);
    }

    public function purge_post_cache(int $post_id, $post = null): void
    {
        if (wp_is_post_revision($post_id)) {
            return;
        }

        $status = get_post_status($post_id);
        if (!in_array($status, ['publish', 'private'], true)) {
            return;
        }

        $permalink = get_permalink($post_id);
        if (is_string($permalink) && $permalink !== '') {
            $this->purge_url($permalink);
        }

        $this->purge_url(home_url('/'));
        $this->purge_url(home_url('/feed/'));

        $taxonomies = get_object_taxonomies(get_post_type($post_id), 'names');
        if (is_array($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                $terms = get_the_terms($post_id, $taxonomy);
                if (is_array($terms)) {
                    foreach ($terms as $term) {
                        $term_link = get_term_link($term);
                        if (!is_wp_error($term_link)) {
                            $this->purge_url((string) $term_link);
                        }
                    }
                }
            }
        }
    }

    public function flush_all(): void
    {
        if (!Filesystem::exists(SPEEDMATE_CACHE_DIR)) {
            return;
        }

        Filesystem::delete(SPEEDMATE_CACHE_DIR, true);
    }

    public function get_nginx_rules(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            $host = '$host';
        }

        return "# SpeedMate static cache\n" .
            "location / {\n" .
            "    if (\$request_method = GET) {\n" .
            "        if (\$query_string = \"\") {\n" .
            "            set \$cache_file /wp-content/cache/speedmate/{$host}\$uri/index.html;\n" .
            "            if (-f \$document_root\$cache_file) {\n" .
            "                rewrite ^ \$cache_file break;\n" .
            "            }\n" .
            "        }\n" .
            "    }\n" .
            "    try_files \$uri \$uri/ /index.php?\$args;\n" .
            "}\n";
    }

    public function get_cache_size_bytes(): int
    {
        if (!is_dir(SPEEDMATE_CACHE_DIR)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(SPEEDMATE_CACHE_DIR, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    public function get_cached_pages_count(): int
    {
        if (!is_dir(SPEEDMATE_CACHE_DIR)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(SPEEDMATE_CACHE_DIR, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'index.html') {
                $count++;
            }
        }

        return $count;
    }

    public function has_cache_for_url(string $url): bool
    {
        $path = $this->get_cache_path_for_url($url);
        if ($path === '') {
            return false;
        }

        if (!Filesystem::exists($path)) {
            return false;
        }

        return $this->is_cache_valid($path);
    }

    private function is_cache_valid(string $path): bool
    {
        $meta_path = $path . '.meta';
        if (!Filesystem::exists($meta_path)) {
            // No metadata means old cache file, consider invalid
            return false;
        }

        $meta_content = Filesystem::get_contents($meta_path);
        if ($meta_content === false) {
            return false;
        }

        $meta = json_decode($meta_content, true);
        if (!is_array($meta) || !isset($meta['created'], $meta['ttl'])) {
            return false;
        }

        $age = time() - (int) $meta['created'];
        return $age < (int) $meta['ttl'];
    }

    private function get_cache_ttl(): int
    {
        $settings = Settings::get();

        // Determine content type and return appropriate TTL
        if (is_front_page()) {
            return (int) ($settings['cache_ttl_homepage'] ?? 3600);
        }

        if (is_singular('post')) {
            return (int) ($settings['cache_ttl_posts'] ?? 7 * DAY_IN_SECONDS);
        }

        if (is_singular('page')) {
            return (int) ($settings['cache_ttl_pages'] ?? 30 * DAY_IN_SECONDS);
        }

        // Default TTL for other content types
        return (int) ($settings['cache_ttl'] ?? 7 * DAY_IN_SECONDS);
    }

    private function get_cache_path(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = strtok($uri, '?') ?: '/';
        $uri = trim($uri, '/');

        $path = trailingslashit(SPEEDMATE_CACHE_DIR . '/' . $host . '/' . $uri);

        return $path . 'index.html';
    }

    private function get_cache_path_for_url(string $url): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!is_string($path)) {
            return '';
        }

        $path = trim($path, '/');
        $cache_path = trailingslashit(SPEEDMATE_CACHE_DIR . '/' . $host . '/' . $path);

        return $cache_path . 'index.html';
    }

    private function purge_url(string $url): void
    {
        $path = $this->get_cache_path_for_url($url);
        if ($path === '') {
            return;
        }

        if (strpos($path, SPEEDMATE_CACHE_DIR) !== 0) {
            return;
        }

        $dir = dirname($path);
        if (Filesystem::exists($dir)) {
            Filesystem::delete($dir, true);
        }
    }

    private function ensure_cache_dir(): void
    {
        if (!Filesystem::exists(SPEEDMATE_CACHE_DIR)) {
            Filesystem::put_contents(trailingslashit(SPEEDMATE_CACHE_DIR) . 'index.html', "");
            Filesystem::delete(trailingslashit(SPEEDMATE_CACHE_DIR) . 'index.html');
        }
    }

    private function write_htaccess_rules(): void
    {
        if (!function_exists('insert_with_markers')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }
        if (!function_exists('get_home_path')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $rules = $this->get_htaccess_rules();
        $htaccess = trailingslashit(get_home_path()) . '.htaccess';
        insert_with_markers($htaccess, 'SpeedMate', $rules);
    }

    private function remove_htaccess_rules(): void
    {
        if (!function_exists('insert_with_markers')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }
        if (!function_exists('get_home_path')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $htaccess = trailingslashit(get_home_path()) . '.htaccess';
        insert_with_markers($htaccess, 'SpeedMate', []);
    }

    private function get_htaccess_rules(): array
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            $host = '%{HTTP_HOST}';
        }

        return [
            '<IfModule mod_rewrite.c>',
            'RewriteEngine On',
            'RewriteCond %{REQUEST_METHOD} =GET',
            'RewriteCond %{QUERY_STRING} ^$',
            'RewriteCond %{REQUEST_URI} !^/wp-admin',
            'RewriteCond %{REQUEST_URI} !^/wp-json',
            'RewriteCond %{HTTP_COOKIE} !wordpress_logged_in',
            'RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/speedmate/' . $host . '%{REQUEST_URI}/index.html -f',
            'RewriteRule ^(.*)$ /wp-content/cache/speedmate/' . $host . '%{REQUEST_URI}/index.html [L]',
            '</IfModule>',
            '<IfModule mod_deflate.c>',
            'AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json image/svg+xml',
            '</IfModule>',
        ];
    }

    private function is_cacheable_request(): bool
    {
        if (is_admin() || is_user_logged_in()) {
            return false;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET') {
            return false;
        }

        if (!empty($_SERVER['QUERY_STRING']) && !$this->is_warm_request()) {
            return false;
        }

        if (is_feed() || is_trackback() || is_preview() || is_search()) {
            return false;
        }

        // Check URL exclusions
        if ($this->is_excluded_url()) {
            return false;
        }

        // Check cookie exclusions
        if ($this->has_excluded_cookies()) {
            return false;
        }

        $settings = Settings::get();
        $mode = $settings['mode'] ?? 'disabled';

        if ($mode === 'disabled') {
            return false;
        }

        return true;
    }

    private function is_excluded_url(): bool
    {
        $settings = Settings::get();
        $patterns = $settings['cache_exclude_urls'] ?? [];
        
        if (empty($patterns)) {
            return false;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    private function has_excluded_cookies(): bool
    {
        $settings = Settings::get();
        $cookies = $settings['cache_exclude_cookies'] ?? [];
        
        if (empty($cookies)) {
            return false;
        }

        foreach ($cookies as $cookie_name) {
            if (isset($_COOKIE[$cookie_name])) {
                return true;
            }
        }

        return false;
    }

    private function is_warm_request(): bool
    {
        $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
        return $query === 'speedmate_warm=1';
    }
}
