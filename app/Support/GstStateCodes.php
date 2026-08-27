<?php

namespace App\Support;

/**
 * Canonical Indian GST state / union-territory codes.
 *
 * Used so the receipt settings can accept a state written as "Delhi / 07"
 * and so every generated PDF prints the state in that same format.
 */
class GstStateCodes
{
    /** @var array<string, string> code => canonical state name */
    public const CODES = [
        '01' => 'Jammu and Kashmir',
        '02' => 'Himachal Pradesh',
        '03' => 'Punjab',
        '04' => 'Chandigarh',
        '05' => 'Uttarakhand',
        '06' => 'Haryana',
        '07' => 'Delhi',
        '08' => 'Rajasthan',
        '09' => 'Uttar Pradesh',
        '10' => 'Bihar',
        '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh',
        '13' => 'Nagaland',
        '14' => 'Manipur',
        '15' => 'Mizoram',
        '16' => 'Tripura',
        '17' => 'Meghalaya',
        '18' => 'Assam',
        '19' => 'West Bengal',
        '20' => 'Jharkhand',
        '21' => 'Odisha',
        '22' => 'Chhattisgarh',
        '23' => 'Madhya Pradesh',
        '24' => 'Gujarat',
        '26' => 'Dadra and Nagar Haveli and Daman and Diu',
        '27' => 'Maharashtra',
        '29' => 'Karnataka',
        '30' => 'Goa',
        '31' => 'Lakshadweep',
        '32' => 'Kerala',
        '33' => 'Tamil Nadu',
        '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands',
        '36' => 'Telangana',
        '37' => 'Andhra Pradesh',
        '38' => 'Ladakh',
        '97' => 'Other Territory',
    ];

    /** @var array<string, string> normalised alias => code */
    public const ALIASES = [
        'orissa' => '21',
        'pondicherry' => '34',
        'newdelhi' => '07',
        'nctofdelhi' => '07',
        'delhinct' => '07',
        'uttaranchal' => '05',
        'damananddiu' => '26',
        'dadraandnagarhaveli' => '26',
        'andamanandnicobar' => '35',
        'jammukashmir' => '01',
        'chattisgarh' => '22',
    ];

    /**
     * Split a free-text state entry such as "Delhi / 07", "Delhi (07)" or
     * "07 - Delhi" into its name and code parts. The code stays null unless it
     * was actually written in the value.
     *
     * @return array{name: ?string, code: ?string}
     */
    public static function split(?string $raw): array
    {
        $value = trim((string) $raw);

        if ($value === '') {
            return ['name' => null, 'code' => null];
        }

        // "Delhi (07)"
        if (preg_match('/^(.*?)\s*\(\s*(\d{1,2})\s*\)$/u', $value, $matches) === 1) {
            return self::pair($matches[1], $matches[2]);
        }

        // "Delhi / 07", "Delhi - 07", "Delhi | 07" and the reversed forms.
        if (preg_match('/^(.*?)\s*[\/|\-–—]\s*(.*)$/u', $value, $matches) === 1) {
            [$left, $right] = [trim($matches[1]), trim($matches[2])];

            if (preg_match('/^\d{1,2}$/', $right) === 1) {
                return self::pair($left, $right);
            }

            if (preg_match('/^\d{1,2}$/', $left) === 1) {
                return self::pair($right, $left);
            }
        }

        // Bare code, e.g. "07".
        if (preg_match('/^\d{1,2}$/', $value) === 1) {
            return ['name' => self::nameFor($value), 'code' => self::pad($value)];
        }

        return self::pair($value, null);
    }

    public static function codeFor(?string $name): ?string
    {
        $key = self::normalise($name);

        if ($key === '') {
            return null;
        }

        foreach (self::CODES as $code => $stateName) {
            if (self::normalise($stateName) === $key) {
                return $code;
            }
        }

        return self::ALIASES[$key] ?? null;
    }

    public static function nameFor(?string $code): ?string
    {
        $padded = self::pad($code);

        return $padded === null ? null : (self::CODES[$padded] ?? null);
    }

    /**
     * Render the printable "Delhi / 07" form, falling back gracefully when
     * only one half is known.
     */
    public static function format(?string $name, ?string $code = null): ?string
    {
        $name = trim((string) $name) ?: null;
        $code = self::pad($code) ?? ($name ? self::codeFor($name) : null);

        if ($name === null) {
            return $code;
        }

        return $code === null ? $name : $name.' / '.$code;
    }

    public static function pad(?string $code): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $code) ?? '';

        if ($digits === '') {
            return null;
        }

        return str_pad(substr($digits, 0, 2), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, array{code: string, name: string, label: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::CODES as $code => $name) {
            $options[] = [
                'code' => $code,
                'name' => $name,
                'label' => $name.' / '.$code,
            ];
        }

        return $options;
    }

    /**
     * Reports only what was actually written. Looking a missing code up from
     * the name is left to the caller so an explicitly typed code always wins.
     *
     * @return array{name: ?string, code: ?string}
     */
    private static function pair(?string $name, ?string $code): array
    {
        return [
            'name' => trim((string) $name) ?: null,
            'code' => self::pad($code),
        ];
    }

    private static function normalise(?string $value): string
    {
        $value = str_replace('&', 'and', mb_strtolower(trim((string) $value)));

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }
}
