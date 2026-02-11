<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Search, Sun, Moon, Monitor, Menu, X } from 'lucide-vue-next';
import { useAppStore } from '@/stores/useAppStore';
import type { ThemeMode } from '@/stores/useAppStore';
import { useAuth } from '@/composables/useAuth';
import { useRoutes } from '@/composables/useRoutes';
import TheNavigation from './TheNavigation.vue';
import UserDropdown from './UserDropdown.vue';
import AuthLinks from './AuthLinks.vue';

const { t } = useI18n();
const { isAuthenticated } = useAuth();
const routes = useRoutes();
const page = usePage();
const appStore = useAppStore();

const isMobileMenuOpen = ref(false);
const isThemeMenuOpen = ref(false);

const appName = computed(() => page.props.appName as string);
const siteLogoLight = computed(() => page.props.siteLogoLight as string);
const siteLogoDark = computed(() => page.props.siteLogoDark as string);

const currentLogo = computed(() => {
    return appStore.isDarkMode ? siteLogoDark.value : siteLogoLight.value;
});

function toggleMobileMenu(): void {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
}

function closeMobileMenu(): void {
    isMobileMenuOpen.value = false;
}

function toggleThemeMenu(): void {
    isThemeMenuOpen.value = !isThemeMenuOpen.value;
}

function closeThemeMenu(): void {
    isThemeMenuOpen.value = false;
}

function selectTheme(mode: ThemeMode): void {
    appStore.setThemeMode(mode);
    closeThemeMenu();
}
</script>

<template>
    <header class="bg-surface shadow-sm dark:shadow-neutral-800/50">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <!-- Logo -->
                <div class="shrink-0">
                    <Link :href="routes.home" class="flex items-center">
                        <img v-if="currentLogo" :src="currentLogo" :alt="appName" class="h-8" />
                        <span v-else class="text-xl font-bold text-primary">
                            {{ t('layout.brand') }}
                        </span>
                    </Link>
                </div>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex md:items-center md:space-x-4">
                    <TheNavigation />

                    <!-- Search button -->
                    <Link
                        :href="routes.search"
                        :aria-label="t('search.title')"
                        class="p-2 rounded-md text-base-secondary hover:bg-muted hover:text-base-primary focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                        <Search class="h-5 w-5" aria-hidden="true" />
                    </Link>

                    <!-- Theme selector dropdown -->
                    <div v-if="appStore.isThemeToggleVisible" class="relative">
                        <button
                            type="button"
                            :aria-label="t('layout.selectTheme')"
                            :aria-expanded="isThemeMenuOpen"
                            class="p-2 rounded-md text-base-secondary hover:bg-muted hover:text-base-primary focus:outline-none focus:ring-2 focus:ring-primary-500"
                            @click="toggleThemeMenu"
                            @blur="closeThemeMenu"
                        >
                            <Monitor v-if="appStore.themeMode === 'system'" class="h-5 w-5" aria-hidden="true" />
                            <Sun v-else-if="appStore.themeMode === 'light'" class="h-5 w-5" aria-hidden="true" />
                            <Moon v-else class="h-5 w-5" aria-hidden="true" />
                        </button>

                        <!-- Dropdown menu -->
                        <div
                            v-if="isThemeMenuOpen"
                            class="absolute right-0 mt-2 w-36 rounded-md bg-surface shadow-lg ring-1 ring-black ring-opacity-5 border-default z-50"
                            role="menu"
                            :aria-label="t('layout.selectTheme')"
                            @mousedown.prevent
                        >
                            <div class="py-1">
                                <button
                                    type="button"
                                    class="flex w-full items-center px-4 py-2 text-sm text-base-primary hover:bg-muted"
                                    :class="{
                                        'bg-muted': appStore.themeMode === 'system',
                                    }"
                                    role="menuitem"
                                    @click="selectTheme('system')"
                                >
                                    <Monitor class="mr-3 h-4 w-4" />
                                    {{ t('layout.systemTheme') }}
                                </button>
                                <button
                                    type="button"
                                    class="flex w-full items-center px-4 py-2 text-sm text-base-primary hover:bg-muted"
                                    :class="{
                                        'bg-muted': appStore.themeMode === 'light',
                                    }"
                                    role="menuitem"
                                    @click="selectTheme('light')"
                                >
                                    <Sun class="mr-3 h-4 w-4" />
                                    {{ t('layout.lightTheme') }}
                                </button>
                                <button
                                    type="button"
                                    class="flex w-full items-center px-4 py-2 text-sm text-base-primary hover:bg-muted"
                                    :class="{
                                        'bg-muted': appStore.themeMode === 'dark',
                                    }"
                                    role="menuitem"
                                    @click="selectTheme('dark')"
                                >
                                    <Moon class="mr-3 h-4 w-4" />
                                    {{ t('layout.darkTheme') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Auth: User dropdown or login/register links -->
                    <UserDropdown v-if="isAuthenticated" />
                    <AuthLinks v-else />
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md p-2 text-base-secondary hover:bg-muted hover:text-base-primary focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                        :aria-expanded="isMobileMenuOpen"
                        @click="toggleMobileMenu"
                    >
                        <span class="sr-only">{{
                            isMobileMenuOpen ? t('layout.closeMenu') : t('layout.openMenu')
                        }}</span>
                        <Menu v-if="!isMobileMenuOpen" class="h-6 w-6" aria-hidden="true" />
                        <X v-else class="h-6 w-6" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div v-if="isMobileMenuOpen" class="border-t border-default md:hidden">
            <div class="space-y-1 px-4 py-3">
                <TheNavigation mobile @navigate="closeMobileMenu" />

                <!-- Search link -->
                <Link
                    :href="routes.search"
                    class="flex items-center px-3 py-2 text-base font-medium text-base-secondary hover:bg-muted hover:text-base-primary rounded-md"
                    @click="closeMobileMenu"
                >
                    <Search class="mr-3 h-5 w-5" aria-hidden="true" />
                    {{ t('search.title') }}
                </Link>

                <!-- Theme selector -->
                <div v-if="appStore.isThemeToggleVisible" class="px-3 py-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-base-muted">
                        {{ t('layout.selectTheme') }}
                    </span>
                    <div class="mt-2 flex gap-2">
                        <button
                            type="button"
                            class="flex flex-1 items-center justify-center rounded-md px-3 py-2 text-sm font-medium transition-colors"
                            :class="
                                appStore.themeMode === 'system'
                                    ? 'bg-primary-light text-primary'
                                    : 'text-base-secondary hover:bg-muted'
                            "
                            @click="selectTheme('system')"
                        >
                            <Monitor class="mr-2 h-4 w-4" />
                            {{ t('layout.systemTheme') }}
                        </button>
                        <button
                            type="button"
                            class="flex flex-1 items-center justify-center rounded-md px-3 py-2 text-sm font-medium transition-colors"
                            :class="
                                appStore.themeMode === 'light'
                                    ? 'bg-primary-light text-primary'
                                    : 'text-base-secondary hover:bg-muted'
                            "
                            @click="selectTheme('light')"
                        >
                            <Sun class="mr-2 h-4 w-4" />
                            {{ t('layout.lightTheme') }}
                        </button>
                        <button
                            type="button"
                            class="flex flex-1 items-center justify-center rounded-md px-3 py-2 text-sm font-medium transition-colors"
                            :class="
                                appStore.themeMode === 'dark'
                                    ? 'bg-primary-light text-primary'
                                    : 'text-base-secondary hover:bg-muted'
                            "
                            @click="selectTheme('dark')"
                        >
                            <Moon class="mr-2 h-4 w-4" />
                            {{ t('layout.darkTheme') }}
                        </button>
                    </div>
                </div>

                <!-- Mobile Auth: User dropdown or login/register links -->
                <div class="border-t border-default pt-3 mt-3">
                    <UserDropdown v-if="isAuthenticated" @navigate="closeMobileMenu" />
                    <AuthLinks v-else mobile @navigate="closeMobileMenu" />
                </div>
            </div>
        </div>
    </header>
</template>
