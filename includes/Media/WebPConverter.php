<?php

declare(strict_types=1);

namespace SpeedMate\Media;

use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Logger;
use SpeedMate\Utils\Singleton;

final class WebPConverter
{
    use Singleton;

    private function __construct()
    {
    }

    private function register_hooks(): void
    {
        $settings = Settings::get();
        if (!($settings['webp_enabled'] ?? false)) {
            return;
        }

        add_filter('wp_handle_upload', [$this, 'convert_on_upload']);
        add_filter('the_content', [$this, 'serve_webp'], 20);
    }

    public function convert_on_upload($file): array
    {
        if (!isset($file['file'], $file['type'])) {
            return $file;
        }

        if (!in_array($file['type'], ['image/jpeg', 'image/png'], true)) {
            return $file;
        }

        if (!$this->webp_supported()) {
            return $file;
        }

        $webp_path = $this->create_webp($file['file']);
        if ($webp_path !== '') {
            $file['webp'] = $webp_path;
        }

        return $file;
    }

    public function serve_webp(string $html): string
    {
        if (!$this->browser_supports_webp()) {
            return $html;
        }

        return preg_replace_callback(
            '/<img([^>]+)src=["\']([^"\']+\.(jpg|jpeg|png))["\']([^>]*)>/i',
            [$this, 'create_picture_tag'],
            $html
        );
    }

    private function create_picture_tag(array $matches): string
    {
        $full_tag = $matches[0];
        $before_src = $matches[1];
        $src = $matches[2];
        $after_src = $matches[4];

        $webp_src = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $src);

        // Check if WebP version exists
        $upload_dir = wp_upload_dir();
        $webp_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $webp_src);

        if (!file_exists($webp_path)) {
            return $full_tag;
        }

        return sprintf(
            '<picture><source srcset="%s" type="image/webp"><img%ssrc="%s"%s></picture>',
            esc_url($webp_src),
            $before_src,
            esc_url($src),
            $after_src
        );
    }

    private function create_webp(string $source_path): string
    {
        $info = @getimagesize($source_path);
        if ($info === false) {
            return '';
        }

        $image = null;
        switch ($info['mime']) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($source_path);
                if ($image !== false) {
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }
                break;
        }

        if ($image === false || $image === null) {
            return '';
        }

        $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $source_path);
        $quality = 80;

        $result = @imagewebp($image, $webp_path, $quality);
        imagedestroy($image);

        if (!$result) {
            Logger::log('warning', 'webp_conversion_failed', ['path' => $source_path]);
            return '';
        }

        return $webp_path;
    }

    private function webp_supported(): bool
    {
        if (!function_exists('imagewebp')) {
            return false;
        }

        $gd_info = gd_info();
        return isset($gd_info['WebP Support']) && $gd_info['WebP Support'];
    }

    private function browser_supports_webp(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return strpos($accept, 'image/webp') !== false;
    }
}
