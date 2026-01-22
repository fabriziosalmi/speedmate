<?php

declare(strict_types=1);

namespace SpeedMate\Utils;

final class CspNonce
{
    private static ?string $nonce = null;

    public static function enabled(): bool
    {
        $enabled = (bool) Settings::get_value('csp_nonce', false);

        return (bool) apply_filters('speedmate_csp_nonce_enabled', $enabled);
    }

    public static function get(): string
    {
        if (!self::enabled()) {
            return '';
        }

        if (self::$nonce === null) {
            self::$nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        }

        return self::$nonce;
    }

    public static function attr(): string
    {
        $nonce = self::get();
        if ($nonce === '') {
            return '';
        }

        return ' nonce="' . esc_attr($nonce) . '"';
    }
}
