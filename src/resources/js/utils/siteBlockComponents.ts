import { defineAsyncComponent, type Component } from 'vue';
import { resolveModuleComponent } from '@/composables/useModuleSlots';
import type { ResolvedSiteBlock } from '@/types/siteBlocks';

/** Where core blocks live, relative to resources/js (the path the server sends) */
const CORE_BLOCKS_PATH = 'components/siteBlocks/';

// Core blocks ship with the core build; there are none until the footer brings its own
const coreBlockLoaders = import.meta.glob<{ default: Component }>(
    '../components/siteBlocks/**/*.vue'
);

// Vue tells components apart by identity: one async wrapper per path, or the block remounts
const coreComponentCache = new Map<string, Component>();

function resolveCoreComponent(componentPath: string): Component | null {
    if (!componentPath.startsWith(CORE_BLOCKS_PATH)) {
        return null;
    }

    const cached = coreComponentCache.get(componentPath);
    if (cached) {
        return cached;
    }

    const loader = coreBlockLoaders[`../${componentPath}`];
    if (!loader) {
        return null;
    }

    const component = defineAsyncComponent({ loader, delay: 0, timeout: 10000 });
    coreComponentCache.set(componentPath, component);

    return component;
}

/**
 * The component that draws a site block: a module's is loaded like a module slot, a core one from
 * the core build. Null when the core has no such component (logged, never thrown).
 */
export function resolveSiteBlockComponent(block: ResolvedSiteBlock): Component | null {
    if (block.module !== null) {
        return resolveModuleComponent(block.module, block.component);
    }

    const component = resolveCoreComponent(block.component);
    if (!component) {
        console.warn(`[SiteBlock] Core block component not found: ${block.component}`);
    }

    return component;
}
