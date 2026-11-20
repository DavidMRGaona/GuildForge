import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import { DEV_DEDUPED_PACKAGES } from './scripts/public-api.mjs';
import { HASH_CHARACTERS, jsFileName } from './scripts/build-file-names.mjs';

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
                // The service worker, served at /sw.js by the site (ViteServiceWorkerScript, core#802)
                'resources/js/serviceWorker/sw.ts',
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
        // The dev server serves module sources through this config, with one dependency cache
        // keyed by import name: a module's own copy of these packages would mix with the core's
        // on the page, and ProseMirror refuses two copies in one editor. Module builds (their own
        // root, vite.module.config.ts) still bundle the module's copies, so the module quality
        // checks require the versions the core installs
        dedupe: [...DEV_DEDUPED_PACKAGES],
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
                // Scripts under their hash alone: content blockers drop them by words in the URL
                entryFileNames: jsFileName,
                chunkFileNames: jsFileName,
                hashCharacters: HASH_CHARACTERS,
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
