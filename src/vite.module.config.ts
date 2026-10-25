import { defineConfig, type Plugin, type UserConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { existsSync, readdirSync, readFileSync, statSync } from 'fs';
import { relative, resolve } from 'path';
import {
    BUILD_PROVIDED_PACKAGES,
    isPrivateCoreImport,
    isSharedWithModules,
    readPublicApi,
    type PublicApi,
} from './scripts/public-api.mjs';

/**
 * Build configuration of a GuildForge module, with the core's toolchain:
 *
 *     // modules/<name>/vite.config.ts
 *     import { defineModuleConfig } from '../../vite.module.config';
 *     export default defineModuleConfig({ name: '<name>', root: __dirname });
 *
 * Run from the core's src/ with `npm run build:module -- <name>`. The shared packages and
 * the public entries of resources/js/public-api.json stay out of the bundle: the page serves
 * them through the import map, so every module uses the installed core. Any other import of
 * the core's sources, or of a package the module does not declare, stops the build.
 */
export interface ModuleConfigOptions {
    /** The module name, as in module.json */
    name: string;
    /** The module's directory (`__dirname` in its vite.config.ts) */
    root: string;
}

const coreDir = import.meta.dirname;
const coreJs = resolve(coreDir, 'resources/js');

/** Every .vue file under a directory, entry name (relative to the module root) => path. */
function discoverEntries(root: string, dir: string): Record<string, string> {
    const absolute = resolve(root, dir);
    if (!existsSync(absolute)) {
        return {};
    }

    const entries: Record<string, string> = {};
    for (const file of readdirSync(absolute)) {
        const path = resolve(absolute, file);
        if (statSync(path).isDirectory()) {
            Object.assign(entries, discoverEntries(root, `${dir}/${file}`));
        } else if (file.endsWith('.vue')) {
            entries[`${dir}/${file}`] = path;
        }
    }

    return entries;
}

/** The package an import names: `@scope/name` or `name`, without a subpath. */
function packageName(id: string): string {
    const parts = id.split('/');

    return id.startsWith('@') ? parts.slice(0, 2).join('/') : (parts[0] ?? id);
}

function declaredDependencies(root: string): string[] {
    const manifest = resolve(root, 'package.json');
    if (!existsSync(manifest)) {
        return [];
    }
    const { dependencies = {} } = JSON.parse(readFileSync(manifest, 'utf8')) as {
        dependencies?: Record<string, string>;
    };

    return Object.keys(dependencies);
}

/**
 * Stops the build on an import the module may not bundle: the core's private sources, a
 * subpath of a shared package the core does not share, or an undeclared package (which would
 * otherwise resolve from the core's node_modules, at a version the module does not control).
 */
function publicApiGuard(root: string, api: PublicApi): Plugin {
    const declared = new Set([...declaredDependencies(root), ...BUILD_PROVIDED_PACKAGES]);
    const sharedPackages = new Set(Object.keys(api.packages).map(packageName));
    const moduleSource = (importer: string | undefined): boolean => {
        const file = importer?.split('?')[0];
        if (file === undefined || importer?.includes('type=style')) {
            return false;
        }
        const path = relative(root, file);

        return (
            !path.startsWith('..') &&
            !path.split(/[\\/]/).includes('node_modules') &&
            /\.(vue|ts|tsx|js|mjs)$/.test(file)
        );
    };

    const privateImport = (id: string): string =>
        `"${id}" is not part of the core's public frontend API (src/resources/js/public-api.json): import a public entry or ask for it to be added`;

    return {
        name: 'guildforge:public-api',
        enforce: 'pre',
        async resolveId(id, importer): Promise<null> {
            // The external option keeps these out before any plugin runs; never judge them here
            if (!moduleSource(importer) || isSharedWithModules(id, api)) {
                return null;
            }
            if (isPrivateCoreImport(id, api)) {
                this.error(privateImport(id));
            }
            if (id.startsWith('.') || id.startsWith('/')) {
                // A path into the core's sources would bundle a private copy of the file
                const resolved = await this.resolve(id, importer, { skipSelf: true });
                const path =
                    resolved === null ? '..' : relative(coreJs, resolved.id.split('?')[0] ?? '');
                if (!path.startsWith('..')) {
                    const specifier = `@/${path.replace(/\.(ts|js)$/, '')}`;
                    this.error(
                        Object.hasOwn(api.entries, specifier)
                            ? `"${id}" is a file of the core's sources: import "${specifier}" instead`
                            : privateImport(specifier)
                    );
                }

                return null;
            }
            if (id.startsWith('\0') || id.startsWith('@/')) {
                return null;
            }
            if (sharedPackages.has(packageName(id))) {
                this.error(
                    `"${id}" is not shared by the core: import one of the ${packageName(id)} specifiers of src/resources/js/public-api.json or ask for it to be added`
                );
            }
            if (!declared.has(packageName(id))) {
                this.error(
                    `"${id}" is not a dependency of the module: declare ${packageName(id)} in the module's package.json dependencies`
                );
            }

            return null;
        },
    };
}

export function defineModuleConfig({ name, root }: ModuleConfigOptions): UserConfig {
    const api = readPublicApi(coreDir);
    const standaloneOutput = process.env.MODULE_STANDALONE_OUTPUT === 'true';
    const entries = {
        ...discoverEntries(root, 'resources/js/components'),
        ...discoverEntries(root, 'resources/js/pages'),
    };
    if (Object.keys(entries).length === 0) {
        console.warn(
            `[${name}] No Vue files found in resources/js/components/ or resources/js/pages/`
        );
    }
    const styles = resolve(root, 'resources/css/module.css');

    return defineConfig({
        root,
        base: standaloneOutput ? '/' : `/build/modules/${name}/`,
        plugins: [publicApiGuard(root, api), vue(), tailwindcss()],
        publicDir: false,
        build: {
            outDir: standaloneOutput
                ? resolve(root, 'public/build')
                : resolve(coreDir, 'public/build/modules', name),
            emptyOutDir: true,
            manifest: 'manifest.json',
            rolldownOptions: {
                input: {
                    ...entries,
                    // Utilities the core stylesheet cannot generate for this module
                    ...(existsSync(styles) ? { 'module-styles': styles } : {}),
                },
                output: {
                    format: 'es',
                    entryFileNames: 'assets/[name]-[hash].js',
                    chunkFileNames: 'assets/[name]-[hash].js',
                    assetFileNames: 'assets/[name]-[hash].[ext]',
                },
                preserveEntrySignatures: 'exports-only',
                // Served by the page through the import map (app/View/Components/ModuleImportMap.php)
                external: (id) => isSharedWithModules(id, api),
            },
        },
    });
}
