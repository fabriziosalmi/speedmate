<?php

declare(strict_types=1);

use SpeedMate\Perf\AutoLCP;

final class AutoLCPTest extends WP_UnitTestCase
{
    public function test_lcp_report_updates_meta(): void
    {
        $post_id = self::factory()->post->create([
            'post_title' => 'Test LCP',
            'post_status' => 'publish',
        ]);

        update_option(SPEEDMATE_OPTION_KEY, [
            'mode' => 'safe',
        ]);

        $request = new WP_REST_Request('POST', '/speedmate/v1/lcp');
        $request->set_param('image_url', 'https://example.com/image.jpg');
        $request->set_param('page_url', get_permalink($post_id));

        $response = AutoLCP::instance()->handle_report($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame('https://example.com/image.jpg', get_post_meta($post_id, '_speedmate_lcp_image', true));
    }
}
