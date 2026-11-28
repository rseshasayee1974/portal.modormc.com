import './bootstrap';
import '../css/base.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { parsePage } from '@inertiajs/core/json';
import { installUserPresence } from '@/Services/userPresence';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia';
import GlobalLoader from '@/Components/GlobalLoader.vue';
import GlobalRequestErrors from '@/Components/GlobalRequestErrors.vue';

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

const appName = 'Modormc';

// Cached Laravel views can still carry the initial page on the root element.
const legacyPage = !document.querySelector('script[data-page="app"][type="application/json"]')
    && document.getElementById('app')?.getAttribute('data-page');

createInertiaApp({
    page: legacyPage ? parsePage(legacyPage) : undefined,
    title: (title) => `${title} - ${appName}`,
    pages: './Pages',
    async setup({ el, App, props, plugin }) {
        installUserPresence(props.initialPage.props.presence, router);
        scheduleServiceWorker(props.initialPage);
        const pinia = createPinia();
        const resolveComponent = async (name, page) => {
            const [component] = await Promise.all([
                props.resolveComponent(name, page),
                name === 'Auth/Login' ? undefined : ensureApplicationUI(),
            ]);
            return component;
        };

        application = createApp({
            render: () => h('div', [
                h(GlobalRequestErrors, { initialPage: props.initialPage }),
                h(App, { ...props, resolveComponent }),
                h(GlobalLoader)
            ])
        })
            .use(plugin)
            .use(ZiggyVue, window.Ziggy)
            .use(pinia);

        if (props.initialPage.component !== 'Auth/Login') await ensureApplicationUI();
        return application.mount(el);
    },
    progress: false,
});

router.on('navigate', event => scheduleServiceWorker(event.detail.page));

router.on('httpException', (event) => {
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

    const url = response?.headers['content-location'];
    if (url && (url.includes('/token') || url.includes('/dispatch-token') || url.includes('/delivery-token')) && !url.includes('/download')) {
        event.preventDefault();
        window.dispatchEvent(new CustomEvent('show-batch-token', { detail: { url } }));
    }
});
