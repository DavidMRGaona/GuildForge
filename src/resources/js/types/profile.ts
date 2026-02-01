export interface ProfileTab {
    id: string;
    label: string;
    icon: ProfileTabIcon;
    badge?: number;
    isModuleTab?: boolean;
    parentId?: string;
}

export type ProfileTabIcon = 'user' | 'lock' | 'dice' | 'trophy' | 'calendar' | 'cog' | 'pencil-square';

export interface ProfileTabMetadata {
    icon: ProfileTabIcon;
    label: string;
    order: number;
}
