#!/usr/bin/env node
// Checks the production build against the module import map
// (app/View/Components/ModuleImportMap.php): its five vendor-exports entries
// and its contracts are entries in the manifest and keep their exports, and
// app.ts, vendor-exports/vue.ts and every contract import the same single
// vendor-vue chunk (a contract on another Vue copy would lose reactivity).
// The other entries' chunk imports are not checked. Run after `vite build`.
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const buildDir = resolve(process.argv[2] ?? 'public/build');
const manifest = JSON.parse(readFileSync(resolve(buildDir, 'manifest.json'), 'utf8'));

// Minimum export counts measured with the current dependencies; raise, never lower.
const ENTRIES = {
    'resources/js/vendor-exports/vue.ts': 171,
    'resources/js/vendor-exports/pinia.ts': 17,
    'resources/js/vendor-exports/vue-i18n.ts': 11,
    'resources/js/vendor-exports/inertia.ts': 23,
    'resources/js/vendor-exports/unhead.ts': 15,
    'resources/js/contracts/cookieConsent.ts': 3,
};
const CONTRACTS = ['resources/js/contracts/cookieConsent.ts'];
const REQUIRED = {
    'resources/js/vendor-exports/inertia.ts': [
        'Link',
        'router',
        'useForm',
        'usePage',
        'createInertiaApp',
    ],
    'resources/js/vendor-exports/pinia.ts': ['defineStore', 'createPinia'],
    'resources/js/vendor-exports/unhead.ts': ['useHead'],
    'resources/js/vendor-exports/vue.ts': ['createApp', 'ref', 'computed', 'defineComponent', 'h'],
    'resources/js/vendor-exports/vue-i18n.ts': ['createI18n', 'useI18n'],
    'resources/js/contracts/cookieConsent.ts': [
        'registerCookieConsentProvider',
        'notifyCookieConsentChanged',
        'hasCookieConsent',
    ],
};

const failures = [];

function exportedNames(file) {
    const source = readFileSync(resolve(buildDir, file), 'utf8');
    const names = new Set();
    for (const match of source.matchAll(/export\s*\{([^}]*)\}/g)) {
        for (const part of match[1].split(',')) {
            const name = part
                .trim()
                .split(/\s+as\s+/)
                .pop();
            if (name) names.add(name);
        }
    }
    return names;
}

for (const [entry, minimum] of Object.entries(ENTRIES)) {
    const chunk = manifest[entry];
    if (!chunk?.isEntry) {
        failures.push(`${entry}: missing from the manifest or not an entry`);
        continue;
    }
    const names = exportedNames(chunk.file);
    if (names.size < minimum) {
        failures.push(`${entry}: ${names.size} exports, expected at least ${minimum}`);
    }
    for (const name of REQUIRED[entry] ?? []) {
        if (!names.has(name)) failures.push(`${entry}: no longer exports ${name}`);
    }
}

// app.ts and vendor-exports/vue.ts must each import exactly one vendor-vue chunk, the same one.
const vueChunks = (entry) =>
    (manifest[entry]?.imports ?? []).filter((name) => /vendor-vue/.test(name));
const appVue = vueChunks('resources/js/app.ts');
const exportVue = vueChunks('resources/js/vendor-exports/vue.ts');
if (appVue.length !== 1 || exportVue.length !== 1 || appVue[0] !== exportVue[0]) {
    failures.push(
        `app.ts and vendor-exports/vue.ts do not share one vendor-vue chunk (${appVue} / ${exportVue})`
    );
}
for (const contract of CONTRACTS) {
    const contractVue = vueChunks(contract);
    if (contractVue.length !== 1 || contractVue[0] !== appVue[0]) {
        failures.push(`${contract} does not import the app's vendor-vue chunk (${contractVue})`);
    }
}

if (failures.length > 0) {
    console.error('Build verification failed:\n - ' + failures.join('\n - '));
    process.exit(1);
}
console.log(
    `Build verified: ${Object.keys(ENTRIES).length} import map entries, one Vue chunk (${appVue[0]}).`
);
