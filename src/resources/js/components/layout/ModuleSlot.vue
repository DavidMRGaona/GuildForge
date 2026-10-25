<script setup lang="ts">
import { computed, onErrorCaptured, ref } from 'vue';
import { useModuleSlots } from '@/composables/useModuleSlots';
import type { SlotPosition } from '@/types/slots';

const props = defineProps<{
    name: SlotPosition;
}>();

const { getSlotComponents, hasSlotComponents } = useModuleSlots();

const components = computed(() => getSlotComponents(props.name));
const hasComponents = computed(() => hasSlotComponents(props.name));

const slotErrors = ref<Map<string, Error>>(new Map());
const isDev = import.meta.env.DEV;

onErrorCaptured((error, instance, info) => {
    const componentKey = instance?.$attrs?.['data-slot-key'] as string | undefined;
    const errorObj = error instanceof Error ? error : new Error(String(error));

    console.error(
        `[ModuleSlot] Error in slot "${props.name}"${componentKey ? ` (component: ${componentKey})` : ''}:`,
        errorObj.message,
        `\nLifecycle: ${info}`
    );

    if (componentKey) {
        slotErrors.value.set(componentKey, errorObj);
    }

    // Prevent error from propagating — the slot renders without this component
    return false;
});
</script>

<template>
    <template v-if="hasComponents">
        <template v-for="item in components" :key="item.key">
            <div
                v-if="slotErrors.has(item.key) && isDev"
                class="my-1 rounded border border-dashed border-error bg-error/5 p-2 text-xs text-error"
            >
                <small>Module slot error: {{ slotErrors.get(item.key)?.message }}</small>
            </div>
            <Suspense v-else>
                <div :data-slot-key="item.key">
                    <component :is="item.component" v-bind="item.props" />
                </div>
                <template #fallback>
                    <div class="min-h-8" aria-hidden="true"></div>
                </template>
            </Suspense>
        </template>
    </template>
</template>
