import type { MailPreferenceSection } from '@/types/mailPreferences';

/** The categories of a section the person can turn on and off (the mandatory ones are always sent). */
export function optionalCategories(
    section: MailPreferenceSection
): MailPreferenceSection['categories'] {
    return section.categories.filter((category) => !category.mandatory);
}

/**
 * How many optional categories of a section are on, following the current choices (which may
 * differ from the saved ones until the person saves).
 */
export function countOptionalOn(
    section: MailPreferenceSection,
    choices: Record<string, boolean>
): number {
    return optionalCategories(section).filter(
        (category) => choices[category.key] ?? category.enabled
    ).length;
}
