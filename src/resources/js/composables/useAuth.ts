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

    const isAdmin = computed<boolean>(() => user.value?.role === 'admin');

    const isEditor = computed<boolean>(() => user.value?.role === 'editor');

    const canManageContent = computed<boolean>(() => isAdmin.value || isEditor.value);

    const authSettings = computed<AuthSettings>(() => ({
        registrationEnabled: props.authSettings?.registrationEnabled ?? true,
        loginEnabled: props.authSettings?.loginEnabled ?? true,
        emailVerificationRequired: props.authSettings?.emailVerificationRequired ?? false,
    }));

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
        logout,
    };
}
