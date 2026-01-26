<?php

declare(strict_types=1);

namespace SpeedMate\Tests\Integration;

use SpeedMate\Media\MediaOptimizer;
use SpeedMate\Utils\Settings;
use WP_UnitTestCase;

/**
 * Integration tests for Media Optimizer functionality.
 *
 * Tests image lazy loading, dimension injection,
 * and DOM manipulation for media optimization.
 *
 * @package SpeedMate\Tests\Integration
 * @group media-optimizer
 */
final class MediaOptimizerTest extends WP_UnitTestCase
{
    private MediaOptimizer $optimizer;

    public function setUp(): void
    {
        parent::setUp();
        $this->optimizer = MediaOptimizer::instance();
        
        // Enable media optimization
        update_option(SPEEDMATE_OPTION_KEY, ['mode' => 'safe']);
        Settings::refresh();
    }

    /**
     * Test lazy loading is applied to images.
     */
    public function test_lazy_loading_applied_to_images(): void
    {
        $html = '<img src="test.jpg" alt="Test">';
        $result = $this->optimizer->optimize_content($html);
        
        $this->assertStringContainsString('loading="lazy"', $result);
    }

    /**
     * Test lazy loading is applied to iframes.
     */
    public function test_lazy_loading_applied_to_iframes(): void
    {
        $html = '<iframe src="https://example.com/video"></iframe>';
        $result = $this->optimizer->optimize_content($html);
        
        $this->assertStringContainsString('loading="lazy"', $result);
    }

    /**
     * Test lazy loading is not duplicated if already present.
     */
    public function test_lazy_loading_not_duplicated(): void
    {
        $html = '<img src="test.jpg" loading="lazy" alt="Test">';
        $result = $this->optimizer->optimize_content($html);
        
        // Should still have only one loading attribute
        $this->assertEquals(1, substr_count($result, 'loading='));
    }

    /**
     * Test images with loading="eager" are preserved.
     */
    public function test_eager_loading_preserved(): void
    {
        $html = '<img src="test.jpg" loading="eager" alt="Test">';
        $result = $this->optimizer->optimize_content($html);
        
        $this->assertStringContainsString('loading="eager"', $result);
        $this->assertStringNotContainsString('loading="lazy"', $result);
    }

    /**
     * Test dimension injection for WordPress attachments.
     */
    public function test_dimension_injection_for_attachments(): void
    {
        // Create test attachment
        $attachment_id = $this->factory()->attachment->create_upload_object(
            SPEEDMATE_PATH . 'tests/fixtures/test-image.jpg'
        );
        
        if ($attachment_id) {
            $url = wp_get_attachment_url($attachment_id);
            $html = '<img src="' . $url . '" alt="Test">';
            
            $result = $this->optimizer->optimize_content($html);
            
            // Should have width and height
            $this->assertMatchesRegularExpression('/width="\d+"/', $result);
            $this->assertMatchesRegularExpression('/height="\d+"/', $result);
        } else {
            $this->markTestSkipped('Could not create test attachment');
        }
    }

    /**
     * Test images with existing dimensions are not modified.
     */
    public function test_existing_dimensions_preserved(): void
    {
        $html = '<img src="test.jpg" width="800" height="600" alt="Test">';
        $result = $this->optimizer->optimize_content($html);
        
        $this->assertStringContainsString('width="800"', $result);
        $this->assertStringContainsString('height="600"', $result);
    }

    /**
     * Test multiple images are all optimized.
     */
    public function test_multiple_images_optimization(): void
    {
        $html = '<img src="test1.jpg"><img src="test2.jpg"><img src="test3.jpg">';
        $result = $this->optimizer->optimize_content($html);
        
        // All 3 images should have lazy loading
        $this->assertEquals(3, substr_count($result, 'loading="lazy"'));
    }

    /**
     * Test SVG images are handled correctly.
     */
    public function test_svg_images_handling(): void
    {
        $html = '<img src="icon.svg" alt="Icon">';
        $result = $this->optimizer->optimize_content($html);
        
        // SVG should still get lazy loading
        $this->assertStringContainsString('loading="lazy"', $result);
    }

    /**
     * Test data URIs are not modified.
     */
    public function test_data_uri_images_preserved(): void
    {
        $html = '<img src="data:image/png;base64,iVBORw..." alt="Test">';
        $result = $this->optimizer->optimize_content($html);
        
        // Data URIs should be preserved
        $this->assertStringContainsString('data:image/', $result);
    }

    /**
     * Test images without src attribute are handled.
     */
    public function test_images_without_src(): void
    {
        $html = '<img alt="No Source">';
        $result = $this->optimizer->optimize_content($html);
        
        // Should not crash
        $this->assertIsString($result);
    }

    /**
     * Test malformed HTML is handled gracefully.
     */
    public function test_malformed_html_handling(): void
    {
        $html = '<img src="test.jpg" <img src="test2.jpg">';
        $result = $this->optimizer->optimize_content($html);
        
        // Should not crash
        $this->assertIsString($result);
    }

    /**
     * Test YouTube iframes are lazy loaded.
     */
    public function test_youtube_iframes_lazy_loading(): void
    {
        $html = '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>';
        $result = $this->optimizer->optimize_content($html);
        
        $this->assertStringContainsString('loading="lazy"', $result);
    }

    /**
     * Test responsive images (srcset) are preserved.
     */
    public function test_responsive_images_preserved(): void
    {
        $html = '<img src="test.jpg" srcset="test-800.jpg 800w, test-1200.jpg 1200w" alt="Test">';
        $result = $this->optimizer->optimize_content($html);
        
        $this->assertStringContainsString('srcset=', $result);
        $this->assertStringContainsString('loading="lazy"', $result);
    }

    /**
     * Test images in noscript tags are not modified.
     */
    public function test_noscript_images_ignored(): void
    {
        $html = '<noscript><img src="test.jpg" alt="Test"></noscript>';
        $result = $this->optimizer->optimize_content($html);
        
        // Noscript content should be preserved as-is
        $this->assertStringContainsString('<noscript>', $result);
    }

    /**
     * Test empty content returns empty string.
     */
    public function test_empty_content_handling(): void
    {
        $result = $this->optimizer->optimize_content('');
        $this->assertEquals('', $result);
    }

    /**
     * Test content without images is returned unchanged.
     */
    public function test_content_without_images(): void
    {
        $html = '<div><p>No images here</p></div>';
        $result = $this->optimizer->optimize_content($html);
        
        $this->assertEquals($html, $result);
    }

    /**
     * Test WordPress attachment URL normalization.
     */
    public function test_url_normalization(): void
    {
        $url_with_query = 'https://example.com/image.jpg?ver=1.0';
        
        $reflection = new \ReflectionClass($this->optimizer);
        $method = $reflection->getMethod('normalize_src');
        $method->setAccessible(true);
        
        $normalized = $method->invoke($this->optimizer, $url_with_query);
        
        $this->assertEquals('https://example.com/image.jpg', $normalized);
    }

    /**
     * Test CLS prevention through dimensions.
     */
    public function test_cls_prevention_with_dimensions(): void
    {
        // Create test attachment with known dimensions
        $attachment_id = $this->factory()->attachment->create_upload_object(
            SPEEDMATE_PATH . 'tests/fixtures/test-image.jpg'
        );
        
        if ($attachment_id) {
            // Set metadata
            wp_update_attachment_metadata($attachment_id, [
                'width' => 1920,
                'height' => 1080,
            ]);
            
            $url = wp_get_attachment_url($attachment_id);
            $html = '<img src="' . $url . '" alt="Test">';
            
            $result = $this->optimizer->optimize_content($html);
            
            // Should have dimensions for CLS prevention
            $this->assertMatchesRegularExpression('/width="\d+"/', $result);
            $this->assertMatchesRegularExpression('/height="\d+"/', $result);
        } else {
            $this->markTestSkipped('Could not create test attachment');
        }
    }

    /**
     * Test performance with large HTML content.
     */
    public function test_performance_with_large_content(): void
    {
        // Generate HTML with 100 images
        $html = str_repeat('<img src="test.jpg" alt="Test">', 100);
        
        $start = microtime(true);
        $result = $this->optimizer->optimize_content($html);
        $duration = microtime(true) - $start;
        
        // Should complete in reasonable time (< 1 second)
        $this->assertLessThan(1.0, $duration);
        $this->assertEquals(100, substr_count($result, 'loading="lazy"'));
    }
}
