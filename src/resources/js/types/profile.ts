export interface ProfileTab {
    id: string;
    label: string;
    icon: ProfileTabIcon;
    badge?: number;
    isModuleTab?: boolean;
}

export type ProfileTabIcon =
    | 'user'
    | 'lock'
    | 'dice'
    | 'trophy'
    | 'calendar'
    | 'cog';

export interface ProfileTabMetadata {
    icon: ProfileTabIcon;
    label: string;
    order: number;
}
