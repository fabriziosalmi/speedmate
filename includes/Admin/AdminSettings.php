<?php

declare(strict_types=1);

namespace SpeedMate\Admin;

/**
 * Handles WordPress settings registration and sanitization.
 *
 * @package SpeedMate\Admin
 * @since 0.4.0
 */
final class AdminSettings
{
    /**
     * Register WordPress settings.
     *
     * @return void
     */
    public function register(): void
    {
        register_setting('speedmate', SPEEDMATE_OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default' => [
                'mode' => 'disabled',
                'beast_whitelist' => [],
                'beast_blacklist' => [],
                'beast_apply_all' => false,
                'logging_enabled' => false,
                'csp_nonce' => false,
            ],
        ]);
    }

    /**
     * Sanitize settings input.
     *
     * @param mixed $input Raw input from form.
     * @return array Sanitized settings.
     */
    public function sanitize($input): array
    {
        $mode = 'disabled';
        if (is_array($input) && isset($input['mode'])) {
            $allowed = ['disabled', 'safe', 'beast'];
            $candidate = sanitize_text_field((string) $input['mode']);
            if (in_array($candidate, $allowed, true)) {
                $mode = $candidate;
            }
        }

        $whitelist = [];
        $blacklist = [];
        $apply_all = false;
        $logging_enabled = false;
        $csp_nonce = false;

        if (is_array($input)) {
            if (isset($input['beast_whitelist'])) {
                $whitelist = $this->sanitize_rules((string) $input['beast_whitelist']);
            }
            if (isset($input['beast_blacklist'])) {
                $blacklist = $this->sanitize_rules((string) $input['beast_blacklist']);
            }
            if (isset($input['beast_apply_all'])) {
                $apply_all = (bool) $input['beast_apply_all'];
            }
            if (isset($input['logging_enabled'])) {
                $logging_enabled = (bool) $input['logging_enabled'];
            }
            if (isset($input['csp_nonce'])) {
                $csp_nonce = (bool) $input['csp_nonce'];
            }
        }

        return [
            'mode' => $mode,
            'beast_whitelist' => $whitelist,
            'beast_blacklist' => $blacklist,
            'beast_apply_all' => $apply_all,
            'logging_enabled' => $logging_enabled,
            'csp_nonce' => $csp_nonce,
        ];
    }

    /**
     * Sanitize rules from textarea input.
     *
     * @param string $input Raw textarea content.
     * @return array<string> Sanitized rules.
     */
    private function sanitize_rules(string $input): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $input);
        if (!is_array($lines)) {
            return [];
        }

        $rules = [];
        foreach ($lines as $line) {
            $line = trim(sanitize_text_field($line));
            if ($line !== '') {
                $rules[] = $line;
            }
        }

        return array_values(array_unique($rules));
    }
}
