import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import ProfileEditorPanel from '../ProfileEditorPanel.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { ProfileEditor, ProfileEditorForm } from '@/types/profileEditor';
import { historyPopListeners, listenToHistoryFirst } from '@/utils/historyPops';
import { editorFixture } from './fixtures';

// As the app does before Inertia starts
listenToHistoryFirst();

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

    const listeners: Array<() => void> = [];

    /**
     * What Inertia would hear of the browser's Back and Forward: it must not swap the page under the panel.
     * Added before the panel, as Inertia adds its own when the app starts. Chrome runs the listeners of
     * `window` in the order they were added, capture or not; happy-dom runs capture ones first, so this
     * one captures to be run in Chrome's order.
     */
    function inertiaHearsPopstate(): ReturnType<typeof vi.fn> {
        const heard = vi.fn();
        window.addEventListener('popstate', heard, true);
        listeners.push(heard);

        return heard;
    }

    afterEach(() => {
        listeners.splice(0).forEach((listener) => {
            window.removeEventListener('popstate', listener, true);
        });
    });

    it('opens on an entry of its own in the history, so going back closes it', () => {
        const before = window.history.length;
        const href = window.location.href;
        const inertia = inertiaHearsPopstate();
        const wrapper = mountPanel();

        expect(window.history.length).toBe(before + 1);
        expect(window.location.href).toBe(href);

        window.history.back();
        expect(wrapper.emitted('close')).toHaveLength(1);
        expect(inertia).not.toHaveBeenCalled();

        // Its entry is gone already: closing takes nothing else from the history
        const back = vi.spyOn(window.history, 'back');
        wrapper.unmount();
        expect(back).not.toHaveBeenCalled();
    });

    it('going back with changes asks first, and staying puts its entry back', async () => {
        const confirm = vi.fn<(message?: string) => boolean>(() => false);
        vi.stubGlobal('confirm', confirm);
        const inertia = inertiaHearsPopstate();
        const wrapper = mountPanel();
        await wrapper.get('[data-testid="bio-input"]').setValue('Hola');

        window.history.back();
        expect(confirm).toHaveBeenCalledWith(
            'Tienes cambios sin guardar en tu perfil. ¿Salir y descartarlos?'
        );
        expect(wrapper.emitted('close')).toBeUndefined();
        expect(inertia).not.toHaveBeenCalled();

        // Still open, on its entry again: the next Back asks again, and leaving closes it
        confirm.mockReturnValue(true);
        window.history.back();
        expect(confirm).toHaveBeenCalledTimes(2);
        expect(wrapper.emitted('close')).toHaveLength(1);
        expect(inertia).not.toHaveBeenCalled();

        const unload = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(unload);
        expect(unload.defaultPrevented).toBe(false);
        wrapper.unmount();
    });

    it('closing it otherwise takes its entry away, leaving the page as it is now', async () => {
        const inertia = inertiaHearsPopstate();
        const wrapper = mountPanel();
        // Inertia keeps the page on the panel's entry (after a new banner, say): the page's own entry gets it
        window.history.replaceState({ page: { url: 'latest' } }, '');
        const back = vi.spyOn(window.history, 'back');

        await wrapper.get('[data-testid="profile-editor"]').trigger('keydown', { key: 'Escape' });
        expect(wrapper.emitted('close')).toHaveLength(1);
        wrapper.unmount();

        expect(back).toHaveBeenCalledTimes(1);
        expect(inertia).not.toHaveBeenCalled();
        expect(window.history.state).toEqual({ page: { url: 'latest' } });

        // Only that one step back was the panel's: the next one is the page's again
        window.history.pushState({ page: { url: 'next' } }, '');
        window.history.back();
        expect(inertia).toHaveBeenCalledTimes(1);
    });

    it('lets a visit elsewhere take its entry instead of adding one after it', () => {
        const wrapper = mountPanel();

        // Its own requests too: one that ends elsewhere (an expired session) replaces the entry
        const own = visitTo('/miembros/ana-prueba/banner', 'post');
        expect((own.detail as { visit: { replace?: boolean } }).visit.replace).toBe(true);

        const elsewhere = visitTo('/eventos');
        expect((elsewhere.detail as { visit: { replace?: boolean } }).visit.replace).toBe(true);

        const back = vi.spyOn(window.history, 'back');
        wrapper.unmount();
        expect(back).not.toHaveBeenCalled();
    });

    it('going back to another page leaves with it, asking first with changes', async () => {
        const confirm = vi.fn<(message?: string) => boolean>(() => false);
        vi.stubGlobal('confirm', confirm);
        window.history.pushState({ page: { url: '/eventos' } }, '', '/eventos');
        window.history.pushState(
            { page: { url: '/miembros/ana-prueba' } },
            '',
            '/miembros/ana-prueba'
        );
        const inertia = inertiaHearsPopstate();
        const wrapper = mountPanel();
        await wrapper.get('[data-testid="bio-input"]').setValue('Hola');

        window.history.go(-2);
        expect(confirm).toHaveBeenCalledTimes(1);
        expect(inertia).not.toHaveBeenCalled();
        expect(window.location.pathname).toBe('/miembros/ana-prueba');

        confirm.mockReturnValue(true);
        window.history.go(-2);
        // Inertia takes the other page, and the panel goes with the page it was on
        expect(inertia).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('close')).toBeUndefined();

        const back = vi.spyOn(window.history, 'back');
        wrapper.unmount();
        expect(back).not.toHaveBeenCalled();
    });

    /** Two pages in the history, the profile last: going back two steps lands on the other one */
    function behindAnotherPage(): void {
        window.history.pushState({ page: { url: '/eventos' } }, '', '/eventos');
        window.history.pushState(
            { page: { url: '/miembros/ana-prueba' } },
            '',
            '/miembros/ana-prueba'
        );
    }

    /** Back several steps (the browser's list of pages) to another page, and staying */
    async function stayAfterGoingBackTwoSteps(wrapper: VueWrapper): Promise<void> {
        vi.stubGlobal(
            'confirm',
            vi.fn(() => false)
        );
        await wrapper.get('[data-testid="bio-input"]').setValue('Hola');
        window.history.go(-2);
        expect(window.location.pathname).toBe('/miembros/ana-prueba');
    }

    it('after staying from several steps back, closing keeps the page in place and Back leaves it', async () => {
        behindAnotherPage();
        const inertia = inertiaHearsPopstate();
        const wrapper = mountPanel();
        await stayAfterGoingBackTwoSteps(wrapper);

        // What is under the panel's entry now is the other page: never go back onto it
        const back = vi.spyOn(window.history, 'back');
        wrapper.unmount();
        expect(back).not.toHaveBeenCalled();
        expect(window.location.pathname).toBe('/miembros/ana-prueba');
        expect(window.history.state).toEqual({ page: { url: '/miembros/ana-prueba' } });
        expect(inertia).not.toHaveBeenCalled();

        back.mockRestore();
        window.history.back();
        expect(window.location.pathname).toBe('/eventos');
        expect(inertia).toHaveBeenCalledTimes(1);
    });

    it('after staying from several steps back, saving stays on the page and Back leaves it', async () => {
        behindAnotherPage();
        const inertia = inertiaHearsPopstate();
        const put = vi.spyOn(router, 'put').mockImplementation((_url, _data, options) => {
            options?.onSuccess?.({} as never);
            options?.onFinish?.({} as never);
        });
        const wrapper = mountPanel();
        await stayAfterGoingBackTwoSteps(wrapper);
        const back = vi.spyOn(window.history, 'back');

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(put).toHaveBeenCalledTimes(1);
        expect(back).not.toHaveBeenCalled();
        expect(window.location.pathname).toBe('/miembros/ana-prueba');
        wrapper.unmount();
        expect(back).not.toHaveBeenCalled();

        back.mockRestore();
        window.history.back();
        expect(window.location.pathname).toBe('/eventos');
        expect(inertia).toHaveBeenCalledTimes(1);
    });

    it('going back while saving goes where Back goes, and the cancelled save puts nothing back', async () => {
        behindAnotherPage();
        const inertia = inertiaHearsPopstate();
        let finish: (() => void) | undefined;
        vi.spyOn(router, 'put').mockImplementation((_url, _data, options) => {
            finish = (): void => options?.onFinish?.({} as never);
        });
        const wrapper = mountPanel();
        await wrapper.get('[data-testid="bio-input"]').setValue('Hola');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        window.history.back();
        expect(window.location.pathname).toBe('/eventos');
        expect(inertia).toHaveBeenCalledTimes(1);

        // Inertia cancels the save: the panel's entry is never put back over the other page
        const push = vi.spyOn(window.history, 'pushState');
        finish?.();
        expect(push).not.toHaveBeenCalled();
        expect(window.location.pathname).toBe('/eventos');
        wrapper.unmount();
    });

    it('leaves the history alone when a request of its own ends on another page', async () => {
        window.history.pushState(
            { page: { url: '/miembros/ana-prueba' } },
            '',
            '/miembros/ana-prueba'
        );
        const inertia = inertiaHearsPopstate();
        const wrapper = mountPanel();
        const back = vi.spyOn(window.history, 'back');

        // The banner's upload, with a session that expired: Inertia goes on to the sign-in page
        window.history.pushState({ page: { url: '/login' } }, '', '/login');
        document.dispatchEvent(new CustomEvent('inertia:finish', { detail: { visit: {} } }));
        wrapper.unmount();

        expect(back).not.toHaveBeenCalled();
        expect(window.location.pathname).toBe('/login');
        expect(window.history.state).toEqual({ page: { url: '/login' } });

        // The panel's entry under it keeps the profile, never the sign-in page
        back.mockRestore();
        window.history.back();
        expect(window.location.pathname).toBe('/miembros/ana-prueba');
        expect(window.history.state).not.toEqual({ page: { url: '/login' } });
        expect(inertia).toHaveBeenCalledTimes(1);
    });

    it('takes a new fragment in the address for the same page, still keeping its latest state', () => {
        window.history.pushState(
            { page: { url: '/miembros/ana-prueba' } },
            '',
            '/miembros/ana-prueba#main-content'
        );
        const inertia = inertiaHearsPopstate();
        const wrapper = mountPanel();
        const back = vi.spyOn(window.history, 'back');

        // A new banner: Inertia answers on the page's address without the fragment, on the panel's entry
        window.history.replaceState({ page: { url: 'new-banner' } }, '', '/miembros/ana-prueba');
        document.dispatchEvent(new CustomEvent('inertia:finish', { detail: { visit: {} } }));
        window.history.replaceState({ page: { url: 'scrolled' } }, '', '/miembros/ana-prueba');
        wrapper.unmount();

        // Still the same page: back over the panel's entry, with the state of the last request
        expect(back).toHaveBeenCalledTimes(1);
        expect(window.location.pathname).toBe('/miembros/ana-prueba');
        expect(window.history.state).toEqual({ page: { url: 'scrolled' } });
        expect(inertia).not.toHaveBeenCalled();
    });

    it('keeps the latest state from its own requests for Back, also after a new fragment', () => {
        window.history.pushState(
            { page: { url: '/miembros/ana-prueba' } },
            '',
            '/miembros/ana-prueba#main-content'
        );
        const inertia = inertiaHearsPopstate();
        const wrapper = mountPanel();

        window.history.replaceState({ page: { url: 'new-banner' } }, '', '/miembros/ana-prueba');
        document.dispatchEvent(new CustomEvent('inertia:finish', { detail: { visit: {} } }));

        // Back closes it, and the page's own entry gets what the request left
        window.history.back();
        expect(wrapper.emitted('close')).toHaveLength(1);
        expect(window.history.state).toEqual({ page: { url: 'new-banner' } });
        expect(inertia).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('stops listening to Back once closed, whichever way it closes', async () => {
        expect(historyPopListeners()).toBe(0);

        const plain = mountPanel();
        expect(historyPopListeners()).toBe(1);
        plain.unmount();
        expect(historyPopListeners()).toBe(0);

        const backed = mountPanel();
        window.history.back();
        backed.unmount();
        expect(historyPopListeners()).toBe(0);

        // After staying from several steps back, closing turns its entry into the page's in place
        behindAnotherPage();
        const stayed = mountPanel();
        await stayAfterGoingBackTwoSteps(stayed);
        stayed.unmount();
        expect(historyPopListeners()).toBe(0);

        const elsewhere = mountPanel();
        window.history.pushState({ page: { url: '/login' } }, '', '/login');
        elsewhere.unmount();
        expect(historyPopListeners()).toBe(0);
    });

    it('puts nothing back once Inertia is reloading the page (a new version)', async () => {
        let finish: (() => void) | undefined;
        vi.spyOn(router, 'put').mockImplementation((_url, _data, options) => {
            finish = (): void => options?.onFinish?.({} as never);
        });
        const wrapper = mountPanel();
        await wrapper.get('[data-testid="bio-input"]').setValue('Hola');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(finish).toBeDefined();
        document.dispatchEvent(
            new CustomEvent('inertia:location', {
                cancelable: true,
                detail: { url: new URL(window.location.href), versionChange: true },
            })
        );
        const push = vi.spyOn(window.history, 'pushState');
        finish?.();
        expect(push).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('saves from the page’s own entry, so the saved page leaves no extra step back', async () => {
        const back = vi.spyOn(window.history, 'back');
        const put = vi.spyOn(router, 'put').mockImplementation((_url, _data, options) => {
            options?.onError?.({ handle: 'No disponible.' });
            options?.onFinish?.({} as never);
        });
        const inertia = inertiaHearsPopstate();
        const wrapper = mountPanel();

        await wrapper.get('[data-testid="bio-input"]').setValue('Hola');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(put).toHaveBeenCalledTimes(1);
        expect(back).toHaveBeenCalledTimes(1);
        expect(back.mock.invocationCallOrder[0]).toBeLessThan(put.mock.invocationCallOrder[0] ?? 0);
        expect(inertia).not.toHaveBeenCalled();

        // Not saved: the panel stays, on an entry of its own again, so Back still closes it
        vi.stubGlobal(
            'confirm',
            vi.fn(() => true)
        );
        window.history.back();
        expect(wrapper.emitted('close')).toHaveLength(1);
        expect(inertia).not.toHaveBeenCalled();
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
        const plain = mountPanel();
        expect(plain.findAll('[data-testid="visibility-option"]')).toHaveLength(4);
        expect(plain.text()).not.toContain('Mostrar que soy socio');
        plain.unmount();

        const member = mountPanel(editorFixture({ canShowMembership: true }));
        expect(member.text()).toContain('Mostrar que soy socio');
        member.unmount();
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
