import { defineConfig, type ViteUserConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { existsSync } from 'fs';
import { resolve } from 'path';

/**
 * Vitest configuration of a GuildForge module, with the core's toolchain:
 *
 *     // modules/<name>/vitest.config.ts
 *     import { defineModuleTestConfig } from '../../vitest.module.config.ts';
 *     export default defineModuleTestConfig({ root: import.meta.dirname });
 *
 * The tests run inside the core, as the build does: components import the core's sources
 * through `@/`, and every package comes from the core's node_modules.
 */
export interface ModuleTestConfigOptions {
    /** The module's directory (`import.meta.dirname` in its vitest.config.ts) */
    root: string;
}

const coreDir = import.meta.dirname;

export function defineModuleTestConfig({ root }: ModuleTestConfigOptions): ViteUserConfig {
    const setup = resolve(root, 'tests/js/setup.ts');

    return defineConfig({
        root,
        plugins: [vue()],
        resolve: {
            alias: {
                '@': resolve(coreDir, 'resources/js'),
                // The core's icons, as ES modules: Vite dedupes their Vue import, while the
                // CommonJS build would require the core's own Vue copy
                '@lucide/vue': resolve(coreDir, 'node_modules/@lucide/vue/dist/esm/lucide-vue.mjs'),
            },
            // The core's sources and the module's would otherwise load a Vue copy each
            dedupe: ['vue', 'vue-i18n'],
        },
        test: {
            environment: 'happy-dom',
            include: ['tests/js/**/*.spec.ts'],
            setupFiles: existsSync(setup) ? [setup] : [],
            restoreMocks: true,
            // Processed by Vite, so that dedupe reaches their Vue import
            server: { deps: { inline: ['@lucide/vue'] } },
        },
    });
}
