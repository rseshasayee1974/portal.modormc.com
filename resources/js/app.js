import './bootstrap';
import '../css/app.css';
import 'primeicons/primeicons.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import { createPinia } from 'pinia';
import Tooltip from 'primevue/tooltip';
import { definePreset } from '@primeuix/themes';
import GlobalLoader from '@/Components/GlobalLoader.vue';

const MyPreset = definePreset(Aura, {
    semantic: {
        fontSize: {
            'xs': '.80rem',
            'sm': '.875rem',
            'xxs': '.70rem',
            'tiny': '.875rem',
            'base': '1rem',
            'lg': '1.125rem',
            'xl': '1.25rem',
            '2xl': '1.5rem',
            '3xl': '1.875rem',
            '4xl': '2.25rem',
            '5xl': '3rem',
            '6xl': '4rem',
            '7xl': '5rem',
            '8xl': '6rem',
            '9xl': '7rem',
            '10xl': '8rem',
        },
        primary: {
            '50': '#f8fafb',
            '100': '#ebf0fc',
            '200': '#d5d7f9',
            '300': '#b3b1ef',
            '400': '#9788e3',
            '500': '#7e63da',
            '600': '#6747c8',
            '700': '#4e35a5',
            '800': '#352477',
            '900': '#1e1748',
        },
        surface: {
            50: '#fafafa',
            100: '#f4f4f5',
            200: '#e4e4e7',
            300: '#d4d4d8',
            400: '#a1a1aa',
            500: '#71717a',
            600: '#52525b',
            700: '#3f3f46',
            800: '#27272a',
            900: '#18181b',
            950: '#18181b'
        },
        indigo: {
            '50': '#f8fafb',
            '100': '#ebf0fc',
            '200': '#d5d7f9',
            '300': '#b3b1ef',
            '400': '#9788e3',
            '500': '#7e63da',
            '600': '#6747c8',
            '700': '#4e35a5',
            '800': '#352477',
            '900': '#1e1748',
        },
        blue: {
            '50': '#f7fafb',
            '100': '#e4f1fd',
            '200': '#c6dafb',
            '300': '#9cb7f3',
            '400': '#778fea',
            '500': '#6069e3',
            '600': '#4e4dd4',
            '700': '#3b39b4',
            '800': '#292785',
            '900': '#161853',
        },
        lightblue: {
            '50': '#f7fafb',
            '100': '#e4f0fc',
            '200': '#c7dafa',
            '300': '#9eb6f2',
            '400': '#798ee8',
            '500': '#626ae0',
            '600': '#504dd1',
            '700': '#3d3aaf',
            '800': '#2a2781',
            '900': '#171851',
        },
        azure: {
            '50': '#eff6ff',
            '100': '#dbeafe',
            '200': '#bfdbfe',
            '300': '#93c5fd',
            '400': '#60a5fa',
            '500': '#3b82f6',
            '600': '#2563eb',
            '700': '#1d4ed8',
            '800': '#1e40af',
            '900': '#1e3a8a',
        },
        navy: {
            '50': '#f3f8f9',
            '100': '#d8f1fa',
            '200': '#ace2f4',
            '300': '#77c4e3',
            '400': '#3fa1cc',
            '500': '#2e81b4',
            '600': '#27679b',
            '700': '#214d7a',
            '800': '#183457',
            '900': '#0f203a',
        },
        pictonblue: {
            '50': '#FFFFFF',
            '100': '#F2F8FD',
            '200': '#C6E0F6',
            '300': '#9AC9EF',
            '400': '#6EB1E8',
            '500': '#4299E1',
            '600': '#2180CF',
            '700': '#1A65A3',
            '800': '#134A77',
            '900': '#0C2E4B'
        },
        bluechill: {
            '50': '#A4DCF7',
            '100': '#8DD3F5',
            '200': '#5DC1F2',
            '300': '#2EB0EE',
            '400': '#1297D7',
            '500': '#0E76A8',
            '600': '#0A5579',
            '700': '#06344A',
            '800': '#02131B',
            '900': '#000000'
        },
        denim: {
            '50': '#FAFDFE',
            '100': '#DFEFFB',
            '200': '#A8D5F4',
            '300': '#72BAEE',
            '400': '#3B9FE7',
            '500': '#1981CC',
            '600': '#156AA8',
            '700': '#105383',
            '800': '#0C3C5F',
            '900': '#07253B'
        },
    }
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const pinia = createPinia();

        return createApp({ 
            render: () => h('div', [
                h(App, props),
                h(GlobalLoader)
            ]) 
        })
            .use(plugin)
            .use(ZiggyVue)
            .use(PrimeVue, {
                theme: {
                    preset: MyPreset
                }
            })
            .use(ToastService)
            .use(ConfirmationService)
            .directive('tooltip', Tooltip)
            .use(pinia)
            .mount(el);
    },
    progress: {
        color: '#fbbf24',
        showSpinner: true,
        includeCSS: true,
    },
});
