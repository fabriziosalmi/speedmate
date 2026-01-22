<?php

declare(strict_types=1);

use SpeedMate\Perf\BeastMode;
use SpeedMate\Utils\Settings;

final class BeastModeTest extends WP_UnitTestCase
{
    public function test_rewrite_scripts_delays_external_script(): void
    {
        update_option(SPEEDMATE_OPTION_KEY, [
            'mode' => 'beast',
            'beast_whitelist' => [],
            'beast_blacklist' => [],
            'beast_apply_all' => true,
        ]);
        Settings::refresh();

        $html = '<script src="https://example.com/app.js"></script>';
        $out = BeastMode::instance()->rewrite_scripts($html);

        $this->assertStringContainsString('data-speedmate-src', $out);
        $this->assertStringContainsString('type="speedmate/delay"', $out);
    }

    public function test_whitelisted_script_not_delayed(): void
    {
        update_option(SPEEDMATE_OPTION_KEY, [
            'mode' => 'beast',
            'beast_whitelist' => ['example.com/app.js'],
            'beast_blacklist' => [],
            'beast_apply_all' => true,
        ]);
        Settings::refresh();

        $html = '<script src="https://example.com/app.js"></script>';
        $out = BeastMode::instance()->rewrite_scripts($html);

        $this->assertStringNotContainsString('data-speedmate-src', $out);
    }

    public function test_blacklisted_script_is_delayed(): void
    {
        update_option(SPEEDMATE_OPTION_KEY, [
            'mode' => 'beast',
            'beast_whitelist' => [],
            'beast_blacklist' => ['example.com/app.js'],
            'beast_apply_all' => true,
        ]);
        Settings::refresh();

        $html = '<script src="https://example.com/app.js"></script>';
        $out = BeastMode::instance()->rewrite_scripts($html);

        $this->assertStringContainsString('data-speedmate-src', $out);
    }
}
