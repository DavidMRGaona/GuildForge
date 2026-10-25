import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

describe('calendarTheme', () => {
    it('imports no stylesheet: as a public API entry its CSS would not reach module pages', () => {
        const source = readFileSync(resolve(import.meta.dirname, '../calendarTheme.ts'), 'utf8');

        expect(source).not.toMatch(/import\s+['"][^'"]+\.css['"]/);
    });
});
