import './bootstrap';
import '../css/base.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router, usePage } from '@inertiajs/vue3';
import { configureEntityTimezone } from '@/Utils/entityDateTime';
import { installUserPresence } from '@/Services/userPresence';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia';
import GlobalLoader from '@/Components/GlobalLoader.vue';

let application;
let uiPromise;
let serviceWorkerScheduled = false;
function scheduleServiceWorker(page) {
    if (serviceWorkerScheduled || !page.props.auth?.user || !('serviceWorker' in navigator)) return;
    serviceWorkerScheduled = true;
    const register = () => navigator.serviceWorker.register('/sw.js').catch(() => {
        serviceWorkerScheduled = false;
    });
    const schedule = () => {
        if ('requestIdleCallback' in window) window.requestIdleCallback(register, { timeout: 5000 });
        else window.setTimeout(register, 1500);
    };
    if (document.readyState === 'complete') schedule();
    else window.addEventListener('load', schedule, { once: true });
}

async function ensureApplicationUI() {
    uiPromise ??= import('./applicationUI.js').catch(error => {
        uiPromise = undefined;
        throw error;
    });
    const ui = await uiPromise;
    if (application) ui.install(application);
}

const appName = import.meta.env.VITE_APP_NAME || 'ModoRmc';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    async resolve(name) {
        const [component] = await Promise.all([
            resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
            name === 'Auth/Login' ? undefined : ensureApplicationUI(),
        ]);
        return component;
    },
    async setup({ el, App, props, plugin }) {
        installUserPresence(props.initialPage.props.presence, router);
        scheduleServiceWorker(props.initialPage);
        configureEntityTimezone(() => usePage().props.entity_timezone || props.initialPage.props.entity_timezone);
        const pinia = createPinia();

        application = createApp({
            render: () => h('div', [
                h(App, props),
                h(GlobalLoader)
            ])
        })
            .use(plugin)
            .use(ZiggyVue, window.Ziggy)
            .use(pinia);

        if (props.initialPage.component !== 'Auth/Login') await ensureApplicationUI();
        return application.mount(el);
    },
    progress: {
        color: '#fbbf24',
        showSpinner: true,
        includeCSS: true,
    },
});

router.on('navigate', event => scheduleServiceWorker(event.detail.page));

router.on('invalid', (event) => {
    const response = event.detail.response;
    if (response && [401, 419].includes(response.status)) {
        event.preventDefault();
        if (window.location.pathname === '/login') {
            window.location.reload();
        } else {
            window.location.href = '/login';
        }
        return;
    }

    const url = response?.request?.responseURL || response?.config?.url;
    if (url && (url.includes('/token') || url.includes('/dispatch-token') || url.includes('/delivery-token')) && !url.includes('/download')) {
        event.preventDefault();
        window.dispatchEvent(new CustomEvent('show-batch-token', { detail: { url } }));
    }
});
