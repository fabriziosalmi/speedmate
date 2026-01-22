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
            wp_die('Unauthorized');
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
            wp_die('Unauthorized');
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

        $content = file_get_contents($file['tmp_name']);
        $data = json_decode($content, true);

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
