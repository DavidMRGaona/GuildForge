// @vitest-environment node
import { execFileSync } from 'node:child_process';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const script = resolve(import.meta.dirname, '../module-build-mode.sh');

function modeOf(files: Record<string, string>): string {
    const dir = mkdtempSync(join(tmpdir(), 'module-build-mode-'));
    for (const [name, content] of Object.entries(files)) {
        writeFileSync(join(dir, name), content);
    }

    return execFileSync('sh', [script, dir], { encoding: 'utf8' }).trim();
}

describe('module-build-mode.sh', () => {
    it('reports a module on the core toolchain', () => {
        expect(modeOf({ 'vite.config.ts': 'export default defineModuleConfig({});' })).toBe('core');
    });

    it('rejects a module with its own toolchain', () => {
        expect(modeOf({ 'vite.config.ts': 'export default {};', 'package.json': '{}' })).toBe(
            'invalid'
        );
    });

    it('reports a module without a frontend build', () => {
        expect(modeOf({})).toBe('none');
    });

    it('reports a build configuration that cannot build', () => {
        expect(modeOf({ 'vite.config.ts': 'export default {};' })).toBe('invalid');
    });
});
