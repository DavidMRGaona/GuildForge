import { ref, computed, watch, type Ref, type ComputedRef } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useModuleSlots } from './useModuleSlots';
import type { ProfileTab, ProfileTabIcon } from '@/types/profile';
import type { ProfileTabMeta, SlotRegistration } from '@/types/slots';

interface UseProfileTabsReturn {
    tabs: ComputedRef<ProfileTab[]>;
    activeTabId: Ref<string>;
    activeTab: ComputedRef<ProfileTab | undefined>;
    setActiveTab: (id: string) => void;
    isActiveTab: (id: string) => boolean;
}

const DEFAULT_TAB = 'account';

function tabFromUrl(url: string): string | null {
    return new URL(url, 'http://localhost').searchParams.get('tab');
}

function urlWithTab(url: string, id: string): string {
    const target = new URL(url, 'http://localhost');
    if (id === DEFAULT_TAB) {
        target.searchParams.delete('tab');
    } else {
        target.searchParams.set('tab', id);
    }
    return `${target.pathname}${target.search}${target.hash}`;
}

// A module hides its tab per request by sharing `false` under `visibleKey`
function isTabVisible(meta: ProfileTabMeta, props: Record<string, unknown>): boolean {
    return meta.visibleKey === undefined || props[meta.visibleKey] !== false;
}

export function useProfileTabs(): UseProfileTabsReturn {
    const { t } = useI18n();
    const { getSlotComponents, moduleSlots } = useModuleSlots();

    const page = usePage();

    // The active tab lives in the URL (`/perfil?tab=…`), so going back to the profile or a link
    // from a module page opens the same tab. The registrations arrive with the page (the tab
    // components may still be loading): a tab no module registers falls back to the account tab
    // Unresolved components are not in getSlotComponents yet, so read the registrations and
    // look the visibility prop up the same way the slot does: static props, then page props
    function registeredTabId(registration: SlotRegistration): string {
        return registration.profileTab?.tabId ?? registration.module;
    }

    function isRegistrationVisible(registration: SlotRegistration): boolean {
        if (!registration.profileTab) {
            return false;
        }
        const props: Record<string, unknown> = { ...registration.props };
        for (const key of registration.dataKeys) {
            if (key in page.props) {
                props[key] = page.props[key];
            }
        }
        return isTabVisible(registration.profileTab, props);
    }

    const profileRegistrations = computed(() =>
        (moduleSlots.value['profile-sections'] ?? []).filter(
            (registration) => registration.profileTab
        )
    );
    const visibleTabIds = profileRegistrations.value
        .filter(isRegistrationVisible)
        .map(registeredTabId);
    const requestedTab = tabFromUrl(page.url);
    const activeTabId = ref(
        requestedTab !== null && visibleTabIds.includes(requestedTab) ? requestedTab : DEFAULT_TAB
    );

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
                if (!isTabVisible(meta, slot.props)) {
                    continue;
                }
                const badge = meta.badgeKey
                    ? (slot.props[meta.badgeKey] as number | undefined)
                    : undefined;

                // Use explicit tabId if provided, otherwise fall back to module name
                const tabId = meta.tabId ?? slot.registration.module;

                const tab: ProfileTab = {
                    id: tabId,
                    label: t(meta.labelKey),
                    icon: meta.icon,
                    isModuleTab: true,
                };

                if (badge !== undefined) {
                    tab.badge = badge;
                }

                // Add parentId if specified for visual hierarchy
                if (meta.parentId) {
                    tab.parentId = meta.parentId;
                }

                baseTabs.push(tab);
            }
        }

        return baseTabs;
    });

    const activeTab = computed(() => tabs.value.find((tab) => tab.id === activeTabId.value));

    // A visit or partial reload can hide the tab that is open (e.g. the last pending item was
    // handled): go back to the account tab, only for registered tabs, never for ones still loading
    watch(
        () =>
            profileRegistrations.value
                .filter((registration) => !isRegistrationVisible(registration))
                .map(registeredTabId),
        (hiddenIds) => {
            if (hiddenIds.includes(activeTabId.value)) {
                setActiveTab(DEFAULT_TAB);
            }
        }
    );

    function setActiveTab(id: string): void {
        activeTabId.value = id;
        router.replace({
            url: urlWithTab(page.url, id),
            preserveScroll: true,
            preserveState: true,
        });
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
