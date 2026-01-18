<script setup lang="ts">
interface Props {
    title?: string;
    subtitle?: string;
    padding?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    padding: true,
});
</script>

<template>
    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div v-if="$slots.header || props.title" class="border-b border-gray-200 px-6 py-4">
            <slot name="header">
                <h3 v-if="props.title" class="text-lg font-semibold text-gray-900">
                    {{ props.title }}
                </h3>
                <p v-if="props.subtitle" class="mt-1 text-sm text-gray-500">
                    {{ props.subtitle }}
                </p>
            </slot>
        </div>

        <div :class="{ 'p-6': props.padding }">
            <slot />
        </div>

        <div v-if="$slots.footer" class="border-t border-gray-200 bg-gray-50 px-6 py-4">
            <slot name="footer" />
        </div>
    </div>
</template>
