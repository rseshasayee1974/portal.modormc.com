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
            filename: 'stats.html',
        }),
    ],
    build: {
        cssCodeSplit: true,
        sourcemap: false,

        rollupOptions: {
            output: {
                manualChunks(id) {

                    if (id.includes('node_modules')) {

                        if (id.includes('vue'))
                            return 'vue';

                        if (id.includes('primevue'))
                            return 'primevue';

                        if (id.includes('apexcharts'))
                            return 'apexcharts';

                        if (id.includes('axios'))
                            return 'axios';

                        if (id.includes('sweetalert2'))
                            return 'sweetalert';

                        return 'vendor';
                    }
                }
            }
        }
    }
});
