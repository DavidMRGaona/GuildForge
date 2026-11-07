/** Personal calendar subscription as the profile page receives it (never the address itself). */
export interface CalendarFeedStatus {
    /** Whether any module can tell what people attend; without one there is nothing to subscribe to. */
    available: boolean;
    /** When the current address was created (ISO 8601), or null when there is none. */
    issuedAt: string | null;
}

/** Answer to creating an address: the only time the address is ever sent. */
export interface CalendarFeedAddresses {
    feedUrl: string;
    webcalUrl: string;
    issuedAt: string | null;
}
