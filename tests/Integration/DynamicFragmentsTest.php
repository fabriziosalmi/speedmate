<?php

declare(strict_types=1);

use SpeedMate\Cache\DynamicFragments;

final class DynamicFragmentsTest extends WP_UnitTestCase
{
    public function test_shortcode_outputs_placeholder_in_safe_mode(): void
    {
        update_option(SPEEDMATE_OPTION_KEY, [
            'mode' => 'safe',
        ]);

        $content = 'Hello [speedmate_dynamic]World[/speedmate_dynamic]';
        $out = do_shortcode($content);

        $this->assertStringContainsString('data-speedmate-fragment', $out);
    }
}
