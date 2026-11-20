// File names of the built scripts, for the core build (vite.config.ts) and the module builds
// (vite.module.config.ts).
//
// Content blockers that hide cookie notices block any script whose URL names cookies or consent.
// A blocked script takes down every module that imports it, and the module slot drops the
// component without a trace: with `@/contracts/cookieConsent` blocked, a table page lost its
// registration button. Only the file name changes: import names and manifest keys stay the same,
// and the import map is built from the manifest.

/** Words those lists look for in script URLs. */
export const BLOCKED_NAME_PATTERN = /cookie|consent|gdpr/i;

/**
 * The name to publish a chunk under: a neutral one when the chunk's own name holds a blocked word.
 *
 * @param {string} name
 * @returns {string}
 */
export function blockerSafeName(name) {
    return BLOCKED_NAME_PATTERN.test(name) ? 'choices' : name;
}

/**
 * `entryFileNames` / `chunkFileNames` of a build: `assets/[name]-[hash].js` with a blocker-safe name.
 *
 * @param {{ name: string }} chunk
 * @returns {string}
 */
export function jsFileName(chunk) {
    return `assets/${blockerSafeName(chunk.name)}-[hash].js`;
}
