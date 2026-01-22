<?php

declare(strict_types=1);

namespace SpeedMate\Admin;

use SpeedMate\Cache\StaticCache;
use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Container;

final class Admin
{
    private static ?Admin $instance = null;

    private function __construct()
    {
    }

    public static function instance(): Admin
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
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_bar_menu', [$this, 'register_admin_bar'], 100);
        add_action('admin_post_speedmate_flush_cache', [$this, 'handle_flush_cache']);
        add_action('admin_post_speedmate_apply_beast_all', [$this, 'handle_apply_beast_all']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('SpeedMate', 'speedmate'),
            __('SpeedMate', 'speedmate'),
            $this->get_capability(),
            'speedmate',
            [$this, 'render_page'],
            'dashicons-dashboard',
            58
        );
    }

    public function register_settings(): void
    {
        register_setting('speedmate', SPEEDMATE_OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => [
                'mode' => 'disabled',
                'beast_whitelist' => [],
                'beast_blacklist' => [],
                'beast_apply_all' => false,
                'logging_enabled' => false,
                'csp_nonce' => false,
            ],
        ]);
    }

    public function sanitize_settings($input): array
    {
        $mode = 'disabled';
        if (is_array($input) && isset($input['mode'])) {
            $allowed = ['disabled', 'safe', 'beast'];
            $candidate = sanitize_text_field((string) $input['mode']);
            if (in_array($candidate, $allowed, true)) {
                $mode = $candidate;
            }
        }

        $whitelist = [];
        $blacklist = [];
        $apply_all = false;
        $logging_enabled = false;
        $csp_nonce = false;

        if (is_array($input)) {
            if (isset($input['beast_whitelist'])) {
                $whitelist = $this->sanitize_rules((string) $input['beast_whitelist']);
            }
            if (isset($input['beast_blacklist'])) {
                $blacklist = $this->sanitize_rules((string) $input['beast_blacklist']);
            }
            if (isset($input['beast_apply_all'])) {
                $apply_all = (bool) $input['beast_apply_all'];
            }
            if (isset($input['logging_enabled'])) {
                $logging_enabled = (bool) $input['logging_enabled'];
            }
            if (isset($input['csp_nonce'])) {
                $csp_nonce = (bool) $input['csp_nonce'];
            }
        }

        return [
            'mode' => $mode,
            'beast_whitelist' => $whitelist,
            'beast_blacklist' => $blacklist,
            'beast_apply_all' => $apply_all,
            'logging_enabled' => $logging_enabled,
            'csp_nonce' => $csp_nonce,
        ];
    }

    public function render_page(): void
    {
        if (!current_user_can($this->get_capability())) {
            return;
        }

        $settings = Settings::get();
        $mode = $settings['mode'] ?? 'disabled';
        $whitelist = $settings['beast_whitelist'] ?? [];
        $blacklist = $settings['beast_blacklist'] ?? [];
        $apply_all = (bool) ($settings['beast_apply_all'] ?? false);
        $logging_enabled = (bool) ($settings['logging_enabled'] ?? false);
        $csp_nonce = (bool) ($settings['csp_nonce'] ?? false);
        $whitelist_text = is_array($whitelist) ? implode("\n", $whitelist) : '';
        $blacklist_text = is_array($blacklist) ? implode("\n", $blacklist) : '';
        $stats = get_option(SPEEDMATE_STATS_KEY, []);
        $warmed = is_array($stats) ? (int) ($stats['warmed_pages'] ?? 0) : 0;
        $lcp = is_array($stats) ? (int) ($stats['lcp_preloads'] ?? 0) : 0;
        $time_saved_ms = is_array($stats) ? (int) ($stats['time_saved_ms'] ?? 0) : 0;
        $cache_size = StaticCache::instance()->get_cache_size_bytes();
        $cached_pages = StaticCache::instance()->get_cached_pages_count();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('SpeedMate', 'speedmate'); ?></h1>
            <p>
                <a class="button button-secondary" href="<?php echo esc_url($this->get_flush_url()); ?>">
                    <?php echo esc_html__('Flush Cache', 'speedmate'); ?>
                </a>
            </p>
            <h2><?php echo esc_html__('Vital Signs', 'speedmate'); ?></h2>
            <table class="widefat striped" style="max-width: 560px;">
                <tbody>
                    <tr>
                        <th><?php echo esc_html__('Cache size', 'speedmate'); ?></th>
                        <td><?php echo esc_html($this->format_bytes($cache_size)); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Cached pages', 'speedmate'); ?></th>
                        <td><?php echo esc_html((string) $cached_pages); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Pages warmed', 'speedmate'); ?></th>
                        <td><?php echo esc_html((string) $warmed); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('LCP preloads', 'speedmate'); ?></th>
                        <td><?php echo esc_html((string) $lcp); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Time saved (week)', 'speedmate'); ?></th>
                        <td><?php echo esc_html($this->format_duration($time_saved_ms)); ?></td>
                    </tr>
                </tbody>
            </table>
            <h2><?php echo esc_html__('Nginx Rules (copy/paste)', 'speedmate'); ?></h2>
            <textarea class="large-text code" rows="8" readonly><?php echo esc_textarea(StaticCache::instance()->get_nginx_rules()); ?></textarea>
            <form method="post" action="options.php">
                <?php settings_fields('speedmate'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Mode', 'speedmate'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="<?php echo esc_attr(SPEEDMATE_OPTION_KEY); ?>[mode]" value="disabled" <?php checked($mode, 'disabled'); ?> />
                                    <?php echo esc_html__('Disabled', 'speedmate'); ?>
                                </label><br />
                                <label>
                                    <input type="radio" name="<?php echo esc_attr(SPEEDMATE_OPTION_KEY); ?>[mode]" value="safe" <?php checked($mode, 'safe'); ?> />
                                    <?php echo esc_html__('Safe Mode', 'speedmate'); ?>
                                </label><br />
                                <label>
                                    <input type="radio" name="<?php echo esc_attr(SPEEDMATE_OPTION_KEY); ?>[mode]" value="beast" <?php checked($mode, 'beast'); ?> />
                                    <?php echo esc_html__('Beast Mode', 'speedmate'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Beast Mode - Whitelist', 'speedmate'); ?></th>
                        <td>
                            <textarea class="large-text code" rows="6" name="<?php echo esc_attr(SPEEDMATE_OPTION_KEY); ?>[beast_whitelist]" placeholder="cdn.example.com/script.js"><?php echo esc_textarea($whitelist_text); ?></textarea>
                            <p class="description">
                                <?php echo esc_html__('One rule per line. Matching scripts will never be delayed.', 'speedmate'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Beast Mode - Blacklist', 'speedmate'); ?></th>
                        <td>
                            <textarea class="large-text code" rows="6" name="<?php echo esc_attr(SPEEDMATE_OPTION_KEY); ?>[beast_blacklist]" placeholder="checkout.js"><?php echo esc_textarea($blacklist_text); ?></textarea>
                            <p class="description">
                                <?php echo esc_html__('One rule per line. Matching scripts are always delayed.', 'speedmate'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Beast Mode - Apply to all visitors', 'speedmate'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(SPEEDMATE_OPTION_KEY); ?>[beast_apply_all]" value="1" <?php checked($apply_all, true); ?> />
                                <?php echo esc_html__('Enable Beast Mode for everyone (disable preview).', 'speedmate'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('Default is Preview Mode: only admins or ?speedmate_test=1 see the effect.', 'speedmate'); ?>
                            </p>
                            <?php if (!$apply_all) : ?>
                                <p>
                                    <a class="button button-primary" href="<?php echo esc_url($this->get_apply_beast_all_url()); ?>">
                                        <?php echo esc_html__('Apply to all now', 'speedmate'); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Structured logging', 'speedmate'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(SPEEDMATE_OPTION_KEY); ?>[logging_enabled]" value="1" <?php checked($logging_enabled, true); ?> />
                                <?php echo esc_html__('Enable JSON logs to PHP error_log.', 'speedmate'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('CSP Nonce for inline scripts', 'speedmate'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(SPEEDMATE_OPTION_KEY); ?>[csp_nonce]" value="1" <?php checked($csp_nonce, true); ?> />
                                <?php echo esc_html__('Add a CSP nonce to inline scripts (Auto‑LCP, Beast Mode).', 'speedmate'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function register_admin_bar($wp_admin_bar): void
    {
        if (!current_user_can($this->get_capability())) {
            return;
        }

        $cache = StaticCache::instance();
        $stats = \SpeedMate\Utils\Stats::get();
        
        $wp_admin_bar->add_node([
            'id' => 'speedmate',
            'title' => $this->get_admin_bar_title($cache, $stats),
            'href' => admin_url('admin.php?page=speedmate'),
        ]);

        $wp_admin_bar->add_node([
            'parent' => 'speedmate',
            'id' => 'speedmate-stats',
            'title' => sprintf(
                'Cache: %s | Pages: %d | LCP: %d',
                size_format($cache->get_cache_size_bytes()),
                $cache->get_cached_pages_count(),
                $stats['lcp_preloads'] ?? 0
            ),
        ]);

        $wp_admin_bar->add_node([
            'parent' => 'speedmate',
            'id' => 'speedmate-timing',
            'title' => sprintf(
                'Avg: %dms cached | %dms uncached',
                $stats['avg_cached_ms'] ?? 50,
                $stats['avg_uncached_ms'] ?? 0
            ),
        ]);

        $wp_admin_bar->add_node([
            'parent' => 'speedmate',
            'id' => 'speedmate-flush-cache',
            'title' => __('Flush Cache', 'speedmate'),
            'href' => $this->get_flush_url(),
        ]);
    }

    private function get_admin_bar_title($cache, array $stats): string
    {
        $mode = Settings::get_value('mode', 'disabled');
        $icon = $mode === 'beast' ? '⚡' : ($mode === 'safe' ? '🔒' : '💤');
        
        return sprintf(
            '%s SpeedMate | Time Saved: %s',
            $icon,
            gmdate('H:i:s', (int) (($stats['time_saved_ms'] ?? 0) / 1000))
        );
    }

    public function handle_flush_cache(): void
    {
        if (!current_user_can($this->get_capability())) {
            wp_die(__('Unauthorized.', 'speedmate'));
        }

        check_admin_referer('speedmate_flush_cache');

        StaticCache::instance()->flush_all();

        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=speedmate'));
        exit;
    }

    public function handle_apply_beast_all(): void
    {
        if (!current_user_can($this->get_capability())) {
            wp_die(__('Unauthorized.', 'speedmate'));
        }

        check_admin_referer('speedmate_apply_beast_all');

        $settings = Settings::get();
        $settings['beast_apply_all'] = true;
        update_option(SPEEDMATE_OPTION_KEY, $settings, false);
        Settings::refresh();

        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=speedmate'));
        exit;
    }

    private function get_flush_url(): string
    {
        $url = admin_url('admin-post.php?action=speedmate_flush_cache');

        return wp_nonce_url($url, 'speedmate_flush_cache');
    }

    private function get_apply_beast_all_url(): string
    {
        $url = admin_url('admin-post.php?action=speedmate_apply_beast_all');

        return wp_nonce_url($url, 'speedmate_apply_beast_all');
    }

    private function get_capability(): string
    {
        return (string) apply_filters('speedmate_admin_capability', 'manage_options');
    }

    private function format_bytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format_i18n($value, $power === 0 ? 0 : 2) . ' ' . $units[$power];
    }

    private function format_duration(int $ms): string
    {
        if ($ms <= 0) {
            return '0 min';
        }

        $seconds = (int) floor($ms / 1000);
        $minutes = (int) floor($seconds / 60);
        $hours = (int) floor($minutes / 60);
        $minutes = $minutes % 60;

        if ($hours > 0) {
            return sprintf('%d h %d min', $hours, $minutes);
        }

        return sprintf('%d min', $minutes);
    }

    private function sanitize_rules(string $input): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $input);
        if (!is_array($lines)) {
            return [];
        }

        $rules = [];
        foreach ($lines as $line) {
            $line = trim(sanitize_text_field($line));
            if ($line !== '') {
                $rules[] = $line;
            }
        }

        return array_values(array_unique($rules));
    }
}
