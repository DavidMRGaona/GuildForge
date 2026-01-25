import { computed, defineAsyncComponent, type Component, type ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SlotRegistration, SlotPosition } from '@/types/slots';

// Eager glob all module components for dynamic loading
// Path: from composables/ -> js/ -> resources/ -> src/ -> modules/
const moduleComponents = import.meta.glob<{ default: Component }>(
    '../../../modules/*/resources/js/components/**/*.vue'
);

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
 * Resolve a component path to an async component
 */
function resolveComponent(module: string, componentPath: string): Component | null {
    // Build the glob path: ../../../modules/{module}/resources/js/{componentPath}
    const globPath = `../../../modules/${module}/resources/js/${componentPath}`;

    const loader = moduleComponents[globPath];
    if (!loader) {
        console.warn(`[ModuleSlots] Component not found: ${module}/${componentPath}`);
        return null;
    }

    return defineAsyncComponent({
        loader,
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
