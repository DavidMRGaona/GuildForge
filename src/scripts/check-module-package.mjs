#!/usr/bin/env node
// Checks a module's package.json against the core's rules: a module builds and tests with
// the core's toolchain, so it declares only its own runtime libraries, in dependencies, with
// the lockfile `npm ci` needs; never build or test tooling, a package the core shares through
// the import map (or another package of its scope, such as @fullcalendar/core, or of a scope
// the core bundles into its shared chunks, such as @vue/reactivity), or a library
// the core provides for module builds. No package.json passes.
//
//   node scripts/check-module-package.mjs modules/<name>
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
    BUILD_PROVIDED_PACKAGES,
    FORBIDDEN_MODULE_PACKAGES,
    readPublicApi,
    SHARED_SCOPES,
} from './public-api.mjs';

const srcDir = resolve(dirname(fileURLToPath(import.meta.url)), '..');

/** The package a specifier names: `@scope/name` or `name`, without a subpath. */
function packageName(specifier) {
    const parts = specifier.split('/');

    return specifier.startsWith('@') ? parts.slice(0, 2).join('/') : parts[0];
}

/**
 * @param {string} moduleDir
 * @returns {string[]} One message per problem, empty when the package.json is acceptable
 */
export function checkModulePackage(moduleDir) {
    const manifestPath = join(moduleDir, 'package.json');
    if (!existsSync(manifestPath)) {
        return [];
    }

    const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    const shared = new Set(Object.keys(readPublicApi(srcDir).packages).map(packageName));
    const sharedScopes = new Set([
        ...SHARED_SCOPES,
        ...[...shared].filter((name) => name.startsWith('@')).map((name) => name.split('/')[0]),
    ]);
    const failures = [];
    const declared = {};

    for (const field of ['devDependencies', 'peerDependencies', 'optionalDependencies']) {
        if (Object.keys(manifest[field] ?? {}).length > 0) {
            failures.push(
                `${field}: a module declares only its runtime libraries, in dependencies`
            );
            Object.assign(declared, manifest[field]);
        }
    }
    Object.assign(declared, manifest.dependencies ?? {});

    for (const name of Object.keys(declared)) {
        if (FORBIDDEN_MODULE_PACKAGES.includes(name)) {
            failures.push(`${name}: build or test tooling, the core provides it`);
        } else if (shared.has(name) || sharedScopes.has(name.split('/')[0])) {
            failures.push(`${name}: shared by the core through the import map`);
        } else if (BUILD_PROVIDED_PACKAGES.includes(name)) {
            failures.push(`${name}: provided by the core for module builds`);
        }
    }

    if (
        Object.keys(manifest.dependencies ?? {}).length > 0 &&
        !existsSync(join(moduleDir, 'package-lock.json'))
    ) {
        failures.push(
            'package-lock.json is missing: generate it with the npm of the Node container'
        );
    }

    return failures;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
    const moduleDir = resolve(process.argv[2] ?? '.');
    const failures = checkModulePackage(moduleDir);
    if (failures.length > 0) {
        console.error(`${join(moduleDir, 'package.json')}:\n - ${failures.join('\n - ')}`);
        process.exit(1);
    }
    console.log('Module package.json follows the core toolchain rules.');
}
