import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const manifest = { 'resources/js/pages/Demo.vue': { file: 'assets/Demo.js', isEntry: true } };

async function loadModule(): Promise<typeof import('../moduleManifest')> {
    vi.resetModules();

    return import('../moduleManifest');
}

describe('getModuleManifest', () => {
    const fetchMock = vi.fn();

    beforeEach(() => {
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        fetchMock.mockReset();
    });

    it('fetches a module manifest once, revalidating the cached copy', async () => {
        fetchMock.mockResolvedValue(new Response(JSON.stringify(manifest)));
        const { getModuleManifest } = await loadModule();

        await expect(getModuleManifest('demo')).resolves.toEqual(manifest);
        await expect(getModuleManifest('demo')).resolves.toEqual(manifest);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock).toHaveBeenCalledWith('/build/modules/demo/manifest.json', {
            cache: 'no-cache',
        });
    });

    it('returns null for a module that was never built', async () => {
        fetchMock.mockResolvedValue(new Response('Not found', { status: 404 }));
        const { getModuleManifest } = await loadModule();

        await expect(getModuleManifest('demo')).resolves.toBeNull();
    });

    it('returns null when the request fails', async () => {
        fetchMock.mockRejectedValue(new TypeError('Network error'));
        const { getModuleManifest } = await loadModule();

        await expect(getModuleManifest('demo')).resolves.toBeNull();
    });

    it('is the only manifest loader: module pages and slot components share its cache', () => {
        for (const file of ['../resolveModulePage.ts', '../../composables/useModuleSlots.ts']) {
            const source = readFileSync(resolve(import.meta.dirname, file), 'utf8');

            expect(source, file).toContain("from '@/utils/moduleManifest'");
            expect(source, file).not.toContain('manifest.json');
        }
    });
});
