<?php
/**
 * Settings management class
 *
 * @package SpeedMate
 */

declare(strict_types=1);

namespace SpeedMate\Utils;

/**
 * Settings management with static caching.
 *
 * Provides centralized access to SpeedMate configuration:
 * - Static cache to avoid repeated get_option() calls
 * - Type-safe value retrieval with fallbacks
 * - Refresh mechanism for cache invalidation
 *
 * Settings stored in wp_options as SPEEDMATE_OPTION_KEY (speedmate_settings).
 *
 * Features:
 * - Single source of truth for all plugin settings
 * - Request-level caching (reduces DB queries)
 * - Type-safe defaults
 * - Filter support for dynamic overrides
 *
 * Usage:
 *   $mode = Settings::get_value('mode', 'disabled');
 *   $all = Settings::get();
 *   Settings::refresh(); // Invalidate cache
 *
 * @package SpeedMate\Utils
 * @since 0.1.0
 */
final class Settings {
	/**
	 * Cached settings
	 *
	 * @var array|null
	 */
	private static ?array $settings = null;

	/**
	 * Get all plugin settings with static caching.
	 *
	 * Retrieves all SpeedMate settings from wp_options and caches them
	 * for the duration of the request. Subsequent calls return cached data
	 * without hitting the database.
	 *
	 * Settings include:
	 * - mode, cache_ttl, excluded_urls, excluded_cookies
	 * - beast_whitelist, beast_blacklist, beast_apply_all
	 * - webp_enabled, critical_css_enabled, preload_hints_enabled
	 * - warmer_enabled, warmer_frequency, warmer_max_urls
	 * - gc_enabled, gc_spam, gc_revisions, gc_transients
	 * - auto_lcp_enabled, auto_lcp_threshold
	 *
	 * @return array All plugin settings as associative array.
	 */
	public static function get(): array {
		if ( null === self::$settings ) {
			$value          = get_option( SPEEDMATE_OPTION_KEY, array() );
			self::$settings = is_array( $value ) ? $value : array();
		}

		return self::$settings;
	}

	/**
	 * Invalidate settings cache and reload from database.
	 *
	 * Forces next Settings::get() call to fetch fresh data from wp_options.
	 * Use after programmatic settings updates to ensure consistency.
	 *
	 * Use cases:
	 * - After updating settings via update_option()
	 * - After import/export operations
	 * - When testing with dynamic configuration
	 *
	 * @return void
	 */
	public static function refresh(): void {
		self::$settings = null;
		self::get();
	}

	/**
	 * Get single setting value with type-safe fallback.
	 *
	 * Retrieves specific setting from cached configuration.
	 * Returns fallback if key doesn't exist.
	 *
	 * Common settings:
	 * - 'mode': 'disabled'|'enabled'|'aggressive'
	 * - 'cache_ttl': int (seconds)
	 * - 'beast_whitelist': array
	 * - 'webp_enabled': bool
	 *
	 * Examples:
	 *   $mode = Settings::get_value('mode', 'disabled');
	 *   $ttl = Settings::get_value('cache_ttl', 3600);
	 *   $enabled = Settings::get_value('webp_enabled', false);
	 *
	 * @param string $key     Setting key to retrieve.
	 * @param mixed  $fallback Default value if key not found.
	 *
	 * @return mixed Setting value or fallback.
	 */
	public static function get_value( string $key, $fallback = null ) {
		$settings = self::get();

		return $settings[ $key ] ?? $fallback;
	}
}
