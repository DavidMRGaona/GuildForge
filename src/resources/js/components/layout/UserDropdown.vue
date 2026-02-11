<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ChevronDown, User, Settings, LogOut } from 'lucide-vue-next';
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
            <ChevronDown
                class="h-4 w-4 transition-transform"
                :class="{ 'rotate-180': isOpen }"
                aria-hidden="true"
            />
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
                    <User class="mr-3 h-4 w-4" aria-hidden="true" />
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
                    <Settings class="mr-3 h-4 w-4" aria-hidden="true" />
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
                    <LogOut class="mr-3 h-4 w-4" aria-hidden="true" />
                    {{ t('auth.userMenu.logout') }}
                </button>
            </div>
        </div>
    </div>
</template>
