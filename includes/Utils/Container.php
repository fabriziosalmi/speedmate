<?php
/**
 * Dependency injection container
 *
 * @package SpeedMate
 */

declare(strict_types=1);

namespace SpeedMate\Utils;

/**
 * Simple dependency injection container for testing.
 *
 * Provides basic service registration for:
 * - Test doubles (mocks, stubs)
 * - Service swapping
 * - Dependency override
 *
 * Lightweight alternative to full DI frameworks.
 * Primarily used in test environment.
 *
 * Usage:
 *   Container::set('cache', $mock_cache);
 *   $cache = Container::get('cache');
 *
 * Note: Not used in production code, only for testability.
 *
 * @package SpeedMate\Utils
 * @since 0.1.0
 */
final class Container {
	/**
	 * Container instances
	 *
	 * @var array<string, object>
	 */
	private static array $instances = array();

	/**
	 * Register service instance in container.
	 *
	 * Stores object instance for later retrieval.
	 * Overwrites existing instance if ID already registered.
	 *
	 * Use cases:
	 * - Test doubles: Container::set('cache', $mock);
	 * - Service override: Container::set('logger', $test_logger);
	 *
	 * @param string $id       Unique service identifier.
	 * @param object $instance Service instance to store.
	 *
	 * @return void
	 */
	public static function set( string $id, object $instance ): void {
		self::$instances[ $id ] = $instance;
	}

	/**
	 * Retrieve service instance from container.
	 *
	 * Returns null if service not registered.
	 * Caller should handle null case gracefully.
	 *
	 * Example:
	 *   $cache = Container::get('cache');
	 *   if ($cache) { $cache->flush(); }
	 *
	 * @param string $id Unique service identifier.
	 *
	 * @return object|null Service instance or null if not found.
	 */
	public static function get( string $id ): ?object {
		return self::$instances[ $id ] ?? null;
	}
}
