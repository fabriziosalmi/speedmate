<?php

declare(strict_types=1);

namespace SpeedMate\Cache;

use SpeedMate\Utils\Stats;
use SpeedMate\Utils\Logger;
use SpeedMate\Utils\Singleton;

/**
 * Main static cache orchestrator.
 * Delegates to specialized classes for different responsibilities.
 *
 * @package SpeedMate\Cache
 * @since 0.4.0
 */
final class StaticCache
{
    use Singleton;

    private float $request_start = 0.0;
    private CacheStorage $storage;
    private CacheRules $rules;
    private CacheTTLManager $ttl;
    private CacheMetadata $metadata;
    private CachePolicy $policy;

    private function __construct()
    {
        $this->storage = new CacheStorage();
        $this->rules = new CacheRules();
        $this->ttl = new CacheTTLManager();
        $this->metadata = new CacheMetadata();
        $this->policy = new CachePolicy();
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
        $instance = self::instance();
        $instance->storage->ensure_cache_dir();
        $instance->rules->write_htaccess();
        Stats::create_table();
        \SpeedMate\Utils\Migration::migrate_stats_to_table();
    }

    public static function deactivate(): void
    {
        self::instance()->rules->remove_htaccess();
    }

    public function start_buffer(): void
    {
        if (!$this->policy->is_cacheable()) {
            return;
        }

        if (ob_get_level() === 0) {
            ob_start();
        }
    }

    public function write_cache(): void
    {
        if (!$this->policy->is_cacheable()) {
            return;
        }

        if (ob_get_level() === 0) {
            return;
        }

        $contents = ob_get_contents();
        if ($contents === false || $contents === '') {
            Logger::log('warning', 'cache_write_empty_buffer');
            return;
        }

        $path = $this->metadata->get_cache_path();
        if ($path === '') {
            Logger::log('warning', 'cache_path_empty');
            return;
        }

        $contents = apply_filters('speedmate_cache_contents', $contents);
        $ttl = $this->ttl->get_ttl();

        if (!$this->storage->write($path, $contents, $ttl)) {
            Logger::log('error', 'cache_write_failed', ['path' => $path]);
            return;
        }

        if ($this->policy->is_warm_request()) {
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

        if (!$this->policy->is_cacheable()) {
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
        Logger::log('info', 'cache_flush_all_triggered');
        $this->storage->flush_all();
        
        // Invalidate cached stats transients
        delete_transient('speedmate_cache_size');
        delete_transient('speedmate_cache_count');
    }

    public function get_nginx_rules(): string
    {
        return $this->rules->get_nginx_rules();
    }

    /**
     * Get cache size in bytes with transient caching.
     * Cached for 5 minutes to reduce filesystem I/O on admin pages.
     *
     * @return int Cache size in bytes.
     */
    public function get_cache_size_bytes(): int
    {
        $transient_key = 'speedmate_cache_size';
        $cached = get_transient($transient_key);

        if ($cached !== false && is_numeric($cached)) {
            return (int) $cached;
        }

        $size = $this->storage->get_size();
        set_transient($transient_key, $size, 5 * MINUTE_IN_SECONDS);

        return $size;
    }

    /**
     * Get cached pages count with transient caching.
     * Cached for 5 minutes to reduce filesystem I/O on admin pages.
     *
     * @return int Number of cached pages.
     */
    public function get_cached_pages_count(): int
    {
        $transient_key = 'speedmate_cache_count';
        $cached = get_transient($transient_key);

        if ($cached !== false && is_numeric($cached)) {
            return (int) $cached;
        }

        $count = $this->storage->count_pages();
        set_transient($transient_key, $count, 5 * MINUTE_IN_SECONDS);

        return $count;
    }

    public function has_cache_for_url(string $url): bool
    {
        $path = $this->metadata->get_cache_path_for_url($url);
        if ($path === '') {
            return false;
        }

        if (!$this->storage->exists($path)) {
            return false;
        }

        return $this->storage->is_valid($path);
    }

    private function purge_url(string $url): void
    {
        $dir = $this->metadata->get_cache_dir_for_url($url);
        if ($dir === '') {
            Logger::log('warning', 'cache_purge_empty_dir', ['url' => $url]);
            return;
        }

        if (strpos($dir, SPEEDMATE_CACHE_DIR) !== 0) {
            Logger::log('error', 'cache_purge_invalid_dir', ['dir' => $dir, 'url' => $url]);
            return;
        }

        $this->storage->delete($dir);
    }
}
