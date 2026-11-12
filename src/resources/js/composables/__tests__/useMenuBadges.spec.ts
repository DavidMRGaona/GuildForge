import { afterEach, describe, expect, it } from 'vitest';
import { clearMenuBadge, setMenuBadge, useMenuBadges } from '../useMenuBadges';

describe('useMenuBadges', () => {
    afterEach(() => {
        clearMenuBadge('forum:forum.index');
        clearMenuBadge('announcements:announcements.index');
    });

    it('marks a link by its key and takes the mark off', () => {
        const { badges, setBadge, clearBadge } = useMenuBadges();

        setBadge('forum:forum.index', '3 temas sin leer');
        expect(badges.value['forum:forum.index']).toBe('3 temas sin leer');

        clearBadge('forum:forum.index');
        expect(badges.value['forum:forum.index']).toBeUndefined();
    });

    it('is one state for everyone who asks for it', () => {
        const first = useMenuBadges();
        const second = useMenuBadges();

        first.setBadge('forum:forum.index', 'Algo nuevo');

        expect(second.badges.value['forum:forum.index']).toBe('Algo nuevo');
    });

    it('keeps the other marks when one changes, and the text of the latest call', () => {
        const { badges, setBadge, clearBadge } = useMenuBadges();
        setBadge('forum:forum.index', 'Uno');
        setBadge('announcements:announcements.index', 'Dos');
        setBadge('forum:forum.index', 'Tres');

        clearBadge('announcements:announcements.index');

        expect(badges.value).toEqual({ 'forum:forum.index': 'Tres' });
    });

    it('does not mind clearing a link that has no mark', () => {
        const { badges, clearBadge } = useMenuBadges();

        expect(() => clearBadge('nobody:nothing')).not.toThrow();
        expect(badges.value).toEqual({});
    });

    it('offers the same functions without the composable, for code outside components', () => {
        setMenuBadge('forum:forum.index', 'Fuera');

        expect(useMenuBadges().badges.value['forum:forum.index']).toBe('Fuera');
    });
});
