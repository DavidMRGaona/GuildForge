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
                // Filament admin panel theme (registered with ->viteTheme())
                'resources/css/filament/admin/theme.css',
                'resources/js/app.ts',
                // Vendor exports for module runtime loading (import maps)
                'resources/js/vendor-exports/vue.ts',
                'resources/js/vendor-exports/pinia.ts',
                'resources/js/vendor-exports/vue-i18n.ts',
                'resources/js/vendor-exports/inertia.ts',
                'resources/js/vendor-exports/unhead.ts',
                // Contracts shared with modules (import maps, kept external by module builds)
                'resources/js/contracts/cookieConsent.ts',
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
            '@': resolve(import.meta.dirname, 'resources/js'),
            '@modules': resolve(import.meta.dirname, 'modules'),
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
        rolldownOptions: {
            output: {
                // Shared vendor chunks: the import map entries and app.ts must load one Vue
                codeSplitting: {
                    groups: [
                        { name: 'vendor-vue', test: /[\\/]node_modules[\\/](vue|@vue|vue-i18n|@intlify|pinia)[\\/]/, priority: 30 },
                        { name: 'vendor-inertia', test: /[\\/]node_modules[\\/]@inertiajs[\\/]/, priority: 20 },
                        { name: 'vendor-head', test: /[\\/]node_modules[\\/](@unhead|unhead)[\\/]/, priority: 10 },
                    ],
                },
            },
            // Preserve exports from vendor-export and contract entry points for import maps
            preserveEntrySignatures: 'exports-only',
        },
    },
});