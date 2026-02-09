import type { DefineComponent } from 'vue';

/**
 * Mapping of page prefixes to module names.
 * This is populated from the backend via Inertia shared props.
 */
let modulePageMapping: Record<string, string> = {};

/**
 * Set the module page mapping from Inertia shared props.
 */
export function setModulePageMapping(mapping: Record<string, string>): void {
    modulePageMapping = mapping;
}

/**
 * Get the current module page mapping.
 */
export function getModulePageMapping(): Record<string, string> {
    return modulePageMapping;
}

/**
 * Extract the prefix from a page name.
 * For example, "GameTables/Index" returns "GameTables".
 */
function extractPrefix(pageName: string): string {
    const slashIndex = pageName.indexOf('/');
    return slashIndex !== -1 ? pageName.substring(0, slashIndex) : pageName;
}

/**
 * Get the module name for a page prefix.
 * Returns null if the prefix is not registered as a module page.
 */
export function getModuleForPage(pageName: string): string | null {
    const prefix = extractPrefix(pageName);
    return modulePageMapping[prefix] ?? null;
}

/**
 * Check if a page name belongs to a module.
 */
export function isModulePage(pageName: string): boolean {
    return getModuleForPage(pageName) !== null;
}

/**
 * Glob patterns for core pages and module pages.
 * Core pages are in ./pages/, module pages are in modules/<module>/resources/js/pages/
 */
type PageGlob = Record<string, () => Promise<DefineComponent>>;

interface ResolveOptions {
    corePages: PageGlob;
    modulePages: PageGlob;
}

// ==================
// Runtime Dynamic Loading (for modules installed after build)
// ==================

/**
 * Vite manifest entry structure.
 */
interface ManifestEntry {
    file: string;
    src?: string;
    isEntry?: boolean;
    css?: string[];
}

/**
 * Cache for module manifests (to avoid repeated fetches).
 */
const manifestCache = new Map<string, Promise<Record<string, ManifestEntry> | null>>();

/**
 * Cache for dynamically loaded page components.
 */
const dynamicPageCache = new Map<string, Promise<DefineComponent>>();

/**
 * Fetch and cache a module's Vite manifest.
 * Returns null if manifest doesn't exist (module not built).
 */
async function getModuleManifest(
    moduleName: string
): Promise<Record<string, ManifestEntry> | null> {
    if (manifestCache.has(moduleName)) {
        return manifestCache.get(moduleName)!;
    }

    const manifestUrl = `/build/modules/${moduleName}/manifest.json`;

    const promise = fetch(manifestUrl)
        .then((response) => {
            if (!response.ok) {
                return null;
            }
            return response.json() as Promise<Record<string, ManifestEntry>>;
        })
        .catch(() => null);

    manifestCache.set(moduleName, promise);
    return promise;
}

/**
 * Load a module page dynamically at runtime.
 * This is the fallback for modules installed after the app was built.
 *
 * Uses the module's Vite manifest to resolve the page path to the
 * actual built filename (handles content hashing in production).
 */
async function loadDynamicPage(
    moduleName: string,
    pagePath: string
): Promise<DefineComponent | null> {
    const cacheKey = `${moduleName}/${pagePath}`;

    // Check cache first
    if (dynamicPageCache.has(cacheKey)) {
        return dynamicPageCache.get(cacheKey)!;
    }

    const loadPromise = (async (): Promise<DefineComponent | null> => {
        // Get the module's Vite manifest
        const manifest = await getModuleManifest(moduleName);

        if (!manifest) {
            if (import.meta.env.DEV) {
                console.warn(
                    `[ModulePages] No manifest found for module "${moduleName}". Has it been built?`
                );
            }
            return null;
        }

        // The manifest key matches the source path relative to the module
        // e.g., "resources/js/pages/GameTables/Index.vue"
        const manifestKey = `resources/js/pages/${pagePath}.vue`;
        const entry = manifest[manifestKey];

        if (!entry) {
            if (import.meta.env.DEV) {
                console.warn(
                    `[ModulePages] Page not found in manifest: ${manifestKey}`,
                    `Available entries: ${Object.keys(manifest)
                        .filter((k) => k.includes('/pages/'))
                        .join(', ')}`
                );
            }
            return null;
        }

        // Construct the full URL to the built page component
        const pageUrl = `/build/modules/${moduleName}/${entry.file}`;

        // Load CSS files if any
        if (entry.css?.length) {
            for (const cssFile of entry.css) {
                const cssUrl = `/build/modules/${moduleName}/${cssFile}`;
                loadCss(cssUrl);
            }
        }

        try {
            const mod = await import(/* @vite-ignore */ pageUrl);
            return mod.default as DefineComponent;
        } catch (error) {
            console.warn(
                `[ModulePages] Failed to load page from ${pageUrl}:`,
                error instanceof Error ? error.message : error,
            );
            return null;
        }
    })();

    dynamicPageCache.set(cacheKey, loadPromise as Promise<DefineComponent>);
    return loadPromise;
}

/**
 * Load a CSS file dynamically by appending a <link> element.
 */
function loadCss(url: string): void {
    // Check if already loaded
    if (document.querySelector(`link[href="${url}"]`)) {
        return;
    }

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = url;
    document.head.appendChild(link);
}

// ==================
// Page Resolver
// ==================

/**
 * Create a page resolver that handles both core and module pages.
 * This is used in app.ts to configure Inertia's page resolution.
 *
 * The resolver tries multiple strategies:
 * 1. Build-time glob (modules present at build time) - fastest
 * 2. Runtime dynamic loading via manifest (modules installed after build)
 */
export function createPageResolver(options: ResolveOptions) {
    return async (name: string): Promise<DefineComponent> => {
        const moduleName = getModuleForPage(name);

        if (moduleName !== null) {
            // This is a module page - try build-time glob first
            const modulePath = `../../modules/${moduleName}/resources/js/pages/${name}.vue`;

            if (options.modulePages[modulePath]) {
                return options.modulePages[modulePath]();
            }

            // Try without the prefix in the path (e.g., GameTables/Index -> modules/game-tables/resources/js/pages/Index.vue)
            const prefix = extractPrefix(name);
            const relativeName = name.substring(prefix.length + 1); // Remove "GameTables/" prefix
            const alternativePath = `../../modules/${moduleName}/resources/js/pages/${relativeName}.vue`;

            if (options.modulePages[alternativePath]) {
                return options.modulePages[alternativePath]();
            }

            // Fallback: try runtime dynamic loading for modules installed after build
            // First try with full page name (e.g., "GameTables/Index")
            const dynamicPage = await loadDynamicPage(moduleName, name);
            if (dynamicPage) {
                return dynamicPage;
            }

            // Also try with relative name (without prefix)
            const dynamicPageRelative = await loadDynamicPage(moduleName, relativeName);
            if (dynamicPageRelative) {
                return dynamicPageRelative;
            }

            throw new Error(
                `Module page not found: ${name}. Looked in module "${moduleName}" for ${modulePath}, ${alternativePath}, and runtime manifest.`
            );
        }

        // This is a core page
        const corePath = `./pages/${name}.vue`;

        if (options.corePages[corePath]) {
            return options.corePages[corePath]();
        }

        throw new Error(`Core page not found: ${name}. Looked for ${corePath}`);
    };
}
