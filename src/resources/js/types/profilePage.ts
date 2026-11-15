import type { SeoProps } from '@/types/seo';
import type { ResolvedProfile } from '@/types/profileSections';
import type { ProfileEditor } from '@/types/profileEditor';

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
    /** Where the owner sets their public name (the account page); null for everybody else */
    accountUrl: string | null;
    /** What the owner's editor starts from (core#743); null for everybody else */
    editor: ProfileEditor | null;
    seo: SeoProps;
}
