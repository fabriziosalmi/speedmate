<?php

declare(strict_types=1);

namespace SpeedMate\Admin;

use SpeedMate\Cache\StaticCache;
use SpeedMate\Utils\Settings;

/**
 * Renders admin page HTML and formats display values.
 *
 * @package SpeedMate\Admin
 * @since 0.4.0
 */
final class AdminRenderer
{
    /**
     * Render main settings page.
     *
     * @param string $capability Required capability.
     * @param string $flush_url URL for cache flush.
     * @param string $apply_beast_url URL for beast mode activation.
     * @return void
     */
    public function render_page(string $capability, string $flush_url, string $apply_beast_url): void
    {
        if (!current_user_can($capability)) {
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
                <a class="button button-secondary" href="<?php echo esc_url($flush_url); ?>">
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
                                    <a class="button button-primary" href="<?php echo esc_url($apply_beast_url); ?>">
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

    /**
     * Format bytes to human-readable size.
     *
     * @param int $bytes Size in bytes.
     * @return string Formatted size string.
     */
    public function format_bytes(int $bytes): string
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

    /**
     * Format milliseconds to human-readable duration.
     *
     * @param int $ms Duration in milliseconds.
     * @return string Formatted duration string.
     */
    public function format_duration(int $ms): string
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
}
