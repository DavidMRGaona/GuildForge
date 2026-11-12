<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import MenuBadge from './MenuBadge.vue';
import NavDropdown from './NavDropdown.vue';
import { useMenuBadges } from '@/composables/useMenuBadges';
import type { MenuItem, Navigation } from '@/types/navigation';
import { isActiveHref } from '@/utils/navigation';

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
const { badges } = useMenuBadges();

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
        key: null,
    },
    {
        id: 'events',
        label: t('common.events'),
        href: '/eventos',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
        key: null,
    },
    {
        id: 'calendar',
        label: t('common.calendar'),
        href: '/calendario',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
        key: null,
    },
    {
        id: 'articles',
        label: t('common.articles'),
        href: '/articulos',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
        key: null,
    },
    {
        id: 'gallery',
        label: t('common.gallery'),
        href: '/galeria',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
        key: null,
    },
    {
        id: 'about',
        label: t('common.about'),
        href: '/nosotros',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
        key: null,
    },
];

const navigation = computed(() => page.props.navigation as Navigation | undefined);
const headerItems = computed(() => {
    const items = navigation.value?.header;
    return items && items.length > 0 ? items : fallbackItems;
});

function isActive(href: string): boolean {
    return isActiveHref(href, page.url, window.location.origin);
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
                    'rounded-md px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-page',
                    mobile ? 'block w-full' : 'whitespace-nowrap',
                    isActive(item.href)
                        ? 'bg-primary-100 text-primary dark:bg-primary-900/30'
                        : 'text-base-secondary hover:bg-muted hover:text-base-primary',
                ]"
                @click="handleNavigate"
            >
                {{ item.label }}
                <MenuBadge v-if="item.key && badges[item.key]" :label="badges[item.key] ?? ''" />
            </Link>
        </template>
    </nav>
</template>
