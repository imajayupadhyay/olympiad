import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { trackMetaEventOnce } from './Services/metaPixel.service.js';

const appName = 'Neoexam.org';

// The Blade shell records the initial Meta Pixel PageView. Inertia navigation
// does not reload that shell, so record subsequent, genuinely new URLs here.
const normalizePixelUrl = (value) => {
    try {
        const url = new URL(value, window.location.origin);
        return `${url.pathname}${url.search}`;
    } catch {
        return value;
    }
};

let lastPixelUrl = normalizePixelUrl(window.location.href);

const trackVerifiedPurchase = (page) => {
    const purchase = page?.props?.flash?.meta_purchase;
    if (!purchase?.event_id) return;

    const value = Number(purchase.value);
    if (!Number.isFinite(value) || value < 0) return;

    trackMetaEventOnce(
        purchase.event_id,
        'Purchase',
        {
            value,
            currency: 'INR',
        },
        { eventID: purchase.event_id },
    );
};

router.on('navigate', (event) => {
    trackVerifiedPurchase(event.detail.page);

    const nextUrl = normalizePixelUrl(event.detail.page.url);
    if (nextUrl === lastPixelUrl) return;

    lastPixelUrl = nextUrl;
    window.fbq?.('track', 'PageView');
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Covers a verified payment landing via a full document load. The
        // navigate listener above covers normal Inertia redirects.
        trackVerifiedPurchase(props.initialPage);

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#EA580C',
    },
});
