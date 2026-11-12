<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * The one place that converts between stored instants and the wall-clock time people
 * read and type (workflow.md §5.7).
 *
 * Every datetime column holds a real instant in UTC (the app timezone). Admins type
 * schedule times, and students read them, in the display timezone (India time by
 * default). So:
 *
 *   - a typed `datetime-local` value goes through parse() before it is saved;
 *   - anything shown to a person goes through format() / forInput() / local().
 *
 * Never call ->format() on a stored datetime for display, and never save a typed
 * wall time with Carbon::parse(): both skip the conversion and shift the time by
 * the UTC offset (5 h 30 min for India).
 */
class LocalTime
{
    public static function timezone(): string
    {
        return config('app.display_timezone') ?: config('app.timezone');
    }

    /** Short name shown next to time inputs, e.g. "IST". */
    public static function abbreviation(): string
    {
        return Carbon::now(self::timezone())->format('T');
    }

    /** The current moment on the display clock (use for "today", start of day, etc.). */
    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    /** A typed wall time ("2026-11-15T09:00") → the instant it means, in the app timezone. */
    public static function parse(?string $wallTime): ?Carbon
    {
        if ($wallTime === null || trim($wallTime) === '') {
            return null;
        }

        return Carbon::parse($wallTime, self::timezone())->setTimezone(config('app.timezone'));
    }

    /** A stored instant on the display clock (a copy; the original is untouched). */
    public static function local(?DateTimeInterface $instant): ?Carbon
    {
        return $instant ? Carbon::instance($instant)->setTimezone(self::timezone()) : null;
    }

    /** A stored instant formatted on the display clock. */
    public static function format(?DateTimeInterface $instant, string $format): ?string
    {
        return self::local($instant)?->format($format);
    }

    /** A stored instant as a `datetime-local` input value. */
    public static function forInput(?DateTimeInterface $instant): ?string
    {
        return self::format($instant, 'Y-m-d\TH:i');
    }
}
