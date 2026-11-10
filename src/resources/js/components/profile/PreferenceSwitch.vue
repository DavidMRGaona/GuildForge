<script setup lang="ts">
/**
 * The on/off switch of the «Correos» tab. aria-disabled (not `disabled`) keeps a locked switch
 * in the tab order, so a screen reader user reaches it and hears that it cannot be changed now.
 */
interface Props {
    checked: boolean;
    locked?: boolean;
    /** Ids naming it (the category, and the channel when there are two). */
    labelledBy: string;
    describedBy?: string;
}

const props = withDefaults(defineProps<Props>(), { locked: false, describedBy: '' });

const emit = defineEmits<{ toggle: [] }>();

function toggle(): void {
    if (!props.locked) {
        emit('toggle');
    }
}
</script>

<template>
    <!-- 44 px tall hit area around the 24 px track -->
    <button
        type="button"
        role="switch"
        :aria-checked="checked"
        :aria-disabled="locked"
        :aria-labelledby="labelledBy"
        :aria-describedby="describedBy || undefined"
        class="-my-2 flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
        :class="locked ? 'cursor-not-allowed' : 'cursor-pointer'"
        @click="toggle"
    >
        <span
            class="relative block h-6 w-11 rounded-full border transition-colors duration-200"
            :class="[
                checked
                    ? 'border-primary-600 bg-primary-600 dark:border-primary-500 dark:bg-primary-500'
                    : 'border-neutral-300 bg-neutral-200 dark:border-neutral-600 dark:bg-neutral-700',
                locked ? 'opacity-60' : '',
            ]"
            aria-hidden="true"
        >
            <!-- Offsets count from inside the 1 px border (22 x 42 px): 1 px each side keeps it centred -->
            <span
                class="absolute left-px top-px h-5 w-5 rounded-full bg-white shadow-sm transition-transform duration-200 ease-in-out"
                :class="checked ? 'translate-x-5' : 'translate-x-0'"
            />
        </span>
    </button>
</template>
