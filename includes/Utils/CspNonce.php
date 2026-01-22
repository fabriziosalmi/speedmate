<?php
/**
 * CSP Nonce utility
 *
 * @package SpeedMate
 */

declare(strict_types=1);

namespace SpeedMate\Utils;

/**
 * Content Security Policy nonce generator
 */
final class CspNonce {
	/**
	 * Nonce value
	 *
	 * @var string|null
	 */
	private static ?string $nonce = null;

	/**
	 * Check if CSP nonce is enabled
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		$enabled = (bool) Settings::get_value( 'csp_nonce', false );

		return (bool) apply_filters( 'speedmate_csp_nonce_enabled', $enabled );
	}

	/**
	 * Get nonce value
	 *
	 * @return string
	 */
	public static function get(): string {
		if ( ! self::enabled() ) {
			return '';
		}

		if ( null === self::$nonce ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			self::$nonce = rtrim( strtr( base64_encode( random_bytes( 16 ) ), '+/', '-_' ), '=' );
		}

		return self::$nonce;
	}

	/**
	 * Get nonce attribute
	 *
	 * @return string
	 */
	public static function attr(): string {
		$nonce = self::get();
		if ( '' === $nonce ) {
			return '';
		}

		return ' nonce="' . esc_attr( $nonce ) . '"';
	}
}
