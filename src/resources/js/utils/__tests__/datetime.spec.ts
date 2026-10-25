import { afterEach, describe, expect, it, vi } from 'vitest';

describe('VENUE_TIMEZONE', () => {
    afterEach(() => {
        document.head.innerHTML = '';
    });

    it('reads the display timezone from the meta tag', async () => {
        document.head.innerHTML = '<meta name="display-timezone" content="Atlantic/Canary">';
        vi.resetModules();
        const { VENUE_TIMEZONE } = await import('../datetime');
        expect(VENUE_TIMEZONE).toBe('Atlantic/Canary');
    });

    it('falls back to Europe/Madrid without the meta tag', async () => {
        document.head.innerHTML = '';
        vi.resetModules();
        const { VENUE_TIMEZONE } = await import('../datetime');
        expect(VENUE_TIMEZONE).toBe('Europe/Madrid');
    });

    it('falls back to Europe/Madrid when the meta value is not a timezone', async () => {
        document.head.innerHTML = '<meta name="display-timezone" content="Not/AZone">';
        vi.resetModules();
        const { VENUE_TIMEZONE } = await import('../datetime');
        expect(VENUE_TIMEZONE).toBe('Europe/Madrid');
    });
});
