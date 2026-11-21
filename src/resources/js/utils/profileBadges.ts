import type { ProfileBadgeColour } from '@/types/profileSections';

/**
 * The classes of each badge colour (core#776), the same list as `App\Domain\Profiles\ProfileBadgeColour`
 * (a test keeps both equal). A strong shade with white text in the light scheme and a light one with
 * dark text in the dark scheme: every pair has a contrast of at least 4.5:1, so the label and the
 * icon pass WCAG AA as text, on the page and on a profile's banner alike. Written out in full so
 * Tailwind finds every class.
 */
export const profileBadgeColourClasses: Record<ProfileBadgeColour, string> = {
    slate: 'bg-slate-700 text-white dark:bg-slate-300 dark:text-neutral-950',
    red: 'bg-red-700 text-white dark:bg-red-300 dark:text-neutral-950',
    orange: 'bg-orange-700 text-white dark:bg-orange-300 dark:text-neutral-950',
    amber: 'bg-amber-700 text-white dark:bg-amber-300 dark:text-neutral-950',
    green: 'bg-green-700 text-white dark:bg-green-300 dark:text-neutral-950',
    teal: 'bg-teal-700 text-white dark:bg-teal-300 dark:text-neutral-950',
    sky: 'bg-sky-700 text-white dark:bg-sky-300 dark:text-neutral-950',
    blue: 'bg-blue-700 text-white dark:bg-blue-300 dark:text-neutral-950',
    violet: 'bg-violet-700 text-white dark:bg-violet-300 dark:text-neutral-950',
    pink: 'bg-pink-700 text-white dark:bg-pink-300 dark:text-neutral-950',
};

/** The classes of a colour; an unknown one (a newer server) is drawn in slate */
export function profileBadgeColourClass(colour: string): string {
    return Object.hasOwn(profileBadgeColourClasses, colour)
        ? profileBadgeColourClasses[colour as ProfileBadgeColour]
        : profileBadgeColourClasses.slate;
}
