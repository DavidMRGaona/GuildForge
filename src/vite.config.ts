import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { readFileSync } from 'fs';
import { resolve } from 'path';

// Shared packages and public entries modules get through the import map (see ModuleImportMap)
const publicApi = JSON.parse(
    readFileSync(resolve(import.meta.dirname, 'resources/js/public-api.json'), 'utf8')
) as { packages: Record<string, string>; entries: Record<string, string> };

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                // Filament admin panel theme (registered with ->viteTheme())
                'resources/css/filament/admin/theme.css',
                'resources/js/app.ts',
                // The public frontend API, one entry each for the module import map
                ...Object.values(publicApi.packages),
                ...Object.values(publicApi.entries),
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
                        {
                            name: 'vendor-vue',
                            test: /[\\/]node_modules[\\/](vue|@vue|vue-i18n|@intlify|pinia)[\\/]/,
                            priority: 30,
                        },
                        {
                            name: 'vendor-inertia',
                            test: /[\\/]node_modules[\\/]@inertiajs[\\/]/,
                            priority: 20,
                        },
                        {
                            name: 'vendor-head',
                            test: /[\\/]node_modules[\\/](@unhead|unhead)[\\/]/,
                            priority: 10,
                        },
                        // One FullCalendar for the app's calendar and the module ones
                        {
                            name: 'vendor-calendar',
                            test: /[\\/]node_modules[\\/]@fullcalendar[\\/]/,
                            priority: 10,
                        },
                    ],
                },
            },
            // Preserve the exports of the public API entries for the import map
            preserveEntrySignatures: 'exports-only',
        },
    },
});
