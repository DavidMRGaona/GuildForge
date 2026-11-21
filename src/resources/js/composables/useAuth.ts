import { usePage, router } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';
import type { AuthSettings, User } from '@/types/models';
import { forgetThisDevice } from '@/utils/webPush';

/** How long signing out waits for this browser's push subscription to be forgotten. */
const LOGOUT_PUSH_TIMEOUT_MS = 2000;

/** The core's sign-out route. */
const LOGOUT_PATH = '/cerrar-sesion';

/**
 * Options of `logout()` (core#804).
 */
export interface LogoutOptions {
    /**
     * Where to send the sign-out request instead of `/cerrar-sesion`: a route of this site that
     * signs out and then leads somewhere else (a module's own). Anything on another site, or not
     * an address at all, is ignored and the core's route is used.
     */
    url?: string;
}

/**
 * The path of a sign-out route of this site, or the core's for anything else: another origin,
 * another scheme, `//host` or `/\host` (both resolve to another origin), and a path that
 * normalises to `//host` (`/.//host`).
 */
function signOutPath(url: string | undefined, origin: string): string {
    if (url === undefined || url === '') {
        return LOGOUT_PATH;
    }

    try {
        const resolved = new URL(url, origin);
        // `/.//host` resolves to this origin with the path `//host`, which a request reads as
        // another site: a path must start with a single slash
        return resolved.origin === origin && !resolved.pathname.startsWith('//')
            ? `${resolved.pathname}${resolved.search}`
            : LOGOUT_PATH;
    } catch {
        return LOGOUT_PATH;
    }
}

function forgetThisDeviceWithin(userId: string, milliseconds: number): Promise<void> {
    return Promise.race([
        forgetThisDevice(userId),
        new Promise<void>((resolve) => setTimeout(resolve, milliseconds)),
    ]);
}

interface AuthComposable {
    user: ComputedRef<User | null>;
    isAuthenticated: ComputedRef<boolean>;
    isEmailVerified: ComputedRef<boolean>;
    /**
     * True while the signed-in account must verify its email: the server will reject what it
     * sends to routes that need a signed-in user. False for guests and on older cores.
     */
    mustVerifyEmail: ComputedRef<boolean>;
    isAdmin: ComputedRef<boolean>;
    isEditor: ComputedRef<boolean>;
    canManageContent: ComputedRef<boolean>;
    authSettings: ComputedRef<AuthSettings>;
    /**
     * Check if the current user has a specific permission.
     * @param permission The permission key (e.g., 'events.create')
     */
    can: (permission: string) => boolean;
    /**
     * Check if the current user has any of the specified permissions.
     * @param permissions Array of permission keys
     */
    canAny: (permissions: string[]) => boolean;
    /**
     * Check if the current user has all of the specified permissions.
     * @param permissions Array of permission keys
     */
    canAll: (permissions: string[]) => boolean;
    /**
     * Check if the current user has a specific role.
     * @param role The role name (e.g., 'admin', 'editor')
     */
    hasRole: (role: string) => boolean;
    /**
     * Check if the current user has any of the specified roles.
     * @param roles Array of role names
     */
    hasAnyRole: (roles: string[]) => boolean;
    /**
     * Signs out. This browser stops getting the account's push notifications first (at most two
     * seconds), then the sign-out request goes to `options.url` (a route of this site) or to
     * `/cerrar-sesion`.
     */
    logout: (options?: LogoutOptions) => void;
}

interface AuthProps {
    auth?: { user?: User | null };
    authSettings?: AuthSettings;
}

export function useAuth(): AuthComposable {
    const page = usePage();
    // Read page.props inside each computed: the object is replaced on every visit, so a copy
    // taken here would go stale in components that survive a visit (preserveState)
    const props = (): AuthProps => page.props as AuthProps;

    const user = computed<User | null>(() => props().auth?.user ?? null);

    const isAuthenticated = computed<boolean>(() => user.value !== null);

    const isEmailVerified = computed<boolean>(() => user.value?.emailVerified ?? false);

    const mustVerifyEmail = computed<boolean>(() => user.value?.mustVerifyEmail === true);

    const isAdmin = computed<boolean>(() => (user.value?.roles ?? []).includes('admin'));

    const isEditor = computed<boolean>(() => (user.value?.roles ?? []).includes('editor'));

    const canManageContent = computed<boolean>(() => isAdmin.value || isEditor.value);

    const authSettings = computed<AuthSettings>(() => ({
        registrationEnabled: props().authSettings?.registrationEnabled ?? true,
        loginEnabled: props().authSettings?.loginEnabled ?? true,
        emailVerificationRequired: props().authSettings?.emailVerificationRequired ?? false,
    }));

    /**
     * Check if the current user has a specific permission.
     * Admin users always have all permissions.
     */
    const can = (permission: string): boolean => {
        if (!user.value) return false;
        if (isAdmin.value) return true;
        const permissions = user.value.permissions ?? [];
        return permissions.includes(permission);
    };

    /**
     * Check if the current user has any of the specified permissions.
     */
    const canAny = (permissions: string[]): boolean => {
        if (!user.value) return false;
        if (isAdmin.value) return true;
        return permissions.some((p) => can(p));
    };

    /**
     * Check if the current user has all of the specified permissions.
     */
    const canAll = (permissions: string[]): boolean => {
        if (!user.value) return false;
        if (isAdmin.value) return true;
        return permissions.every((p) => can(p));
    };

    /**
     * Check if the current user has a specific role.
     */
    const hasRole = (role: string): boolean => {
        if (!user.value) return false;
        const roles = user.value.roles ?? [];
        return roles.includes(role);
    };

    /**
     * Check if the current user has any of the specified roles.
     */
    const hasAnyRole = (roles: string[]): boolean => {
        if (!user.value) return false;
        const userRoles = user.value.roles ?? [];
        return roles.some((r) => userRoles.includes(r));
    };

    const logout = (options?: LogoutOptions): void => {
        // This browser stops getting the account's push notifications first (core#803); the
        // server forgets it on sign-out too, so a slow or failed request never holds this up
        const userId = user.value?.id ?? null;
        const forget =
            page.props.webPush && userId !== null
                ? forgetThisDeviceWithin(userId, LOGOUT_PUSH_TIMEOUT_MS)
                : Promise.resolve();
        // `@click="logout"` hands in the click event: only a plain string `url` counts
        const url = typeof options?.url === 'string' ? options.url : undefined;
        const path = signOutPath(url, window.location.origin);
        void forget.then(() => router.post(path));
    };

    return {
        user,
        isAuthenticated,
        isEmailVerified,
        mustVerifyEmail,
        isAdmin,
        isEditor,
        canManageContent,
        authSettings,
        can,
        canAny,
        canAll,
        hasRole,
        hasAnyRole,
        logout,
    };
}
