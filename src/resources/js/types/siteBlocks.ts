/**
 * A site block resolved on the server for the person viewing the page (core#696): the footer and
 * the popups send their blocks like this, already filtered to what can be drawn.
 */
export interface ResolvedSiteBlock {
    /** `core.<block>` or `<module>.<block>` */
    key: string;
    /** The module that draws it; null for a core block */
    module: string | null;
    /** Relative to the owner's `resources/js`, e.g. `components/SubscribeBlock.vue` */
    component: string;
    props: Record<string, unknown>;
}

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
