/**
 * Helpers of the web rich text editor (core#573): links people type and images they upload.
 */

import { csrfHeaders, isCsrfFailure } from '@/utils/csrf';

/** Same types as the panel editor; no SVG (it can carry scripts) */
export const EDITOR_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'] as const;

/** Bytes: the server's limit (2 MB), checked here only to save the trip */
export const EDITOR_IMAGE_MAX_BYTES = 2048 * 1024;

export interface UploadedEditorImage {
    /** The stored path: it goes in the image's `data-id`, which the panel editor knows it by */
    id: string;
    url: string;
}

export type ImageProblem = 'type' | 'size';

/**
 * A link a person typed, ready to store: `https://` is added to a bare address, and only web and
 * mail addresses are accepted (the server sanitises again, this only gives an early answer).
 * Null when it cannot be used.
 */
export function normalizeLinkHref(input: string): string | null {
    const value = input.trim();

    if (value === '' || /\s/.test(value)) {
        return null;
    }

    if (/^mailto:[^\s@]+@[^\s@]+$/i.test(value)) {
        return value;
    }

    if (/^[a-z][a-z0-9+.-]*:/i.test(value) && !/^https?:\/\//i.test(value)) {
        return null;
    }

    const href = /^https?:\/\//i.test(value) ? value : `https://${value.replace(/^\/\//, '')}`;

    try {
        const url = new URL(href);

        return url.hostname.includes('.') || url.hostname === 'localhost' ? url.href : null;
    } catch {
        return null;
    }
}

export function checkImageFile(file: File): ImageProblem | null {
    if (!(EDITOR_IMAGE_TYPES as readonly string[]).includes(file.type)) {
        return 'type';
    }

    return file.size > EDITOR_IMAGE_MAX_BYTES ? 'size' : null;
}

export class ImageUploadError extends Error {
    constructor(
        readonly reason: 'session' | 'forbidden' | 'invalid' | 'limit' | 'failed',
        message: string
    ) {
        super(message);
    }
}

/**
 * Sends one image to the editor's upload endpoint. Failures come as an {@link ImageUploadError}
 * whose `reason` the editor turns into a message in the current language.
 */
export async function uploadEditorImage(
    uploadUrl: string,
    file: File
): Promise<UploadedEditorImage> {
    const body = new FormData();
    body.append('image', file);

    let response: Response;

    try {
        response = await fetch(uploadUrl, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...csrfHeaders() },
        });
    } catch {
        throw new ImageUploadError('failed', 'network');
    }

    if (response.status === 401 || isCsrfFailure(response)) {
        throw new ImageUploadError('session', String(response.status));
    }

    if (response.status === 403) {
        throw new ImageUploadError('forbidden', '403');
    }

    if (response.status === 422) {
        throw new ImageUploadError('invalid', '422');
    }

    if (response.status === 429) {
        throw new ImageUploadError('limit', '429');
    }

    if (!response.ok) {
        throw new ImageUploadError('failed', String(response.status));
    }

    const data: unknown = await response.json().catch(() => null);

    if (
        typeof data !== 'object' ||
        data === null ||
        typeof (data as Record<string, unknown>).id !== 'string' ||
        typeof (data as Record<string, unknown>).url !== 'string'
    ) {
        throw new ImageUploadError('failed', 'response');
    }

    const { id, url } = data as UploadedEditorImage;

    return { id, url };
}
