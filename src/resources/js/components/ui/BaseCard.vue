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
    <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-stone-800 dark:shadow-stone-900/50">
        <div v-if="$slots.header || props.title" class="border-b border-stone-200 px-6 py-4 dark:border-stone-700">
            <slot name="header">
                <h3 v-if="props.title" class="text-lg font-semibold text-stone-900 dark:text-stone-100">
                    {{ props.title }}
                </h3>
                <p v-if="props.subtitle" class="mt-1 text-sm text-stone-500 dark:text-stone-400">
                    {{ props.subtitle }}
                </p>
            </slot>
        </div>

        <div :class="{ 'p-6': props.padding }">
            <slot />
        </div>

        <div v-if="$slots.footer" class="border-t border-stone-200 bg-stone-50 px-6 py-4 dark:border-stone-700 dark:bg-stone-900">
            <slot name="footer" />
        </div>
    </div>
</template>
