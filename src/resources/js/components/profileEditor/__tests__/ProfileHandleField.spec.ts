import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProfileHandleField from '../ProfileHandleField.vue';
import { HANDLE_CHECK_DELAY_MS } from '@/composables/useHandleAvailability';
import en from '@/locales/en';
import es from '@/locales/es';
import type { ProfileEditor } from '@/types/profileEditor';
import { editorFixture } from './fixtures';

function mountField(editor: ProfileEditor = editorFixture()): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });
    const wrapper = mount(ProfileHandleField, {
        props: {
            editor,
            modelValue: editor.handle,
            'onUpdate:modelValue': (value: string) => wrapper.setProps({ modelValue: value }),
        },
        global: { plugins: [i18n] },
    });

    return wrapper;
}

describe('ProfileHandleField', () => {
    const fetchMock = vi.fn();

    beforeEach(() => {
        vi.useFakeTimers();
        fetchMock.mockReset();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('shows the prefix and asks the server a moment after the last key', async () => {
        fetchMock.mockResolvedValue(
            new Response(JSON.stringify({ status: 'unavailable' }), { status: 200 })
        );
        const wrapper = mountField();

        expect(wrapper.text()).toContain('/miembros/');
        await wrapper.get('[data-testid="handle-input"]').setValue('ana');
        await wrapper.get('[data-testid="handle-input"]').setValue('ana-rol');
        expect(wrapper.get('[data-testid="handle-status"]').text()).toBe('Comprobando…');
        expect(fetchMock).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(HANDLE_CHECK_DELAY_MS);
        await vi.waitFor(() =>
            expect(wrapper.get('[data-testid="handle-status"]').text()).toBe('No disponible')
        );

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0]?.[0]).toBe(
            '/miembros/ana-prueba/disponibilidad?direccion=ana-rol'
        );
        expect(wrapper.get('[data-testid="handle-input"]').attributes('aria-invalid')).toBe('true');
    });

    it('never asks for a malformed address or the current one', async () => {
        const wrapper = mountField();

        await wrapper.get('[data-testid="handle-input"]').setValue('Ana--Rol');
        await vi.advanceTimersByTimeAsync(HANDLE_CHECK_DELAY_MS);
        expect(wrapper.get('[data-testid="handle-status"]').text()).toContain('Usa de 3 a 30');

        await wrapper.get('[data-testid="handle-input"]').setValue(' Ana-Prueba ');
        await vi.advanceTimersByTimeAsync(HANDLE_CHECK_DELAY_MS);
        expect(wrapper.get('[data-testid="handle-status"]').text()).toBe('Es tu dirección actual');
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('says when the server could not answer', async () => {
        fetchMock.mockResolvedValue(new Response('', { status: 429 }));
        const wrapper = mountField();

        await wrapper.get('[data-testid="handle-input"]').setValue('ana-rol');
        await vi.advanceTimersByTimeAsync(HANDLE_CHECK_DELAY_MS);

        await vi.waitFor(() =>
            expect(wrapper.get('[data-testid="handle-status"]').text()).toBe(
                'No se ha podido comprobar ahora; se comprobará al guardar.'
            )
        );
    });

    it('is read-only with the date while the 30 days have not passed', async () => {
        const wrapper = mountField(
            editorFixture({ nextHandleChangeAt: '2026-12-15T11:00:00+01:00' })
        );

        expect(wrapper.get('[data-testid="handle-input"]').attributes('readonly')).toBeDefined();
        expect(wrapper.text()).toContain(
            'Podrás cambiarla de nuevo a partir del 15 de diciembre de 2026.'
        );
        await wrapper.get('[data-testid="handle-input"]').setValue('otra');
        await vi.advanceTimersByTimeAsync(HANDLE_CHECK_DELAY_MS);
        expect(fetchMock).not.toHaveBeenCalled();
    });
});
