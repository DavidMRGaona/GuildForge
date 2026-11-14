import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { defineComponent, h, nextTick, reactive, ref } from 'vue';
import type { VNode } from 'vue';
import { createHead, renderDOMHead } from '@unhead/vue/client';
import { useHead } from '@unhead/vue';
import { resetSiteNotificationsState, useBellVisible } from '@/composables/useSiteNotifications';
import { unreadBadge, useUnreadTitle, withUnreadCount } from '../useUnreadTitle';

const page = reactive<{ url: string; props: Record<string, unknown> | undefined }>({
    url: '/',
    props: { siteNotifications: { unread: 3 } },
});

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): typeof page => page,
}));

const enabled = ref(true);

function mountWithTitle(): { wrapper: VueWrapper; head: ReturnType<typeof createHead> } {
    const head = createHead();
    const Page = defineComponent({
        setup() {
            useHead({ title: 'Eventos · Sitio de ejemplo' });
            useUnreadTitle(() => enabled.value);

            return (): VNode => h('div');
        },
    });

    return { wrapper: mount(Page, { global: { plugins: [head] } }), head };
}

async function titleAfterRender(head: ReturnType<typeof createHead>): Promise<string> {
    await nextTick();
    renderDOMHead(head, { document });

    return document.title;
}

describe('withUnreadCount', () => {
    it('puts the unread count before the title, capped like the bell', () => {
        expect(withUnreadCount('Eventos', 3)).toBe('(3) Eventos');
        expect(withUnreadCount('Eventos', 12)).toBe('(9+) Eventos');
        expect(withUnreadCount('', 2)).toBe('(2)');
        expect(withUnreadCount(undefined, 2)).toBe('(2)');
    });

    it('leaves the title alone with nothing unread', () => {
        expect(withUnreadCount('Eventos', 0)).toBe('Eventos');
        expect(withUnreadCount(undefined, 0)).toBe('');
    });

    it('caps the badge at 9+', () => {
        expect(unreadBadge(9)).toBe('9');
        expect(unreadBadge(10)).toBe('9+');
    });
});

describe('useUnreadTitle', () => {
    beforeEach(() => {
        resetSiteNotificationsState();
        enabled.value = true;
        page.props = { siteNotifications: { unread: 3 } };
        document.title = '';
    });

    afterEach(() => {
        resetSiteNotificationsState();
    });

    it('shows the unread count in the tab title and follows it', async () => {
        const { wrapper, head } = mountWithTitle();

        expect(await titleAfterRender(head)).toBe('(3) Eventos · Sitio de ejemplo');

        page.props = { siteNotifications: { unread: 15 } };
        expect(await titleAfterRender(head)).toBe('(9+) Eventos · Sitio de ejemplo');

        page.props = { siteNotifications: { unread: 0 } };
        expect(await titleAfterRender(head)).toBe('Eventos · Sitio de ejemplo');
        wrapper.unmount();
    });

    it('adds nothing where the bell is not shown', async () => {
        enabled.value = false;
        const { wrapper, head } = mountWithTitle();

        expect(await titleAfterRender(head)).toBe('Eventos · Sitio de ejemplo');

        enabled.value = true;
        expect(await titleAfterRender(head)).toBe('(3) Eventos · Sitio de ejemplo');
        wrapper.unmount();
    });
});

describe('useBellVisible', () => {
    const user = { id: 'u1', mustVerifyEmail: false };

    it('shows the bell to a signed-in account with the counter in its props', () => {
        page.props = { auth: { user }, siteNotifications: { unread: 0 } };

        expect(useBellVisible().value).toBe(true);
    });

    it('hides it from guests, from accounts that must verify their email and without the counter', () => {
        page.props = { auth: { user: null }, siteNotifications: null };
        expect(useBellVisible().value).toBe(false);

        page.props = {
            auth: { user: { ...user, mustVerifyEmail: true } },
            siteNotifications: { unread: 2 },
        };
        expect(useBellVisible().value).toBe(false);

        page.props = { auth: { user }, siteNotifications: null };
        expect(useBellVisible().value).toBe(false);
    });

    it('waits quietly while the app root runs before the first page', () => {
        page.props = undefined;

        expect(useBellVisible().value).toBe(false);
    });
});
