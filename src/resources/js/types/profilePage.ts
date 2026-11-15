import type { SeoProps } from '@/types/seo';

/** The header of a public profile (core#741): never the account name or email. */
export interface PublicProfileHeader {
    handle: string;
    url: string;
    /** Null only for the owner, who is asked to choose one */
    publicName: string | null;
    avatarPublicId: string | null;
    bannerPublicId: string | null;
    membershipBadge: boolean;
    /** The month the account was created in the display zone, `YYYY-MM` */
    memberSince: string | null;
}

export interface PublicProfileNotices {
    choosePublicName: boolean;
    onlyOwner: boolean;
}

export interface PublicProfilePageProps {
    profile: PublicProfileHeader;
    isOwner: boolean;
    notices: PublicProfileNotices;
    /** Where the owner edits what the profile shows; null for everybody else */
    editUrl: string | null;
    seo: SeoProps;
}
