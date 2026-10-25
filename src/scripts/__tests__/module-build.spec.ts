// @vitest-environment node
import { resolve } from 'node:path';
import { build, resolveConfig } from 'vite';
import type { RolldownOutput } from 'rolldown';
import { afterEach, describe, expect, it, vi } from 'vitest';

const fixtures = resolve(import.meta.dirname, 'fixtures');

async function buildFixture(name: string): Promise<RolldownOutput> {
    const output = await build({
        configFile: resolve(fixtures, name, 'vite.config.ts'),
        logLevel: 'silent',
        build: { write: false },
    });

    return (Array.isArray(output) ? output[0] : output) as RolldownOutput;
}

function pageCode(output: RolldownOutput): string {
    const page = output.output.find((item) => item.type === 'chunk' && item.isEntry);

    return page?.type === 'chunk' ? page.code : '';
}

describe('defineModuleConfig', () => {
    afterEach(() => {
        vi.unstubAllEnvs();
    });

    it('leaves a public entry of the core out of the bundle', async () => {
        const code = pageCode(await buildFixture('demo-module'));

        expect(code).toMatch(/from\s*"@\/utils\/datetime"/);
        expect(code).not.toContain('Europe/Madrid');
    });

    it('leaves a shared package out of the bundle', async () => {
        expect(pageCode(await buildFixture('demo-module'))).toMatch(/from\s*"@fullcalendar\/vue3"/);
    });

    it('bundles the libraries the core provides for module builds', async () => {
        expect(pageCode(await buildFixture('demo-module'))).not.toMatch(/from\s*"@lucide\/vue"/);
    });

    it('compiles type imports of the core sources, which never reach the bundle', async () => {
        expect(pageCode(await buildFixture('demo-module'))).not.toContain('@/types');
    });

    it('stops the build on a private import of the core sources', async () => {
        await expect(buildFixture('private-import-module')).rejects.toThrow(
            `"@/utils/icons" is not part of the core's public frontend API (src/resources/js/public-api.json): import a public entry or ask for it to be added`
        );
    });

    it('stops the build on a package the module does not declare', async () => {
        await expect(buildFixture('undeclared-module')).rejects.toThrow(
            `"leaflet" is not a dependency of the module: declare leaflet in the module's package.json dependencies`
        );
    });

    it('stops the build on a subpath of a shared package the core does not share', async () => {
        await expect(buildFixture('subpath-module')).rejects.toThrow(
            `"@fullcalendar/vue3/locales/es" is not shared by the core`
        );
    });

    it('writes the manifest at the root of the output', async () => {
        const output = await buildFixture('demo-module');

        expect(output.output.map((item) => item.fileName)).toContain('manifest.json');
    });

    it('writes into the module with MODULE_STANDALONE_OUTPUT, into the core otherwise', async () => {
        const configFile = resolve(fixtures, 'demo-module', 'vite.config.ts');

        const core = await resolveConfig({ configFile, logLevel: 'silent' }, 'build');
        vi.stubEnv('MODULE_STANDALONE_OUTPUT', 'true');
        const standalone = await resolveConfig({ configFile, logLevel: 'silent' }, 'build');

        expect(core.build.outDir).toBe(
            resolve(import.meta.dirname, '../../public/build/modules/demo-module')
        );
        expect(core.base).toBe('/build/modules/demo-module/');
        expect(standalone.build.outDir).toBe(resolve(fixtures, 'demo-module/public/build'));
        expect(standalone.base).toBe('/');
    });
});
