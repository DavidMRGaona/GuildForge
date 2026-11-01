// @vitest-environment node
import { existsSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { SPECS_TSCONFIG, specsTsconfig, writeSpecsTsconfig } from '../module-specs-tsconfig.mjs';

const STUB_INCLUDE = [
    'resources/js/**/*.ts',
    'resources/js/**/*.vue',
    '../../resources/js/types/**/*.d.ts',
];

function moduleWith(tsconfig: string): string {
    const dir = mkdtempSync(join(tmpdir(), 'module-specs-tsconfig-'));
    writeFileSync(join(dir, 'tsconfig.json'), tsconfig);
    mkdirSync(join(dir, 'tests', 'js'), { recursive: true });

    return dir;
}

describe('specsTsconfig', () => {
    it("extends the module's configuration and adds the Vitest specs to what it includes", () => {
        const dir = moduleWith(JSON.stringify({ include: STUB_INCLUDE }));

        expect(specsTsconfig(dir)).toEqual({
            extends: './tsconfig.json',
            include: [...STUB_INCLUDE, 'tests/js/**/*.ts'],
        });
    });

    it('keeps what the module already includes, comments and all, without repeating the specs', () => {
        const dir = moduleWith(`{
            // Specs already checked
            "include": ["resources/js/**/*.ts", "tests/js/**/*.ts", "types/*.d.ts"],
        }`);

        expect(specsTsconfig(dir).include).toEqual([
            'resources/js/**/*.ts',
            'tests/js/**/*.ts',
            'types/*.d.ts',
        ]);
    });

    it("adds the specs to TypeScript's default when the module includes nothing", () => {
        const dir = moduleWith('{ "compilerOptions": { "strict": true } }');

        expect(specsTsconfig(dir).include).toEqual(['**/*', 'tests/js/**/*.ts']);
    });

    it('fails on a configuration TypeScript cannot read', () => {
        const dir = moduleWith('{ "include": [');

        expect(() => specsTsconfig(dir)).toThrow(/tsconfig\.json/);
    });
});

describe('writeSpecsTsconfig', () => {
    it("writes the configuration next to the module's, where its paths resolve", () => {
        const dir = moduleWith(JSON.stringify({ include: STUB_INCLUDE }));

        const path = writeSpecsTsconfig(dir);

        expect(path).toBe(join(dir, SPECS_TSCONFIG));
        expect(existsSync(path)).toBe(true);
        expect(JSON.parse(readFileSync(path, 'utf8'))).toEqual(specsTsconfig(dir));
    });
});
