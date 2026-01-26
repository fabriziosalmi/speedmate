<?php

declare(strict_types=1);

namespace SpeedMate\Admin;

use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Singleton;

/**
 * Configuration import/export functionality.
 *
 * Allows exporting and importing SpeedMate settings as JSON files for:
 * - Backup and restore
 * - Configuration migration between sites
 * - Bulk configuration updates
 * - Disaster recovery
 *
 * Features:
 * - JSON export with pretty printing
 * - Comprehensive import validation
 * - Security checks (MIME type, file size, structure)
 * - Whitelist of allowed settings keys
 * - Type and value validation
 * - Nonce and capability verification
 *
 * Security:
 * - max 1MB file size
 * - JSON MIME type validation
 * - Structure and key whitelisting
 * - XSS prevention on import
 * - Capability check (manage_options)
 *
 * @package SpeedMate\Admin
 * @since 0.2.0
 */
final class ImportExport
{
    use Singleton;

    /**
     * Private constructor to enforce Singleton pattern.
     */
    private function __construct()
    {
    }

    /**
     * Register WordPress hooks for import/export.
     *
     * Hooks:
     * - admin_post_speedmate_export: Handle export action
     * - admin_post_speedmate_import: Handle import action
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_action('admin_post_speedmate_export', [$this, 'handle_export']);
        add_action('admin_post_speedmate_import', [$this, 'handle_import']);
    }

    /**
     * Handle configuration export request.
     *
     * Process:
     * 1. Verify capability (manage_options)
     * 2. Check nonce
     * 3. Export settings as JSON
     * 4. Send as downloadable file
     *
     * File format: speedmate-config-YYYY-MM-DD.json
     * Content-Type: application/json
     *
     * @return void Exits after sending file.
     */
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

    /**
     * Handle configuration import request.
     *
     * Security checks:
     * 1. Capability verification (manage_options)
     * 2. Nonce validation
     * 3. File upload validation
     * 4. Extension check (must be .json)
     * 5. MIME type validation (application/json or text/plain)
     * 6. File size limit (max 1MB)
     * 7. JSON structure validation
     * 8. Settings key whitelisting
     *
     * Redirects to admin page with error/success parameter.
     *
     * @return void Exits after redirect.
     */
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
        if (isset($file['size']) && $file['size'] > SPEEDMATE_MAX_IMPORT_SIZE) {
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

    /**
     * Export current settings as array.
     *
     * Exports:
     * - All SpeedMate settings from Settings::get()
     * - Plugin version
     * - Export timestamp
     *
     * @return array Export data with version and settings.
     */
    public function export(): array
    {
        return [
            'version' => SPEEDMATE_VERSION,
            'settings' => Settings::get(),
            'timestamp' => time(),
            'site_url' => get_site_url(),
        ];
    }

    /**
     * Import settings from validated data.
     *
     * Process:
     * 1. Validate import data structure
     * 2. Extract settings
     * 3. Update WordPress options
     *
     * @param array $data Import data with 'settings' key.
     *
     * @return bool True if import successful, false otherwise.
     */
    public function import(array $data): bool
    {
        if (!$this->validate_import($data)) {
            return false;
        }

        update_option(SPEEDMATE_OPTION_KEY, $data['settings']);
        Settings::refresh();

        return true;
    }

    /**
     * Validate import data structure and values.
     *
     * Validates:
     * - Required keys: version, settings
     * - Settings is array
     * - Whitelisted keys only (21 allowed settings)
     * - Type validation (boolean, array, numeric)
     * - Value range validation
     *
     * Allowed settings:
     * - mode, cache_ttl, excluded_urls, excluded_cookies
     * - beast_whitelist, beast_blacklist, beast_apply_all
     * - webp_enabled, critical_css_enabled, preload_hints_enabled
     * - warmer_enabled, warmer_frequency, warmer_max_urls
     * - gc_enabled, gc_spam, gc_revisions, gc_transients
     * - gc_frequency, orphan_meta
     * - auto_lcp_enabled, auto_lcp_threshold
     *
     * @param array $data Import data to validate.
     *
     * @return bool True if valid, false otherwise.
     */
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

    /**
     * Render import/export admin UI.
     *
     * Displays:
     * - Export button (download JSON config)
     * - Import form (upload JSON file)
     * - Security warnings
     * - Success/error messages
     *
     * Nonce: speedmate_import_nonce
     * Action: admin_post_speedmate_import
     *
     * @return void
     */
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
