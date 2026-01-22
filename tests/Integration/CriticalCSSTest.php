<?php

declare(strict_types=1);

namespace SpeedMate\Tests\Integration;

use SpeedMate\Perf\CriticalCSS;
use SpeedMate\Utils\Settings;
use WP_UnitTestCase;

final class CriticalCSSTest extends WP_UnitTestCase
{
    private CriticalCSS $critical;

    public function setUp(): void
    {
        parent::setUp();
        $this->critical = CriticalCSS::instance();
    }

    public function test_stylesheets_are_deferred(): void
    {
        update_option(SPEEDMATE_OPTION_KEY, [
            'critical_css_enabled' => true,
        ]);
        Settings::refresh();

        $html = '<link rel="stylesheet" href="style.css">';
        
        $reflection = new \ReflectionClass($this->critical);
        $method = $reflection->getMethod('defer_stylesheets');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->critical, $html);
        
        $this->assertStringContainsString('media="print"', $result);
        $this->assertStringContainsString('onload=', $result);
    }

    public function test_critical_css_disabled_by_default(): void
    {
        $settings = Settings::get();
        $enabled = $settings['critical_css_enabled'] ?? false;
        
        $this->assertFalse($enabled);
    }

    public function test_existing_print_media_not_modified(): void
    {
        $html = '<link rel="stylesheet" href="print.css" media="print">';
        
        $reflection = new \ReflectionClass($this->critical);
        $method = $reflection->getMethod('defer_stylesheets');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->critical, $html);
        
        // Should remain unchanged
        $this->assertStringContainsString('media="print"', $result);
    }
}
