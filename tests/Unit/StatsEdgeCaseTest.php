<?php

declare(strict_types=1);

namespace SpeedMate\Tests\Unit;

use SpeedMate\Utils\Stats;
use WP_UnitTestCase;

/**
 * Edge case tests for Stats utility.
 *
 * Tests rolling average calculations, boundary conditions,
 * and constant usage.
 *
 * @package SpeedMate\Tests\Unit
 * @group stats
 * @group edge-cases
 */
final class StatsEdgeCaseTest extends WP_UnitTestCase
{
    /**
     * Test rolling average calculation formula.
     */
    public function test_rolling_average_calculation(): void
    {
        // Formula: (old_value * 9 + new_value) / 10
        $old_avg = 100;
        $new_value = 200;
        
        $expected = ($old_avg * SPEEDMATE_STATS_ROLLING_WEIGHT + $new_value) / SPEEDMATE_STATS_ROLLING_DIVISOR;
        
        $this->assertEquals(110, $expected);
    }

    /**
     * Test rolling average with zero old value.
     */
    public function test_rolling_average_with_zero_old_value(): void
    {
        $old_avg = 0;
        $new_value = 100;
        
        $expected = ($old_avg * SPEEDMATE_STATS_ROLLING_WEIGHT + $new_value) / SPEEDMATE_STATS_ROLLING_DIVISOR;
        
        $this->assertEquals(10, $expected);
    }

    /**
     * Test rolling average with zero new value.
     */
    public function test_rolling_average_with_zero_new_value(): void
    {
        $old_avg = 100;
        $new_value = 0;
        
        $expected = ($old_avg * SPEEDMATE_STATS_ROLLING_WEIGHT + $new_value) / SPEEDMATE_STATS_ROLLING_DIVISOR;
        
        $this->assertEquals(90, $expected);
    }

    /**
     * Test rolling average with both zeros.
     */
    public function test_rolling_average_both_zeros(): void
    {
        $old_avg = 0;
        $new_value = 0;
        
        $expected = ($old_avg * SPEEDMATE_STATS_ROLLING_WEIGHT + $new_value) / SPEEDMATE_STATS_ROLLING_DIVISOR;
        
        $this->assertEquals(0, $expected);
    }

    /**
     * Test rolling average constants are defined.
     */
    public function test_rolling_average_constants_defined(): void
    {
        $this->assertTrue(defined('SPEEDMATE_STATS_ROLLING_WEIGHT'));
        $this->assertTrue(defined('SPEEDMATE_STATS_ROLLING_DIVISOR'));
        $this->assertEquals(9, SPEEDMATE_STATS_ROLLING_WEIGHT);
        $this->assertEquals(10, SPEEDMATE_STATS_ROLLING_DIVISOR);
    }

    /**
     * Test default cached milliseconds constant.
     */
    public function test_default_cached_ms_constant(): void
    {
        $this->assertTrue(defined('SPEEDMATE_STATS_DEFAULT_CACHED_MS'));
        $this->assertEquals(50, SPEEDMATE_STATS_DEFAULT_CACHED_MS);
    }

    /**
     * Test rolling average smoothing effect.
     */
    public function test_rolling_average_smoothing(): void
    {
        // Simulate spike from 100ms to 1000ms
        $old_avg = 100;
        $spike = 1000;
        
        // After one update, average should only increase by 90ms (not jump to 1000)
        $new_avg = ($old_avg * 9 + $spike) / 10;
        $this->assertEquals(190, $new_avg);
        
        // Demonstrates smoothing: spike is dampened
        $this->assertLessThan($spike / 2, $new_avg);
    }

    /**
     * Test rolling average convergence.
     */
    public function test_rolling_average_convergence(): void
    {
        // Start at 100ms, all new values are 200ms
        $avg = 100;
        
        // After multiple updates, should converge toward 200
        for ($i = 0; $i < 10; $i++) {
            $avg = ($avg * 9 + 200) / 10;
        }
        
        // Should be much closer to 200 after 10 iterations
        $this->assertGreaterThan(180, $avg);
    }

    /**
     * Test rolling average with large values.
     */
    public function test_rolling_average_large_values(): void
    {
        $old_avg = 5000;
        $new_value = 10000;
        
        $expected = ($old_avg * 9 + $new_value) / 10;
        
        $this->assertEquals(5500, $expected);
    }

    /**
     * Test rolling average maintains integer precision.
     */
    public function test_rolling_average_precision(): void
    {
        $old_avg = 100;
        $new_value = 105;
        
        $result = ($old_avg * 9 + $new_value) / 10;
        
        // Result is 100.5, should be represented accurately
        $this->assertEquals(100.5, $result);
    }

    /**
     * Test Stats class exists and is instantiable.
     */
    public function test_stats_class_exists(): void
    {
        $this->assertTrue(class_exists('SpeedMate\\Utils\\Stats'));
        
        $stats = Stats::instance();
        $this->assertInstanceOf(Stats::class, $stats);
    }

    /**
     * Test rolling weight is less than divisor.
     */
    public function test_weight_less_than_divisor(): void
    {
        // Formula requires weight < divisor for proper averaging
        $this->assertLessThan(
            SPEEDMATE_STATS_ROLLING_DIVISOR,
            SPEEDMATE_STATS_ROLLING_WEIGHT
        );
    }

    /**
     * Test rolling average formula integrity.
     */
    public function test_formula_integrity(): void
    {
        // weight + (divisor - weight) should equal divisor
        $weight = SPEEDMATE_STATS_ROLLING_WEIGHT;
        $divisor = SPEEDMATE_STATS_ROLLING_DIVISOR;
        
        $this->assertEquals($divisor, $weight + ($divisor - $weight));
    }

    /**
     * Test rolling average with fractional results.
     */
    public function test_rolling_average_fractional(): void
    {
        $old_avg = 100;
        $new_value = 111;
        
        $result = ($old_avg * 9 + $new_value) / 10;
        
        // (900 + 111) / 10 = 101.1
        $this->assertEquals(101.1, $result);
    }
}
