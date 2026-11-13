<script setup lang="ts">
import { ref, computed, useTemplateRef, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Search, Sun, Moon, Monitor, Menu, X } from '@lucide/vue';
import { useAppStore } from '@/stores/useAppStore';
import type { ThemeMode } from '@/stores/useAppStore';
import { useAuth } from '@/composables/useAuth';
import { useRoutes } from '@/composables/useRoutes';
import { useOnNavigate } from '@/composables/useOnNavigate';
import { useFitsWithoutOverflow } from '@/composables/useFitsWithoutOverflow';
import { useSiteLogo } from '@/composables/useSiteLogo';
import SearchPanel from '@/components/search/SearchPanel.vue';
import TheNavigation from './TheNavigation.vue';
import UserDropdown from './UserDropdown.vue';
import NotificationBell from './NotificationBell.vue';
import AuthLinks from './AuthLinks.vue';

const { t } = useI18n();
const { isAuthenticated, mustVerifyEmail } = useAuth();
const routes = useRoutes();
const page = usePage();
const appStore = useAppStore();

const isMobileMenuOpen = ref(false);
const isThemeMenuOpen = ref(false);
const isSearchOpen = ref(false);
const searchButton = useTemplateRef<HTMLButtonElement>('searchButton');
const menuButton = useTemplateRef<HTMLButtonElement>('menuButton');
let searchTrigger: HTMLElement | null = null;

// How wide the full bar is depends on the site's links and the user's name, so no breakpoint
// fits every site: below md, or whenever the bar overflows, the header uses the menu button.
// The logo is observed too: it has no width until the image loads, and each theme has its own
const headerRow = useTemplateRef<HTMLElement>('headerRow');
const brand = useTemplateRef<HTMLElement>('brand');
const fitsFullBar = useFitsWithoutOverflow(
    headerRow,
    () => [page.props.navigation, page.props.auth, appStore.isThemeToggleVisible],
    [brand]
);

useOnNavigate(() => {
    isMobileMenuOpen.value = false;
    isThemeMenuOpen.value = false;
});

// Read when the panel closes (focus return) and forgotten afterwards, so a later «/» does not
// send focus to a button that did not open it
watch(isSearchOpen, (isOpen) => {
    if (!isOpen) {
        searchTrigger = null;
    }
});

const appName = computed(() => page.props.appName as string);

// The bell (core#491): signed in, and not while the account must verify its email (its routes are
// closed until then)
const showBell = computed(
    () =>
        isAuthenticated.value &&
        !mustVerifyEmail.value &&
        (page.props as { siteNotifications?: unknown }).siteNotifications != null
);
const { logo: currentLogo, markFailed: markLogoFailed } = useSiteLogo();

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

function openSearch(event: MouseEvent): void {
    searchTrigger = event.currentTarget instanceof HTMLElement ? event.currentTarget : null;
    isSearchOpen.value = true;
}

/**
 * The button that opened the panel; after «/», whatever was focused before, or the visible
 * magnifier (menu button when the bar is collapsed) when that was the page itself.
 */
function searchReturnFocus(opener: HTMLElement | null): HTMLElement | null {
    if (searchTrigger?.isConnected) {
        return searchTrigger;
    }
    if (opener?.isConnected) {
        return opener;
    }
    const fullBarShown = fitsFullBar.value && window.matchMedia('(min-width: 768px)').matches;
    return (fullBarShown ? searchButton.value : menuButton.value) ?? null;
}

function selectTheme(mode: ThemeMode): void {
    appStore.setThemeMode(mode);
    closeThemeMenu();
}
</script>

<template>
    <header
        class="relative z-50 bg-surface shadow-sm dark:border-b dark:border-white/10 dark:shadow-depth-strong"
    >
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div ref="headerRow" class="flex h-16 items-center justify-between">
                <!-- Logo -->
                <div ref="brand" class="shrink-0">
                    <Link :href="routes.home" class="flex items-center">
                        <img
                            v-if="currentLogo"
                            :src="currentLogo"
                            :alt="appName"
                            class="h-8"
                            @error="markLogoFailed"
                        />
                        <span v-else class="text-xl font-bold text-primary">
                            {{ appName }}
                        </span>
                    </Link>
                </div>

                <!-- Desktop Navigation -->
                <div
                    :class="fitsFullBar ? 'hidden md:flex md:items-center md:space-x-4' : 'hidden'"
                >
                    <TheNavigation />

                    <!-- Search button: opens the search panel -->
                    <button
                        ref="searchButton"
                        type="button"
                        :aria-label="t('search.title')"
                        aria-haspopup="dialog"
                        :aria-expanded="isSearchOpen"
                        class="p-2 rounded-md text-base-secondary hover:bg-muted hover:text-base-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        @click="openSearch"
                    >
                        <Search class="h-5 w-5" aria-hidden="true" />
                    </button>

                    <!-- Theme selector dropdown -->
                    <div v-if="appStore.isThemeToggleVisible" class="relative">
                        <button
                            type="button"
                            :aria-label="t('layout.selectTheme')"
                            :aria-expanded="isThemeMenuOpen"
                            class="p-2 rounded-md text-base-secondary hover:bg-muted hover:text-base-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            @click="toggleThemeMenu"
                            @blur="closeThemeMenu"
                        >
                            <Monitor
                                v-if="appStore.themeMode === 'system'"
                                class="h-5 w-5"
                                aria-hidden="true"
                            />
                            <Sun
                                v-else-if="appStore.themeMode === 'light'"
                                class="h-5 w-5"
                                aria-hidden="true"
                            />
                            <Moon v-else class="h-5 w-5" aria-hidden="true" />
                        </button>

                        <!-- Dropdown menu -->
                        <div
                            v-if="isThemeMenuOpen"
                            class="absolute right-0 mt-2 w-36 rounded-lg border border-default bg-surface shadow-lg z-50"
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

                    <NotificationBell v-if="showBell" />

                    <!-- Auth: User dropdown or login/register links -->
                    <UserDropdown v-if="isAuthenticated" />
                    <AuthLinks v-else />
                </div>

                <!-- Mobile menu button, with the bell beside it so it never hides in the menu -->
                <div class="flex items-center gap-1" :class="{ 'md:hidden': fitsFullBar }">
                    <NotificationBell
                        v-if="showBell"
                        panel-id="site-notifications-panel-narrow"
                        beside-menu-button
                    />
                    <button
                        ref="menuButton"
                        type="button"
                        aria-controls="mobile-menu"
                        class="inline-flex items-center justify-center rounded-md p-2 text-base-secondary hover:bg-muted hover:text-base-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
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
        <div
            v-if="isMobileMenuOpen"
            id="mobile-menu"
            class="border-t border-default"
            :class="{ 'md:hidden': fitsFullBar }"
        >
            <div class="space-y-1 px-4 py-3">
                <TheNavigation mobile @navigate="closeMobileMenu" />

                <!-- Search: opens the search panel -->
                <button
                    type="button"
                    aria-haspopup="dialog"
                    :aria-expanded="isSearchOpen"
                    class="flex w-full items-center px-3 py-2 text-base font-medium text-base-secondary hover:bg-muted hover:text-base-primary rounded-md"
                    @click="openSearch"
                >
                    <Search class="mr-3 h-5 w-5" aria-hidden="true" />
                    {{ t('search.title') }}
                </button>

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

        <SearchPanel v-model:open="isSearchOpen" :return-focus="searchReturnFocus" />
    </header>
</template>
