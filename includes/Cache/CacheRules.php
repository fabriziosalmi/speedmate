<?php

declare(strict_types=1);

namespace SpeedMate\Cache;

/**
 * Generates .htaccess and Nginx rules for static cache.
 *
 * @package SpeedMate\Cache
 * @since 0.4.0
 */
final class CacheRules
{
    /**
     * Get Apache .htaccess rules.
     *
     * @return array<string> Rules as array of strings.
     */
    public function get_htaccess_rules(): array
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            $host = '%{HTTP_HOST}';
        }

        return [
            '<IfModule mod_rewrite.c>',
            'RewriteEngine On',
            'RewriteCond %{REQUEST_METHOD} =GET',
            'RewriteCond %{QUERY_STRING} ^$',
            'RewriteCond %{REQUEST_URI} !^/wp-admin',
            'RewriteCond %{REQUEST_URI} !^/wp-json',
            'RewriteCond %{HTTP_COOKIE} !wordpress_logged_in',
            'RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/speedmate/' . $host . '%{REQUEST_URI}/index.html -f',
            'RewriteRule ^(.*)$ /wp-content/cache/speedmate/' . $host . '%{REQUEST_URI}/index.html [L]',
            '</IfModule>',
            '<IfModule mod_deflate.c>',
            'AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json image/svg+xml',
            '</IfModule>',
        ];
    }

    /**
     * Get Nginx configuration rules.
     *
     * @return string Nginx configuration.
     */
    public function get_nginx_rules(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            $host = '$host';
        }

        return "# SpeedMate static cache\n" .
            "location / {\n" .
            "    if (\$request_method = GET) {\n" .
            "        if (\$query_string = \"\") {\n" .
            "            set \$cache_file /wp-content/cache/speedmate/{$host}\$uri/index.html;\n" .
            "            if (-f \$document_root\$cache_file) {\n" .
            "                rewrite ^ \$cache_file break;\n" .
            "            }\n" .
            "        }\n" .
            "    }\n" .
            "    try_files \$uri \$uri/ /index.php?\$args;\n" .
            "}\n";
    }

    /**
     * Write rules to .htaccess file.
     *
     * @return void
     */
    public function write_htaccess(): void
    {
        if (!function_exists('insert_with_markers')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }
        if (!function_exists('get_home_path')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $rules = $this->get_htaccess_rules();
        $htaccess = trailingslashit(get_home_path()) . '.htaccess';
        insert_with_markers($htaccess, 'SpeedMate', $rules);
    }

    /**
     * Remove rules from .htaccess file.
     *
     * @return void
     */
    public function remove_htaccess(): void
    {
        if (!function_exists('insert_with_markers')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }
        if (!function_exists('get_home_path')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $htaccess = trailingslashit(get_home_path()) . '.htaccess';
        insert_with_markers($htaccess, 'SpeedMate', []);
    }
}
