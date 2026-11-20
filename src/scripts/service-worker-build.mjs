// The service worker (core#802) is a Vite entry like any other, but browsers register it as a
// classic script (`/sw.js`, ViteServiceWorkerScript): one shared chunk, helper or `import.meta`
// pulled into it by the build and the worker would fail to install, without any error on the page.
// verify-build.mjs runs this check on every build.

export const SERVICE_WORKER_ENTRY = 'resources/js/serviceWorker/sw.ts';

// Module syntax a classic script cannot run. `\b` keeps `importScripts` out.
const MODULE_SYNTAX = [
    /\bimport\b\s*(?:[{*"'`(]|[A-Za-z_$])/,
    /\bimport\s*\.\s*meta\b/,
    /\bexport\b\s*(?:[{*]|default\b|const\b|let\b|var\b|function\b|class\b|async\b)/,
];

/**
 * Why the built worker could not run as a classic script, if anything.
 *
 * @param {Record<string, {file: string, imports?: string[], dynamicImports?: string[], css?: string[]}>} manifest
 * @param {(file: string) => string} readFile reads a file of the build
 * @returns {string[]}
 */
export function serviceWorkerProblems(manifest, readFile) {
    const chunk = manifest[SERVICE_WORKER_ENTRY];
    if (chunk === undefined) {
        return [`${SERVICE_WORKER_ENTRY}: missing from the manifest`];
    }

    const problems = [];
    const loaded = [...(chunk.imports ?? []), ...(chunk.dynamicImports ?? [])];
    if (loaded.length > 0) {
        problems.push(`${SERVICE_WORKER_ENTRY}: loads other chunks (${loaded.join(', ')})`);
    }
    if ((chunk.css ?? []).length > 0) {
        problems.push(`${SERVICE_WORKER_ENTRY}: ships CSS`);
    }

    const source = readFile(chunk.file);
    if (MODULE_SYNTAX.some((pattern) => pattern.test(source))) {
        problems.push(
            `${SERVICE_WORKER_ENTRY}: ${chunk.file} is not a classic script (import or export)`
        );
    }

    return problems;
}
