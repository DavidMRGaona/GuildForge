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
                    'rounded-md px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-page',
                    mobile ? 'block w-full' : '',
                    isActive(item.href)
                        ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                        : 'text-base-secondary hover:bg-muted hover:text-base-primary',
                ]"
                @click="handleNavigate"
            >
                {{ item.label }}
            </Link>
        </template>
    </nav>
</template>
