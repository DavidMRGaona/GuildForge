import { IdCard, Puzzle, User } from '@lucide/vue';
import { describe, expect, it } from 'vitest';
import { resolveProfileTabIcon } from '../icons';

describe('resolveProfileTabIcon', () => {
    it('resolves a known tab icon', () => {
        expect(resolveProfileTabIcon('user')).toBe(User);
    });

    it('resolves the id-card icon used by module tabs', () => {
        expect(resolveProfileTabIcon('id-card')).toBe(IdCard);
    });

    it('falls back to the neutral icon for an unknown name', () => {
        expect(resolveProfileTabIcon('not-an-icon')).toBe(Puzzle);
    });
});
