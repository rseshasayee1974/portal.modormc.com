import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

import { visualizer } from 'rollup-plugin-visualizer';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        visualizer({
            open: false,
            gzipSize: true,
            sourcemap: false,
            filename: 'stats.html',
        }),
    ],
    build: {
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true,
                pure_funcs: ['console.log'],
                passes: 3
            },
            output: {
                comments: false
            }
        },
        cssCodeSplit: true,
        sourcemap: false,
        // Let Rollup preserve dynamic-import boundaries. Grouping every package
        // containing "vue" also grouped PrimeVue and chart wrappers into startup.
    }
});
