import { computed, defineAsyncComponent, type Component, type ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SlotRegistration, SlotPosition } from '@/types/slots';

// Glob all module components for dynamic loading
// These modules are discovered at build time - modules installed after build
// will be loaded via the runtime fallback mechanism
// Path: from composables/ -> js/ -> resources/ -> src/ -> modules/
const moduleComponents = import.meta.glob<{ default: Component }>(
    '../../../modules/*/resources/js/components/**/*.vue'
);

// Cache for dynamically loaded module components (runtime fallback)
const dynamicComponentCache = new Map<string, Promise<{ default: Component }>>();

// Cache for module manifests (to avoid repeated fetches)
const manifestCache = new Map<string, Promise<Record<string, ManifestEntry> | null>>();

interface ManifestEntry {
    file: string;
    src?: string;
    isEntry?: boolean;
    css?: string[];
}

/**
 * Fetch and cache a module's Vite manifest.
 * Returns null if manifest doesn't exist (module not built).
 */
async function getModuleManifest(module: string): Promise<Record<string, ManifestEntry> | null> {
    if (manifestCache.has(module)) {
        return manifestCache.get(module)!;
    }

    const manifestUrl = `/build/modules/${module}/manifest.json`;

    const promise = fetch(manifestUrl)
        .then((response) => {
            if (!response.ok) {
                return null;
            }
            return response.json() as Promise<Record<string, ManifestEntry>>;
        })
        .catch(() => null);

    manifestCache.set(module, promise);
    return promise;
}

export interface ResolvedSlotComponent {
    key: string;
    component: Component;
    props: Record<string, unknown>;
    registration: SlotRegistration;
}

interface ModuleSlotsComposable {
    moduleSlots: ComputedRef<Record<string, SlotRegistration[]>>;
    getSlotComponents: (slotName: SlotPosition) => ResolvedSlotComponent[];
    hasSlotComponents: (slotName: SlotPosition) => boolean;
}

/**
 * Load a module component dynamically at runtime.
 * This is the fallback for modules installed after the app was built.
 *
 * Uses the module's Vite manifest to resolve the component path to the
 * actual built filename (handles content hashing in production).
 */
async function loadDynamicComponent(
    module: string,
    componentPath: string
): Promise<{ default: Component } | null> {
    const cacheKey = `${module}/${componentPath}`;

    // Check cache first
    if (dynamicComponentCache.has(cacheKey)) {
        return dynamicComponentCache.get(cacheKey)!;
    }

    const loadPromise = (async (): Promise<{ default: Component } | null> => {
        // Get the module's Vite manifest
        const manifest = await getModuleManifest(module);

        if (!manifest) {
            console.warn(
                `[ModuleSlots] No manifest found for module "${module}". Has it been built?`
            );
            return null;
        }

        // The manifest key matches the source path relative to the module
        // e.g., "resources/js/components/AnnouncementBanner.vue"
        const manifestKey = `resources/js/${componentPath}`;
        const entry = manifest[manifestKey];

        if (!entry) {
            console.warn(
                `[ModuleSlots] Component not found in manifest: ${manifestKey}`,
                `Available entries: ${Object.keys(manifest).join(', ')}`
            );
            return null;
        }

        // Construct the full URL to the built component
        const componentUrl = `/build/modules/${module}/${entry.file}`;

        try {
            const mod = await import(/* @vite-ignore */ componentUrl);
            return mod as { default: Component };
        } catch (error) {
            console.warn(
                `[ModuleSlots] Failed to load component from ${componentUrl}:`,
                error instanceof Error ? error.message : error
            );
            return null;
        }
    })();

    dynamicComponentCache.set(cacheKey, loadPromise as Promise<{ default: Component }>);
    return loadPromise;
}

/**
 * Resolve a component path to an async component.
 * First tries the build-time glob, then falls back to actuaruntime loading.
 */
function resolveComponent(module: string, componentPath: string): Component | null {
    // Build the glob path: ../../../modules/{module}/resources/js/{componentPath}
    const globPath = `../../../modules/${module}/resources/js/${componentPath}`;

    // Try build-time glob first (fastest, for modules present at build time)
    const staticLoader = moduleComponents[globPath];
    if (staticLoader) {
        return defineAsyncComponent({
            loader: staticLoader,
            delay: 0,
            timeout: 10000,
            onError: (error, _retry, fail) => {
                console.error(
                    `[ModuleSlots] Failed to load component: ${module}/${componentPath}`,
                    error
                );
                fail();
            },
        });
    }

    // Fallback: try runtime dynamic loading for modules installed after build
    return defineAsyncComponent({
        loader: async () => {
            const result = await loadDynamicComponent(module, componentPath);
            if (!result) {
                throw new Error(`Component not found: ${module}/${componentPath}`);
            }
            return result;
        },
        delay: 0,
        timeout: 15000, // Slightly longer timeout for network requests
        onError: (error, _retry, fail) => {
            console.error(
                `[ModuleSlots] Failed to load component: ${module}/${componentPath}`,
                error
            );
            fail();
        },
    });
}

/**
 * Get props for a slot component, merging static props with Inertia data
 */
function getComponentProps(
    registration: SlotRegistration,
    pageProps: Record<string, unknown>
): Record<string, unknown> {
    const props: Record<string, unknown> = { ...registration.props };

    // Inject data from Inertia page props based on dataKeys
    for (const dataKey of registration.dataKeys) {
        if (dataKey in pageProps) {
            props[dataKey] = pageProps[dataKey];
        }
    }

    return props;
}

/**
 * Composable for working with module slots
 */
export function useModuleSlots(): ModuleSlotsComposable {
    const page = usePage();

    const moduleSlots = computed(
        () => (page.props.moduleSlots as Record<string, SlotRegistration[]>) ?? {}
    );

    /**
     * Get resolved components for a specific slot position
     */
    function getSlotComponents(slotName: SlotPosition): ResolvedSlotComponent[] {
        const registrations = moduleSlots.value[slotName] ?? [];

        return registrations
            .map((registration) => {
                const component = resolveComponent(registration.module, registration.component);

                if (!component) {
                    return null;
                }

                return {
                    key: `${registration.module}-${registration.component}-${registration.order}`,
                    component,
                    props: getComponentProps(registration, page.props as Record<string, unknown>),
                    registration,
                };
            })
            .filter((item): item is ResolvedSlotComponent => item !== null);
    }

    /**
     * Check if a slot has any components registered
     */
    function hasSlotComponents(slotName: SlotPosition): boolean {
        return (moduleSlots.value[slotName]?.length ?? 0) > 0;
    }

    return {
        moduleSlots,
        getSlotComponents,
        hasSlotComponents,
    };
}
