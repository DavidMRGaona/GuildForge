import type { SeoProps } from '@/types/seo';
import type { ResolvedProfile } from '@/types/profileSections';

/** What only the owner is told about their own profile (core#741) */
export interface PublicProfileNotices {
    choosePublicName: boolean;
    onlyOwner: boolean;
}

/** The public profile page, `/miembros/{handle}` (core#741) */
export interface PublicProfilePageProps {
    /** Already decided for the person viewing: the server only sends a profile they may see */
    profile: ResolvedProfile;
    notices: PublicProfileNotices;
    /** Where the owner edits what the profile shows; null for everybody else */
    editUrl: string | null;
    seo: SeoProps;
}
