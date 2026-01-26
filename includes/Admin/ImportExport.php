<?php

declare(strict_types=1);

namespace SpeedMate\Admin;

use SpeedMate\Utils\Settings;

final class ImportExport
{
    private static ?ImportExport $instance = null;

    private function __construct()
    {
    }

    public static function instance(): ImportExport
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->register_hooks();
        }

        return self::$instance;
    }

    private function register_hooks(): void
    {
        add_action('admin_post_speedmate_export', [$this, 'handle_export']);
        add_action('admin_post_speedmate_import', [$this, 'handle_import']);
    }

    public function handle_export(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'speedmate'), 403);
        }

        check_admin_referer('speedmate_export');

        $config = $this->export();

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="speedmate-config-' . gmdate('Y-m-d') . '.json"');
        echo wp_json_encode($config, JSON_PRETTY_PRINT);
        exit;
    }

    public function handle_import(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'speedmate'), 403);
        }

        check_admin_referer('speedmate_import');

        if (!isset($_FILES['import_file'])) {
            wp_redirect(admin_url('admin.php?page=speedmate&import_error=no_file'));
            exit;
        }

        $file = $_FILES['import_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(admin_url('admin.php?page=speedmate&import_error=upload_failed'));
            exit;
        }

        // Security: Validate file extension
        $filename = isset($file['name']) ? sanitize_file_name($file['name']) : '';
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext !== 'json') {
            wp_redirect(admin_url('admin.php?page=speedmate&import_error=invalid_extension'));
            exit;
        }

        // Security: Validate MIME type
        $mime_type = '';
        if (function_exists('mime_content_type') && isset($file['tmp_name'])) {
            $mime_type = mime_content_type($file['tmp_name']);
        }
        if (!in_array($mime_type, ['application/json', 'text/plain'], true)) {
            wp_redirect(admin_url('admin.php?page=speedmate&import_error=invalid_mime'));
            exit;
        }

        // Security: Validate file size (max 1MB)
        if (isset($file['size']) && $file['size'] > 1048576) {
            wp_redirect(admin_url('admin.php?page=speedmate&import_error=file_too_large'));
            exit;
        }

        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            wp_redirect(admin_url('admin.php?page=speedmate&import_error=read_failed'));
            exit;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_redirect(admin_url('admin.php?page=speedmate&import_error=invalid_json'));
            exit;
        }

        if (!$this->import($data)) {
            wp_redirect(admin_url('admin.php?page=speedmate&import_error=invalid_data'));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=speedmate&import_success=1'));
        exit;
    }

    public function export(): array
    {
        return [
            'version' => SPEEDMATE_VERSION,
            'settings' => Settings::get(),
            'timestamp' => time(),
            'site_url' => get_site_url(),
        ];
    }

    public function import(array $data): bool
    {
        if (!$this->validate_import($data)) {
            return false;
        }

        update_option(SPEEDMATE_OPTION_KEY, $data['settings']);
        Settings::refresh();

        return true;
    }

    private function validate_import(array $data): bool
    {
        if (!isset($data['version'], $data['settings'], $data['timestamp'])) {
            return false;
        }

        if (!is_array($data['settings'])) {
            return false;
        }

        // Basic version compatibility check
        if (version_compare($data['version'], '0.1.0', '<')) {
            return false;
        }

        // Security: Whitelist allowed settings keys
        $allowed_keys = [
            'mode',
            'beast_whitelist',
            'beast_blacklist',
            'beast_apply_all',
            'logging_enabled',
            'csp_nonce',
            'cache_ttl',
            'cache_ttl_homepage',
            'cache_ttl_posts',
            'cache_ttl_pages',
            'cache_exclude_urls',
            'cache_exclude_cookies',
            'cache_exclude_query_params',
            'warmer_enabled',
            'warmer_frequency',
            'warmer_max_urls',
            'warmer_concurrent',
            'webp_enabled',
            'critical_css_enabled',
            'preload_hints_enabled',
            'garbage_collector_enabled',
            'garbage_collector_delete_spam',
        ];

        // Security: Remove any keys not in whitelist
        foreach (array_keys($data['settings']) as $key) {
            if (!in_array($key, $allowed_keys, true)) {
                unset($data['settings'][$key]);
            }
        }

        // Security: Validate mode value
        if (isset($data['settings']['mode'])) {
            $valid_modes = ['disabled', 'safe', 'beast'];
            if (!in_array($data['settings']['mode'], $valid_modes, true)) {
                return false;
            }
        }

        // Security: Validate boolean fields
        $boolean_fields = [
            'beast_apply_all',
            'logging_enabled',
            'csp_nonce',
            'warmer_enabled',
            'webp_enabled',
            'critical_css_enabled',
            'preload_hints_enabled',
            'garbage_collector_enabled',
            'garbage_collector_delete_spam',
        ];

        foreach ($boolean_fields as $field) {
            if (isset($data['settings'][$field]) && !is_bool($data['settings'][$field])) {
                return false;
            }
        }

        // Security: Validate array fields
        $array_fields = [
            'beast_whitelist',
            'beast_blacklist',
            'cache_exclude_urls',
            'cache_exclude_cookies',
            'cache_exclude_query_params',
        ];

        foreach ($array_fields as $field) {
            if (isset($data['settings'][$field]) && !is_array($data['settings'][$field])) {
                return false;
            }
        }

        // Security: Validate numeric fields
        $numeric_fields = [
            'cache_ttl',
            'cache_ttl_homepage',
            'cache_ttl_posts',
            'cache_ttl_pages',
            'warmer_max_urls',
            'warmer_concurrent',
        ];

        foreach ($numeric_fields as $field) {
            if (isset($data['settings'][$field])) {
                if (!is_numeric($data['settings'][$field]) || $data['settings'][$field] < 0) {
                    return false;
                }
            }
        }

        return true;
    }

    public function render_ui(): void
    {
        ?>
        <div class="speedmate-import-export">
            <h3>Export Configuration</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('speedmate_export'); ?>
                <input type="hidden" name="action" value="speedmate_export">
                <p>Download your current SpeedMate configuration as a JSON file.</p>
                <button type="submit" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span> Export Configuration
                </button>
            </form>

            <hr style="margin: 30px 0;">

            <h3>Import Configuration</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('speedmate_import'); ?>
                <input type="hidden" name="action" value="speedmate_import">
                <p>Upload a previously exported configuration file to restore settings.</p>
                <p><input type="file" name="import_file" accept=".json" required></p>
                <button type="submit" class="button button-secondary">
                    <span class="dashicons dashicons-upload"></span> Import Configuration
                </button>
            </form>

            <?php if (isset($_GET['import_success'])): ?>
                <div class="notice notice-success" style="margin-top: 20px;">
                    <p>Configuration imported successfully!</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['import_error'])): ?>
                <div class="notice notice-error" style="margin-top: 20px;">
                    <p>Import failed: <?php echo esc_html($_GET['import_error']); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
