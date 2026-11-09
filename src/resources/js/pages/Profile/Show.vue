<script setup lang="ts">
import { computed } from 'vue';
import ProfileHeader from '@/components/profile/ProfileHeader.vue';
import ProfileSidebar from '@/components/profile/ProfileSidebar.vue';
import ProfileTabBar from '@/components/profile/ProfileTabBar.vue';
import ProfileAccountTab from '@/components/profile/ProfileAccountTab.vue';
import ProfileCalendarTab from '@/components/profile/ProfileCalendarTab.vue';
import ProfileMailTab from '@/components/profile/ProfileMailTab.vue';
import { CALENDAR_TAB, MAIL_TAB, useProfileTabs } from '@/composables/useProfileTabs';
import { useModuleSlots } from '@/composables/useModuleSlots';
import { useFlashMessages } from '@/composables/useFlashMessages';
import type { User } from '@/types/models';
import type { CalendarFeedStatus } from '@/types/calendarFeed';
import type { MailPreferencesProps } from '@/types/mailPreferences';

interface Props {
    user: User;
    calendarFeed?: CalendarFeedStatus;
    /** Only sent while the «Correos» tab is open (`?tab=mail`). */
    mailPreferences?: MailPreferencesProps | null;
}

withDefaults(defineProps<Props>(), {
    calendarFeed: () => ({ available: false, issuedAt: null }),
    mailPreferences: null,
});

const { tabs, activeTabId, setActiveTab } = useProfileTabs();
const { getSlotComponents, hasSlotComponents } = useModuleSlots();
const { success: successMessage, error: errorMessage } = useFlashMessages();

const moduleComponents = computed(() => getSlotComponents('profile-sections'));
const hasModuleTabs = computed(() => hasSlotComponents('profile-sections'));
</script>

<template>
    <!-- Hero header -->
    <ProfileHeader :user="user" />

    <!-- Flash messages -->
    <div v-if="successMessage || errorMessage" class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <div v-if="successMessage" class="rounded-md bg-success-light p-4">
            <p class="text-sm text-success">{{ successMessage }}</p>
        </div>
        <div v-if="errorMessage" class="rounded-md bg-error-light p-4">
            <p class="text-sm text-error">{{ errorMessage }}</p>
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
            <div class="min-w-0">
                <!-- Account tab -->
                <ProfileAccountTab v-if="activeTabId === 'account'" :user="user" />

                <!-- Personal calendar subscription -->
                <ProfileCalendarTab
                    v-if="activeTabId === CALENDAR_TAB && calendarFeed.available"
                    :calendar-feed="calendarFeed"
                />

                <!-- Which optional mail the person receives -->
                <template v-if="activeTabId === MAIL_TAB">
                    <ProfileMailTab
                        v-if="mailPreferences"
                        :sections="mailPreferences.sections"
                        :pause="mailPreferences.pause"
                    />
                    <div v-else class="flex items-center justify-center py-12">
                        <div
                            class="h-8 w-8 animate-spin rounded-full border-4 border-primary-500 border-t-transparent"
                        />
                    </div>
                </template>

                <!-- Module tabs -->
                <template v-if="hasModuleTabs">
                    <template v-for="item in moduleComponents" :key="item.key">
                        <Suspense
                            v-if="
                                activeTabId ===
                                (item.registration.profileTab?.tabId ?? item.registration.module)
                            "
                        >
                            <component :is="item.component" v-bind="item.props" />
                            <template #fallback>
                                <div class="flex items-center justify-center py-12">
                                    <div
                                        class="h-8 w-8 animate-spin rounded-full border-4 border-primary-500 border-t-transparent"
                                    />
                                </div>
                            </template>
                        </Suspense>
                    </template>
                </template>
            </div>
        </div>
    </div>
</template>
