#!/usr/bin/env node
// Writes the TypeScript configuration vue-tsc checks a module with: the module's own
// tsconfig.json plus its Vitest specs (tests/js), whatever that file includes, so a type
// error in a spec fails the quality check as it does in the core, whose specs live in
// resources/js. It goes next to the module's tsconfig.json, where `include` and the
// inherited `paths` resolve; the caller removes it afterwards. Prints the path written.
//
//   node scripts/module-specs-tsconfig.mjs modules/<name>
import { writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import ts from 'typescript';

export const SPECS_TSCONFIG = '.tsconfig.quality-specs.json';

const SPECS = 'tests/js/**/*.ts';

// What TypeScript includes when a configuration names neither include nor files
const DEFAULT_INCLUDE = ['**/*'];

/**
 * @param {string} moduleDir
 * @returns {{ extends: string, include: string[] }}
 */
export function specsTsconfig(moduleDir) {
    const path = join(moduleDir, 'tsconfig.json');
    // TypeScript's own reader, so comments and trailing commas are read as vue-tsc reads them
    const { config, error } = ts.readConfigFile(path, ts.sys.readFile);
    if (error !== undefined) {
        throw new Error(`${path}: ${ts.flattenDiagnosticMessageText(error.messageText, '\n')}`);
    }

    const include = Array.isArray(config.include) ? config.include : DEFAULT_INCLUDE;

    return {
        extends: './tsconfig.json',
        include: include.includes(SPECS) ? include : [...include, SPECS],
    };
}

/**
 * @param {string} moduleDir
 * @returns {string} The path of the configuration written
 */
export function writeSpecsTsconfig(moduleDir) {
    const path = join(moduleDir, SPECS_TSCONFIG);
    writeFileSync(path, `${JSON.stringify(specsTsconfig(moduleDir), null, 4)}\n`);

    return path;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
    console.log(writeSpecsTsconfig(resolve(process.argv[2] ?? '.')));
}
