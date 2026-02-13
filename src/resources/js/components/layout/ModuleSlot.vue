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
        `\nLifecycle: ${info}`,
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
            <div v-if="slotErrors.has(item.key) && isDev" class="module-slot-error">
                <small>Module slot error: {{ slotErrors.get(item.key)?.message }}</small>
            </div>
            <Suspense v-else>
                <div :data-slot-key="item.key">
                    <component :is="item.component" v-bind="item.props" />
                </div>
                <template #fallback>
                    <div class="module-slot-loading" aria-hidden="true"></div>
                </template>
            </Suspense>
        </template>
    </template>
</template>

<style scoped>
.module-slot-loading {
    min-height: 2rem;
}

.module-slot-error {
    padding: 0.5rem;
    margin: 0.25rem 0;
    border: 1px dashed #e74c3c;
    border-radius: 0.25rem;
    color: #e74c3c;
    font-size: 0.75rem;
    background: rgba(231, 76, 60, 0.05);
}
</style>
