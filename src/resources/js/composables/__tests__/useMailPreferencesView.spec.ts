import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { useMailPreferencesView } from '../useMailPreferencesView';

describe('useMailPreferencesView', () => {
    afterEach(() => {
        window.localStorage.clear();
        vi.restoreAllMocks();
    });

    it('starts on the cards', () => {
        expect(useMailPreferencesView().view.value).toBe('cards');
    });

    it('remembers the view the person chose', async () => {
        const { view } = useMailPreferencesView();

        view.value = 'tabs';
        await nextTick();

        expect(window.localStorage.getItem('mail-preferences-view')).toBe('tabs');
        expect(useMailPreferencesView().view.value).toBe('tabs');
    });

    it('ignores a stored value it does not know', () => {
        window.localStorage.setItem('mail-preferences-view', 'grid');

        expect(useMailPreferencesView().view.value).toBe('cards');
    });

    it('works when the browser storage throws', async () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('blocked');
        });
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('blocked');
        });

        const { view } = useMailPreferencesView();
        expect(view.value).toBe('cards');

        view.value = 'tabs';
        await nextTick();
        expect(view.value).toBe('tabs');
    });
});
