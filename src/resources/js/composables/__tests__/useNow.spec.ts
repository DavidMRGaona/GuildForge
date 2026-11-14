import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope } from 'vue';
import { NOW_TICK_MS, useNow } from '../useNow';

describe('useNow', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-11-14T10:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('moves on every minute while someone reads it', () => {
        const scope = effectScope();
        const now = scope.run(() => useNow());

        expect(now?.value.toISOString()).toBe('2026-11-14T10:00:00.000Z');

        vi.advanceTimersByTime(NOW_TICK_MS);
        expect(now?.value.toISOString()).toBe('2026-11-14T10:01:00.000Z');

        scope.stop();
    });

    it('shares one clock between readers and stops it when the last one goes', () => {
        const first = effectScope();
        const second = effectScope();
        const a = first.run(() => useNow());
        const b = second.run(() => useNow());

        expect(vi.getTimerCount()).toBe(1);
        expect(a).toBe(b);

        first.stop();
        expect(vi.getTimerCount()).toBe(1);

        second.stop();
        expect(vi.getTimerCount()).toBe(0);
    });

    it('starts from the current instant when a reader comes back', () => {
        const first = effectScope();
        first.run(() => useNow());
        first.stop();

        vi.setSystemTime(new Date('2026-11-14T12:30:00Z'));
        const again = effectScope();
        const now = again.run(() => useNow());

        expect(now?.value.toISOString()).toBe('2026-11-14T12:30:00.000Z');
        again.stop();
    });
});
