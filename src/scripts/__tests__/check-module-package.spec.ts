// @vitest-environment node
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { checkModulePackage } from '../check-module-package.mjs';
import { DEV_DEDUPED_PACKAGES } from '../public-api.mjs';

function moduleWith(manifest: object | null, lock: boolean | object = true): string {
    const dir = mkdtempSync(join(tmpdir(), 'module-package-'));
    if (manifest !== null) {
        writeFileSync(join(dir, 'package.json'), JSON.stringify(manifest));
        if (lock !== false) {
            writeFileSync(
                join(dir, 'package-lock.json'),
                JSON.stringify(lock === true ? {} : lock)
            );
        }
    }

    return dir;
}

/** The version the core's lockfile installs for a package */
function coreVersion(name: string): string {
    const lock = JSON.parse(
        readFileSync(resolve(import.meta.dirname, '../../package-lock.json'), 'utf8')
    ) as { packages: Record<string, { version?: string }> };
    const version = lock.packages[`node_modules/${name}`]?.version;
    if (version === undefined) {
        throw new Error(`The core does not install ${name}`);
    }

    return version;
}

/** A module lockfile installing the given packages, hoisted or nested */
function lockWith(packages: Record<string, string>): object {
    return {
        lockfileVersion: 3,
        packages: {
            '': { dependencies: {} },
            ...Object.fromEntries(
                Object.entries(packages).map(([path, version]) => [path, { version }])
            ),
        },
    };
}

describe('checkModulePackage', () => {
    it('accepts a module without package.json', () => {
        expect(checkModulePackage(moduleWith(null))).toEqual([]);
    });

    it('accepts runtime libraries the core does not bring', () => {
        expect(checkModulePackage(moduleWith({ dependencies: { 'chart.js': '^4' } }))).toEqual([]);
    });

    it('rejects build tooling, naming it', () => {
        const failures = checkModulePackage(moduleWith({ devDependencies: { vite: '^8' } }));

        expect(failures).toContain(
            'devDependencies: a module declares only its runtime libraries, in dependencies'
        );
        expect(failures).toContain('vite: build or test tooling, the core provides it');
    });

    it('rejects a package the core shares', () => {
        expect(
            checkModulePackage(moduleWith({ dependencies: { '@fullcalendar/vue3': '^7' } }))
        ).toEqual(['@fullcalendar/vue3: shared by the core through the import map']);
    });

    it('rejects a package of a scope the core bundles into its shared chunks', () => {
        expect(
            checkModulePackage(
                moduleWith({
                    dependencies: { '@vue/reactivity': '^3', '@intlify/core-base': '^11' },
                })
            )
        ).toEqual([
            '@vue/reactivity: shared by the core through the import map',
            '@intlify/core-base: shared by the core through the import map',
        ]);
    });

    it('rejects Tailwind, which the core builds with', () => {
        expect(checkModulePackage(moduleWith({ dependencies: { tailwindcss: '^4' } }))).toEqual([
            'tailwindcss: build or test tooling, the core provides it',
        ]);
    });

    it('rejects a library the core provides for module builds', () => {
        expect(checkModulePackage(moduleWith({ dependencies: { '@lucide/vue': '^1' } }))).toEqual([
            '@lucide/vue: provided by the core for module builds',
        ]);
    });

    it('deduplicates TipTap and ProseMirror under the dev server', () => {
        expect(DEV_DEDUPED_PACKAGES).toEqual([
            '@tiptap/core',
            '@tiptap/pm',
            '@tiptap/vue-3',
            'prosemirror-model',
            'prosemirror-state',
            'prosemirror-transform',
            'prosemirror-view',
        ]);
    });

    it('accepts deduplicated packages at the versions the core installs', () => {
        const tiptap = coreVersion('@tiptap/core');
        const state = coreVersion('prosemirror-state');

        expect(
            checkModulePackage(
                moduleWith(
                    { dependencies: { '@tiptap/core': tiptap } },
                    lockWith({
                        'node_modules/@tiptap/core': tiptap,
                        'node_modules/prosemirror-state': state,
                    })
                )
            )
        ).toEqual([]);
    });

    it('rejects a deduplicated package locked at another version than the core', () => {
        const tiptap = coreVersion('@tiptap/core');

        expect(
            checkModulePackage(
                moduleWith(
                    { dependencies: { '@tiptap/core': '99.0.0' } },
                    lockWith({ 'node_modules/@tiptap/core': '99.0.0' })
                )
            )
        ).toEqual([
            `@tiptap/core 99.0.0: the core installs ${tiptap}, and the dev server serves the core's copy; lock the same version`,
        ]);
    });

    it('rejects a deduplicated package another dependency brings at another version', () => {
        const state = coreVersion('prosemirror-state');

        expect(
            checkModulePackage(
                moduleWith(
                    { dependencies: { 'prosemirror-markdown': '^1' } },
                    lockWith({
                        'node_modules/prosemirror-markdown': '1.13.0',
                        'node_modules/prosemirror-markdown/node_modules/prosemirror-state': '0.1.0',
                    })
                )
            )
        ).toEqual([
            `prosemirror-state 0.1.0: the core installs ${state}, and the dev server serves the core's copy; lock the same version`,
        ]);
    });

    it('ignores packages whose name only ends like a deduplicated one', () => {
        expect(
            checkModulePackage(
                moduleWith(
                    { dependencies: { 'my-prosemirror-state': '^1' } },
                    lockWith({ 'node_modules/my-prosemirror-state': '0.1.0' })
                )
            )
        ).toEqual([]);
    });

    it('requires the lockfile when there are dependencies', () => {
        expect(
            checkModulePackage(moduleWith({ dependencies: { 'chart.js': '^4' } }, false))
        ).toEqual(['package-lock.json is missing: generate it with the npm of the Node container']);
    });
});
