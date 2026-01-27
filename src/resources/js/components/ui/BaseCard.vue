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
    <div class="overflow-hidden rounded-lg bg-surface shadow dark:shadow-stone-900/50">
        <div v-if="$slots.header || props.title" class="border-b border-default px-6 py-4">
            <slot name="header">
                <h3 v-if="props.title" class="text-lg font-semibold text-base-primary">
                    {{ props.title }}
                </h3>
                <p v-if="props.subtitle" class="mt-1 text-sm text-base-muted">
                    {{ props.subtitle }}
                </p>
            </slot>
        </div>

        <div :class="{ 'p-6': props.padding }">
            <slot />
        </div>

        <div v-if="$slots.footer" class="border-t border-default bg-muted px-6 py-4">
            <slot name="footer" />
        </div>
    </div>
</template>
