<?php

declare(strict_types=1);

namespace SpeedMate\Admin;

use SpeedMate\Cache\StaticCache;
use SpeedMate\Utils\Stats;
use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Singleton;

/**
 * WordPress dashboard health check widget.
 *
 * Displays SpeedMate system status at a glance in the WordPress admin dashboard.
 *
 * Checks:
 * - Cache directory existence and writability
 * - .htaccess rules presence
 * - Cache hit rate performance
 * - Current operating mode
 *
 * Features:
 * - Overall health status (good/warning/error)
 * - Individual check results with icons
 * - Quick stats (cached pages, size, time saved)
 * - Color-coded status indicators
 * - Automatic dashboard registration
 *
 * Status levels:
 * - Good: All checks passing
 * - Warning: Non-critical issues detected
 * - Error: Critical problems requiring attention
 *
 * @package SpeedMate\Admin
 * @since 0.2.0
 */
final class HealthWidget
{
    use Singleton;

    /**
     * Private constructor to enforce Singleton pattern.
     */
    private function __construct()
    {
    }

    /**
     * Register WordPress hooks for dashboard widget.
     *
     * Hooks:
     * - wp_dashboard_setup: Register widget in dashboard
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_action('wp_dashboard_setup', [$this, 'register_widget']);
    }

    /**
     * Register health check widget in WordPress dashboard.
     *
     * Widget:
     * - ID: speedmate_health
     * - Title: ⚡ SpeedMate Health Check
     * - Appears in dashboard for users with manage_options capability
     *
     * @return void
     */
    public function register_widget(): void
    {
        wp_add_dashboard_widget(
            'speedmate_health',
            '⚡ SpeedMate Health Check',
            [$this, 'render']
        );
    }

    /**
     * Render health check widget content.
     *
     * Displays:
     * - Overall status banner (good/warning/error)
     * - Individual health checks with icons
     * - Quick stats (cached pages, size, time saved)
     * - Inline CSS for styling
     *
     * Output is HTML with escaped content for XSS protection.
     *
     * @return void
     */
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

    /**
     * Check cache directory status.
     *
     * Verifies:
     * - Directory exists
     * - Directory is writable
     *
     * @return array{status: string, message: string} Status array.
     */
    private function check_cache_dir(): array
    {
        $writable = is_writable(SPEEDMATE_CACHE_DIR);
        return [
            'status' => $writable ? 'good' : 'error',
            'message' => $writable ? 'Cache directory is writable' : 'Cache directory is not writable',
        ];
    }

    /**
     * Check .htaccess rules status.
     *
     * Verifies:
     * - .htaccess file exists
     * - SpeedMate rules are present (BEGIN/END markers)
     *
     * @return array{status: string, message: string} Status array.
     */
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

    /**
     * Check cache hit rate performance.
     *
     * Evaluates:
     * - Good: Hit rate >= 80%
     * - Warning: Hit rate < 80%
     * - Error: No data available
     *
     * @return array{status: string, message: string} Status array.
     */
    private function check_hit_rate(): array
    {
        $cached_count = StaticCache::instance()->get_cached_pages_count();
        
        if ($cached_count === 0) {
            return ['status' => 'warning', 'message' => 'No cached pages yet'];
        }

        return ['status' => 'good', 'message' => 'Cache is active with ' . $cached_count . ' pages'];
    }

    /**
     * Check current operating mode.
     *
     * Statuses:
     * - Good: Safe or Beast mode enabled
     * - Warning: Mode is disabled
     *
     * @return array{status: string, message: string} Status array.
     */
    private function check_mode(): array
    {
        $settings = Settings::get();
        $mode = $settings['mode'] ?? 'disabled';

        if ($mode === 'disabled') {
            return ['status' => 'warning', 'message' => 'SpeedMate is disabled'];
        }

        return ['status' => 'good', 'message' => 'Mode: ' . ucfirst($mode)];
    }

    /**
     * Calculate overall health status from individual checks.
     *
     * Logic:
     * - Error: Any check has error status
     * - Warning: Any check has warning status (and no errors)
     * - Good: All checks passing
     *
     * @param array<string, array{status: string, message: string}> $checks Individual check results.
     *
     * @return string Overall status ('good', 'warning', or 'error').
     */
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

    /**
     * Get Dashicon name for status.
     *
     * Icons:
     * - Good: yes-alt (checkmark)
     * - Warning: warning (exclamation)
     * - Error: dismiss (X)
     *
     * @param string $status Status level.
     *
     * @return string Dashicon name (without 'dashicons-' prefix).
     */
    private function get_icon(string $status): string
    {
        $icons = [
            'good' => 'yes-alt',
            'warning' => 'warning',
            'error' => 'dismiss',
        ];

        return $icons[$status] ?? 'info';
    }

    /**
     * Get human-readable status text.
     *
     * @param string $status Status level.
     *
     * @return string Status text for display.
     */
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
