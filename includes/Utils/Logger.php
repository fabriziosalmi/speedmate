<?php
/**
 * Logging utility class
 *
 * @package SpeedMate
 */

declare(strict_types=1);

namespace SpeedMate\Utils;

/**
 * Structured logging utility for SpeedMate.
 *
 * Features:
 * - Conditional logging (can be disabled via settings)
 * - Structured JSON format for easy parsing
 * - ISO 8601 timestamps
 * - Log levels: info, warning, error, debug
 * - Context data for debugging
 * - Filter support for log customization
 *
 * Log format:
 *   {"level":"error","event":"cache_write_failed","context":{"path":"/cache/page.html"},"ts":"2026-01-26T12:00:00+00:00"}
 *
 * Usage:
 *   Logger::log('error', 'cache_write_failed', ['path' => $path]);
 *   Logger::log('info', 'cache_warmed', ['count' => 10]);
 *
 * Enable via:
 *   Settings: logging_enabled = true
 *   Filter: speedmate_logging_enabled
 *
 * @package SpeedMate\Utils
 * @since 0.1.0
 */
final class Logger {
	/**
	 * Check if logging is enabled.
	 *
	 * Checks:
	 * 1. Settings: logging_enabled value
	 * 2. Filter: speedmate_logging_enabled (allows runtime override)
	 *
	 * Logging disabled by default to avoid log bloat in production.
	 * Enable for debugging or monitoring purposes.
	 *
	 * Filter example:
	 *   add_filter('speedmate_logging_enabled', '__return_true');
	 *
	 * @return bool True if logging is enabled, false otherwise.
	 */
	public static function enabled(): bool {
		$enabled = (bool) Settings::get_value( 'logging_enabled', false );

		return (bool) apply_filters( 'speedmate_logging_enabled', $enabled );
	}

	/**
	 * Log a structured message to error_log.
	 *
	 * Message format (JSON):
	 * - level: Log severity (info, warning, error, debug)
	 * - event: Event identifier (e.g., 'cache_hit', 'lcp_detected')
	 * - context: Additional data (paths, counts, errors)
	 * - ts: ISO 8601 timestamp (UTC)
	 *
	 * Does nothing if logging is disabled.
	 * Output goes to PHP error_log (check php.ini error_log setting).
	 *
	 * Examples:
	 *   Logger::log('error', 'cache_write_failed', ['path' => '/cache/page.html', 'reason' => 'permission denied']);
	 *   Logger::log('info', 'cache_warmed', ['count' => 20, 'duration' => 5.2]);
	 *   Logger::log('warning', 'rate_limit_exceeded', ['ip' => '1.2.3.4']);
	 *
	 * @param string $level   Log level (info|warning|error|debug).
	 * @param string $event   Event identifier (lowercase_snake_case).
	 * @param array  $context Additional data for debugging.
	 *
	 * @return void
	 */
	public static function log( string $level, string $event, array $context = array() ): void {
		if ( ! self::enabled() ) {
			return;
		}

		$payload = array(
			'level'   => $level,
			'event'   => $event,
			'context' => $context,
			'ts'      => gmdate( 'c' ),
		);

		error_log( wp_json_encode( $payload ) );
	}
}
