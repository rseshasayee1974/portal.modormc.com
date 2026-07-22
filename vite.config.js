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
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        const parts = id.toString().split('node_modules/');
                        if (parts[1]) {
                            const name = parts[1].split('/')[0];
                            // Group Vue and Inertia core components
                            if (name.includes('vue') || name.includes('inertia')) {
                                return 'framework-core';
                            }
                            // Group apexcharts and its Vue wrapper together
                            if (name.includes('apexcharts')) {
                                return 'vendor-apexcharts';
                            }
                            return 'vendor-' + name.replace('@', '').replace('/', '-');
                        }
                    }
                }
            }
        }
    }
});
