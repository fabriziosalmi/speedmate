<?php

declare(strict_types=1);

namespace SpeedMate\Tests\Integration;

use SpeedMate\Utils\Multisite;
use SpeedMate\Cache\StaticCache;
use SpeedMate\Utils\Settings;
use WP_UnitTestCase;

/**
 * Integration tests for Multisite functionality.
 *
 * Tests network-wide cache operations, site switching,
 * and site-specific cache isolation.
 *
 * @package SpeedMate\Tests\Integration
 * @group multisite
 */
final class MultisiteTest extends WP_UnitTestCase
{
    private StaticCache $cache;

    public function setUp(): void
    {
        parent::setUp();
        $this->cache = StaticCache::instance();
    }

    public function tearDown(): void
    {
        // Clean up test cache directories
        if (is_dir(SPEEDMATE_CACHE_DIR)) {
            $this->recursive_rmdir(SPEEDMATE_CACHE_DIR);
        }
        parent::tearDown();
    }

    /**
     * Test that multisite detection works correctly.
     */
    public function test_multisite_detection(): void
    {
        $result = Multisite::is_multisite();
        $this->assertIsBool($result);
        $this->assertEquals(is_multisite(), $result);
    }

    /**
     * Test that site-specific cache directories are created correctly.
     *
     * @group multisite-required
     */
    public function test_site_cache_dir_returns_valid_path(): void
    {
        $dir = Multisite::get_site_cache_dir();
        
        $this->assertIsString($dir);
        $this->assertNotEmpty($dir);
        
        if (is_multisite()) {
            $site_id = get_current_blog_id();
            $expected_dir = trailingslashit(SPEEDMATE_CACHE_DIR) . 'site-' . $site_id;
            $this->assertEquals($expected_dir, $dir);
            $this->assertStringContainsString('site-', $dir);
        } else {
            $this->assertEquals(SPEEDMATE_CACHE_DIR, $dir);
        }
    }

    /**
     * Test network cache directory is separate from site caches.
     */
    public function test_network_cache_dir_structure(): void
    {
        $dir = Multisite::get_network_cache_dir();
        
        $this->assertIsString($dir);
        
        if (is_multisite()) {
            $site_dir = Multisite::get_site_cache_dir();
            $this->assertNotEquals($dir, $site_dir);
            $this->assertStringContainsString('speedmate-network', $dir);
        } else {
            $this->assertEquals(SPEEDMATE_CACHE_DIR, $dir);
        }
    }

    /**
     * Test cache isolation between sites.
     *
     * @group multisite-required
     */
    public function test_cache_isolation_between_sites(): void
    {
        if (!is_multisite()) {
            $this->markTestSkipped('This test requires multisite');
        }

        $original_site = get_current_blog_id();

        // Create cache on current site
        $cache_content = '<html><body>Site Content</body></html>';
        $cache_dir = Multisite::get_site_cache_dir();
        $cache_path = $cache_dir . '/test/index.html';

        if (!is_dir(dirname($cache_path))) {
            mkdir(dirname($cache_path), 0755, true);
        }
        file_put_contents($cache_path, $cache_content);

        $this->assertFileExists($cache_path);
        $this->assertEquals($cache_content, file_get_contents($cache_path));
    }

    /**
     * Test network settings inheritance with site overrides.
     *
     * @group multisite-required
     */
    public function test_settings_with_fallback(): void
    {
        $settings = Multisite::get_settings();
        
        $this->assertIsArray($settings);
        $this->assertArrayHasKey('mode', $settings);
        
        if (is_multisite()) {
            // Set network-wide setting
            update_site_option(SPEEDMATE_OPTION_KEY . '_network', [
                'cache_ttl' => 7200,
                'beast_whitelist' => ['test'],
            ]);

            // Set site-specific override
            update_option(SPEEDMATE_OPTION_KEY, [
                'mode' => 'aggressive',
            ]);
            Settings::refresh();

            $merged = Multisite::get_settings();
            
            // Site override should win
            $this->assertEquals('aggressive', $merged['mode']);
            
            // Network setting should be inherited
            $this->assertEquals(7200, $merged['cache_ttl'] ?? null);
        }
    }

    /**
     * Test network admin capability check.
     */
    public function test_can_manage_network_permission(): void
    {
        $result = Multisite::can_manage_network();
        $this->assertIsBool($result);
        
        if (!is_multisite()) {
            $this->assertFalse($result);
        }
    }

    /**
     * Test transients are site-specific in multisite.
     *
     * @group multisite-required
     */
    public function test_transients_site_isolation(): void
    {
        if (!is_multisite()) {
            $this->markTestSkipped('This test requires multisite');
        }

        // Set transient on current site
        set_transient('speedmate_test_transient', 'test_value', 3600);
        $this->assertEquals('test_value', get_transient('speedmate_test_transient'));

        // Cleanup
        delete_transient('speedmate_test_transient');
    }

    /**
     * Helper to recursively remove directory.
     */
    private function recursive_rmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->recursive_rmdir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
