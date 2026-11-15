<script setup lang="ts">
import { inject } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { FOOTER_BLOCK_TITLE_ID } from '@/components/siteBlocks/footerBlockTitle';
import type { MenuItem } from '@/types/navigation';
import { isInertiaPage } from '@/utils/inertiaLinks';

/**
 * Footer block `core.links`: the links of one footer menu, already filtered for the person viewing
 * (core#697). Submenus are drawn flat: a footer has no dropdowns.
 */
defineProps<{
    items: MenuItem[];
}>();

const { t } = useI18n();

// Named after the block's title when it has one
const titleId = inject(FOOTER_BLOCK_TITLE_ID, null);

const linkClasses =
    'text-sm text-neutral-300 transition-colors hover:text-primary dark:text-neutral-400 dark:hover:text-primary';

// Absolute addresses, the panel and files are plain links: only Inertia pages are visited client-side
function isExternal(item: MenuItem): boolean {
    return !isInertiaPage(item.href);
}
</script>

<template>
    <nav
        v-if="items.length > 0"
        :aria-labelledby="titleId ?? undefined"
        :aria-label="titleId ? undefined : t('layout.footerLinks')"
    >
        <ul class="space-y-2">
            <template v-for="item in items" :key="item.id">
                <li>
                    <a
                        v-if="isExternal(item)"
                        :href="item.href"
                        :target="item.target"
                        :rel="item.target === '_blank' ? 'noopener noreferrer' : undefined"
                        :class="linkClasses"
                    >
                        {{ item.label }}
                    </a>
                    <Link v-else :href="item.href" :target="item.target" :class="linkClasses">
                        {{ item.label }}
                    </Link>
                </li>
                <li v-for="child in item.children" :key="child.id" class="ps-3">
                    <a
                        v-if="isExternal(child)"
                        :href="child.href"
                        :target="child.target"
                        :rel="child.target === '_blank' ? 'noopener noreferrer' : undefined"
                        :class="linkClasses"
                    >
                        {{ child.label }}
                    </a>
                    <Link v-else :href="child.href" :target="child.target" :class="linkClasses">
                        {{ child.label }}
                    </Link>
                </li>
            </template>
        </ul>
    </nav>
</template>
