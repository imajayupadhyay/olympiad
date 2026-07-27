<?php

namespace App\Services;

use InvalidArgumentException;

class PhoneNumberService
{
    /**
     * Normalize an Indian mobile number to E.164. International numbers are
     * accepted only when the caller supplies an explicit leading plus sign.
     */
    public function normalize(string $value): string
    {
        $input = trim($value);
        $hadInternationalPrefix = str_starts_with($input, '+');
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10 && preg_match('/^[6-9]\d{9}$/', $digits)) {
            return '+91'.$digits;
        }

        if (strlen($digits) === 12 && preg_match('/^91[6-9]\d{9}$/', $digits)) {
            return '+'.$digits;
        }

        if ($hadInternationalPrefix && strlen($digits) >= 8 && strlen($digits) <= 15 && $digits[0] !== '0') {
            return '+'.$digits;
        }

        throw new InvalidArgumentException('Enter a valid mobile number with its country code.');
    }

    public function tryNormalize(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return $this->normalize($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function mask(string $e164): string
    {
        $digits = ltrim($e164, '+');
        $visible = substr($digits, -4);
        $country = str_starts_with($digits, '91') ? '+91 ' : '+';

        return $country.str_repeat('•', max(4, strlen($digits) - strlen($visible) - ($country === '+91 ' ? 2 : 0))).$visible;
    }
}
