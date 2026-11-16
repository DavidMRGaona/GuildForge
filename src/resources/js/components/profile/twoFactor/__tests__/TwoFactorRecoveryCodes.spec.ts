import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import TwoFactorRecoveryCodes from '../TwoFactorRecoveryCodes.vue';
import en from '@/locales/en';
import es from '@/locales/es';

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): typeof page => page,
}));

vi.mock('@/composables/useNotifications', () => ({
    useNotifications: (): { error: () => void } => ({ error: vi.fn() }),
}));

const CODES = ['abcde-fghij', 'klmno-pqrst'];

function mountCodes(locale: 'es' | 'en'): ReturnType<typeof mount> {
    page.props = { appName: 'Example' };
    const i18n = createI18n({ legacy: false, locale, messages: { es, en } });

    return mount(TwoFactorRecoveryCodes, { props: { codes: CODES }, global: { plugins: [i18n] } });
}

describe('TwoFactorRecoveryCodes', () => {
    const downloads: string[] = [];
    const blobs: Blob[] = [];

    beforeEach(() => {
        downloads.length = 0;
        blobs.length = 0;
        URL.createObjectURL = vi.fn((blob: Blob | MediaSource): string => {
            blobs.push(blob as Blob);

            return 'blob:test';
        });
        URL.revokeObjectURL = vi.fn();
        vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (
            this: HTMLAnchorElement
        ) {
            downloads.push(this.download);
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('names the downloaded file through the translations', async () => {
        const spanish = mountCodes('es');
        const english = mountCodes('en');

        const button = (wrapper: ReturnType<typeof mount>): ReturnType<typeof wrapper.get> =>
            wrapper.findAll('button')[1] as ReturnType<typeof wrapper.get>;
        await button(spanish).trigger('click');
        await button(english).trigger('click');

        expect(downloads).toEqual(['codigos-de-recuperacion.txt', 'recovery-codes.txt']);
    });

    it('puts the site name and every code in the file', async () => {
        const wrapper = mountCodes('es');

        await wrapper.findAll('button')[1]?.trigger('click');

        const text = await blobs[0]?.text();
        expect(text).toContain('Example');
        for (const code of CODES) {
            expect(text).toContain(code);
        }
    });
});
