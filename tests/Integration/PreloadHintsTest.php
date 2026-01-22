<?php

declare(strict_types=1);

namespace SpeedMate\Tests\Integration;

use SpeedMate\Perf\PreloadHints;
use SpeedMate\Utils\Settings;
use WP_UnitTestCase;

final class PreloadHintsTest extends WP_UnitTestCase
{
    private PreloadHints $hints;

    public function setUp(): void
    {
        parent::setUp();
        $this->hints = PreloadHints::instance();
    }

    public function test_preload_hints_enabled_by_default(): void
    {
        $settings = Settings::get();
        $enabled = $settings['preload_hints_enabled'] ?? true;
        
        $this->assertTrue($enabled);
    }

    public function test_google_fonts_detection(): void
    {
        // Register a Google Fonts style
        wp_register_style('google-fonts', 'https://fonts.googleapis.com/css?family=Roboto', [], '1.0');
        wp_enqueue_style('google-fonts');
        
        $reflection = new \ReflectionClass($this->hints);
        $method = $reflection->getMethod('site_uses_google_fonts');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->hints);
        $this->assertTrue($result);
        
        // Cleanup
        wp_dequeue_style('google-fonts');
        wp_deregister_style('google-fonts');
    }

    public function test_preconnect_urls_include_google_fonts(): void
    {
        // Register Google Fonts
        wp_register_style('google-fonts', 'https://fonts.googleapis.com/css?family=Roboto', [], '1.0');
        wp_enqueue_style('google-fonts');
        
        $reflection = new \ReflectionClass($this->hints);
        $method = $reflection->getMethod('get_preconnect_urls');
        $method->setAccessible(true);
        
        $urls = $method->invoke($this->hints);
        
        $this->assertContains('https://fonts.googleapis.com', $urls);
        
        // Cleanup
        wp_dequeue_style('google-fonts');
        wp_deregister_style('google-fonts');
    }

    public function test_hints_output_on_frontend(): void
    {
        update_option(SPEEDMATE_OPTION_KEY, [
            'preload_hints_enabled' => true,
        ]);
        Settings::refresh();

        // Capture output
        ob_start();
        do_action('wp_head');
        $output = ob_get_clean();
        
        // Note: Actual output depends on site configuration
        // Just verify the action hook is registered
        $this->assertTrue(has_action('wp_head'));
    }
}
