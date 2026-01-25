import { usePage, router } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';
import type { AuthSettings, User } from '@/types/models';

interface AuthComposable {
    user: ComputedRef<User | null>;
    isAuthenticated: ComputedRef<boolean>;
    isEmailVerified: ComputedRef<boolean>;
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
    logout: () => void;
}

interface AuthProps {
    auth?: { user?: User | null };
    authSettings?: AuthSettings;
}

export function useAuth(): AuthComposable {
    const page = usePage();
    const props = page.props as AuthProps;

    const user = computed<User | null>(() => props.auth?.user ?? null);

    const isAuthenticated = computed<boolean>(() => user.value !== null);

    const isEmailVerified = computed<boolean>(() => user.value?.emailVerified ?? false);

    // Check admin via new roles array first, fallback to old role field
    const isAdmin = computed<boolean>(() => {
        const roles = user.value?.roles ?? [];
        if (roles.includes('admin')) return true;
        return user.value?.role === 'admin';
    });

    // Check editor via new roles array first, fallback to old role field
    const isEditor = computed<boolean>(() => {
        const roles = user.value?.roles ?? [];
        if (roles.includes('editor')) return true;
        return user.value?.role === 'editor';
    });

    const canManageContent = computed<boolean>(() => isAdmin.value || isEditor.value);

    const authSettings = computed<AuthSettings>(() => ({
        registrationEnabled: props.authSettings?.registrationEnabled ?? true,
        loginEnabled: props.authSettings?.loginEnabled ?? true,
        emailVerificationRequired: props.authSettings?.emailVerificationRequired ?? false,
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

    const logout = (): void => {
        router.post('/logout');
    };

    return {
        user,
        isAuthenticated,
        isEmailVerified,
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
