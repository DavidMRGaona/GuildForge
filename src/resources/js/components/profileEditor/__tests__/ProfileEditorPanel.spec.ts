import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import ProfileEditorPanel from '../ProfileEditorPanel.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { ProfileEditor, ProfileEditorForm } from '@/types/profileEditor';
import { editorFixture } from './fixtures';

function mountPanel(editor: ProfileEditor = editorFixture()): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(ProfileEditorPanel, {
        attachTo: document.body,
        props: { editor },
        global: { plugins: [i18n], stubs: { teleport: true } },
    });
}

describe('ProfileEditorPanel', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        document.body.innerHTML = '';
    });

    it('is a dialog named by its title that takes the focus', () => {
        const wrapper = mountPanel();
        const dialog = wrapper.get('[data-testid="profile-editor"]');

        expect(dialog.attributes('role')).toBe('dialog');
        expect(dialog.attributes('aria-modal')).toBe('true');
        expect(
            document.getElementById(dialog.attributes('aria-labelledby') ?? '')?.textContent?.trim()
        ).toBe('Editar perfil');
        expect(document.activeElement?.textContent?.trim()).toBe('Editar perfil');
        wrapper.unmount();
    });

    it('offers the four audiences and the badge only to an association member', () => {
        expect(mountPanel().findAll('[data-testid="visibility-option"]')).toHaveLength(4);
        expect(mountPanel().text()).not.toContain('Mostrar que soy socio');
        expect(mountPanel(editorFixture({ canShowMembership: true })).text()).toContain(
            'Mostrar que soy socio'
        );
    });

    it('counts the characters of «Sobre mí» and marks a text too long', async () => {
        const wrapper = mountPanel();

        await wrapper.get('[data-testid="bio-input"]').setValue('ñandú 😀');
        expect(wrapper.get('[data-testid="bio-counter"]').text()).toBe('7 de 500');

        await wrapper.get('[data-testid="bio-input"]').setValue('a'.repeat(501));
        expect(wrapper.get('[data-testid="bio-counter"]').classes()).toContain('text-red-600');
        expect(wrapper.get('[data-testid="bio-input"]').attributes('aria-invalid')).toBe('true');
        wrapper.unmount();
    });

    it('sends every change to the preview', async () => {
        const wrapper = mountPanel();

        await wrapper.get('[data-testid="bio-input"]').setValue('Hola');
        await wrapper.findAll('[data-testid="visibility-option"]')[0]?.setValue(true);

        const last = wrapper.emitted('preview')?.at(-1)?.[0] as ProfileEditorForm;
        expect(last).toMatchObject({ bio: 'Hola', visibility: 'public', handle: 'ana-prueba' });
        wrapper.unmount();
    });

    it('saves everything in one request and closes once saved', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation((_url, _data, options) => {
            options?.onSuccess?.({} as never);
        });
        const wrapper = mountPanel();

        await wrapper.get('[data-testid="bio-input"]').setValue('Hola');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(put).toHaveBeenCalledTimes(1);
        expect(put.mock.calls[0]?.[0]).toBe('/miembros/ana-prueba');
        expect(put.mock.calls[0]?.[1]).toEqual({
            handle: 'ana-prueba',
            visibility: 'members',
            bio: 'Hola',
            show_membership: false,
            sections: [
                { key: 'core.about', enabled: true },
                { key: 'forum.latest', enabled: false },
            ],
            stats: [],
        });
        expect(wrapper.emitted('close')).toHaveLength(1);
        wrapper.unmount();
    });

    it('never sends a text too long', async () => {
        const put = vi.spyOn(router, 'put');
        const wrapper = mountPanel();

        await wrapper.get('[data-testid="bio-input"]').setValue('a'.repeat(501));
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(put).not.toHaveBeenCalled();
        expect(wrapper.get('[data-testid="profile-editor-errors"]').text()).toBe(
            'Revisa los campos marcados.'
        );
        expect(wrapper.text()).toContain('Puedes escribir 500 caracteres como mucho.');
        wrapper.unmount();
    });

    it('closes with Escape at once when nothing changed, and asks first when something would be lost', async () => {
        const untouched = mountPanel();
        await untouched.get('[data-testid="profile-editor"]').trigger('keydown', { key: 'Escape' });
        expect(untouched.emitted('close')).toHaveLength(1);
        untouched.unmount();

        const edited = mountPanel();
        await edited.get('[data-testid="bio-input"]').setValue('Hola');
        await edited.get('[data-testid="profile-editor"]').trigger('keydown', { key: 'Escape' });
        expect(edited.emitted('close')).toBeUndefined();
        expect(edited.text()).toContain('¿Descartar los cambios?');
        edited.unmount();
    });
});
