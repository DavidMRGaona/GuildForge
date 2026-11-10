import { ref, watch, type Ref } from 'vue';

export type MailPreferencesView = 'cards' | 'tabs';

const STORAGE_KEY = 'mail-preferences-view';
const DEFAULT_VIEW: MailPreferencesView = 'cards';

function read(): MailPreferencesView {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        return stored === 'tabs' || stored === 'cards' ? stored : DEFAULT_VIEW;
    } catch {
        // Private windows or blocked site data: the page works without remembering
        return DEFAULT_VIEW;
    }
}

function write(view: MailPreferencesView): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, view);
    } catch {
        // Nothing to do: the choice only lasts until the page is closed
    }
}

/** Which way the person reads the sections of the «Avisos» tab, remembered in their browser. */
export function useMailPreferencesView(): { view: Ref<MailPreferencesView> } {
    const view = ref<MailPreferencesView>(read());

    watch(view, write);

    return { view };
}
