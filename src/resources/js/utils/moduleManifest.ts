/**
 * A module's Vite manifest entry.
 */
export interface ModuleManifestEntry {
    file: string;
    src?: string;
    isEntry?: boolean;
    css?: string[];
    imports?: string[];
}

export type ModuleManifest = Record<string, ModuleManifestEntry>;

/** One request per module, shared by module pages and slot components. */
const manifestCache = new Map<string, Promise<ModuleManifest | null>>();

/**
 * Fetch and cache a module's Vite manifest; null when the module was never built.
 *
 * The manifest keeps the same URL across module updates while the chunks it points to are
 * renamed, so a cached copy must always be revalidated. Browsers that cached it as immutable
 * would otherwise import chunks that no longer exist.
 */
export function getModuleManifest(module: string): Promise<ModuleManifest | null> {
    const cached = manifestCache.get(module);
    if (cached !== undefined) {
        return cached;
    }

    const promise = fetch(`/build/modules/${module}/manifest.json`, { cache: 'no-cache' })
        .then((response) => (response.ok ? (response.json() as Promise<ModuleManifest>) : null))
        .catch(() => null);

    manifestCache.set(module, promise);

    return promise;
}
