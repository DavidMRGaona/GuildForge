import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    checkImageFile,
    EDITOR_IMAGE_MAX_BYTES,
    ImageUploadError,
    normalizeLinkHref,
    uploadEditorImage,
} from '../richText';

describe('normalizeLinkHref', () => {
    it.each([
        ['https://example.org/a?b=1', 'https://example.org/a?b=1'],
        ['example.org', 'https://example.org/'],
        ['  http://example.org  ', 'http://example.org/'],
        ['//example.org/x', 'https://example.org/x'],
        ['mailto:hola@example.org', 'mailto:hola@example.org'],
    ])('accepts %s', (input, expected) => {
        expect(normalizeLinkHref(input)).toBe(expected);
    });

    it.each([
        '',
        '   ',
        'javascript:alert(1)',
        'data:text/html,x',
        'ftp://example.org',
        'two words.org',
        'sinpunto',
        'mailto:',
    ])('refuses %j', (input) => {
        expect(normalizeLinkHref(input)).toBeNull();
    });
});

describe('checkImageFile', () => {
    it('accepts the panel editor types', () => {
        for (const type of ['image/jpeg', 'image/png', 'image/webp', 'image/gif']) {
            expect(checkImageFile(new File(['x'], 'a', { type }))).toBeNull();
        }
    });

    it('refuses SVG and other types', () => {
        expect(checkImageFile(new File(['x'], 'a.svg', { type: 'image/svg+xml' }))).toBe('type');
        expect(checkImageFile(new File(['x'], 'a.pdf', { type: 'application/pdf' }))).toBe('type');
    });

    it('refuses what is over the limit', () => {
        const big = new File([new Uint8Array(EDITOR_IMAGE_MAX_BYTES + 1)], 'a.png', {
            type: 'image/png',
        });

        expect(checkImageFile(big)).toBe('size');
    });
});

describe('uploadEditorImage', () => {
    afterEach(() => vi.unstubAllGlobals());

    const file = new File(['x'], 'a.png', { type: 'image/png' });

    function answer(status: number, body: unknown = {}): void {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(new Response(JSON.stringify(body), { status }))
        );
    }

    it('posts the image and gives back the stored path and URL', async () => {
        answer(201, { id: 'articles/content/2026/11/a.png', url: 'https://cdn.example/a.png' });

        await expect(uploadEditorImage('/editor/imagenes/articulos', file)).resolves.toEqual({
            id: 'articles/content/2026/11/a.png',
            url: 'https://cdn.example/a.png',
        });

        const [url, init] = vi.mocked(fetch).mock.calls[0] as [string, RequestInit];
        expect(url).toBe('/editor/imagenes/articulos');
        expect(init.method).toBe('POST');
        expect((init.body as FormData).get('image')).toBeInstanceOf(File);
        expect((init.headers as Record<string, string>).Accept).toBe('application/json');
    });

    it.each([
        [401, 'session'],
        [419, 'session'],
        [403, 'forbidden'],
        [422, 'invalid'],
        [429, 'limit'],
        [500, 'failed'],
    ])('turns a %i into the reason %s', async (status, reason) => {
        answer(status);

        await expect(uploadEditorImage('/x', file)).rejects.toMatchObject({ reason });
    });

    it('fails when the answer is not what the editor needs', async () => {
        answer(201, { id: 5 });

        await expect(uploadEditorImage('/x', file)).rejects.toBeInstanceOf(ImageUploadError);
    });

    it('fails when the network does', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('offline')));

        await expect(uploadEditorImage('/x', file)).rejects.toMatchObject({ reason: 'failed' });
    });
});
