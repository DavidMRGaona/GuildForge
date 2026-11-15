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
        vi.unstubAllGlobals();
        document.body.innerHTML = '';
    });

    /** What Inertia sends before a visit, as `router.on('before')` receives it */
    function visitTo(url: string, method = 'get'): CustomEvent {
        const event = new CustomEvent('inertia:before', {
            cancelable: true,
            detail: { visit: { url: new URL(url, window.location.origin), method } },
        });
        document.dispatchEvent(event);

        return event;
    }

    it('makes the whole page behind inert while it is open, and gives it back', () => {
        const app = document.createElement('div');
        app.id = 'app';
        document.body.appendChild(app);

        const wrapper = mountPanel();
        expect(app.hasAttribute('inert')).toBe(true);

        wrapper.unmount();
        expect(app.hasAttribute('inert')).toBe(false);
    });

    it('takes the clicks on the page behind with a veil that closes it, asking first with changes', async () => {
        const untouched = mountPanel();
        await untouched.get('[data-testid="profile-editor-backdrop"]').trigger('click');
        expect(untouched.emitted('close')).toHaveLength(1);
        untouched.unmount();

        const edited = mountPanel();
        await edited.get('[data-testid="bio-input"]').setValue('Hola');
        await edited.get('[data-testid="profile-editor-backdrop"]').trigger('click');
        expect(edited.emitted('close')).toBeUndefined();
        expect(edited.text()).toContain('¿Descartar los cambios?');
        edited.unmount();
    });

    it('asks before a visit elsewhere would lose the changes, and only then', async () => {
        const confirm = vi.fn<(message?: string) => boolean>(() => false);
        vi.stubGlobal('confirm', confirm);
        const wrapper = mountPanel();

        expect(visitTo('/eventos').defaultPrevented).toBe(false);
        expect(confirm).not.toHaveBeenCalled();

        await wrapper.get('[data-testid="bio-input"]').setValue('Hola');
        expect(visitTo('/eventos').defaultPrevented).toBe(true);
        expect(confirm).toHaveBeenCalledWith(
            'Tienes cambios sin guardar en tu perfil. ¿Salir y descartarlos?'
        );

        // The editor's own requests go through
        expect(visitTo('/miembros/ana-prueba', 'put').defaultPrevented).toBe(false);
        expect(visitTo('/miembros/ana-prueba/banner', 'post').defaultPrevented).toBe(false);

        confirm.mockReturnValue(true);
        expect(visitTo('/eventos').defaultPrevented).toBe(false);

        wrapper.unmount();
        confirm.mockClear();
        expect(visitTo('/eventos').defaultPrevented).toBe(false);
        expect(confirm).not.toHaveBeenCalled();
    });

    it('once a visit elsewhere was accepted, nothing stops the page from going', async () => {
        vi.stubGlobal(
            'confirm',
            vi.fn(() => true)
        );
        const wrapper = mountPanel();
        await wrapper.get('[data-testid="bio-input"]').setValue('Hola');

        expect(visitTo('/eventos').defaultPrevented).toBe(false);

        // A visit Inertia ends with a full reload (a new deploy) must not be asked about again
        const unload = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(unload);
        expect(unload.defaultPrevented).toBe(false);
        wrapper.unmount();
    });

    it('asks the browser before leaving the site with changes', async () => {
        const wrapper = mountPanel();
        const untouched = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(untouched);
        expect(untouched.defaultPrevented).toBe(false);

        await wrapper.get('[data-testid="bio-input"]').setValue('Hola');
        const edited = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(edited);
        expect(edited.defaultPrevented).toBe(true);
        wrapper.unmount();
    });

    it('names the membership error on its switch', async () => {
        const wrapper = mountPanel(editorFixture({ canShowMembership: true }));
        vi.spyOn(router, 'put').mockImplementation((_url, _data, options) => {
            options?.onError?.({ show_membership: 'Solo quien es socio puede mostrarlo.' });
        });

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        const toggle = wrapper.get('[data-field="show_membership"] [role="switch"]');
        const ids = (toggle.attributes('aria-describedby') ?? '').split(' ');
        const error = ids.map((id) => document.getElementById(id)?.textContent?.trim());
        expect(error).toContain('Solo quien es socio puede mostrarlo.');
        wrapper.unmount();
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
