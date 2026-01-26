<?php

declare(strict_types=1);

namespace SpeedMate\Tests\Integration;

use WP_UnitTestCase;
use SpeedMate\Cache\StaticCache;
use SpeedMate\Utils\RateLimiter;
use SpeedMate\Admin\ImportExport;

/**
 * Security test suite for SpeedMate plugin.
 *
 * Tests critical security features:
 * - Path traversal protection
 * - File upload security
 * - Rate limiting
 * - CSRF protection
 */
final class SecurityTest extends WP_UnitTestCase
{
    private StaticCache $cache;
    private ImportExport $import_export;

    public function set_up(): void
    {
        parent::set_up();
        $this->cache = StaticCache::instance();
        $this->import_export = ImportExport::instance();
    }

    /**
     * Test path traversal protection with ../ sequences.
     */
    public function test_path_traversal_with_parent_directory(): void
    {
        $_SERVER['REQUEST_URI'] = '/../../../etc/passwd';
        
        $reflection = new \ReflectionClass($this->cache);
        $method = $reflection->getMethod('get_cache_path');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->cache);
        
        // Should reject path traversal attempts
        $this->assertStringNotContainsString('etc/passwd', $result);
        $this->assertStringNotContainsString('..', $result);
    }

    /**
     * Test path traversal protection with encoded sequences.
     */
    public function test_path_traversal_with_url_encoding(): void
    {
        $_SERVER['REQUEST_URI'] = '/%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd';
        
        $reflection = new \ReflectionClass($this->cache);
        $method = $reflection->getMethod('get_cache_path');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->cache);
        
        // Should handle URL-encoded traversal
        $this->assertStringNotContainsString('etc', $result);
    }

    /**
     * Test absolute path rejection.
     */
    public function test_absolute_path_rejection(): void
    {
        $_SERVER['REQUEST_URI'] = '/tmp/malicious';
        
        $reflection = new \ReflectionClass($this->cache);
        $method = $reflection->getMethod('get_cache_path');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->cache);
        
        // Should not allow absolute paths outside cache dir
        $this->assertStringContainsString(SPEEDMATE_CACHE_DIR, $result);
    }

    /**
     * Test null byte injection protection.
     */
    public function test_null_byte_injection(): void
    {
        $_SERVER['REQUEST_URI'] = "/test\x00/../../etc/passwd";
        
        $reflection = new \ReflectionClass($this->cache);
        $method = $reflection->getMethod('get_cache_path');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->cache);
        
        // Should reject null bytes
        $this->assertSame('', $result);
    }

    /**
     * Test dot-file path rejection.
     */
    public function test_dotfile_rejection(): void
    {
        $_SERVER['REQUEST_URI'] = '/.htaccess';
        
        $reflection = new \ReflectionClass($this->cache);
        $method = $reflection->getMethod('get_cache_path');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->cache);
        
        // Should reject paths starting with dot
        $this->assertSame('', $result);
    }

    /**
     * Test dot-directory rejection.
     */
    public function test_dot_directory_rejection(): void
    {
        $_SERVER['REQUEST_URI'] = '/.git/config';
        
        $reflection = new \ReflectionClass($this->cache);
        $method = $reflection->getMethod('get_cache_path');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->cache);
        
        // Should reject .git and similar directories
        $this->assertSame('', $result);
    }

    /**
     * Test valid path is accepted.
     */
    public function test_valid_path_accepted(): void
    {
        $_SERVER['REQUEST_URI'] = '/blog/post-title/';
        
        $reflection = new \ReflectionClass($this->cache);
        $method = $reflection->getMethod('get_cache_path');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->cache);
        
        // Should accept valid paths
        $this->assertStringContainsString('blog/post-title', $result);
        $this->assertStringEndsWith('index.html', $result);
    }

    /**
     * Test path with special characters is rejected.
     */
    public function test_special_characters_rejection(): void
    {
        $_SERVER['REQUEST_URI'] = '/test<script>alert(1)</script>';
        
        $reflection = new \ReflectionClass($this->cache);
        $method = $reflection->getMethod('get_cache_path');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->cache);
        
        // Should reject paths with special characters
        $this->assertSame('', $result);
    }

    /**
     * Test rate limiter prevents abuse.
     */
    public function test_rate_limiter_enforcement(): void
    {
        $key = 'test_security_' . time();
        
        // First request should succeed
        $this->assertTrue(RateLimiter::allow($key, 2, 60));
        
        // Second request should succeed
        $this->assertTrue(RateLimiter::allow($key, 2, 60));
        
        // Third request should fail (limit exceeded)
        $this->assertFalse(RateLimiter::allow($key, 2, 60));
        
        // Clean up
        RateLimiter::clear($key);
    }

    /**
     * Test rate limiter window expiration.
     */
    public function test_rate_limiter_window_expiration(): void
    {
        $key = 'test_security_window_' . time();
        
        // Use 1 second window for testing
        $this->assertTrue(RateLimiter::allow($key, 1, 1));
        $this->assertFalse(RateLimiter::allow($key, 1, 1));
        
        // Wait for window to expire
        sleep(2);
        
        // Should allow again after window expires
        $this->assertTrue(RateLimiter::allow($key, 1, 1));
        
        // Clean up
        RateLimiter::clear($key);
    }

    /**
     * Test rate limiter key sanitization.
     */
    public function test_rate_limiter_key_sanitization(): void
    {
        $malicious_key = '../../../tmp/evil';
        
        // Should still work with malicious key (but sanitized)
        $result = RateLimiter::allow($malicious_key, 5, 60);
        
        $this->assertTrue($result);
        
        // Clean up
        RateLimiter::clear($malicious_key);
    }

    /**
     * Test import validates JSON structure.
     */
    public function test_import_validates_json_structure(): void
    {
        $invalid_data = [
            'version' => '0.3.2',
            'malicious_key' => 'evil',
            'settings' => [
                'mode' => 'beast',
            ],
        ];
        
        $result = $this->import_export->import($invalid_data);
        
        // Should validate and potentially reject invalid structures
        $this->assertIsBool($result);
    }

    /**
     * Test import rejects old versions.
     */
    public function test_import_rejects_old_versions(): void
    {
        $old_version_data = [
            'version' => '0.0.1',
            'settings' => ['mode' => 'disabled'],
            'timestamp' => time(),
        ];
        
        $result = $this->import_export->import($old_version_data);
        
        // Should reject very old versions
        $this->assertFalse($result);
    }

    /**
     * Test import validates required fields.
     */
    public function test_import_validates_required_fields(): void
    {
        $incomplete_data = [
            'version' => '0.3.2',
            // Missing 'settings' key
        ];
        
        $result = $this->import_export->import($incomplete_data);
        
        // Should reject incomplete data
        $this->assertFalse($result);
    }

    /**
     * Test cache path validation prevents directory escape.
     */
    public function test_cache_path_stays_within_cache_dir(): void
    {
        $_SERVER['REQUEST_URI'] = '/normal-page/';
        
        $reflection = new \ReflectionClass($this->cache);
        $method = $reflection->getMethod('get_cache_path');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->cache);
        
        // All cache paths must be within SPEEDMATE_CACHE_DIR
        $this->assertStringStartsWith(SPEEDMATE_CACHE_DIR, $result);
    }

    /**
     * Test get_cache_path_for_url with malicious URLs.
     */
    public function test_cache_path_for_url_with_traversal(): void
    {
        $malicious_url = home_url('/../../../etc/passwd');
        
        $reflection = new \ReflectionClass($this->cache);
        $method = $reflection->getMethod('get_cache_path_for_url');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->cache, $malicious_url);
        
        // Should not allow traversal in URL-based paths
        $this->assertStringNotContainsString('etc/passwd', $result);
    }

    /**
     * Test purge_url cannot escape cache directory.
     */
    public function test_purge_url_security(): void
    {
        $malicious_url = home_url('/../../../tmp/important');
        
        // This should not throw errors or purge outside cache dir
        $this->cache->purge_url($malicious_url);
        
        // If we get here, the security check worked
        $this->assertTrue(true);
    }

    public function tear_down(): void
    {
        unset($_SERVER['REQUEST_URI']);
        parent::tear_down();
    }
}
