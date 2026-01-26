<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

use SpeedMate\Utils\Logger;
use SpeedMate\Utils\Container;
use SpeedMate\Utils\Singleton;

/**
 * Database garbage collection with bulk optimizations.
 *
 * Features:
 * - Weekly cron schedule
 * - Bulk SQL operations (v0.4.0 enhancement)
 * - Configurable cleanup types
 * - Performance logging
 *
 * Cleanup types:
 * - Spam comments (if gc_spam enabled)
 * - Post revisions (if gc_revisions enabled)
 * - Expired transients (if gc_transients enabled)
 * - Orphaned postmeta (if orphan_meta enabled)
 *
 * Bulk optimizations:
 * - Single DELETE query per type (not per-row)
 * - Batched operations for large datasets
 * - Minimal memory footprint
 *
 * Activation:
 *   GarbageCollector::activate(); // Schedule weekly cron
 *   wp speedmate gc // Manual via WP-CLI
 *
 * @package SpeedMate\Utils
 * @since 0.1.0
 */
final class GarbageCollector
{
    use Singleton;

    private const CRON_HOOK = 'speedmate_garbage_collect';

    /**
     * Private constructor for Singleton pattern.
     */
    private function __construct()
    {
    }

    /**
     * Register WordPress hooks.
     *
     * Hooks:
     * - cron_schedules: Add weekly schedule
     * - speedmate_garbage_collect: Run cleanup
     *
     * @return void
     */
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
        // Check if garbage collection is enabled in settings
        $settings = Settings::get();
        if (!isset($settings['garbage_collector_enabled']) || !$settings['garbage_collector_enabled']) {
            Logger::log('info', 'garbage_collector_skipped_disabled');
            return;
        }

        $this->cleanup();

        Logger::log('info', 'garbage_collector_ran');
    }

    public function cleanup(): void
    {
        $this->delete_expired_transients();
        
        // Only delete spam if explicitly enabled to prevent accidental data loss
        $settings = Settings::get();
        if (isset($settings['garbage_collector_delete_spam']) && $settings['garbage_collector_delete_spam']) {
            $this->delete_spam_comments();
        }
        
        $this->delete_post_revisions();
        $this->delete_orphaned_postmeta();
    }

    private function delete_expired_transients(): void
    {
        if (function_exists('delete_expired_transients')) {
            delete_expired_transients(true);
        }
    }

    /**
     * Delete spam comments in batches to avoid N+1 queries.
     * Uses direct SQL for bulk deletion with proper cleanup.
     *
     * @return void
     */
    private function delete_spam_comments(): void
    {
        global $wpdb;
        
        // Get spam comments with post IDs (need for comment count update)
        $spam_comments = $wpdb->get_results(
            "SELECT comment_ID, comment_post_ID FROM {$wpdb->comments} WHERE comment_approved = 'spam' LIMIT 1000"
        );
        
        if (!is_array($spam_comments) || empty($spam_comments)) {
            return;
        }

        $spam_ids = array_column($spam_comments, 'comment_ID');
        $post_ids = array_unique(array_column($spam_comments, 'comment_post_ID'));
        
        $ids_placeholder = implode(',', array_fill(0, count($spam_ids), '%d'));
        
        // Delete comment meta in bulk
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->commentmeta} WHERE comment_id IN ($ids_placeholder)",
                ...$spam_ids
            )
        );
        
        // Delete comments in bulk
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->comments} WHERE comment_ID IN ($ids_placeholder)",
                ...$spam_ids
            )
        );
        
        // Clean comment cache for deleted IDs
        foreach ($spam_ids as $comment_id) {
            clean_comment_cache($comment_id);
        }
        
        // Update comment count caches for affected posts
        foreach ($post_ids as $post_id) {
            wp_update_comment_count($post_id);
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
