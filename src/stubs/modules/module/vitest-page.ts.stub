import { reactive } from 'vue';

/**
 * The Inertia page the mocked `usePage()` returns. Tests set the shared props they need
 * (`auth.user`, …) and `setup.ts` empties them before each test.
 */
export const page = reactive<{ props: Record<string, unknown>; url: string; component: string }>({
    props: {},
    url: '/',
    component: 'Test',
});

/** Signs a user in on the page; `mustVerifyEmail` closes the actions behind the verification gate. */
export function signIn(user: { mustVerifyEmail?: boolean } = {}): void {
    page.props = {
        auth: {
            user: {
                id: 'user-1',
                name: 'John Doe',
                email: 'john.doe@example.com',
                emailVerified: user.mustVerifyEmail !== true,
                mustVerifyEmail: user.mustVerifyEmail === true,
                roles: ['member'],
            },
        },
    };
}
