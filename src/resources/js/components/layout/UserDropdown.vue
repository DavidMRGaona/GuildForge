<script setup lang="ts">
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ChevronDown, User, UserCog, UserRound, Settings, LogOut, CircleHelp } from '@lucide/vue';
import { useAuth } from '@/composables/useAuth';
import { useRoutes } from '@/composables/useRoutes';
import { useOnNavigate } from '@/composables/useOnNavigate';
import { buildAvatarUrl } from '@/utils/cloudinary';
import { initialsOf } from '@/utils/initials';

const emit = defineEmits<{
    (e: 'navigate'): void;
}>();

const { t } = useI18n();
const { user, can } = useAuth();
const routes = useRoutes();

const isOpen = ref(false);
const userName = computed(() => user.value?.displayName || user.value?.name);
// Where the server says the account's own public profile is (core#745): never built here
const publicProfilePath = computed(() => user.value?.publicProfilePath ?? null);
// A 28 px circle: twice the pixels for sharp screens; without a photo, the initials of the shown name
const avatarUrl = computed(() => buildAvatarUrl(user.value?.avatarPublicId, 56));
// A photo that fails to load (deleted from the store) shows the initials, never a broken image
const avatarFailed = ref(false);
watch(avatarUrl, () => {
    avatarFailed.value = false;
});
const initials = computed(() => initialsOf(userName.value));
const dropdownRef = ref<HTMLElement | null>(null);

function toggleDropdown(): void {
    isOpen.value = !isOpen.value;
}

function closeDropdown(): void {
    isOpen.value = false;
}

useOnNavigate(closeDropdown);

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
            class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-base-primary hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            :aria-expanded="isOpen"
            @click="toggleDropdown"
        >
            <span class="sr-only">{{ t('auth.userMenu.openMenu') }}</span>
            <!-- The name is right beside it: nothing to add for a screen reader -->
            <span
                class="flex size-7 shrink-0 items-center justify-center overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700"
                aria-hidden="true"
                data-testid="user-menu-avatar"
            >
                <img
                    v-if="avatarUrl && !avatarFailed"
                    :src="avatarUrl"
                    alt=""
                    class="size-full object-cover"
                    @error="avatarFailed = true"
                />
                <span v-else-if="initials" class="text-xs font-semibold text-base-secondary">
                    {{ initials }}
                </span>
                <UserRound v-else class="size-4 text-neutral-500 dark:text-neutral-400" />
            </span>
            <span class="max-w-40 truncate" :title="userName">{{ userName }}</span>
            <ChevronDown
                class="h-4 w-4 transition-transform"
                :class="{ 'rotate-180': isOpen }"
                aria-hidden="true"
            />
        </button>

        <!-- Dropdown menu -->
        <div
            v-if="isOpen"
            class="absolute right-0 mt-2 w-56 rounded-lg border border-default bg-surface shadow-lg z-50"
        >
            <div class="py-1" role="menu">
                <!-- Own public profile: only for an account that has one -->
                <Link
                    v-if="publicProfilePath"
                    :href="publicProfilePath"
                    class="flex w-full items-center px-4 py-2 text-sm text-base-primary hover:bg-muted"
                    role="menuitem"
                    @click="closeDropdown"
                >
                    <User class="mr-3 h-4 w-4 shrink-0" aria-hidden="true" />
                    {{ t('auth.userMenu.profile') }}
                </Link>

                <!-- Account settings: /perfil, whose address stays the same -->
                <Link
                    :href="routes.profile"
                    class="flex w-full items-center px-4 py-2 text-sm text-base-primary hover:bg-muted"
                    role="menuitem"
                    @click="closeDropdown"
                >
                    <UserCog class="mr-3 h-4 w-4 shrink-0" aria-hidden="true" />
                    {{ t('auth.userMenu.accountSettings') }}
                </Link>

                <!-- Help centre -->
                <Link
                    :href="routes.knowledge.index"
                    class="flex w-full items-center px-4 py-2 text-sm text-base-primary hover:bg-muted"
                    role="menuitem"
                    @click="closeDropdown"
                >
                    <CircleHelp class="mr-3 h-4 w-4" aria-hidden="true" />
                    {{ t('auth.userMenu.help') }}
                </Link>

                <!-- Admin panel link: same rule as the server, admin.access from any role -->
                <a
                    v-if="can('admin.access')"
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
