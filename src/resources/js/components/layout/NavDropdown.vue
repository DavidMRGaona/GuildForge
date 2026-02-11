<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import type { MenuItem } from '@/types/navigation';

interface Props {
    item: MenuItem;
    mobile?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    mobile: false,
});

const emit = defineEmits<{
    navigate: [];
}>();

const page = usePage();
const isOpen = ref(false);

const currentUrl = computed(() => page.url);

function isActive(href: string): boolean {
    if (href === '/') {
        return currentUrl.value === '/';
    }
    return currentUrl.value.startsWith(href);
}

function isParentActive(): boolean {
    if (isActive(props.item.href)) {
        return true;
    }
    return props.item.children.some((child) => isActive(child.href));
}

function toggleDropdown(): void {
    isOpen.value = !isOpen.value;
}

function handleNavigate(): void {
    isOpen.value = false;
    emit('navigate');
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        isOpen.value = false;
    }
}
</script>

<template>
    <div class="relative" :class="mobile ? 'w-full' : ''" @keydown="handleKeydown">
        <!-- Dropdown trigger button -->
        <button
            type="button"
            :class="[
                'flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-page',
                mobile ? 'w-full justify-between' : '',
                isParentActive()
                    ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                    : 'text-base-secondary hover:bg-muted hover:text-base-primary',
            ]"
            :aria-expanded="isOpen"
            aria-haspopup="true"
            @click="toggleDropdown"
        >
            <span>{{ item.label }}</span>
            <ChevronDown
                class="h-4 w-4 transition-transform"
                :class="{ 'rotate-180': isOpen }"
            />
        </button>

        <!-- Dropdown menu (desktop) -->
        <Transition
            v-if="!mobile"
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-show="isOpen"
                class="absolute left-0 z-50 mt-2 w-48 origin-top-left rounded-md bg-surface shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none border-default"
            >
                <div class="py-1">
                    <Link
                        v-for="child in item.children"
                        :key="child.id"
                        :href="child.href"
                        :target="child.target"
                        :class="[
                            'block px-4 py-2 text-sm transition-colors',
                            isActive(child.href)
                                ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                                : 'text-base-primary hover:bg-muted',
                        ]"
                        @click="handleNavigate"
                    >
                        {{ child.label }}
                    </Link>
                </div>
            </div>
        </Transition>

        <!-- Dropdown menu (mobile) -->
        <Transition
            v-else
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-show="isOpen" class="mt-1 space-y-1 pl-4">
                <Link
                    v-for="child in item.children"
                    :key="child.id"
                    :href="child.href"
                    :target="child.target"
                    :class="[
                        'block rounded-md px-3 py-2 text-sm font-medium transition-colors',
                        isActive(child.href)
                            ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                            : 'text-base-secondary hover:bg-muted hover:text-base-primary',
                    ]"
                    @click="handleNavigate"
                >
                    {{ child.label }}
                </Link>
            </div>
        </Transition>
    </div>

    <!-- Click outside to close (desktop only) -->
    <Teleport v-if="isOpen && !mobile" to="body">
        <div class="fixed inset-0 z-40" @click="isOpen = false" />
    </Teleport>
</template>
