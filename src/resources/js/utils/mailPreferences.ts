import type { MailDelivery, MailPreference, MailPreferenceSection } from '@/types/mailPreferences';

/** Whether the category is sent by mail (every category but those shown only on the site). */
export function isMailed(category: MailPreference): boolean {
    return category.mail !== false;
}

/** Whether the category also appears in the notification centre (core#491). */
export function hasSiteNotices(category: MailPreference): boolean {
    return category.site === true;
}

/** The categories of a section the person can turn on and off (the mandatory ones are always sent). */
export function optionalCategories(
    section: MailPreferenceSection
): MailPreferenceSection['categories'] {
    return section.categories.filter((category) => !category.mandatory);
}

/**
 * How many optional categories of a section are on, following the current choices (which may
 * differ from the saved ones until the person saves). A category is on when the person gets it
 * by mail (at once or in the daily digest) or sees it in the notification centre (core#491).
 */
export function countOptionalOn(
    section: MailPreferenceSection,
    choices: Record<string, MailDelivery>,
    siteChoices: Record<string, boolean> = {}
): number {
    return optionalCategories(section).filter((category) => {
        const byMail =
            isMailed(category) && (choices[category.key] ?? category.delivery) !== 'none';
        const onSite =
            hasSiteNotices(category) &&
            (siteChoices[category.key] ?? category.siteEnabled === true);

        return byMail || onSite;
    }).length;
}
