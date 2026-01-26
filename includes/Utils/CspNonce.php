<?php
/**
 * CSP Nonce utility
 *
 * @package SpeedMate
 */

declare(strict_types=1);

namespace SpeedMate\Utils;

/**
 * Content Security Policy nonce generator.
 *
 * Generates cryptographically secure nonces for inline scripts:
 * - Prevents XSS attacks
 * - Allows controlled inline JavaScript
 * - Base64url encoding (URL-safe)
 *
 * CSP header example:
 *   Content-Security-Policy: script-src 'nonce-abc123...'
 *
 * HTML usage:
 *   <script nonce="<?php echo CspNonce::get(); ?>">
 *
 * Features:
 * - 16 bytes cryptographic randomness
 * - Request-level caching (single nonce per request)
 * - Filter support for dynamic control
 * - Disabled by default (opt-in)
 *
 * Enable via:
 *   Settings: csp_nonce = true
 *   Filter: speedmate_csp_nonce_enabled
 *
 * @package SpeedMate\Utils
 * @since 0.1.0
 */
final class CspNonce {
	/**
	 * Nonce value
	 *
	 * @var string|null
	 */
	private static ?string $nonce = null;

	/**
	 * Check if CSP nonce generation is enabled.
	 *
	 * Checks:
	 * 1. Settings: csp_nonce value
	 * 2. Filter: speedmate_csp_nonce_enabled (runtime override)
	 *
	 * Disabled by default to avoid breaking existing CSP policies.
	 * Enable when implementing strict CSP.
	 *
	 * Filter example:
	 *   add_filter('speedmate_csp_nonce_enabled', '__return_true');
	 *
	 * @return bool True if nonce generation enabled, false otherwise.
	 */
	public static function enabled(): bool {
		$enabled = (bool) Settings::get_value( 'csp_nonce', false );

		return (bool) apply_filters( 'speedmate_csp_nonce_enabled', $enabled );
	}

	/**
	 * Get or generate CSP nonce value.
	 *
	 * Returns:
	 * - Empty string if disabled
	 * - Cached nonce if already generated this request
	 * - New nonce if first call
	 *
	 * Nonce format:
	 * - 16 bytes random_bytes()
	 * - Base64url encoding (URL-safe, no padding)
	 * - Example: 'Xy3z9K8mP4qL7nR2'
	 *
	 * Use in HTML:
	 *   <script nonce="<?php echo esc_attr(CspNonce::get()); ?>">
	 *
	 * CSP header:
	 *   Content-Security-Policy: script-src 'nonce-<?php echo CspNonce::get(); ?>'
	 *
	 * @return string Nonce value or empty string if disabled.
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
