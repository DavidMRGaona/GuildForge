import { describe, expect, it } from 'vitest';
import { isActiveHref } from '../navigation';

const origin = 'https://guild.test';

describe('isActiveHref', () => {
    it('matches an absolute href from the server against the current path', () => {
        expect(isActiveHref('https://guild.test/articulos', '/articulos', origin)).toBe(true);
    });

    it('matches a relative href', () => {
        expect(isActiveHref('/articulos', '/articulos', origin)).toBe(true);
    });

    it('keeps the link active on the pages below it', () => {
        expect(isActiveHref('https://guild.test/eventos', '/eventos/torneo-de-otono', origin)).toBe(
            true
        );
    });

    it('does not match a sibling path that only shares a prefix', () => {
        expect(isActiveHref('/eventos', '/eventos-pasados', origin)).toBe(false);
    });

    it('ignores the query string and the fragment', () => {
        expect(isActiveHref('/eventos', '/eventos?page=2#lista', origin)).toBe(true);
        expect(isActiveHref('/eventos?tipo=rol', '/eventos', origin)).toBe(true);
    });

    it('ignores a trailing slash on either side', () => {
        expect(isActiveHref('/galeria/', '/galeria', origin)).toBe(true);
        expect(isActiveHref('/galeria', '/galeria/', origin)).toBe(true);
    });

    it('marks the home link only on the home page', () => {
        expect(isActiveHref('https://guild.test/', '/', origin)).toBe(true);
        expect(isActiveHref('https://guild.test', '/', origin)).toBe(true);
        expect(isActiveHref('/', '/articulos', origin)).toBe(false);
    });

    it('compares encoded and decoded paths alike', () => {
        expect(isActiveHref('https://guild.test/galer%C3%ADa', '/galería', origin)).toBe(true);
    });

    it('never marks a link to another site', () => {
        expect(isActiveHref('https://other.test/articulos', '/articulos', origin)).toBe(false);
        expect(isActiveHref('mailto:info@guild.test', '/', origin)).toBe(false);
    });

    it('never marks a placeholder href', () => {
        expect(isActiveHref('#', '/', origin)).toBe(false);
        expect(isActiveHref('', '/', origin)).toBe(false);
    });
});
