// @vitest-environment node
import { mkdirSync, mkdtempSync, readFileSync, realpathSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { resolveConfig } from 'vite';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

const core = resolve(import.meta.dirname, '../..');
const stubs = resolve(core, 'stubs/modules/module');

let host: string;
let moduleDir: string;

/** Writes a stub, rendered for the module `demo`, into the module's directory. */
function writeModuleFromStub(stub: string, file: string): string {
    const source = readFileSync(resolve(stubs, stub), 'utf8').replaceAll(
        '{{ moduleName }}',
        'demo'
    );
    const path = join(moduleDir, file);
    writeFileSync(path, source);

    return path;
}

describe('module configuration stubs', () => {
    // A host laid out like the core (modules/<name> next to the shared configurations), whose
    // vite.module.config.ts and vitest.module.config.ts re-export the core's, so the stubs
    // load with their own relative imports.
    beforeEach(() => {
        host = realpathSync(mkdtempSync(join(tmpdir(), 'module-config-stubs-')));
        moduleDir = join(host, 'modules', 'demo');
        mkdirSync(moduleDir, { recursive: true });

        for (const shared of ['vite.module.config.ts', 'vitest.module.config.ts']) {
            writeFileSync(
                join(host, shared),
                `export * from '${pathToFileURL(resolve(core, shared)).href}';\n`
            );
        }
    });

    afterEach(() => {
        rmSync(host, { recursive: true, force: true });
    });

    it("loads the module's vite.config.ts with Vite's native config loader", async () => {
        const config = await resolveConfig(
            {
                configFile: writeModuleFromStub('vite.config.ts.stub', 'vite.config.ts'),
                configLoader: 'native',
                logLevel: 'silent',
            },
            'build'
        );

        expect(config.root).toBe(moduleDir);
    });

    it("loads the module's vitest.config.ts with Vite's native config loader", async () => {
        const config = await resolveConfig(
            {
                configFile: writeModuleFromStub('vitest.config.ts.stub', 'vitest.config.ts'),
                configLoader: 'native',
                logLevel: 'silent',
            },
            'serve'
        );

        expect(config.root).toBe(moduleDir);
    });
});
