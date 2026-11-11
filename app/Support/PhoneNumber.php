<?php

namespace App\Support;

/**
 * Normalizes Kenyan phone numbers to the storage format used across the app:
 * country code followed by the subscriber number, without a leading plus sign
 * (for example 254792314330).
 */
final class PhoneNumber
{
    public static function normalize(string $phoneNumber): string
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '254')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '254'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '254'.$digits;
        }

        return $digits;
    }
}
