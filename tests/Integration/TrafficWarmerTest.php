<?php

declare(strict_types=1);

namespace SpeedMate\Tests\Integration;

use SpeedMate\Cache\TrafficWarmer;
use SpeedMate\Utils\Settings;
use WP_UnitTestCase;

final class TrafficWarmerTest extends WP_UnitTestCase
{
    private TrafficWarmer $warmer;

    public function setUp(): void
    {
        parent::setUp();
        $this->warmer = TrafficWarmer::instance();
    }

    public function test_warmer_activation_schedules_cron(): void
    {
        update_option(SPEEDMATE_OPTION_KEY, [
            'warmer_enabled' => true,
            'warmer_frequency' => 'hourly',
        ]);
        Settings::refresh();

        TrafficWarmer::activate();
        
        $timestamp = wp_next_scheduled('speedmate_warm_cron');
        $this->assertNotFalse($timestamp);
        
        // Cleanup
        TrafficWarmer::deactivate();
    }

    public function test_warmer_respects_max_urls_setting(): void
    {
        update_option(SPEEDMATE_OPTION_KEY, [
            'mode' => 'safe',
            'warmer_enabled' => true,
            'warmer_max_urls' => 5,
        ]);
        Settings::refresh();

        // Add test transient with hits
        $hits = [];
        for ($i = 1; $i <= 10; $i++) {
            $hits[home_url("/page-{$i}/")] = $i;
        }
        set_transient('speedmate_hits', $hits, HOUR_IN_SECONDS);

        // Note: Full run() test would require mocking wp_remote_get
        // This tests the setting is read correctly
        $settings = Settings::get();
        $this->assertEquals(5, $settings['warmer_max_urls']);
    }

    public function test_warmer_disabled_when_setting_is_false(): void
    {
        update_option(SPEEDMATE_OPTION_KEY, [
            'warmer_enabled' => false,
        ]);
        Settings::refresh();

        // Clear any existing schedule
        $timestamp = wp_next_scheduled('speedmate_warm_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'speedmate_warm_cron');
        }

        TrafficWarmer::activate();
        
        $timestamp = wp_next_scheduled('speedmate_warm_cron');
        $this->assertFalse($timestamp);
    }
}
