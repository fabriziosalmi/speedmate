<?php

declare(strict_types=1);

namespace SpeedMate\Tests\Integration;

use SpeedMate\Utils\Stats;
use WP_UnitTestCase;

final class StatsTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        
        // Create stats table
        Stats::create_table();
    }

    public function test_stats_table_is_created(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'speedmate_stats';
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        $this->assertTrue($table_exists);
    }

    public function test_increment_stats(): void
    {
        Stats::increment('warmed_pages', 5);
        
        $stats = Stats::get();
        $this->assertGreaterThanOrEqual(5, $stats['warmed_pages']);
    }

    public function test_record_uncached_time(): void
    {
        Stats::record_uncached_time(850);
        
        $stats = Stats::get();
        $this->assertGreaterThan(0, $stats['avg_uncached_ms']);
    }

    public function test_add_time_saved_from_hit(): void
    {
        // Set up averages
        Stats::record_uncached_time(1000);
        
        // Add time saved
        Stats::add_time_saved_from_hit();
        
        $stats = Stats::get();
        $this->assertGreaterThan(0, $stats['time_saved_ms']);
    }

    public function test_stats_default_values(): void
    {
        $stats = Stats::get();
        
        $this->assertArrayHasKey('warmed_pages', $stats);
        $this->assertArrayHasKey('lcp_preloads', $stats);
        $this->assertArrayHasKey('time_saved_ms', $stats);
        $this->assertArrayHasKey('avg_uncached_ms', $stats);
        $this->assertArrayHasKey('avg_cached_ms', $stats);
        $this->assertArrayHasKey('week_key', $stats);
    }

    public function test_week_key_format(): void
    {
        $stats = Stats::get();
        $this->assertMatchesRegularExpression('/^\d{4}\d{2}$/', $stats['week_key']);
    }
}
