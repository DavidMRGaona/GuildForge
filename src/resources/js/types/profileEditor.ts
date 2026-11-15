import type { ProfileRegion } from '@/types/profileSections';

/** Who may see a public profile, the values of `App\Domain\Profiles\ProfileVisibility` */
export type ProfileVisibility = 'public' | 'members' | 'association' | 'hidden';

/** A section or stat the owner turns on or off and reorders (core#743) */
export interface ProfileEditorLayoutItem {
    /** `core.<x>` or `<module>.<x>` */
    key: string;
    label: string;
    /** One line saying what it shows */
    description: string;
    enabled: boolean;
    /** Sections only: where it is drawn */
    region?: ProfileRegion | null;
    /** Sections only: the module that draws it; null for the core */
    module?: string | null;
    /** Sections only: relative to the owner's `resources/js` */
    component?: string | null;
}

export interface ProfileEditorLimits {
    /** Characters of «Sobre mí» */
    bio: number;
    handleMin: number;
    handleMax: number;
    /** Days between two choices of the address */
    handleChangeDays: number;
    bannerKilobytes: number;
    /** File extensions */
    bannerTypes: string[];
}

export interface ProfileEditorUrls {
    /** `PUT`: saves everything */
    update: string;
    /** `GET ?direccion=`: whether an address can be chosen */
    availability: string;
    /** `POST` uploads a banner, `DELETE` takes it away */
    banner: string;
}

/** What the owner's editor starts from; only the owner ever gets it */
export interface ProfileEditor {
    handle: string;
    visibility: ProfileVisibility;
    bio: string;
    bannerPublicId: string | null;
    showMembership: boolean;
    /** Whether the owner is an association member: the badge is only offered then */
    canShowMembership: boolean;
    /** When the address may be chosen again (ISO 8601, display zone); null when it may now */
    nextHandleChangeAt: string | null;
    sections: ProfileEditorLayoutItem[];
    stats: ProfileEditorLayoutItem[];
    limits: ProfileEditorLimits;
    urls: ProfileEditorUrls;
}

/** One entry of the layout as it is saved */
export interface ProfileLayoutChoice {
    key: string;
    enabled: boolean;
}

/** What `PUT /miembros/{handle}` takes, field by field */
export interface ProfileEditorForm {
    handle: string;
    visibility: ProfileVisibility;
    bio: string;
    show_membership: boolean;
    sections: ProfileLayoutChoice[];
    stats: ProfileLayoutChoice[];
}

/** What the editor says about the address being typed */
export type HandleAvailabilityStatus =
    'idle' | 'checking' | 'available' | 'current' | 'unavailable' | 'malformed' | 'failed';
