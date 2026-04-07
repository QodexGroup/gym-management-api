<?php

namespace App\Support;

final class EmailValidator
{
    public static function normalize(?string $value): string
    {
        return trim((string) $value);
    }

    /**
     * Pragmatic email validation (not full RFC 5322).
     * - reject empty
     * - reject whitespace
     * - require single "@"
     * - require a dot in the domain portion (basic x@y.z)
     */
    public static function isValid(?string $value): bool
    {
        $email = self::normalize($value);
        if ($email === '') {
            return false;
        }

        if (preg_match('/\s/', $email)) {
            return false;
        }

        $atPos = strpos($email, '@');
        if ($atPos === false || $atPos === 0) {
            return false;
        }

        // ensure only one "@"
        if (strpos($email, '@', $atPos + 1) !== false) {
            return false;
        }

        $domain = substr($email, $atPos + 1);
        if ($domain === '') {
            return false;
        }

        $dotPos = strpos($domain, '.');
        if ($dotPos === false || $dotPos === 0) {
            return false;
        }
        if ($dotPos === strlen($domain) - 1) {
            return false;
        }

        return true;
    }
}

