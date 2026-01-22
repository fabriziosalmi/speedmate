<?php
/**
 * Settings management class
 *
 * @package SpeedMate
 */

declare(strict_types=1);

namespace SpeedMate\Utils;

/**
 * Settings utility class
 */
final class Settings {
	/**
	 * Cached settings
	 *
	 * @var array|null
	 */
	private static ?array $settings = null;

	/**
	 * Get all settings
	 *
	 * @return array
	 */
	public static function get(): array {
		if ( null === self::$settings ) {
			$value          = get_option( SPEEDMATE_OPTION_KEY, array() );
			self::$settings = is_array( $value ) ? $value : array();
		}

		return self::$settings;
	}

	/**
	 * Refresh settings cache
	 *
	 * @return void
	 */
	public static function refresh(): void {
		self::$settings = null;
		self::get();
	}

	/**
	 * Get single setting value
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Default value.
	 * @return mixed
	 */
	public static function get_value( string $key, $fallback = null ) {
		$settings = self::get();

		return $settings[ $key ] ?? $fallback;
	}
}
