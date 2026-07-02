import { onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Silently keep a subset of the current page's Inertia props fresh, so
 * server-side counters (e.g. referral progress) update without a manual refresh.
 *
 * - Re-fetches only the listed props via an Inertia partial reload (no full page
 *   navigation; scroll + component state are preserved).
 * - Polls on an interval AND immediately when the tab regains focus/visibility,
 *   so it feels instant when the user comes back to the tab.
 * - Skips polling while the tab is hidden to avoid needless requests.
 *
 * @param {string[]} only        Prop keys to refresh (must match the controller's Inertia props).
 * @param {number}   intervalMs  Poll cadence in ms (default 5000).
 */
export function usePoll(only, intervalMs = 5000) {
    let timer = null;
    let inFlight = false;

    const reload = () => {
        if (inFlight || document.hidden) return;
        inFlight = true;
        router.reload({
            only,
            preserveScroll: true,
            preserveState: true,
            onFinish: () => { inFlight = false; },
        });
    };

    const onVisibility = () => {
        if (!document.hidden) reload();
    };

    onMounted(() => {
        timer = setInterval(reload, intervalMs);
        document.addEventListener('visibilitychange', onVisibility);
        window.addEventListener('focus', reload);
    });

    onBeforeUnmount(() => {
        if (timer) clearInterval(timer);
        document.removeEventListener('visibilitychange', onVisibility);
        window.removeEventListener('focus', reload);
    });

    return { reload };
}
