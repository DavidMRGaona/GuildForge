<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import ProfileHeader from '@/components/profile/ProfileHeader.vue';
import ProfileSidebar from '@/components/profile/ProfileSidebar.vue';
import ProfileTabBar from '@/components/profile/ProfileTabBar.vue';
import ProfileAccountTab from '@/components/profile/ProfileAccountTab.vue';
import { useSeo } from '@/composables/useSeo';
import { useProfileTabs } from '@/composables/useProfileTabs';
import { useModuleSlots } from '@/composables/useModuleSlots';
import type { User } from '@/types/models';

interface Props {
    user: User;
}

interface FlashProps {
    flash?: { success?: string; error?: string };
}

defineProps<Props>();

const { t } = useI18n();
const page = usePage();
const pageProps = page.props as FlashProps;
const { tabs, activeTabId, setActiveTab } = useProfileTabs();
const { getSlotComponents, hasSlotComponents } = useModuleSlots();

useSeo({
    title: t('auth.profile.title'),
});

const successMessage = computed(() => pageProps.flash?.success);
const errorMessage = computed(() => pageProps.flash?.error);

const moduleComponents = computed(() => getSlotComponents('profile-sections'));
const hasModuleTabs = computed(() => hasSlotComponents('profile-sections'));
</script>

<template>
    <DefaultLayout>
        <!-- Hero header -->
        <ProfileHeader :user="user" />

        <!-- Flash messages -->
        <div
            v-if="successMessage || errorMessage"
            class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8"
        >
            <div v-if="successMessage" class="rounded-md bg-green-50 p-4 dark:bg-green-900/30">
                <p class="text-sm text-green-700 dark:text-green-400">{{ successMessage }}</p>
            </div>
            <div v-if="errorMessage" class="rounded-md bg-red-50 p-4 dark:bg-red-900/30">
                <p class="text-sm text-red-700 dark:text-red-400">{{ errorMessage }}</p>
            </div>
        </div>

        <!-- Mobile tab bar (visible on < lg) -->
        <div class="lg:hidden">
            <ProfileTabBar :tabs="tabs" :active-tab-id="activeTabId" @select-tab="setActiveTab" />
        </div>

        <!-- Main content area -->
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="lg:grid lg:grid-cols-[240px_1fr] lg:gap-8">
                <!-- Desktop sidebar (hidden on < lg) -->
                <aside class="hidden lg:block">
                    <ProfileSidebar
                        :tabs="tabs"
                        :active-tab-id="activeTabId"
                        @select-tab="setActiveTab"
                    />
                </aside>

                <!-- Tab content -->
                <main class="min-w-0">
                    <!-- Account tab -->
                    <ProfileAccountTab v-if="activeTabId === 'account'" :user="user" />

                    <!-- Module tabs -->
                    <template v-if="hasModuleTabs">
                        <template v-for="item in moduleComponents" :key="item.key">
                            <Suspense v-if="activeTabId === item.registration.module">
                                <component :is="item.component" v-bind="item.props" />
                                <template #fallback>
                                    <div class="flex items-center justify-center py-12">
                                        <div
                                            class="h-8 w-8 animate-spin rounded-full border-4 border-amber-500 border-t-transparent"
                                        />
                                    </div>
                                </template>
                            </Suspense>
                        </template>
                    </template>
                </main>
            </div>
        </div>
    </DefaultLayout>
</template>
