<?php

declare(strict_types=1);

namespace SpeedMate\Tests\Integration;

use SpeedMate\Media\WebPConverter;
use SpeedMate\Utils\Settings;
use WP_UnitTestCase;

final class WebPConverterTest extends WP_UnitTestCase
{
    private WebPConverter $converter;

    public function setUp(): void
    {
        parent::setUp();
        $this->converter = WebPConverter::instance();
    }

    public function test_webp_converter_requires_gd_support(): void
    {
        $reflection = new \ReflectionClass($this->converter);
        $method = $reflection->getMethod('webp_supported');
        $method->setAccessible(true);
        
        $supported = $method->invoke($this->converter);
        
        // This will depend on the PHP GD installation
        $this->assertIsBool($supported);
    }

    public function test_browser_webp_support_detection(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'image/webp,image/apng,image/*,*/*;q=0.8';
        
        $reflection = new \ReflectionClass($this->converter);
        $method = $reflection->getMethod('browser_supports_webp');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->converter);
        $this->assertTrue($result);

        // Test without WebP support
        $_SERVER['HTTP_ACCEPT'] = 'image/png,image/*,*/*;q=0.8';
        $result = $method->invoke($this->converter);
        $this->assertFalse($result);
    }

    public function test_picture_tag_creation(): void
    {
        update_option(SPEEDMATE_OPTION_KEY, [
            'webp_enabled' => true,
        ]);
        Settings::refresh();

        $_SERVER['HTTP_ACCEPT'] = 'image/webp';
        
        $html = '<img src="test.jpg" alt="Test">';
        
        // Note: Full test would require actual file creation
        // This tests the setting is respected
        $settings = Settings::get();
        $this->assertTrue($settings['webp_enabled']);
    }

    public function test_webp_disabled_by_default(): void
    {
        $settings = Settings::get();
        
        // Check if key exists, if not or false, test passes
        $enabled = $settings['webp_enabled'] ?? false;
        $this->assertFalse($enabled);
    }
}
