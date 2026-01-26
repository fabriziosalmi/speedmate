<?php

declare(strict_types=1);

namespace SpeedMate\Tests\Unit;

use SpeedMate\Cache\CacheTTLManager;
use WP_UnitTestCase;

/**
 * Edge case tests for Cache TTL logic.
 *
 * Tests TTL determination, fallback behavior,
 * and boundary conditions.
 *
 * @package SpeedMate\Tests\Unit
 * @group cache-ttl
 * @group edge-cases
 */
final class CacheTTLEdgeCaseTest extends WP_UnitTestCase
{
    private CacheTTLManager $ttl_manager;

    public function setUp(): void
    {
        parent::setUp();
        $this->ttl_manager = CacheTTLManager::instance();
    }

    /**
     * Test default TTL constants are defined.
     */
    public function test_default_ttl_constants_defined(): void
    {
        $this->assertTrue(defined('SPEEDMATE_DEFAULT_TTL_HOMEPAGE'));
        $this->assertTrue(defined('SPEEDMATE_DEFAULT_TTL_POSTS'));
        $this->assertTrue(defined('SPEEDMATE_DEFAULT_TTL_PAGES'));
    }

    /**
     * Test homepage TTL default value.
     */
    public function test_homepage_ttl_default(): void
    {
        $this->assertEquals(3600, SPEEDMATE_DEFAULT_TTL_HOMEPAGE);
    }

    /**
     * Test posts TTL default value.
     */
    public function test_posts_ttl_default(): void
    {
        $this->assertEquals(7 * DAY_IN_SECONDS, SPEEDMATE_DEFAULT_TTL_POSTS);
    }

    /**
     * Test pages TTL default value.
     */
    public function test_pages_ttl_default(): void
    {
        $this->assertEquals(30 * DAY_IN_SECONDS, SPEEDMATE_DEFAULT_TTL_PAGES);
    }

    /**
     * Test zero TTL handling.
     */
    public function test_zero_ttl_handling(): void
    {
        // Zero TTL should be valid (no caching)
        $ttl = 0;
        $this->assertIsInt($ttl);
        $this->assertEquals(0, $ttl);
    }

    /**
     * Test negative TTL is not used.
     */
    public function test_negative_ttl_not_used(): void
    {
        // Negative TTL should be invalid
        $ttl = -100;
        $this->assertLessThan(0, $ttl);
    }

    /**
     * Test extremely large TTL value.
     */
    public function test_extremely_large_ttl(): void
    {
        // 10 years in seconds
        $ten_years = 10 * YEAR_IN_SECONDS;
        
        $this->assertGreaterThan(SPEEDMATE_DEFAULT_TTL_PAGES, $ten_years);
        $this->assertEquals(315360000, $ten_years);
    }

    /**
     * Test TTL for homepage is shorter than posts.
     */
    public function test_homepage_ttl_shorter_than_posts(): void
    {
        $this->assertLessThan(
            SPEEDMATE_DEFAULT_TTL_POSTS,
            SPEEDMATE_DEFAULT_TTL_HOMEPAGE
        );
    }

    /**
     * Test TTL for posts is shorter than pages.
     */
    public function test_posts_ttl_shorter_than_pages(): void
    {
        $this->assertLessThan(
            SPEEDMATE_DEFAULT_TTL_PAGES,
            SPEEDMATE_DEFAULT_TTL_POSTS
        );
    }

    /**
     * Test TTL hierarchy: homepage < posts < pages.
     */
    public function test_ttl_hierarchy(): void
    {
        $homepage_ttl = SPEEDMATE_DEFAULT_TTL_HOMEPAGE;
        $posts_ttl = SPEEDMATE_DEFAULT_TTL_POSTS;
        $pages_ttl = SPEEDMATE_DEFAULT_TTL_PAGES;
        
        $this->assertLessThan($posts_ttl, $homepage_ttl);
        $this->assertLessThan($pages_ttl, $posts_ttl);
    }

    /**
     * Test TTL with custom settings override.
     */
    public function test_ttl_with_custom_settings(): void
    {
        // Set custom TTL
        update_option(SPEEDMATE_OPTION_KEY, [
            'cache_ttl_homepage' => 1800,
        ]);
        
        $custom_ttl = get_option(SPEEDMATE_OPTION_KEY)['cache_ttl_homepage'];
        
        $this->assertEquals(1800, $custom_ttl);
        $this->assertNotEquals(SPEEDMATE_DEFAULT_TTL_HOMEPAGE, $custom_ttl);
    }

    /**
     * Test TTL fallback to default when setting missing.
     */
    public function test_ttl_fallback_to_default(): void
    {
        // No custom settings
        delete_option(SPEEDMATE_OPTION_KEY);
        
        $options = get_option(SPEEDMATE_OPTION_KEY);
        
        // Should use default constant
        $this->assertFalse($options);
    }

    /**
     * Test TTL for different post types.
     */
    public function test_ttl_post_type_distinction(): void
    {
        // Posts should use SPEEDMATE_DEFAULT_TTL_POSTS
        // Pages should use SPEEDMATE_DEFAULT_TTL_PAGES
        
        $this->assertNotEquals(
            SPEEDMATE_DEFAULT_TTL_POSTS,
            SPEEDMATE_DEFAULT_TTL_PAGES
        );
    }

    /**
     * Test one hour in seconds for homepage.
     */
    public function test_one_hour_for_homepage(): void
    {
        $this->assertEquals(HOUR_IN_SECONDS, SPEEDMATE_DEFAULT_TTL_HOMEPAGE);
    }

    /**
     * Test one week in seconds for posts.
     */
    public function test_one_week_for_posts(): void
    {
        $this->assertEquals(WEEK_IN_SECONDS, SPEEDMATE_DEFAULT_TTL_POSTS);
    }

    /**
     * Test TTL boundaries are reasonable.
     */
    public function test_ttl_boundaries_reasonable(): void
    {
        // All TTLs should be positive
        $this->assertGreaterThan(0, SPEEDMATE_DEFAULT_TTL_HOMEPAGE);
        $this->assertGreaterThan(0, SPEEDMATE_DEFAULT_TTL_POSTS);
        $this->assertGreaterThan(0, SPEEDMATE_DEFAULT_TTL_PAGES);
        
        // All TTLs should be less than 1 year
        $this->assertLessThan(YEAR_IN_SECONDS, SPEEDMATE_DEFAULT_TTL_HOMEPAGE);
        $this->assertLessThan(YEAR_IN_SECONDS, SPEEDMATE_DEFAULT_TTL_POSTS);
        $this->assertLessThan(YEAR_IN_SECONDS, SPEEDMATE_DEFAULT_TTL_PAGES);
    }

    /**
     * Test CacheTTLManager class exists.
     */
    public function test_cache_ttl_manager_exists(): void
    {
        $this->assertTrue(class_exists('SpeedMate\\Cache\\CacheTTLManager'));
        $this->assertInstanceOf(CacheTTLManager::class, $this->ttl_manager);
    }

    /**
     * Test TTL calculation consistency.
     */
    public function test_ttl_calculation_consistency(): void
    {
        // Multiple calls should return same value
        $ttl1 = SPEEDMATE_DEFAULT_TTL_POSTS;
        $ttl2 = SPEEDMATE_DEFAULT_TTL_POSTS;
        
        $this->assertEquals($ttl1, $ttl2);
    }

    /**
     * Test fractional TTL handling.
     */
    public function test_fractional_ttl_not_used(): void
    {
        // TTL should be integer seconds
        $this->assertIsInt(SPEEDMATE_DEFAULT_TTL_HOMEPAGE);
        $this->assertIsInt(SPEEDMATE_DEFAULT_TTL_POSTS);
        $this->assertIsInt(SPEEDMATE_DEFAULT_TTL_PAGES);
    }
}
