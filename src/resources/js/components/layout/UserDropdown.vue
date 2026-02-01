<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';
import { useRoutes } from '@/composables/useRoutes';

const emit = defineEmits<{
    (e: 'navigate'): void;
}>();

const { t } = useI18n();
const { user, isAdmin, isEditor } = useAuth();
const routes = useRoutes();

const isOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

function toggleDropdown(): void {
    isOpen.value = !isOpen.value;
}

function closeDropdown(): void {
    isOpen.value = false;
}

function handleClickOutside(event: MouseEvent): void {
    const target = event.target as HTMLElement | null;
    if (dropdownRef.value && target && !dropdownRef.value.contains(target)) {
        closeDropdown();
    }
}

function logout(): void {
    closeDropdown();
    emit('navigate');
    router.post(routes.auth.logout);
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <button
            type="button"
            class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-base-primary hover:bg-muted focus:outline-none focus:ring-2 focus:ring-primary-500"
            :aria-expanded="isOpen"
            @click="toggleDropdown"
        >
            <span class="sr-only">{{ t('auth.userMenu.openMenu') }}</span>
            <span>{{ user?.displayName || user?.name }}</span>
            <svg
                class="h-4 w-4 transition-transform"
                :class="{ 'rotate-180': isOpen }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 9l-7 7-7-7"
                />
            </svg>
        </button>

        <!-- Dropdown menu -->
        <div
            v-if="isOpen"
            class="absolute right-0 mt-2 w-48 rounded-md bg-surface shadow-lg ring-1 ring-black ring-opacity-5 border-default z-50"
        >
            <div class="py-1" role="menu">
                <!-- Profile link -->
                <Link
                    :href="routes.profile"
                    class="flex w-full items-center px-4 py-2 text-sm text-base-primary hover:bg-muted"
                    role="menuitem"
                    @click="closeDropdown"
                >
                    <svg
                        class="mr-3 h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                        />
                    </svg>
                    {{ t('auth.userMenu.profile') }}
                </Link>

                <!-- Admin panel link (only for admins/editors) -->
                <a
                    v-if="isAdmin || isEditor"
                    :href="routes.admin"
                    class="flex w-full items-center px-4 py-2 text-sm text-base-primary hover:bg-muted"
                    role="menuitem"
                    @click="closeDropdown"
                >
                    <svg
                        class="mr-3 h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                        />
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                        />
                    </svg>
                    {{ t('auth.userMenu.admin') }}
                </a>

                <div class="border-t border-default my-1"></div>

                <!-- Logout button -->
                <button
                    type="button"
                    class="flex w-full items-center px-4 py-2 text-sm text-base-primary hover:bg-muted"
                    role="menuitem"
                    @click="logout"
                >
                    <svg
                        class="mr-3 h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                        />
                    </svg>
                    {{ t('auth.userMenu.logout') }}
                </button>
            </div>
        </div>
    </div>
</template>
