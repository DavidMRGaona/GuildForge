// Props of the core's own footer blocks (core#698). Private: not part of the public frontend API.
/** Props of the footer block `core.upcoming_events` (core#698): ids and display-zone instants only */
export interface FooterUpcomingEvent {
    id: string;
    title: string;
    /** Path of the event page */
    url: string;
    /** ISO 8601 instant with the display zone's offset */
    startDate: string;
    location: string | null;
}

/** One logo of the footer block `core.logos`; the alternative text is never empty */
export interface FooterLogo {
    src: string;
    alt: string;
    url: string | null;
    external: boolean;
}
