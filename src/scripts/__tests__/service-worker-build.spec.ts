import { describe, expect, it } from 'vitest';
import { SERVICE_WORKER_ENTRY, serviceWorkerProblems } from '../service-worker-build.mjs';

const FILE = 'assets/k3j9x0ab.js';

function problems(source: string, chunk: Record<string, unknown> = {}): string[] {
    const manifest = { [SERVICE_WORKER_ENTRY]: { file: FILE, isEntry: true, ...chunk } };

    return serviceWorkerProblems(manifest, (file: string) => (file === FILE ? source : ''));
}

describe('serviceWorkerProblems', () => {
    it('accepts a worker built as one classic script', () => {
        expect(
            problems(
                'function e(e){return e}self.addEventListener(`push`,t=>{importScripts;t.waitUntil(e(1))});'
            )
        ).toEqual([]);
    });

    it('refuses a build without the worker', () => {
        expect(serviceWorkerProblems({}, () => '')).toEqual([
            `${SERVICE_WORKER_ENTRY}: missing from the manifest`,
        ]);
    });

    it.each([
        ['a static import', 'import{a as e}from"./x.js";e();'],
        ['a bare import', 'import"./x.js";'],
        ['a dynamic import', 'self.onpush=()=>import(`./x.js`);'],
        ['import.meta', 'self.x=import.meta.url;'],
        ['an export list', 'var e=1;export{e as t};'],
        ['an exported declaration', 'export const e=1;'],
        ['a default export', 'export default 1;'],
    ])('refuses %s, which a classic worker cannot run', (_, source) => {
        expect(problems(source)).toEqual([
            `${SERVICE_WORKER_ENTRY}: ${FILE} is not a classic script (import or export)`,
        ]);
    });

    it('refuses a worker split into chunks or shipping CSS', () => {
        expect(
            problems('void 0;', {
                imports: ['_vendor-vue-ab12cd34.js'],
                dynamicImports: ['x.js'],
                css: ['a.css'],
            })
        ).toEqual([
            `${SERVICE_WORKER_ENTRY}: loads other chunks (_vendor-vue-ab12cd34.js, x.js)`,
            `${SERVICE_WORKER_ENTRY}: ships CSS`,
        ]);
    });
});
