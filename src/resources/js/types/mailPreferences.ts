/** One mail category as the «Correos» tab of the profile receives it (texts already translated). */
export interface MailPreference {
    /** Stable `<module>.<name>` key, the one sent back when saving. */
    key: string;
    label: string;
    description: string;
    /** A mandatory category is always sent: it is shown locked, with its reason. */
    mandatory: boolean;
    mandatoryReason: string | null;
    enabled: boolean;
}

/** A group of categories under one heading («Cuenta», «Partidas»…). */
export interface MailPreferenceSection {
    key: string;
    label: string;
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

/** Prop of the profile page, present only when the «Correos» tab is open. */
export interface MailPreferencesProps {
    sections: MailPreferenceSection[];
    pause: MailPause;
}
