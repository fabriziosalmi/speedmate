<?php

declare(strict_types=1);

namespace SpeedMate\Tests\Integration;

use SpeedMate\Utils\Multisite;
use WP_UnitTestCase;

final class MultisiteTest extends WP_UnitTestCase
{
    public function test_multisite_detection(): void
    {
        $result = Multisite::is_multisite();
        $this->assertIsBool($result);
        $this->assertEquals(is_multisite(), $result);
    }

    public function test_site_cache_dir_returns_valid_path(): void
    {
        $dir = Multisite::get_site_cache_dir();
        
        $this->assertIsString($dir);
        $this->assertNotEmpty($dir);
        
        if (is_multisite()) {
            $this->assertStringContainsString('site-', $dir);
        }
    }

    public function test_network_cache_dir_structure(): void
    {
        $dir = Multisite::get_network_cache_dir();
        
        $this->assertIsString($dir);
        
        if (is_multisite()) {
            $this->assertStringContainsString('speedmate-network', $dir);
        }
    }

    public function test_settings_with_fallback(): void
    {
        $settings = Multisite::get_settings();
        
        $this->assertIsArray($settings);
        $this->assertArrayHasKey('mode', $settings);
    }

    public function test_can_manage_network_permission(): void
    {
        $result = Multisite::can_manage_network();
        $this->assertIsBool($result);
    }
}
