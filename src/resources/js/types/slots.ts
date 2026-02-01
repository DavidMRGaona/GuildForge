import type { ProfileTabIcon } from './profile';

export type SlotPosition =
    | 'before-header'
    | 'after-header'
    | 'before-content'
    | 'after-content'
    | 'before-footer'
    | 'after-footer'
    // Page-specific slots
    | 'event-detail-actions'
    | 'game-table-registration'
    | 'campaign-detail-actions'
    | 'profile-sections';

export interface ProfileTabMeta {
    tabId?: string;
    icon: ProfileTabIcon;
    labelKey: string;
    badgeKey?: string;
    parentId?: string;
}

export interface SlotRegistration {
    slot: string;
    component: string;
    module: string;
    order: number;
    props: Record<string, unknown>;
    dataKeys: string[];
    profileTab?: ProfileTabMeta;
}

export type ModuleSlots = Record<string, SlotRegistration[]>;
