/**
 * Where a profile section is drawn (core#742), the same values as `App\Domain\Profiles\ProfileRegion`:
 * the wide column or the narrow one beside it.
 */
export type ProfileRegion = 'main' | 'aside';

/**
 * The colours a profile badge may have (core#776), the same values as
 * `App\Domain\Profiles\ProfileBadgeColour`: a closed palette that reads well in both schemes.
 */
export type ProfileBadgeColour =
    'slate' | 'red' | 'orange' | 'amber' | 'green' | 'teal' | 'sky' | 'blue' | 'violet' | 'pink';

/**
 * How large `ProfileBadges.vue` draws badges: `sm` as icons only, next to an author's name (a post
 * header); `md` with their label, on the profile and its card.
 */
export type ProfileBadgeSize = 'sm' | 'md';

/**
 * A featured badge of a public profile (core#776), given by a module and checked on the server:
 * `ProfileBadgeDTO`. Only badges of profiles the person viewing may see ever reach the browser.
 */
export interface ProfileBadge {
    /** `<module>.<badge>`, unique among the badges of one account */
    key: string;
    /** The badge's name, already translated: its accessible name too */
    label: string;
    /** What it was given for, already translated; may be empty */
    description: string;
    /** One of `ProfileBadgeIcons::NAMES` (`profileBadgeIconMap`), drawn when there is no image */
    icon: string;
    colour: ProfileBadgeColour;
    /** An `https` image, drawn cropped to a square instead of the icon */
    imageUrl: string | null;
    /** An absolute `https` address or a path of the site; null when it links nowhere */
    url: string | null;
}

/**
 * The top of a public profile. Never the account name or the email: `publicName` is the only name.
 */
export interface ProfileHeader {
    /** The address, as in `/miembros/<handle>` */
    handle: string;
    /** Null only for the owner looking at their own profile before choosing a public name */
    publicName: string | null;
    /** Cloudinary public id, or null for the initials */
    avatarPublicId: string | null;
    /** Cloudinary public id, or null for the accent gradient */
    bannerPublicId: string | null;
    /** When the account was created, ISO 8601 with the display zone's offset */
    memberSince: string | null;
    /** The «Socio» badge: the owner chose to show it and holds the association role */
    showsMembership: boolean;
    /** Whether the person viewing is the owner */
    isOwner: boolean;
    /** At most three, in the owner's order, drawn under the name */
    badges: ProfileBadge[];
}

/** A counter counted for the person viewing, already filtered to what can be drawn */
export interface ResolvedProfileStat {
    /** `core.<stat>` or `<module>.<stat>` */
    key: string;
    label: string;
    value: number;
    /** An absolute `https` address or a path of the site; null when it links nowhere */
    url: string | null;
}

/** A profile section resolved on the server for the person viewing */
export interface ResolvedProfileSection {
    /** `core.<section>` or `<module>.<section>` */
    key: string;
    /** The module that draws it; null for a core section */
    module: string | null;
    /** Relative to the owner's `resources/js`, e.g. `components/LatestPostsSection.vue` */
    component: string;
    region: ProfileRegion;
    /** The section's title, already translated; `ProfileSection.vue` draws it */
    label: string;
    props: Record<string, unknown>;
}

/** A public profile drawn for one person: what `ProfileRendererInterface::render()` returns */
export interface ResolvedProfile {
    header: ProfileHeader;
    /** In the owner's order; a summary card shows the first four */
    stats: ResolvedProfileStat[];
    /** Each region's sections in the owner's order */
    sections: Record<ProfileRegion, ResolvedProfileSection[]>;
}

/** One piece of a line of the «Sobre mí» text: plain text, or a link when `href` is set */
export interface ProfileTextSegment {
    text: string;
    href: string | null;
}

/**
 * The summary card of a public profile (core#744): what `GET /miembros/{handle}/tarjeta` answers for
 * whoever may see the profile (`ProfileCardServiceInterface::card()` on the server). Never the
 * account id, the account name, the email or the roles.
 */
export interface ProfileCard {
    /** The current address, as in `/miembros/<handle>` (an old address answers the current card) */
    handle: string;
    /** Null only on the owner's own card, before they choose a public name */
    publicName: string | null;
    /** The profile page, absolute */
    url: string;
    /** `https` image, 128 × 128 cropped around the face; null for the initials */
    avatarUrl: string | null;
    /** `https` image, 600 × 200; null when there is none */
    bannerUrl: string | null;
    /** When the account was created, ISO 8601 with the display zone's offset */
    memberSince: string | null;
    /** The «Socio» badge: the owner chose to show it and holds the association role */
    showsMembership: boolean;
    /** Whether the person asking is the owner */
    isOwner: boolean;
    /** At most four, in the owner's order */
    stats: ResolvedProfileStat[];
    /** The featured badges, at most three, in the owner's order */
    badges: ProfileBadge[];
}
