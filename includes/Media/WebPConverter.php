<?php

declare(strict_types=1);

namespace SpeedMate\Media;

use SpeedMate\Utils\Settings;
use SpeedMate\Utils\Logger;
use SpeedMate\Utils\Singleton;

/**
 * WebP image format conversion for better compression.
 *
 * Converts JPEG and PNG images to WebP format for:
 * - 25-35% smaller file sizes vs JPEG
 * - 25-50% smaller file sizes vs PNG
 * - Better compression with similar quality
 * - Faster page load times
 *
 * Features:
 * - Automatic conversion on upload
 * - <picture> tag for progressive enhancement
 * - Browser support detection (Accept header)
 * - Fallback to original format for unsupported browsers
 * - PNG alpha channel preservation
 * - Comprehensive error handling with logging
 *
 * Requirements:
 * - PHP GD library with WebP support
 * - gd_info()['WebP Support'] must be true
 *
 * @package SpeedMate\Media
 * @since 0.2.0
 */
final class WebPConverter
{
    use Singleton;

    /**
     * Private constructor to enforce Singleton pattern.
     */
    private function __construct()
    {
    }

    /**
     * Register WordPress hooks for WebP conversion.
     *
     * Only registers if webp_enabled setting is true.
     *
     * Hooks:
     * - wp_handle_upload: Convert uploaded images to WebP
     * - the_content (priority 20): Serve WebP versions in <picture> tags
     *
     * @return void
     */
    private function register_hooks(): void
    {
        $settings = Settings::get();
        if (!($settings['webp_enabled'] ?? false)) {
            return;
        }

        add_filter('wp_handle_upload', [$this, 'convert_on_upload']);
        add_filter('the_content', [$this, 'serve_webp'], 20);
    }

    /**
     * Convert uploaded image to WebP format.
     *
     * Triggered on wp_handle_upload filter. Creates WebP version
     * alongside original file for supported formats (JPEG, PNG).
     *
     * Process:
     * 1. Validate file type (JPEG or PNG only)
     * 2. Check GD WebP support
     * 3. Create WebP version
     * 4. Store WebP path in $file['webp']
     *
     * @param array $file Upload file data with 'file' and 'type' keys.
     *
     * @return array Modified file data with 'webp' key if successful.
     */
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

    /**
     * Replace img tags with picture tags for WebP delivery.
     *
     * Uses progressive enhancement:
     * - <picture> with <source type="image/webp"> for WebP
     * - <img> fallback for unsupported browsers
     *
     * Only replaces if:
     * - Browser supports WebP (Accept: image/webp header)
     * - WebP version exists on disk
     *
     * @param string $html HTML content with img tags.
     *
     * @return string HTML with picture tags for WebP-capable browsers.
     */
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

    /**
     * Create picture tag with WebP source for image.
     *
     * Callback for preg_replace_callback in serve_webp().
     *
     * Output format:
     * <picture>
     *   <source srcset="image.webp" type="image/webp">
     *   <img src="image.jpg" ...>
     * </picture>
     *
     * @param array $matches Regex match array from preg_replace_callback.
     *
     * @return string Picture tag HTML or original img tag if WebP unavailable.
     */
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

    /**
     * Create WebP version of JPEG or PNG image.
     *
     * Process:
     * 1. Get image dimensions and MIME type
     * 2. Load source image with GD
     * 3. Preserve PNG transparency/alpha
     * 4. Save as WebP with 80% quality
     * 5. Clean up GD resources
     *
     * Error handling:
     * - Logs specific failure at each step
     * - Returns empty string on any error
     * - Ensures imagedestroy() called on all paths
     *
     * Supported formats:
     * - image/jpeg -> WebP
     * - image/png -> WebP (with alpha)
     *
     * @param string $source_path Absolute path to source image.
     *
     * @return string Path to WebP file, or empty string on failure.
     */
    private function create_webp(string $source_path): string
    {
        try {
            $info = @getimagesize($source_path);
            if ($info === false) {
                Logger::log('warning', 'webp_getimagesize_failed', ['path' => $source_path]);
                return '';
            }

            $image = null;
            switch ($info['mime']) {
                case 'image/jpeg':
                    $image = @imagecreatefromjpeg($source_path);
                    if ($image === false) {
                        Logger::log('warning', 'webp_jpeg_load_failed', ['path' => $source_path]);
                        return '';
                    }
                    break;
                case 'image/png':
                    $image = @imagecreatefrompng($source_path);
                    if ($image === false) {
                        Logger::log('warning', 'webp_png_load_failed', ['path' => $source_path]);
                        return '';
                    }
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                    break;
            }

            if ($image === null) {
                Logger::log('warning', 'webp_unsupported_mime', ['mime' => $info['mime']]);
                return '';
            }

            $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $source_path);
            if ($webp_path === null || $webp_path === $source_path) {
                imagedestroy($image);
                Logger::log('warning', 'webp_path_generation_failed', ['path' => $source_path]);
                return '';
            }
            
            $quality = 80;

            $result = @imagewebp($image, $webp_path, $quality);
            imagedestroy($image);

            if (!$result) {
                Logger::log('warning', 'webp_conversion_failed', ['path' => $source_path]);
                return '';
            }

            return $webp_path;
        } catch (\Exception $e) {
            Logger::log('error', 'webp_conversion_exception', [
                'path' => $source_path,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Check if server supports WebP image creation.
     *
     * Verifies:
     * - imagewebp() function exists
     * - GD library has WebP support enabled
     *
     * @return bool True if WebP supported, false otherwise.
     */
    private function webp_supported(): bool
    {
        if (!function_exists('imagewebp')) {
            return false;
        }

        $gd_info = gd_info();
        return isset($gd_info['WebP Support']) && $gd_info['WebP Support'];
    }

    /**
     * Check if browser supports WebP images.
     *
     * Checks HTTP Accept header for 'image/webp' MIME type.
     * Supported by:
     * - Chrome 23+, Edge 18+, Firefox 65+, Opera 12.1+
     * - Safari 14+ (macOS 11+, iOS 14+)
     *
     * @return bool True if browser supports WebP, false otherwise.
     */
    private function browser_supports_webp(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return strpos($accept, 'image/webp') !== false;
    }
}
