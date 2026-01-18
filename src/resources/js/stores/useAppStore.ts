import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useAppStore = defineStore('app', () => {
    const locale = ref<'es' | 'en'>('es');
    const isSidebarOpen = ref(false);
    const isLoading = ref(false);

    const currentLocale = computed(() => locale.value);

    function setLocale(newLocale: 'es' | 'en'): void {
        locale.value = newLocale;
    }

    function toggleSidebar(): void {
        isSidebarOpen.value = !isSidebarOpen.value;
    }

    function setLoading(loading: boolean): void {
        isLoading.value = loading;
    }

    return {
        locale,
        isSidebarOpen,
        isLoading,
        currentLocale,
        setLocale,
        toggleSidebar,
        setLoading,
    };
});