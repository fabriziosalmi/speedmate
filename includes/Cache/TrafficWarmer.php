<?php

declare(strict_types=1);

namespace SpeedMate\Cache;

use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Logger;
use SpeedMate\Utils\Container;
use SpeedMate\Utils\Singleton;

/**
 * Traffic-based cache warming system.
 *
 * Automatically warms static cache for most-visited pages based on
 * real traffic patterns. Ensures popular pages stay cached.
 *
 * Features:
 * - Tracks page hits in transient (2h rolling window)
 * - Scheduled cron job for warming top pages
 * - Atomic hit counting with retry logic (race condition safe)
 * - Configurable frequency and max URLs
 * - Non-blocking background requests
 * - Automatic lock management for cron jobs
 *
 * Process:
 * 1. Track GET requests to public pages
 * 2. Store hit counts in transient
 * 3. Cron job sorts by popularity
 * 4. Warm top N uncached pages
 * 5. Clear hit counts after warming
 *
 * Configuration:
 * - warmer_enabled: Enable/disable warmer
 * - warmer_frequency: Cron schedule (hourly, twicedaily, daily)
 * - warmer_max_urls: Max pages to warm per run (default 20)
 *
 * @package SpeedMate\Cache
 * @since 0.2.0
 */
final class TrafficWarmer
{
    use Singleton;

    /**
     * Transient key for storing hit counts.
     *
     * @var string
     */
    private const TRANSIENT_KEY = 'speedmate_hits';

    /**
     * WordPress cron hook name.
     *
     * @var string
     */
    private const CRON_HOOK = 'speedmate_warm_cron';

    /**
     * Private constructor to enforce Singleton pattern.
     */
    private function __construct()
    {
    }

    /**
     * Register WordPress hooks for traffic warming.
     *
     * Hooks:
     * - template_redirect (priority 5): Track page hits
     * - speedmate_warm_cron: Run warming job
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_action('template_redirect', [$this, 'track_hit'], 5);
        add_action(self::CRON_HOOK, [$this, 'run']);
    }

    /**
     * Schedule warming cron job on plugin activation.
     *
     * Checks settings and schedules cron event if:
     * - warmer_enabled is true
     * - Cron event not already scheduled
     *
     * Called from Plugin activation hook.
     *
     * @return void
     */
    public static function activate(): void
    {
        $settings = Settings::get();
        $enabled = $settings['warmer_enabled'] ?? true;
        $frequency = $settings['warmer_frequency'] ?? 'hourly';

        if ($enabled && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, $frequency, self::CRON_HOOK);
        }
    }

    /**
     * Unschedule warming cron job on plugin deactivation.
     *
     * Cleans up cron event to prevent orphaned jobs.
     * Called from Plugin deactivation hook.
     *
     * @return void
     */
    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Track page hit for warming prioritization.
     *
     * Uses atomic increment with lock and retry logic to prevent
     * race conditions under high concurrency.
     *
     * Process:
     * 1. Validate trackable request (public, GET, no query)
     * 2. Acquire lock (max 3 attempts with 10ms backoff)
     * 3. Increment hit count in transient
     * 4. Release lock
     * 5. Update stats
     *
     * Lock timeout: 2 seconds
     * Transient TTL: 2 hours (rolling window)
     *
     * @return void
     */
    public function track_hit(): void
    {
        if (!$this->is_trackable_request()) {
            return;
        }

        $url = home_url($this->get_request_path());
        if ($url === '') {
            return;
        }

        // Attempt atomic increment with simple locking
        $max_attempts = 3;
        $attempt = 0;
        
        while ($attempt < $max_attempts) {
            // Try to acquire lock with timeout
            if ($this->acquire_hit_lock()) {
                $hits = get_transient(self::TRANSIENT_KEY);
                if (!is_array($hits)) {
                    $hits = [];
                }

                $hits[$url] = isset($hits[$url]) ? ((int) $hits[$url] + 1) : 1;

                set_transient(self::TRANSIENT_KEY, $hits, HOUR_IN_SECONDS * 2);
                
                $this->release_hit_lock();
                break;
            }
            
            // Wait briefly and retry
            $attempt++;
            if ($attempt < $max_attempts) {
                usleep(10000); // 10ms
            } else {
                Logger::log('warning', 'hit_tracking_retry_exhausted', ['url' => $url, 'attempts' => $max_attempts]);
            }
        }

        \SpeedMate\Utils\Stats::add_time_saved_from_hit();
    }

    /**
     * Acquire lock for atomic hit tracking.
     *
     * Uses WordPress options API (not transients) for better atomicity.
     * add_option() fails if option exists, providing simple mutex.
     *
     * Timeout handling:
     * - If lock exists and < 2s old: Returns false (locked)
     * - If lock exists and >= 2s old: Updates lock (expired)
     * - If lock doesn't exist: Creates lock
     *
     * @return bool True if lock acquired, false if already locked.
     */
    private function acquire_hit_lock(): bool
    {
        $lock_key = 'speedmate_hit_lock';
        $lock_timeout = 2; // seconds
        
        // Try to add lock (fails if already exists)
        $acquired = add_option($lock_key, time(), '', false);
        
        if ($acquired) {
            return true;
        }
        
        // Check if existing lock is expired
        $lock_time = get_option($lock_key);
        if (is_numeric($lock_time) && (time() - (int) $lock_time) > $lock_timeout) {
            // Expired lock, update it
            update_option($lock_key, time(), false);
            return true;
        }
        
        return false;
    }

    /**
     * Release atomic hit tracking lock.
     *
     * Deletes the option-based lock to allow other requests
     * to increment hit counts.
     *
     * @return void
     */
    private function release_hit_lock(): void
    {
        delete_option('speedmate_hit_lock');
    }

    /**
     * Execute warming job for top traffic pages.
     *
     * Process:
     * 1. Check cron lock (skip if locked)
     * 2. Acquire cron lock (5min)
     * 3. Load hit counts from transient
     * 4. Sort by popularity (descending)
     * 5. Select top N pages (warmer_max_urls)
     * 6. Skip already cached pages
     * 7. Request each uncached page with speedmate_warm=1
     * 8. Delete hit counts
     * 9. Release lock
     *
     * Requests are non-blocking with 3s timeout.
     * Uses custom User-Agent for identification.
     *
     * @return void
     */
    public function run(): void
    {
        if ($this->has_lock()) {
            Logger::log('info', 'warm_skipped', ['reason' => 'lock']);
            return;
        }

        $this->set_lock();

        $hits = get_transient(self::TRANSIENT_KEY);
        if (!is_array($hits) || $hits === []) {
            Logger::log('info', 'warm_no_hits');
            $this->release_lock();
            return;
        }

        arsort($hits);
        
        $settings = Settings::get();
        $max_urls = (int) ($settings['warmer_max_urls'] ?? 20);
        $top = array_slice($hits, 0, $max_urls, true);

        foreach ($top as $url => $count) {
            if (!is_string($url) || $url === '') {
                continue;
            }

            if (StaticCache::instance()->has_cache_for_url($url)) {
                continue;
            }

            $warm_url = add_query_arg('speedmate_warm', '1', $url);
            $response = wp_remote_get($warm_url, [
                'timeout' => 3,
                'blocking' => false,
                'user-agent' => 'SpeedMate/1.0; ' . home_url('/'),
            ]);
            
            if (is_wp_error($response)) {
                Logger::log('warning', 'warm_request_failed', ['url' => $url, 'error' => $response->get_error_message()]);
            } else {
                Logger::log('info', 'warm_request_sent', ['url' => $url, 'hits' => $count]);
            }
        }

        delete_transient(self::TRANSIENT_KEY);
        $this->release_lock();
    }

    /**
     * Check if request should be tracked for warming.
     *
     * Tracks only:
     * - GET requests
     * - Public pages (not admin/logged-in/feed/preview/search)
     * - No query parameters
     * - SpeedMate not disabled
     *
     * @return bool True if trackable, false otherwise.
     */
    private function is_trackable_request(): bool
    {
        if (is_admin() || is_user_logged_in() || is_feed() || is_preview() || is_search()) {
            return false;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET') {
            return false;
        }

        if (!empty($_SERVER['QUERY_STRING'])) {
            return false;
        }

        $settings = Settings::get();
        $mode = $settings['mode'] ?? 'disabled';

        return $mode !== 'disabled';
    }

    /**
     * Check if warming cron job is locked.
     *
     * @return bool True if locked, false otherwise.
     */
    private function has_lock(): bool
    {
        return (bool) get_transient('speedmate_warm_lock');
    }

    /**
     * Acquire warming cron job lock.
     *
     * Lock prevents concurrent warming runs.
     * TTL: 5 minutes
     *
     * @return void
     */
    private function set_lock(): void
    {
        set_transient('speedmate_warm_lock', 1, 5 * MINUTE_IN_SECONDS);
    }

    /**
     * Release warming cron job lock.
     *
     * @return void
     */
    private function release_lock(): void
    {
        delete_transient('speedmate_warm_lock');
    }

    /**
     * Get normalized request path from REQUEST_URI.
     *
     * Strips query string and returns path only.
     *
     * @return string Request path (e.g., "/blog/post-name/").
     */
    private function get_request_path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = strtok($uri, '?');

        return $path !== false ? $path : '/';
    }
}
