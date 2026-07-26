/**
 * Safe Meta Pixel event helpers.
 *
 * The base pixel is installed in app.blade.php. These helpers keep feature
 * code independent of the loader and prevent an Inertia history visit or a
 * payment retry from emitting the same funnel event twice in one session.
 */
export function trackMetaEvent(event, parameters, options) {
    if (typeof window === 'undefined' || typeof window.fbq !== 'function') {
        return false;
    }

    const args = ['track', event];
    if (parameters !== undefined) args.push(parameters);
    if (options !== undefined) args.push(options);

    window.fbq(...args);
    return true;
}

export function trackMetaEventOnce(key, event, parameters, options) {
    const storageKey = `noh_meta_event_${key}`;

    try {
        if (window.sessionStorage.getItem(storageKey) === '1') return false;
    } catch { /* storage may be unavailable; Meta's eventID remains a fallback */ }

    if (!trackMetaEvent(event, parameters, options)) return false;

    try { window.sessionStorage.setItem(storageKey, '1'); } catch { /* event was still queued */ }
    return true;
}
