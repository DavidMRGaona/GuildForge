/** How a category is received (core#333): `digest` only where the category is digestible. */
export type MailDelivery = 'immediate' | 'digest' | 'none';

/** One mail category as the «Avisos» tab of the profile receives it (texts already translated). */
export interface MailPreference {
    /** Stable `<module>.<name>` key, the one sent back when saving. */
    key: string;
    label: string;
    description: string;
    /** A mandatory category is always sent: it is shown locked, with its reason. */
    mandatory: boolean;
    mandatoryReason: string | null;
    /** False only when the person receives none of it. */
    enabled: boolean;
    /** Offers «al momento», «resumen diario» and «no recibir» instead of a switch. */
    digestible: boolean;
    delivery: MailDelivery;
    /** Sent by mail at all; false for a category shown only in the notification centre (core#491). Missing = true. */
    mail?: boolean;
    /** Also shown in the notification centre, with its own switch (core#491). Missing = false. */
    site?: boolean;
    /** Whether the person sees its notices there; always true for a mandatory one. */
    siteEnabled?: boolean;
}

/** A group of categories under one heading («Cuenta», «Partidas»…). */
export interface MailPreferenceSection {
    key: string;
    label: string;
    /** Lucide icon name (kebab case) a module declares for its section; null falls back to a mail icon. */
    icon: string | null;
    categories: MailPreference[];
}

/** State of the pause of every optional mail. */
export interface MailPause {
    /** Whether optional mail is held back right now (a pause that is over is not active). */
    active: boolean;
    /** Instant it ends, ISO 8601 in the display zone; null while paused = until the person resumes. */
    until: string | null;
    /** Last day it covers (`YYYY-MM-DD`, display zone), for «hasta el …»; null like `until`. */
    lastDay: string | null;
}

/** One thing (a game, a campaign, a tournament...) muted from a mail footer. */
export interface MutedMailSubject {
    /** `<module>.<kind>`, sent back with `id` to unmute it. */
    type: string;
    id: string;
    /** Name stored when it was muted: it stays even once the thing is gone. */
    label: string;
    /** Kind of thing («Campaña»); null when its module no longer translates it. */
    typeLabel: string | null;
    /** Instant it was muted, ISO 8601 in the display zone. */
    mutedAt: string | null;
}

/** Prop of the profile page, present only when the «Avisos» tab is open. */
export interface MailPreferencesProps {
    sections: MailPreferenceSection[];
    pause: MailPause;
    mutedSubjects: MutedMailSubject[];
    /** When the daily digest goes out, `HH:MM` in the display zone. */
    digestTime: string;
}
