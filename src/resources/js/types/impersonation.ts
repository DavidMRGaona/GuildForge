/**
 * Shared prop `impersonation` (core#580): whom an administrator is viewing the site as. Null
 * outside an impersonation. It never carries data about the administrator.
 */
export type ImpersonationMode = 'read_only' | 'simulate';

export interface ImpersonationSharedProps {
    targetName: string;
    mode: ImpersonationMode;
    /** Message for the administrator from the previous request (core#614), shown by the band */
    notice: string | null;
    /** Display names of the roles in a role view (core#644); null when an account is viewed */
    viewedRoles: string[] | null;
}
