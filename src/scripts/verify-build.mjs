#!/usr/bin/env node
// Checks the production build against the core's public frontend API
// (resources/js/public-api.json), which the module import map serves
// (app/View/Components/ModuleImportMap.php):
//
// - every shared package and public entry is an entry in the manifest;
// - no public entry ships CSS: a module loads the entry from the import map,
//   which never loads its stylesheet;
// - the public entries keep the exports in resources/js/public-api.exports.json:
//   a missing one is a breaking change, a new one updates the snapshot
//   (`node scripts/verify-build.mjs <build> --write-snapshot`);
// - the app, the shared packages and the public entries reach one chunk per
//   vendor group (a second Vue, Inertia or FullCalendar would split their state);
// - the core build contains no module sources: in production module pages, slot
//   components, translations and stylesheets only come from each module's own build.
//
// Run after `vite build`.
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { readPublicApi } from './public-api.mjs';

const srcDir = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const args = process.argv.slice(2);
const writeSnapshot = args.includes('--write-snapshot');
const buildDir = resolve(args.find((arg) => !arg.startsWith('--')) ?? 'public/build');
const snapshotPath = resolve(srcDir, 'resources/js/public-api.exports.json');
const manifest = JSON.parse(readFileSync(resolve(buildDir, 'manifest.json'), 'utf8'));
const api = readPublicApi(srcDir);

// Minimum export counts measured with the current dependencies; raise, never lower.
const PACKAGE_MINIMUMS = {
    vue: 171,
    pinia: 17,
    'vue-i18n': 11,
    '@inertiajs/vue3': 23,
    '@unhead/vue': 15,
};
const PACKAGE_REQUIRED = {
    vue: ['createApp', 'ref', 'computed', 'defineComponent', 'h'],
    pinia: ['defineStore', 'createPinia'],
    'vue-i18n': ['createI18n', 'useI18n'],
    '@inertiajs/vue3': ['Link', 'router', 'useForm', 'usePage', 'createInertiaApp'],
    '@unhead/vue': ['useHead'],
    '@fullcalendar/vue3': ['default', 'joinClassNames'],
    '@fullcalendar/vue3/daygrid': ['default'],
    '@fullcalendar/vue3/timegrid': ['default'],
    '@fullcalendar/vue3/interaction': ['default'],
    '@fullcalendar/vue3/themes/classic': ['default'],
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

/** Every chunk a manifest item loads statically, itself included. */
function staticChunks(key, seen = new Set()) {
    if (manifest[key] === undefined || seen.has(key)) return seen;
    seen.add(key);
    for (const imported of manifest[key].imports ?? []) staticChunks(imported, seen);
    return seen;
}

/** Vendor group name => chunk, for the vendor chunks a manifest item loads. */
function vendorChunks(key) {
    const groups = {};
    for (const chunk of staticChunks(key)) {
        const group = /^_(vendor-[a-z]+)-/.exec(chunk)?.[1];
        if (group) groups[group] = chunk;
    }
    return groups;
}

/** The shared packages and public entries present in the manifest, specifier => exported names. */
const built = {};
for (const [specifier, entry] of [
    ...Object.entries(api.packages),
    ...Object.entries(api.entries),
]) {
    const chunk = manifest[entry];
    if (!chunk?.isEntry) {
        failures.push(`${specifier}: missing from the manifest`);
        continue;
    }
    built[specifier] = exportedNames(chunk.file);
}

for (const [specifier, names] of Object.entries(built)) {
    if (!Object.hasOwn(api.packages, specifier)) continue;
    const minimum = PACKAGE_MINIMUMS[specifier] ?? 0;
    if (names.size < minimum) {
        failures.push(`${specifier}: ${names.size} exports, expected at least ${minimum}`);
    }
    for (const name of PACKAGE_REQUIRED[specifier] ?? []) {
        if (!names.has(name)) failures.push(`${specifier}: no longer exports ${name}`);
    }
    if (Object.keys(vendorChunks(api.packages[specifier])).length === 0) {
        failures.push(`${specifier}: its vendor export loads no vendor chunk`);
    }
}

for (const [specifier, entry] of Object.entries(api.entries)) {
    if (!built[specifier]) continue;
    if ([...staticChunks(entry)].some((chunk) => (manifest[chunk].css ?? []).length > 0)) {
        failures.push(`${specifier}: ships CSS (public entries must not)`);
    }
}

// The exports of the public entries against the snapshot
const current = Object.fromEntries(
    Object.keys(api.entries)
        .filter((specifier) => built[specifier])
        .sort()
        .map((specifier) => [specifier, [...built[specifier]].sort()])
);
if (writeSnapshot) {
    writeFileSync(snapshotPath, JSON.stringify(current, null, 4) + '\n');
    console.log(`Wrote ${snapshotPath}`);
}
let snapshot = {};
try {
    snapshot = JSON.parse(readFileSync(snapshotPath, 'utf8'));
} catch {
    failures.push(
        'resources/js/public-api.exports.json is missing or not JSON (run with --write-snapshot)'
    );
}
for (const [specifier, names] of Object.entries(snapshot)) {
    if (!Object.hasOwn(api.entries, specifier)) {
        failures.push(`${specifier}: no longer a public entry (breaking change)`);
        continue;
    }
    if (!current[specifier]) continue;
    for (const name of names) {
        if (!current[specifier].includes(name)) {
            failures.push(`${specifier}: no longer exports ${name} (breaking change)`);
        }
    }
}
for (const [specifier, names] of Object.entries(current)) {
    for (const name of names) {
        if (!(snapshot[specifier] ?? []).includes(name)) {
            failures.push(
                `${specifier}: exports ${name} not in public-api.exports.json (update the snapshot)`
            );
        }
    }
}

// One chunk per vendor group: the app's own (app.ts and its lazy calendar) is the reference
const appCalendar = Object.keys(manifest).find((key) => manifest[key].name === 'EventCalendar');
const appVendor = { ...vendorChunks(appCalendar), ...vendorChunks('resources/js/app.ts') };
if (
    !appVendor['vendor-vue'] ||
    appVendor['vendor-vue'] !== vendorChunks(api.packages.vue)['vendor-vue']
) {
    failures.push(`app.ts and vendor-exports/vue.ts do not share one vendor-vue chunk`);
}
if (
    !appVendor['vendor-calendar'] ||
    appVendor['vendor-calendar'] !==
        vendorChunks(api.packages['@fullcalendar/vue3'])['vendor-calendar']
) {
    failures.push(
        `vendor-exports/fullcalendar.ts and the app's calendar do not share one vendor-calendar chunk`
    );
}
for (const [specifier, entry] of [
    ...Object.entries(api.packages),
    ...Object.entries(api.entries),
]) {
    for (const [group, chunk] of Object.entries(vendorChunks(entry))) {
        if (appVendor[group] !== undefined && appVendor[group] !== chunk) {
            failures.push(`${specifier} does not share the app's ${group} chunk (${chunk})`);
        }
    }
}

// A glob over module sources leaves their paths as string literals in the bundle
const MODULE_SOURCE = /modules\/[a-z0-9-]+\/resources\/(?:js|css)\//;
function builtScripts(dir) {
    return readdirSync(dir, { withFileTypes: true }).flatMap((item) => {
        const path = join(dir, item.name);
        if (item.isDirectory()) return item.name === 'modules' ? [] : builtScripts(path);
        return item.name.endsWith('.js') ? [path] : [];
    });
}
for (const file of builtScripts(buildDir)) {
    const match = readFileSync(file, 'utf8').match(MODULE_SOURCE);
    if (match) {
        failures.push(`${file.slice(buildDir.length + 1)} contains module sources (${match[0]}…)`);
    }
}

if (failures.length > 0) {
    console.error('Build verification failed:\n - ' + failures.join('\n - '));
    process.exit(1);
}
console.log(
    `Build verified: ${Object.keys(api.packages).length} shared packages and ` +
        `${Object.keys(api.entries).length} public entries, one chunk per vendor group ` +
        `(${Object.values(appVendor).join(', ')}).`
);
