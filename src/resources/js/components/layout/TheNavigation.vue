<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

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

interface NavItem {
    key: string;
    href: string;
}

const navItems: NavItem[] = [
    { key: 'common.home', href: '/' },
    { key: 'common.events', href: '/eventos' },
    { key: 'common.calendar', href: '/calendario' },
    { key: 'common.articles', href: '/articulos' },
    { key: 'common.gallery', href: '/galeria' },
    { key: 'common.about', href: '/nosotros' },
];

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
        <Link
            v-for="item in navItems"
            :key="item.href"
            :href="item.href"
            :aria-current="isActive(item.href) ? 'page' : undefined"
            :class="[
                'rounded-md px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2',
                mobile ? 'block w-full' : '',
                isActive(item.href)
                    ? 'bg-amber-100 text-amber-700'
                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
            ]"
            @click="handleNavigate"
        >
            {{ t(item.key) }}
        </Link>
    </nav>
</template>
