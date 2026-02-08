import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.ts',
                // Vendor exports for module runtime loading (import maps)
                'resources/js/vendor-exports/vue.ts',
                'resources/js/vendor-exports/pinia.ts',
                'resources/js/vendor-exports/vue-i18n.ts',
                'resources/js/vendor-exports/inertia.ts',
                // App exports for module runtime loading (import maps)
                'resources/js/app-exports/cloudinary.ts',
                'resources/js/app-exports/confirm-dialog.ts',
                'resources/js/app-exports/form.ts',
            ],
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
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
            '@modules': resolve(__dirname, 'modules'),
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor-vue': ['vue', 'vue-i18n', 'pinia'],
                    'vendor-inertia': ['@inertiajs/vue3'],
                    'vendor-head': ['@unhead/vue'],
                },
            },
            // Preserve exports from vendor-export entry points for import maps
            preserveEntrySignatures: 'exports-only',
        },
    },
});