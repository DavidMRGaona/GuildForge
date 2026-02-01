import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { ThemeSettings } from '@/types/inertia';

export type ThemeMode = 'light' | 'dark' | 'system';

export const useAppStore = defineStore('app', () => {
    const locale = ref<'es' | 'en'>('es');
    const isSidebarOpen = ref(false);
    const isLoading = ref(false);
    const themeMode = ref<ThemeMode>('system');
    const systemPrefersDark = ref(false);
    const themeSettings = ref<ThemeSettings | null>(null);

    // Track media query listener for cleanup
    let mediaQueryList: MediaQueryList | null = null;
    let mediaQueryHandler: ((e: MediaQueryListEvent) => void) | null = null;

    const currentLocale = computed(() => locale.value);

    const isDarkMode = computed(() => {
        if (themeMode.value === 'system') {
            return systemPrefersDark.value;
        }
        return themeMode.value === 'dark';
    });

    const isThemeToggleVisible = computed(() => {
        return themeSettings.value?.darkModeToggleVisible ?? true;
    });

    function setLocale(newLocale: 'es' | 'en'): void {
        locale.value = newLocale;
    }

    function toggleSidebar(): void {
        isSidebarOpen.value = !isSidebarOpen.value;
    }

    function setLoading(loading: boolean): void {
        isLoading.value = loading;
    }

    function setThemeSettings(settings?: ThemeSettings): void {
        if (settings) {
            themeSettings.value = settings;
        }
    }

    function initTheme(serverTheme?: ThemeSettings): void {
        // Store server theme settings
        if (serverTheme) {
            themeSettings.value = serverTheme;
        }

        const stored = window.localStorage.getItem('themeMode') as ThemeMode | null;

        if (stored !== null && ['light', 'dark', 'system'].includes(stored)) {
            themeMode.value = stored;
        } else if (serverTheme?.darkModeDefault) {
            // Use server default if no localStorage preference
            themeMode.value = 'dark';
        } else {
            themeMode.value = 'system';
        }

        // Clean up existing listener if initTheme is called again
        if (mediaQueryList && mediaQueryHandler) {
            mediaQueryList.removeEventListener('change', mediaQueryHandler);
        }

        mediaQueryList = window.matchMedia('(prefers-color-scheme: dark)');
        systemPrefersDark.value = mediaQueryList.matches;

        mediaQueryHandler = (e: MediaQueryListEvent): void => {
            systemPrefersDark.value = e.matches;
            applyTheme();
        };

        mediaQueryList.addEventListener('change', mediaQueryHandler);

        applyTheme();
    }

    function setThemeMode(mode: ThemeMode): void {
        themeMode.value = mode;
        window.localStorage.setItem('themeMode', mode);
        applyTheme();
    }

    function cycleThemeMode(): void {
        const modes: ThemeMode[] = ['system', 'light', 'dark'];
        const currentIndex = modes.indexOf(themeMode.value);
        const nextIndex = (currentIndex + 1) % modes.length;
        const nextMode = modes[nextIndex];
        if (nextMode !== undefined) {
            setThemeMode(nextMode);
        }
    }

    function applyTheme(): void {
        document.documentElement.classList.toggle('dark', isDarkMode.value);
    }

    return {
        locale,
        isSidebarOpen,
        isLoading,
        themeMode,
        isDarkMode,
        themeSettings,
        isThemeToggleVisible,
        currentLocale,
        setLocale,
        toggleSidebar,
        setLoading,
        setThemeSettings,
        initTheme,
        setThemeMode,
        cycleThemeMode,
    };
});
