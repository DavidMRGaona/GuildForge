import { describe, expect, it } from 'vitest';
import { initialsOf } from '../initials';

describe('initialsOf', () => {
    it('takes the first letters of the first and last words', () => {
        expect(initialsOf('Ana María Prueba')).toBe('AP');
        expect(initialsOf('ana')).toBe('A');
        expect(initialsOf('  Íñigo   Ñúñez ')).toBe('ÍÑ');
    });

    it('skips leading punctuation and keeps whole characters', () => {
        expect(initialsOf('«Berta» (la Grande)')).toBe('BG');
        expect(initialsOf('𝒜lba Ruiz')).toBe('𝒜R');
    });

    it('is empty for a blank or missing name', () => {
        expect(initialsOf('')).toBe('');
        expect(initialsOf('   ')).toBe('');
        expect(initialsOf(null)).toBe('');
        expect(initialsOf('— ·')).toBe('');
    });
});
