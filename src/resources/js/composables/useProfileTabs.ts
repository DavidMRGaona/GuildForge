import { ref, computed, type Ref, type ComputedRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { useModuleSlots } from './useModuleSlots';
import type { ProfileTab, ProfileTabIcon } from '@/types/profile';

interface UseProfileTabsReturn {
    tabs: ComputedRef<ProfileTab[]>;
    activeTabId: Ref<string>;
    activeTab: ComputedRef<ProfileTab | undefined>;
    setActiveTab: (id: string) => void;
    isActiveTab: (id: string) => boolean;
}

export function useProfileTabs(): UseProfileTabsReturn {
    const { t } = useI18n();
    const { getSlotComponents } = useModuleSlots();

    const activeTabId = ref('account');

    const tabs = computed<ProfileTab[]>(() => {
        const baseTabs: ProfileTab[] = [
            {
                id: 'account',
                label: t('auth.profile.tabs.account'),
                icon: 'user' as ProfileTabIcon,
            },
        ];

        // Add module tabs from profile-sections slot
        const moduleSlots = getSlotComponents('profile-sections');
        for (const slot of moduleSlots) {
            if (slot.registration.profileTab) {
                const meta = slot.registration.profileTab;
                const badge = meta.badgeKey
                    ? (slot.props[meta.badgeKey] as number | undefined)
                    : undefined;

                const tab: ProfileTab = {
                    id: slot.registration.module,
                    label: t(meta.labelKey),
                    icon: meta.icon,
                    isModuleTab: true,
                };

                if (badge !== undefined) {
                    tab.badge = badge;
                }

                baseTabs.push(tab);
            }
        }

        return baseTabs;
    });

    const activeTab = computed(() => tabs.value.find((tab) => tab.id === activeTabId.value));

    function setActiveTab(id: string): void {
        activeTabId.value = id;
    }

    function isActiveTab(id: string): boolean {
        return activeTabId.value === id;
    }

    return {
        tabs,
        activeTabId,
        activeTab,
        setActiveTab,
        isActiveTab,
    };
}
