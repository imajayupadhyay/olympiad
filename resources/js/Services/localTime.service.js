/**
 * Schedule times on the display clock (workflow.md §5.7).
 *
 * The server stores every datetime as a UTC instant and sends it as an ISO string.
 * Admins type schedule times, and everyone reads them, on ONE clock: the display
 * timezone shared as the `timezone` Inertia prop (India time by default). Formatting
 * with the device's own timezone would show a different time on a phone or laptop
 * set to another zone, so always go through these helpers for exam times.
 */
import { usePage } from '@inertiajs/vue3';

const FALLBACK = { name: 'Asia/Kolkata', abbr: 'IST' };

const zone = () => {
    try {
        return usePage().props.timezone ?? FALLBACK;
    } catch {
        return FALLBACK;
    }
};

export const timezoneName = () => zone().name;
export const timezoneAbbr = () => zone().abbr;

const DATE_TIME = { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' };

/** A stored instant (ISO string or Date) → text on the display clock. */
export const formatLocal = (value, options = DATE_TIME, fallback = '') => {
    if (!value) return fallback;
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return fallback;

    return new Intl.DateTimeFormat('en-IN', { ...options, timeZone: timezoneName() }).format(date);
};

/** Date only, e.g. "15 Nov 2026". */
export const formatLocalDate = (value, fallback = '') =>
    formatLocal(value, { day: '2-digit', month: 'short', year: 'numeric' }, fallback);

/**
 * A `datetime-local` value ("2026-11-15T09:00") is already display-clock wall time.
 * Format it as typed, with no timezone shift (used for unsaved form values).
 */
export const formatWall = (value, options = { dateStyle: 'medium', timeStyle: 'short' }, fallback = '') => {
    if (!value) return fallback;
    const date = new Date(`${String(value).slice(0, 16)}:00Z`);
    if (Number.isNaN(date.getTime())) return fallback;

    return new Intl.DateTimeFormat('en-IN', { ...options, timeZone: 'UTC' }).format(date);
};

/** A moment → the `datetime-local` value it has on the display clock. */
export const toLocalInput = (date = new Date()) => {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', {
            timeZone: timezoneName(),
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
        }).formatToParts(date).map((part) => [part.type, part.value]),
    );

    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
};
