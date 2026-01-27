<script setup lang="ts">
import { computed } from 'vue';
import { useModuleSlots } from '@/composables/useModuleSlots';
import type { SlotPosition } from '@/types/slots';

const props = defineProps<{
    name: SlotPosition;
}>();

const { getSlotComponents, hasSlotComponents } = useModuleSlots();

const components = computed(() => getSlotComponents(props.name));
const hasComponents = computed(() => hasSlotComponents(props.name));
</script>

<template>
    <template v-if="hasComponents">
        <template v-for="item in components" :key="item.key">
            <Suspense>
                <div>
                    <component :is="item.component" v-bind="item.props" />
                </div>
                <template #fallback>
                    <div></div>
                </template>
            </Suspense>
        </template>
    </template>
</template>
