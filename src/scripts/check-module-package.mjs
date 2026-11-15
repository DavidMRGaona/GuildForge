#!/usr/bin/env node
// Checks a module's package.json against the core's rules: a module builds and tests with
// the core's toolchain, so it declares only its own runtime libraries, in dependencies, with
// the lockfile `npm ci` needs; never build or test tooling, a package the core shares through
// the import map (or another package of its scope, such as @fullcalendar/core, or of a scope
// the core bundles into its shared chunks, such as @vue/reactivity), or a library
// the core provides for module builds. The packages the dev server takes from the core
// (DEV_DEDUPED_PACKAGES) must be locked at the versions the core installs, wherever they sit in
// the module's lockfile. No package.json passes.
//
//   node scripts/check-module-package.mjs modules/<name>
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
    BUILD_PROVIDED_PACKAGES,
    DEV_DEDUPED_PACKAGES,
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
 * Every installed copy in a lockfile (v2/v3 `packages`), as [package name, version].
 *
 * @param {string} path
 * @returns {Array<[string, string]>}
 */
function lockedPackages(path) {
    if (!existsSync(path)) {
        return [];
    }
    const { packages = {} } = JSON.parse(readFileSync(path, 'utf8'));

    return Object.entries(packages).flatMap(([location, entry]) => {
        const at = location.lastIndexOf('node_modules/');
        if (at === -1 || typeof entry?.version !== 'string') {
            return [];
        }

        return [[location.slice(at + 'node_modules/'.length), entry.version]];
    });
}

/**
 * The dev server serves the core's copy of each deduplicated package while the module build
 * bundles the module's: any other locked version would behave differently in each.
 *
 * @param {string} moduleDir
 * @returns {string[]}
 */
function dedupedVersionFailures(moduleDir) {
    // The copy the dev server resolves from the core's root, never a nested one
    const { packages: corePackages = {} } = JSON.parse(
        readFileSync(join(srcDir, 'package-lock.json'), 'utf8')
    );
    const core = new Map(
        DEV_DEDUPED_PACKAGES.flatMap((name) => {
            const version = corePackages[`node_modules/${name}`]?.version;

            return typeof version === 'string' ? [[name, version]] : [];
        })
    );
    const failures = new Set();
    for (const [name, version] of lockedPackages(join(moduleDir, 'package-lock.json'))) {
        if (!DEV_DEDUPED_PACKAGES.includes(name) || core.get(name) === version) {
            continue;
        }
        failures.add(
            core.has(name)
                ? `${name} ${version}: the core installs ${core.get(name)}, and the dev server serves the core's copy; lock the same version`
                : `${name} ${version}: the dev server takes it from the core, which does not install it`
        );
    }

    return [...failures];
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

    return [...failures, ...dedupedVersionFailures(moduleDir)];
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
