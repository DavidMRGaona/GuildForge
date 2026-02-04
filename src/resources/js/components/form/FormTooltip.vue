<script setup lang="ts">
import { ref, computed } from 'vue';

const props = withDefaults(
    defineProps<{
        content: string;
        position?: 'top' | 'bottom' | 'left' | 'right';
        maxWidth?: 'xs' | 'sm' | 'md' | 'lg';
    }>(),
    {
        position: 'top',
        maxWidth: 'md',
    }
);

const isVisible = ref(false);

const widthClass = computed(() => {
    const widths = {
        xs: 'w-64', // 256px
        sm: 'w-80', // 320px
        md: 'w-96', // 384px
        lg: 'w-[28rem]', // 448px
    };
    return widths[props.maxWidth];
});

function show(): void {
    isVisible.value = true;
}

function hide(): void {
    isVisible.value = false;
}
</script>

<template>
    <div
        class="relative inline-flex"
        @mouseenter="show"
        @mouseleave="hide"
        @focus="show"
        @blur="hide"
    >
        <!-- Trigger element (slot) -->
        <slot />

        <!-- Tooltip -->
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="isVisible && content"
                role="tooltip"
                class="absolute z-30 rounded-lg bg-neutral-900 dark:bg-neutral-100 px-3 py-2 text-sm text-white dark:text-neutral-900 shadow-lg pointer-events-none whitespace-normal"
                :class="[
                    widthClass,
                    {
                        'bottom-full left-1/2 -translate-x-1/2 mb-2': position === 'top',
                        'top-full left-1/2 -translate-x-1/2 mt-2': position === 'bottom',
                        'right-full top-1/2 -translate-y-1/2 mr-2': position === 'left',
                        'left-full top-1/2 -translate-y-1/2 ml-2': position === 'right',
                    },
                ]"
            >
                {{ content }}

                <!-- Arrow -->
                <div
                    class="absolute h-2 w-2 rotate-45 bg-neutral-900 dark:bg-neutral-100"
                    :class="{
                        'top-full left-1/2 -translate-x-1/2 -mt-1': position === 'top',
                        'bottom-full left-1/2 -translate-x-1/2 -mb-1': position === 'bottom',
                        'left-full top-1/2 -translate-y-1/2 -ml-1': position === 'left',
                        'right-full top-1/2 -translate-y-1/2 -mr-1': position === 'right',
                    }"
                />
            </div>
        </transition>
    </div>
</template>
