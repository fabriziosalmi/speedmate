<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

use SpeedMate\Utils\Logger;
use SpeedMate\Utils\Container;

final class GarbageCollector
{
    private const CRON_HOOK = 'speedmate_garbage_collect';

    private static ?GarbageCollector $instance = null;

    private function __construct()
    {
    }

    public static function instance(): GarbageCollector
    {
        $override = Container::get(self::class);
        if ($override instanceof self) {
            return $override;
        }

        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->register_hooks();
        }

        return self::$instance;
    }

    private function register_hooks(): void
    {
        add_filter('cron_schedules', [$this, 'register_weekly_schedule']);
        add_action(self::CRON_HOOK, [$this, 'run']);
    }

    public function register_weekly_schedule(array $schedules): array
    {
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = [
                'interval' => WEEK_IN_SECONDS,
                'display' => __('Once Weekly'),
            ];
        }

        return $schedules;
    }

    public static function activate(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'weekly', self::CRON_HOOK);
        }
    }

    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    public function run(): void
    {
        $this->cleanup();

        Logger::log('info', 'garbage_collector_ran');
    }

    public function cleanup(): void
    {
        $this->delete_expired_transients();
        $this->delete_spam_comments();
        $this->delete_post_revisions();
        $this->delete_orphaned_postmeta();
    }

    private function delete_expired_transients(): void
    {
        if (function_exists('delete_expired_transients')) {
            delete_expired_transients(true);
        }
    }

    private function delete_spam_comments(): void
    {
        global $wpdb;
        $spam_ids = $wpdb->get_col("SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
        if (is_array($spam_ids)) {
            foreach ($spam_ids as $comment_id) {
                wp_delete_comment((int) $comment_id, true);
            }
        }
    }

    private function delete_post_revisions(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'");
    }

    private function delete_orphaned_postmeta(): void
    {
        global $wpdb;
        $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL"
        );
    }
}
