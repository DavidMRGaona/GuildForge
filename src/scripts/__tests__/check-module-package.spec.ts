// @vitest-environment node
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { checkModulePackage } from '../check-module-package.mjs';

function moduleWith(manifest: object | null, lock = true): string {
    const dir = mkdtempSync(join(tmpdir(), 'module-package-'));
    if (manifest !== null) {
        writeFileSync(join(dir, 'package.json'), JSON.stringify(manifest));
        if (lock) writeFileSync(join(dir, 'package-lock.json'), '{}');
    }

    return dir;
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

    it('rejects a library the core provides for module builds', () => {
        expect(checkModulePackage(moduleWith({ dependencies: { '@lucide/vue': '^1' } }))).toEqual([
            '@lucide/vue: provided by the core for module builds',
        ]);
    });

    it('requires the lockfile when there are dependencies', () => {
        expect(
            checkModulePackage(moduleWith({ dependencies: { 'chart.js': '^4' } }, false))
        ).toEqual(['package-lock.json is missing: generate it with the npm of the Node container']);
    });
});
