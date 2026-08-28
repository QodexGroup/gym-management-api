<?php

namespace App\Helpers;

/**
 * Philippine mobile number normalisation.
 *
 * Duplicate detection on the public registration endpoint is only meaningful if
 * the same number in different shapes collides. Without this,
 * "0917-123-4567", "09171234567" and "+639171234567" are three different
 * members, and the duplicate check is bypassed by typing a dash.
 */
class PhoneNumberHelper
{
    /**
     * Reduce a phone number to a canonical comparable form.
     *
     * Strips every non-digit, then folds the +63 / 63 country prefix and the
     * bare 10-digit form to the local leading-zero form:
     *   +63 917 123 4567 -> 09171234567
     *   0917-123-4567    -> 09171234567
     *   9171234567       -> 09171234567
     *
     * Anything that does not match a known Philippine shape is returned as
     * digits only, so non-PH numbers still compare consistently against
     * themselves.
     *
     * @param string|null $phoneNumber
     * @return string Empty string when there are no digits at all.
     */
    public static function normalize(?string $phoneNumber): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phoneNumber) ?? '';

        if ($digits === '') {
            return '';
        }

        // 639171234567 -> 09171234567
        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0' . substr($digits, 2);
        }

        // 9171234567 -> 09171234567
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '0' . $digits;
        }

        return $digits;
    }
}
