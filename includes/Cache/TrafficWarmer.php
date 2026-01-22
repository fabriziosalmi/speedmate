<?php

declare(strict_types=1);

namespace SpeedMate\Cache;

use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Logger;
use SpeedMate\Utils\Container;

final class TrafficWarmer
{
    private const TRANSIENT_KEY = 'speedmate_hits';
    private const CRON_HOOK = 'speedmate_warm_cron';

    private static ?TrafficWarmer $instance = null;

    private function __construct()
    {
    }

    public static function instance(): TrafficWarmer
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
        add_action('template_redirect', [$this, 'track_hit'], 5);
        add_action(self::CRON_HOOK, [$this, 'run']);
    }

    public static function activate(): void
    {
        $settings = Settings::get();
        $enabled = $settings['warmer_enabled'] ?? true;
        $frequency = $settings['warmer_frequency'] ?? 'hourly';

        if ($enabled && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, $frequency, self::CRON_HOOK);
        }
    }

    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    public function track_hit(): void
    {
        if (!$this->is_trackable_request()) {
            return;
        }

        $url = home_url($this->get_request_path());
        if ($url === '') {
            return;
        }

        $hits = get_transient(self::TRANSIENT_KEY);
        if (!is_array($hits)) {
            $hits = [];
        }

        $hits[$url] = isset($hits[$url]) ? ((int) $hits[$url] + 1) : 1;

        set_transient(self::TRANSIENT_KEY, $hits, HOUR_IN_SECONDS * 2);

        \SpeedMate\Utils\Stats::add_time_saved_from_hit();
    }

    public function run(): void
    {
        if ($this->has_lock()) {
            Logger::log('info', 'warm_skipped', ['reason' => 'lock']);
            return;
        }

        $this->set_lock();

        $hits = get_transient(self::TRANSIENT_KEY);
        if (!is_array($hits) || $hits === []) {
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
            wp_remote_get($warm_url, [
                'timeout' => 3,
                'blocking' => false,
                'user-agent' => 'SpeedMate/1.0; ' . home_url('/'),
            ]);
        }

        delete_transient(self::TRANSIENT_KEY);
        $this->release_lock();
    }

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

    private function has_lock(): bool
    {
        return (bool) get_transient('speedmate_warm_lock');
    }

    private function set_lock(): void
    {
        set_transient('speedmate_warm_lock', 1, 5 * MINUTE_IN_SECONDS);
    }

    private function release_lock(): void
    {
        delete_transient('speedmate_warm_lock');
    }

    private function get_request_path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = strtok($uri, '?') ?: '/';

        return $path === '' ? '/' : $path;
    }
}
