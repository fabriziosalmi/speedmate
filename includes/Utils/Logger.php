<?php
/**
 * Logging utility class
 *
 * @package SpeedMate
 */

declare(strict_types=1);

namespace SpeedMate\Utils;

/**
 * Logger class for structured logging
 */
final class Logger {
	/**
	 * Check if logging is enabled
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		$enabled = (bool) Settings::get_value( 'logging_enabled', false );

		return (bool) apply_filters( 'speedmate_logging_enabled', $enabled );
	}

	/**
	 * Log a message
	 *
	 * @param string $level   Log level.
	 * @param string $event   Event name.
	 * @param array  $context Context data.
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
