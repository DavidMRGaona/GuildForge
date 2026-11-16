/** Where the account stands with two-factor authentication (page prop `security.twoFactor`, core#769). */
export interface TwoFactorState {
    active: boolean;
    /** ISO instant it was turned on; null while off. */
    since: string | null;
    /** Recovery codes not used yet. */
    remainingCodes: number;
    /** False while something (a role that requires it) says it cannot be turned off. */
    canDisable: boolean;
}

export interface SecurityProps {
    twoFactor: TwoFactorState;
}

/** What starting a setup answers: shown once, never kept by the page after the dialog closes. */
export interface TwoFactorSetup {
    secret: string;
    /** The key in groups of four, as it is typed by hand. */
    groupedSecret: string;
    /** The QR code as an `<svg>` element (text). */
    qrCodeSvg: string;
}

export interface TwoFactorRecoveryCodesResponse {
    recoveryCodes: string[];
}

/** The four things the dialog does, each one a flow of its own. */
export type TwoFactorMode = 'enable' | 'changeApp' | 'recoveryCodes' | 'disable';
