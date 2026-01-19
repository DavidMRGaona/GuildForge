export interface EventDateBadge {
    day: string;
    month: string;
}

const MONTH_ABBREVIATIONS_ES = 'ENE|FEB|MAR|ABR|MAY|JUN|JUL|AGO|SEP|OCT|NOV|DIC';
const MONTH_ABBREVIATIONS_EN = 'JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC';

const MONTH_ABBREVIATIONS: Record<string, string> = {
    es: MONTH_ABBREVIATIONS_ES,
    en: MONTH_ABBREVIATIONS_EN,
};

export function useEventDateBadge(locale = 'es'): {
    getDateBadge: (dateString: string) => EventDateBadge;
} {
    const abbreviations = MONTH_ABBREVIATIONS[locale] ?? MONTH_ABBREVIATIONS_ES;
    const months = abbreviations.split('|');

    function getDateBadge(dateString: string): EventDateBadge {
        const date = new Date(dateString);
        const day = date.getDate().toString();
        const monthIndex = date.getMonth();
        // months array is always 12 elements, monthIndex is always 0-11
        const month = months[monthIndex] as string;

        return { day, month };
    }

    return { getDateBadge };
}
