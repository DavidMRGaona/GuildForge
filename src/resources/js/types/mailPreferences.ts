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

/** Prop of the profile page, present only when the «Correos» tab is open. */
export interface MailPreferencesProps {
    sections: MailPreferenceSection[];
}
