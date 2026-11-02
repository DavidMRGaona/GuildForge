import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { effectScope, nextTick } from 'vue';
import type { EffectScope } from 'vue';
import { useSearchSuggestions } from '../useSearchSuggestions';
import type { SearchHit } from '@/types/search';

function makeHit(title: string): SearchHit {
    return {
        type: 'events',
        typeLabel: 'Evento',
        title,
        url: `/eventos/${title.toLowerCase()}`,
        meta: null,
        image: null,
        date: 'sáb 15 nov · 10:00',
        pastLabel: null,
        snippet: [],
    };
}

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

describe('useSearchSuggestions', () => {
    const fetchMock = vi.fn<(url: string, init: RequestInit) => Promise<Response>>();
    let scope: EffectScope;

    function setup(): ReturnType<typeof useSearchSuggestions> {
        scope = effectScope();
        const suggestions = scope.run(() => useSearchSuggestions());
        if (!suggestions) {
            throw new Error('The composable did not run');
        }

        return suggestions;
    }

    beforeEach(() => {
        vi.useFakeTimers();
        fetchMock.mockReset();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        scope.stop();
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('waits 200 ms before requesting', async () => {
        fetchMock.mockResolvedValue(jsonResponse({ results: [makeHit('Dragón')], total: 3 }));
        const suggestions = setup();

        suggestions.query.value = 'dragón';
        await nextTick();
        vi.advanceTimersByTime(199);

        expect(fetchMock).not.toHaveBeenCalled();
        expect(suggestions.loading.value).toBe(true);

        vi.advanceTimersByTime(1);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledOnce();
        expect(fetchMock.mock.calls[0]?.[0]).toBe('/buscar/sugerencias?q=drag%C3%B3n');
        expect(suggestions.results.value.map((hit) => hit.title)).toEqual(['Dragón']);
        expect(suggestions.total.value).toBe(3);
        expect(suggestions.loading.value).toBe(false);
        expect(suggestions.failed.value).toBe(false);
    });

    it('cancels the previous request', async () => {
        let resolveFirst: (response: Response) => void = () => undefined;
        fetchMock.mockImplementationOnce(
            () =>
                new Promise<Response>((resolve) => {
                    resolveFirst = resolve;
                })
        );
        fetchMock.mockImplementationOnce(() =>
            Promise.resolve(jsonResponse({ results: [makeHit('Dragones')], total: 1 }))
        );
        const suggestions = setup();

        suggestions.query.value = 'drag';
        await nextTick();
        vi.advanceTimersByTime(200);
        const firstSignal = fetchMock.mock.calls[0]?.[1].signal;

        suggestions.query.value = 'dragones';
        await nextTick();

        expect(firstSignal?.aborted).toBe(true);

        vi.advanceTimersByTime(200);
        await flushPromises();
        // The first answer arrives late: it must not replace the newer one
        resolveFirst(jsonResponse({ results: [makeHit('Drag')], total: 9 }));
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(suggestions.results.value.map((hit) => hit.title)).toEqual(['Dragones']);
        expect(suggestions.total.value).toBe(1);
    });

    it('skips queries under two characters', async () => {
        fetchMock.mockResolvedValue(jsonResponse({ results: [makeHit('Dragón')], total: 1 }));
        const suggestions = setup();

        suggestions.query.value = 'dr';
        await nextTick();
        vi.advanceTimersByTime(200);
        await flushPromises();
        expect(suggestions.results.value).toHaveLength(1);

        suggestions.query.value = ' d ';
        await nextTick();
        vi.advanceTimersByTime(1000);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledOnce();
        expect(suggestions.results.value).toEqual([]);
        expect(suggestions.total.value).toBe(0);
        expect(suggestions.loading.value).toBe(false);
    });

    it('sets failed on error', async () => {
        fetchMock.mockResolvedValueOnce(jsonResponse({ message: 'Too Many Attempts.' }, 429));
        fetchMock.mockRejectedValueOnce(new TypeError('Network error'));
        fetchMock.mockResolvedValueOnce(jsonResponse({ results: [], total: 0 }));
        const suggestions = setup();

        suggestions.query.value = 'dragón';
        await nextTick();
        vi.advanceTimersByTime(200);
        await flushPromises();

        expect(suggestions.failed.value).toBe(true);
        expect(suggestions.results.value).toEqual([]);
        expect(suggestions.loading.value).toBe(false);

        suggestions.query.value = 'dragones';
        await nextTick();
        vi.advanceTimersByTime(200);
        await flushPromises();

        expect(suggestions.failed.value).toBe(true);

        suggestions.query.value = 'dragonera';
        await nextTick();
        vi.advanceTimersByTime(200);
        await flushPromises();

        expect(suggestions.failed.value).toBe(false);
    });
});
