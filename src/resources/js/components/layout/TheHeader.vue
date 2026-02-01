<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
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
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            />
                        </svg>
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
                            <!-- System icon -->
                            <svg
                                v-if="appStore.themeMode === 'system'"
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                />
                            </svg>
                            <!-- Sun icon -->
                            <svg
                                v-else-if="appStore.themeMode === 'light'"
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
                                />
                            </svg>
                            <!-- Moon icon -->
                            <svg
                                v-else
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
                                />
                            </svg>
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
                                    <svg
                                        class="mr-3 h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                        />
                                    </svg>
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
                                    <svg
                                        class="mr-3 h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
                                        />
                                    </svg>
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
                                    <svg
                                        class="mr-3 h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
                                        />
                                    </svg>
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
                        <!-- Hamburger icon -->
                        <svg
                            v-if="!isMobileMenuOpen"
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"
                            />
                        </svg>
                        <!-- Close icon -->
                        <svg
                            v-else
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div
            v-if="isMobileMenuOpen"
            class="border-t border-default md:hidden"
        >
            <div class="space-y-1 px-4 py-3">
                <TheNavigation mobile @navigate="closeMobileMenu" />

                <!-- Search link -->
                <Link
                    :href="routes.search"
                    class="flex items-center px-3 py-2 text-base font-medium text-base-secondary hover:bg-muted hover:text-base-primary rounded-md"
                    @click="closeMobileMenu"
                >
                    <svg
                        class="mr-3 h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                    </svg>
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
                            <svg
                                class="mr-2 h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                />
                            </svg>
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
                            <svg
                                class="mr-2 h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
                                />
                            </svg>
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
                            <svg
                                class="mr-2 h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
                                />
                            </svg>
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
