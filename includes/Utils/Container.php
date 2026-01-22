<?php
/**
 * Dependency injection container
 *
 * @package SpeedMate
 */

declare(strict_types=1);

namespace SpeedMate\Utils;

/**
 * Simple DI container for testing
 */
final class Container {
	/**
	 * Container instances
	 *
	 * @var array<string, object>
	 */
	private static array $instances = array();

	/**
	 * Set instance
	 *
	 * @param string $id       Instance ID.
	 * @param object $instance Instance object.
	 * @return void
	 */
	public static function set( string $id, object $instance ): void {
		self::$instances[ $id ] = $instance;
	}

	/**
	 * Get instance
	 *
	 * @param string $id Instance ID.
	 * @return object|null
	 */
	public static function get( string $id ): ?object {
		return self::$instances[ $id ] ?? null;
	}
}
