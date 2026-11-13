/**
 * Shared prop `impersonation` (core#580): whom an administrator is viewing the site as. Null
 * outside an impersonation. It never carries data about the administrator.
 */
export type ImpersonationMode = 'read_only' | 'simulate';

export interface ImpersonationSharedProps {
    targetName: string;
    mode: ImpersonationMode;
}
