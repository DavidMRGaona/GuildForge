import { beforeEach, describe, expect, it } from 'vitest';
import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import {
    IMPERSONATION_READ_ONLY_HEADER,
    useImpersonationReadOnly,
    type ImpersonationReadOnly,
} from '../useImpersonationReadOnly';
import { useNotifications } from '../useNotifications';
import es from '@/locales/es';

function setup(): ImpersonationReadOnly {
    let api: ImpersonationReadOnly | undefined;
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es } });
    mount(
        defineComponent({
            setup(): () => null {
                api = useImpersonationReadOnly();

                return () => null;
            },
        }),
        { global: { plugins: [i18n] } }
    );

    if (api === undefined) {
        throw new Error('composable not set up');
    }

    return api;
}

function refusal(): Response {
    return new Response(
        JSON.stringify({
            message: 'Estás viendo el sitio como Ana: no se guardan cambios.',
            error: 'impersonation_read_only',
        }),
        {
            status: 403,
            headers: { [IMPERSONATION_READ_ONLY_HEADER]: '1', 'Content-Type': 'application/json' },
        }
    );
}

describe('useImpersonationReadOnly', () => {
    beforeEach(() => {
        useNotifications().clearAll();
    });

    it('recognises the 403 by its header', () => {
        expect(setup().isReadOnlyRefusal(refusal())).toBe(true);
    });

    it('does not take any other 403 for it', () => {
        const other = new Response('{}', {
            status: 403,
            headers: { 'X-Email-Verification-Required': '1' },
        });

        expect(setup().isReadOnlyRefusal(other)).toBe(false);
    });

    it('recognises a parsed body, an axios-like error and an Inertia response', () => {
        const api = setup();
        const body = { error: 'impersonation_read_only' };

        expect(api.isReadOnlyRefusal(body)).toBe(true);
        expect(api.isReadOnlyRefusal({ response: { status: 403, data: body } })).toBe(true);
        expect(api.isReadOnlyRefusal({ status: 403, data: JSON.stringify(body) })).toBe(true);
        expect(api.isReadOnlyRefusal({ status: 500, data: body })).toBe(false);
        expect(api.isReadOnlyRefusal(null)).toBe(false);
        expect(api.isReadOnlyRefusal('not json')).toBe(false);
    });

    it('shows the server text, which names the account', async () => {
        const handled = await setup().notifyRefusal(refusal());

        expect(handled).toBe(true);
        expect(useNotifications().notifications.value.map((n) => n.message)).toEqual([
            'Estás viendo el sitio como Ana: no se guardan cambios.',
        ]);
    });

    it('falls back to the generic text when the body cannot be read', () => {
        const handled = setup().notifyIfReadOnly({ error: 'impersonation_read_only' });

        expect(handled).toBe(true);
        expect(useNotifications().notifications.value[0]?.message).toBe(
            es.impersonation.readOnlyNotice
        );
    });

    it('shows nothing for another failure', async () => {
        const api = setup();

        expect(await api.notifyRefusal(new Response('', { status: 500 }))).toBe(false);
        expect(api.notifyIfReadOnly({ error: 'other' })).toBe(false);
        expect(useNotifications().notifications.value).toHaveLength(0);
    });
});
