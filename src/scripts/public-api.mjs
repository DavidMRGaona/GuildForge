// The core's public frontend API for modules: the packages the page shares through the
// import map and the files of resources/js a module may import at runtime. One list
// (resources/js/public-api.json) read by the core's build, the import map, verify-build.mjs
// and the module build; everything else under @/ is private to the core.
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * @typedef {object} PublicApi
 * @property {Record<string, string>} packages Bare specifier => vendor export entry, relative to src/
 * @property {Record<string, string>} entries `@/` specifier => entry file, relative to src/
 */

/** Libraries the core provides for module builds without sharing them: each module bundles what it uses. */
export const BUILD_PROVIDED_PACKAGES = Object.freeze(['@lucide/vue']);

/** Build and test tooling a module never declares: modules build and test with the core's. */
export const FORBIDDEN_MODULE_PACKAGES = Object.freeze([
    'vite',
    '@vitejs/plugin-vue',
    '@tailwindcss/vite',
    'vitest',
    '@vue/test-utils',
    'happy-dom',
    'typescript',
    'vue-tsc',
    'vue',
]);

/**
 * @param {string} srcDir The core's src/ directory
 * @returns {PublicApi}
 */
export function readPublicApi(srcDir) {
    const list = JSON.parse(readFileSync(resolve(srcDir, 'resources/js/public-api.json'), 'utf8'));

    return { packages: list.packages, entries: list.entries };
}

/**
 * Whether the page provides this import, so a module build must leave it out of the bundle.
 *
 * @param {string} id
 * @param {PublicApi} api
 */
export function isSharedWithModules(id, api) {
    return Object.hasOwn(api.packages, id) || Object.hasOwn(api.entries, id);
}

/**
 * Whether this is an import of the core's sources that is not part of the public API.
 *
 * @param {string} id
 * @param {PublicApi} api
 */
export function isPrivateCoreImport(id, api) {
    return id.startsWith('@/') && !Object.hasOwn(api.entries, id);
}
