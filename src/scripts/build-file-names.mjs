// File names of the built scripts, for the core build (vite.config.ts) and the module builds
// (vite.module.config.ts).
//
// Content blockers drop scripts by words in their URL: cookie notices, share buttons, social
// widgets, trackers, newsletters... A blocked chunk takes down everything that imports it: with
// `@/contracts/cookieConsent` blocked, a table page lost its registration button (#791), and a
// page that imports a blocked chunk does not load at all. So every script is published under its
// content hash alone, in lower-case letters and digits, with no separator a rule could match a
// word between. Only the shared vendor groups keep their name, which verify-build.mjs reads.
// Import names and manifest keys stay the same: everything that loads a script finds it through
// the manifest.

/** Hash alphabet of the built files: no `-` or `_`, so a hash never ends a word with one. */
export const HASH_CHARACTERS = 'base36';

/** The shared vendor groups of the core build (`codeSplitting.groups` in vite.config.ts). */
const SHARED_GROUP = /^vendor-[a-z]+$/;

/** What a published script is called: `assets/<hash>.js`, or `assets/vendor-<group>-<hash>.js`. */
export const PUBLISHED_SCRIPT = /^assets\/(?:vendor-[a-z]+-)?[a-z0-9]{8}\.js$/;

/**
 * `entryFileNames` / `chunkFileNames` of a build.
 *
 * @param {{ name: string }} chunk
 * @returns {string}
 */
export function jsFileName(chunk) {
    return SHARED_GROUP.test(chunk.name) ? `assets/${chunk.name}-[hash].js` : 'assets/[hash].js';
}
