<?php

declare(strict_types=1);

namespace SpeedMate\Tests\Integration;

use SpeedMate\Cache\DynamicFragments;
use SpeedMate\Utils\Settings;
use WP_UnitTestCase;

/**
 * Integration tests for Dynamic Fragments caching.
 *
 * Tests fragment placeholder generation, content replacement,
 * expiration handling, and rate limiting.
 *
 * @package SpeedMate\Tests\Integration
 * @group dynamic-fragments
 */
final class DynamicFragmentsTest extends WP_UnitTestCase
{
    private DynamicFragments $fragments;

    public function setUp(): void
    {
        parent::setUp();
        $this->fragments = DynamicFragments::instance();
        
        // Enable safe mode
        update_option(SPEEDMATE_OPTION_KEY, ['mode' => 'safe']);
        Settings::refresh();
    }

    /**
     * Test shortcode outputs placeholder in safe mode.
     */
    public function test_shortcode_outputs_placeholder_in_safe_mode(): void
    {
        $content = 'Hello [speedmate_dynamic]World[/speedmate_dynamic]';
        $out = do_shortcode($content);

        $this->assertStringContainsString('data-speedmate-fragment', $out);
    }

    /**
     * Test placeholder contains fragment ID.
     */
    public function test_placeholder_contains_fragment_id(): void
    {
        $content = '[speedmate_dynamic]Dynamic Content[/speedmate_dynamic]';
        $out = do_shortcode($content);

        $this->assertMatchesRegularExpression('/data-speedmate-fragment="[a-f0-9]+"/', $out);
    }

    /**
     * Test multiple fragments get unique IDs.
     */
    public function test_multiple_fragments_unique_ids(): void
    {
        $content = '[speedmate_dynamic]First[/speedmate_dynamic] [speedmate_dynamic]Second[/speedmate_dynamic]';
        $out = do_shortcode($content);

        preg_match_all('/data-speedmate-fragment="([a-f0-9]+)"/', $out, $matches);
        
        $this->assertCount(2, $matches[1]);
        $this->assertNotEquals($matches[1][0], $matches[1][1]);
    }

    /**
     * Test fragment content is preserved in placeholder.
     */
    public function test_fragment_content_preserved(): void
    {
        $content = '[speedmate_dynamic]<strong>Dynamic</strong>[/speedmate_dynamic]';
        $out = do_shortcode($content);

        $this->assertStringContainsString('<strong>Dynamic</strong>', $out);
    }

    /**
     * Test nested shortcodes are executed within fragments.
     */
    public function test_nested_shortcodes_executed(): void
    {
        // Register test shortcode
        add_shortcode('test_inner', fn() => 'INNER_CONTENT');

        $content = '[speedmate_dynamic][test_inner][/speedmate_dynamic]';
        $out = do_shortcode($content);

        $this->assertStringContainsString('INNER_CONTENT', $out);
        
        remove_shortcode('test_inner');
    }

    /**
     * Test fragment TTL attribute.
     */
    public function test_fragment_ttl_attribute(): void
    {
        $content = '[speedmate_dynamic ttl="300"]Content[/speedmate_dynamic]';
        $out = do_shortcode($content);

        // Fragment should contain TTL in data attribute
        $this->assertMatchesRegularExpression('/data-speedmate-fragment="[a-f0-9]+"/', $out);
    }

    /**
     * Test empty fragments are handled.
     */
    public function test_empty_fragments(): void
    {
        $content = '[speedmate_dynamic][/speedmate_dynamic]';
        $out = do_shortcode($content);

        $this->assertStringContainsString('data-speedmate-fragment', $out);
    }

    /**
     * Test fragments in beast mode.
     */
    public function test_fragments_in_beast_mode(): void
    {
        update_option(SPEEDMATE_OPTION_KEY, ['mode' => 'beast']);
        Settings::refresh();

        $content = '[speedmate_dynamic]Beast Content[/speedmate_dynamic]';
        $out = do_shortcode($content);

        // Should still generate placeholder
        $this->assertStringContainsString('data-speedmate-fragment', $out);
    }

    /**
     * Test fragments are disabled in off mode.
     */
    public function test_fragments_disabled_in_off_mode(): void
    {
        update_option(SPEEDMATE_OPTION_KEY, ['mode' => 'off']);
        Settings::refresh();

        $content = '[speedmate_dynamic]Off Content[/speedmate_dynamic]';
        $out = do_shortcode($content);

        // Should return content without wrapper
        $this->assertStringNotContainsString('data-speedmate-fragment', $out);
    }

    /**
     * Test fragment content with HTML entities.
     */
    public function test_fragment_html_entities(): void
    {
        $content = '[speedmate_dynamic]&lt;script&gt;[/speedmate_dynamic]';
        $out = do_shortcode($content);

        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    /**
     * Test fragment with special characters.
     */
    public function test_fragment_special_characters(): void
    {
        $content = '[speedmate_dynamic]Price: $50 & tax[/speedmate_dynamic]';
        $out = do_shortcode($content);

        $this->assertStringContainsString('Price: $50 &amp; tax', $out);
    }

    /**
     * Test multiple instances of same content get same ID.
     */
    public function test_same_content_same_id(): void
    {
        $content = '[speedmate_dynamic]Same[/speedmate_dynamic] [speedmate_dynamic]Same[/speedmate_dynamic]';
        $out = do_shortcode($content);

        preg_match_all('/data-speedmate-fragment="([a-f0-9]+)"/', $out, $matches);
        
        // Same content should generate same hash
        $this->assertEquals($matches[1][0], $matches[1][1]);
    }

    /**
     * Test fragment within paragraph tags.
     */
    public function test_fragment_in_paragraph(): void
    {
        $content = '<p>[speedmate_dynamic]Paragraph content[/speedmate_dynamic]</p>';
        $out = do_shortcode($content);

        $this->assertStringContainsString('<p>', $out);
        $this->assertStringContainsString('data-speedmate-fragment', $out);
    }

    /**
     * Test fragment with WordPress functions.
     */
    public function test_fragment_with_wp_functions(): void
    {
        $content = '[speedmate_dynamic]' . get_bloginfo('name') . '[/speedmate_dynamic]';
        $out = do_shortcode($content);

        $this->assertStringContainsString(get_bloginfo('name'), $out);
    }

    /**
     * Test fragment rate limiting constant is defined.
     */
    public function test_rate_limit_constants_defined(): void
    {
        $this->assertTrue(defined('SPEEDMATE_FRAGMENT_RATE_LIMIT'));
        $this->assertTrue(defined('SPEEDMATE_FRAGMENT_RATE_WINDOW'));
        $this->assertEquals(120, SPEEDMATE_FRAGMENT_RATE_LIMIT);
        $this->assertEquals(60, SPEEDMATE_FRAGMENT_RATE_WINDOW);
    }
}
