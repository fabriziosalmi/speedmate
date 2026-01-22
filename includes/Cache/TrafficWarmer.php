<?php

declare(strict_types=1);

namespace SpeedMate\Cache;

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
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK);
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
    }

    public function run(): void
    {
        $hits = get_transient(self::TRANSIENT_KEY);
        if (!is_array($hits) || $hits === []) {
            return;
        }

        arsort($hits);
        $top = array_slice($hits, 0, 20, true);

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

        $settings = get_option(SPEEDMATE_OPTION_KEY, []);
        $mode = is_array($settings) ? ($settings['mode'] ?? 'disabled') : 'disabled';

        return $mode !== 'disabled';
    }

    private function get_request_path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = strtok($uri, '?') ?: '/';

        return $path === '' ? '/' : $path;
    }
}
