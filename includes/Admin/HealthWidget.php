<?php

declare(strict_types=1);

namespace SpeedMate\Admin;

use SpeedMate\Cache\StaticCache;
use SpeedMate\Utils\Stats;
use SpeedMate\Utils\Settings;

final class HealthWidget
{
    private static ?HealthWidget $instance = null;

    private function __construct()
    {
    }

    public static function instance(): HealthWidget
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->register_hooks();
        }

        return self::$instance;
    }

    private function register_hooks(): void
    {
        add_action('wp_dashboard_setup', [$this, 'register_widget']);
    }

    public function register_widget(): void
    {
        wp_add_dashboard_widget(
            'speedmate_health',
            '⚡ SpeedMate Health Check',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        $checks = [
            'cache_dir' => $this->check_cache_dir(),
            'htaccess' => $this->check_htaccess(),
            'hit_rate' => $this->check_hit_rate(),
            'mode' => $this->check_mode(),
        ];

        $overall = $this->get_overall_status($checks);

        echo '<div class="speedmate-health-widget">';
        echo '<div class="speedmate-status speedmate-status-' . esc_attr($overall) . '">';
        echo '<span class="dashicons dashicons-' . esc_attr($this->get_icon($overall)) . '"></span> ';
        echo '<strong>' . esc_html($this->get_status_text($overall)) . '</strong>';
        echo '</div>';

        echo '<ul class="speedmate-checks">';
        foreach ($checks as $check) {
            echo '<li class="speedmate-check-' . esc_attr($check['status']) . '">';
            echo '<span class="dashicons dashicons-' . esc_attr($this->get_icon($check['status'])) . '"></span> ';
            echo esc_html($check['message']);
            echo '</li>';
        }
        echo '</ul>';

        $stats = Stats::get();
        echo '<div class="speedmate-quick-stats">';
        echo '<p><strong>Quick Stats:</strong></p>';
        echo '<ul>';
        echo '<li>Cached Pages: ' . esc_html((string) StaticCache::instance()->get_cached_pages_count()) . '</li>';
        echo '<li>Cache Size: ' . esc_html(size_format(StaticCache::instance()->get_cache_size_bytes())) . '</li>';
        echo '<li>Time Saved: ' . esc_html(gmdate('H:i:s', (int) ($stats['time_saved_ms'] / 1000))) . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '<style>
            .speedmate-health-widget { padding: 10px 0; }
            .speedmate-status { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
            .speedmate-status-good { background: #d4edda; color: #155724; }
            .speedmate-status-warning { background: #fff3cd; color: #856404; }
            .speedmate-status-error { background: #f8d7da; color: #721c24; }
            .speedmate-checks { list-style: none; margin: 0; padding: 0; }
            .speedmate-checks li { padding: 5px 0; }
            .speedmate-check-good { color: #155724; }
            .speedmate-check-warning { color: #856404; }
            .speedmate-check-error { color: #721c24; }
            .speedmate-quick-stats { margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd; }
            .speedmate-quick-stats ul { margin: 5px 0 0 20px; }
        </style>';

        echo '</div>';
    }

    private function check_cache_dir(): array
    {
        $writable = is_writable(SPEEDMATE_CACHE_DIR);
        return [
            'status' => $writable ? 'good' : 'error',
            'message' => $writable ? 'Cache directory is writable' : 'Cache directory is not writable',
        ];
    }

    private function check_htaccess(): array
    {
        if (!function_exists('get_home_path')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $htaccess = trailingslashit(get_home_path()) . '.htaccess';
        if (!file_exists($htaccess)) {
            return ['status' => 'warning', 'message' => '.htaccess file not found'];
        }

        $content = file_get_contents($htaccess);
        $has_rules = strpos($content, 'SpeedMate') !== false;

        return [
            'status' => $has_rules ? 'good' : 'warning',
            'message' => $has_rules ? '.htaccess rules are active' : '.htaccess rules are missing',
        ];
    }

    private function check_hit_rate(): array
    {
        $cached_count = StaticCache::instance()->get_cached_pages_count();
        
        if ($cached_count === 0) {
            return ['status' => 'warning', 'message' => 'No cached pages yet'];
        }

        return ['status' => 'good', 'message' => 'Cache is active with ' . $cached_count . ' pages'];
    }

    private function check_mode(): array
    {
        $settings = Settings::get();
        $mode = $settings['mode'] ?? 'disabled';

        if ($mode === 'disabled') {
            return ['status' => 'warning', 'message' => 'SpeedMate is disabled'];
        }

        return ['status' => 'good', 'message' => 'Mode: ' . ucfirst($mode)];
    }

    private function get_overall_status(array $checks): string
    {
        foreach ($checks as $check) {
            if ($check['status'] === 'error') {
                return 'error';
            }
        }

        foreach ($checks as $check) {
            if ($check['status'] === 'warning') {
                return 'warning';
            }
        }

        return 'good';
    }

    private function get_icon(string $status): string
    {
        $icons = [
            'good' => 'yes-alt',
            'warning' => 'warning',
            'error' => 'dismiss',
        ];

        return $icons[$status] ?? 'info';
    }

    private function get_status_text(string $status): string
    {
        $texts = [
            'good' => 'All Systems Operational',
            'warning' => 'Attention Required',
            'error' => 'Critical Issues Detected',
        ];

        return $texts[$status] ?? 'Unknown Status';
    }
}
