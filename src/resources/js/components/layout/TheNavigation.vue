<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import NavDropdown from './NavDropdown.vue';
import type { MenuItem, Navigation } from '@/types/navigation';

interface Props {
    mobile?: boolean;
}

withDefaults(defineProps<Props>(), {
    mobile: false,
});

const emit = defineEmits<{
    navigate: [];
}>();

const { t } = useI18n();
const page = usePage();

// Fallback navigation items if none configured
const fallbackItems: MenuItem[] = [
    {
        id: 'home',
        label: t('common.home'),
        href: '/',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
    },
    {
        id: 'events',
        label: t('common.events'),
        href: '/eventos',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
    },
    {
        id: 'calendar',
        label: t('common.calendar'),
        href: '/calendario',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
    },
    {
        id: 'articles',
        label: t('common.articles'),
        href: '/articulos',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
    },
    {
        id: 'gallery',
        label: t('common.gallery'),
        href: '/galeria',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
    },
    {
        id: 'about',
        label: t('common.about'),
        href: '/nosotros',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
    },
];

const navigation = computed(() => page.props.navigation as Navigation | undefined);
const headerItems = computed(() => {
    const items = navigation.value?.header;
    return items && items.length > 0 ? items : fallbackItems;
});

const currentUrl = computed(() => page.url);

function isActive(href: string): boolean {
    if (href === '/') {
        return currentUrl.value === '/';
    }
    return currentUrl.value.startsWith(href);
}

function handleNavigate(): void {
    emit('navigate');
}
</script>

<template>
    <nav :class="mobile ? 'flex flex-col space-y-1' : 'flex items-center space-x-1'">
        <template v-for="item in headerItems" :key="item.id">
            <!-- Item with children (dropdown) -->
            <NavDropdown
                v-if="item.children.length > 0"
                :item="item"
                :mobile="mobile"
                @navigate="handleNavigate"
            />

            <!-- Regular link item -->
            <Link
                v-else
                :href="item.href"
                :target="item.target"
                :aria-current="isActive(item.href) ? 'page' : undefined"
                :class="[
                    'rounded-md px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-stone-900',
                    mobile ? 'block w-full' : '',
                    isActive(item.href)
                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                        : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900 dark:text-stone-300 dark:hover:bg-stone-800 dark:hover:text-stone-100',
                ]"
                @click="handleNavigate"
            >
                {{ item.label }}
            </Link>
        </template>
    </nav>
</template>
