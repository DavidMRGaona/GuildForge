import { defineAsyncComponent, type Component } from 'vue';
import { resolveModuleComponent } from '@/composables/useModuleSlots';
import type { ResolvedProfileSection } from '@/types/profileSections';

/** Where core sections live, relative to resources/js (the path the server sends) */
const CORE_SECTIONS_PATH = 'components/profileSections/';

// Core sections ship with the core build (core#742)
const coreSectionLoaders = import.meta.glob<{ default: Component }>(
    '../components/profileSections/**/*.vue'
);

// Vue tells components apart by identity: one async wrapper per path, or the section remounts
const coreComponentCache = new Map<string, Component>();

function resolveCoreComponent(componentPath: string): Component | null {
    if (!componentPath.startsWith(CORE_SECTIONS_PATH)) {
        return null;
    }

    const cached = coreComponentCache.get(componentPath);
    if (cached) {
        return cached;
    }

    const loader = coreSectionLoaders[`../${componentPath}`];
    if (!loader) {
        return null;
    }

    const component = defineAsyncComponent({ loader, delay: 0, timeout: 10000 });
    coreComponentCache.set(componentPath, component);

    return component;
}

/**
 * The component that draws a profile section: a module's is loaded like a module slot, a core one
 * from the core build. Null when the core has no such component (logged, never thrown).
 */
export function resolveProfileSectionComponent(section: ResolvedProfileSection): Component | null {
    if (section.module !== null) {
        return resolveModuleComponent(section.module, section.component);
    }

    const component = resolveCoreComponent(section.component);
    if (!component) {
        console.warn(`[ProfileSection] Core section component not found: ${section.component}`);
    }

    return component;
}
