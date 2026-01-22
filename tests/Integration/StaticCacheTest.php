<?php

declare(strict_types=1);

namespace SpeedMate\Tests\Integration;

use SpeedMate\Cache\StaticCache;
use SpeedMate\Utils\Settings;
use WP_UnitTestCase;

final class StaticCacheTest extends WP_UnitTestCase
{
    private StaticCache $cache;

    public function setUp(): void
    {
        parent::setUp();
        $this->cache = StaticCache::instance();
    }

    public function test_cache_directory_is_created_on_activation(): void
    {
        StaticCache::activate();
        $this->assertTrue(is_dir(SPEEDMATE_CACHE_DIR));
    }

    public function test_cache_ttl_metadata_is_written(): void
    {
        // Enable caching
        update_option(SPEEDMATE_OPTION_KEY, [
            'mode' => 'safe',
            'cache_ttl' => 3600,
        ]);
        Settings::refresh();

        // Simulate cache write
        $test_html = '<html><body>Test</body></html>';
        $path = SPEEDMATE_CACHE_DIR . '/test/index.html';
        
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        file_put_contents($path, $test_html);
        
        // Write metadata
        $meta = [
            'created' => time(),
            'ttl' => 3600,
        ];
        file_put_contents($path . '.meta', wp_json_encode($meta));

        // Verify metadata exists
        $this->assertTrue(file_exists($path . '.meta'));
        
        $meta_content = file_get_contents($path . '.meta');
        $meta_data = json_decode($meta_content, true);
        
        $this->assertIsArray($meta_data);
        $this->assertArrayHasKey('created', $meta_data);
        $this->assertArrayHasKey('ttl', $meta_data);
        $this->assertEquals(3600, $meta_data['ttl']);
        
        // Cleanup
        unlink($path);
        unlink($path . '.meta');
        rmdir(dirname($path));
    }

    public function test_url_exclusion_patterns(): void
    {
        update_option(SPEEDMATE_OPTION_KEY, [
            'mode' => 'safe',
            'cache_exclude_urls' => [
                '/checkout/',
                '/cart/',
            ],
        ]);
        Settings::refresh();

        // Test excluded URL
        $_SERVER['REQUEST_URI'] = '/checkout/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->cache);
        $method = $reflection->getMethod('is_excluded_url');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->cache);
        $this->assertTrue($result);

        // Test non-excluded URL
        $_SERVER['REQUEST_URI'] = '/about/';
        $result = $method->invoke($this->cache);
        $this->assertFalse($result);
    }

    public function test_cookie_exclusion(): void
    {
        update_option(SPEEDMATE_OPTION_KEY, [
            'mode' => 'safe',
            'cache_exclude_cookies' => [
                'woocommerce_items_in_cart',
            ],
        ]);
        Settings::refresh();

        // Test with excluded cookie
        $_COOKIE['woocommerce_items_in_cart'] = '1';
        
        $reflection = new \ReflectionClass($this->cache);
        $method = $reflection->getMethod('has_excluded_cookies');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->cache);
        $this->assertTrue($result);

        // Test without excluded cookie
        unset($_COOKIE['woocommerce_items_in_cart']);
        $result = $method->invoke($this->cache);
        $this->assertFalse($result);
    }

    public function test_cache_size_calculation(): void
    {
        // Create test cache files
        $test_dir = SPEEDMATE_CACHE_DIR . '/test-size';
        mkdir($test_dir, 0755, true);
        
        file_put_contents($test_dir . '/index.html', str_repeat('x', 1024)); // 1KB
        
        $size = $this->cache->get_cache_size_bytes();
        $this->assertGreaterThan(0, $size);
        
        // Cleanup
        unlink($test_dir . '/index.html');
        rmdir($test_dir);
    }

    public function test_cached_pages_count(): void
    {
        // Create test cache files
        $test_dir1 = SPEEDMATE_CACHE_DIR . '/page1';
        $test_dir2 = SPEEDMATE_CACHE_DIR . '/page2';
        
        mkdir($test_dir1, 0755, true);
        mkdir($test_dir2, 0755, true);
        
        file_put_contents($test_dir1 . '/index.html', 'test1');
        file_put_contents($test_dir2 . '/index.html', 'test2');
        
        $count = $this->cache->get_cached_pages_count();
        $this->assertGreaterThanOrEqual(2, $count);
        
        // Cleanup
        unlink($test_dir1 . '/index.html');
        unlink($test_dir2 . '/index.html');
        rmdir($test_dir1);
        rmdir($test_dir2);
    }

    public function test_flush_all_removes_cache(): void
    {
        // Create test cache
        $test_dir = SPEEDMATE_CACHE_DIR . '/test-flush';
        mkdir($test_dir, 0755, true);
        file_put_contents($test_dir . '/index.html', 'test');
        
        $this->assertTrue(file_exists($test_dir . '/index.html'));
        
        // Flush cache
        $this->cache->flush_all();
        
        // Verify removed
        $this->assertFalse(file_exists($test_dir . '/index.html'));
    }
}
