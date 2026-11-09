import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import MutedMailSubjectsBlock from '../MutedMailSubjectsBlock.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { MutedMailSubject } from '@/types/mailPreferences';

type DeleteOptions = {
    data?: Record<string, string>;
    onStart?: () => void;
    onFinish?: () => void;
    onError?: () => void;
};

const router = vi.hoisted(() => ({ delete: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ router }));

const SUBJECTS: MutedMailSubject[] = [
    {
        type: 'game-tables.campaign',
        id: 'c-1',
        label: 'La tumba de los horrores',
        typeLabel: 'Campaña',
        mutedAt: '2026-12-01T23:30:00+01:00',
    },
    {
        type: 'gone.thing',
        id: '42',
        label: 'Algo que ya no existe',
        typeLabel: null,
        mutedAt: '2026-11-20T10:00:00+01:00',
    },
];

function mountBlock(subjects: MutedMailSubject[] = SUBJECTS, blocked = false): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(MutedMailSubjectsBlock, {
        props: { subjects, blocked },
        global: { plugins: [i18n] },
    });
}

describe('MutedMailSubjectsBlock', () => {
    beforeEach(() => {
        router.delete.mockReset();
    });

    it('lists each muted thing with its kind, name and the display day it was muted', () => {
        const wrapper = mountBlock();
        const rows = wrapper.findAll('li');

        expect(rows).toHaveLength(2);
        expect(rows[0]?.text()).toContain('Campaña');
        expect(rows[0]?.text()).toContain('La tumba de los horrores');
        // The day of the display zone, not of the browser
        expect(rows[0]?.text()).toContain('Desde el 1 de diciembre de 2026');
        expect(rows[1]?.text()).toContain('Algo que ya no existe');
        expect(rows[1]?.text()).not.toContain('Campaña');
    });

    it('is a labelled section', () => {
        const section = mountBlock().find('section');

        expect(section.attributes('aria-labelledby')).toBe('muted-mail-subjects-title');
        expect(section.find('#muted-mail-subjects-title').text()).toBe('Avisos silenciados');
    });

    it('explains how to mute something when the list is empty', () => {
        const wrapper = mountBlock([]);

        expect(wrapper.find('[data-testid="muted-mail-subjects-empty"]').exists()).toBe(true);
        expect(wrapper.find('ul').exists()).toBe(false);
    });

    it('names the thing in the accessible name of each button', () => {
        const button = mountBlock().find('[data-subject="game-tables.campaign:c-1"] button');

        expect(button.text()).toBe('Volver a recibir');
        expect(button.attributes('aria-label')).toBe(
            'Volver a recibir avisos de «La tumba de los horrores»'
        );
    });

    it('unmutes the thing of the row it was asked from', async () => {
        const wrapper = mountBlock();

        await wrapper.find('[data-subject="gone.thing:42"] button').trigger('click');

        expect(router.delete).toHaveBeenCalledTimes(1);
        const [url, options] = router.delete.mock.calls[0] as [string, DeleteOptions];
        expect(url).toBe('/perfil/correos/silenciados');
        expect(options.data).toEqual({ type: 'gone.thing', id: '42' });
    });

    it('shows an error when unmuting fails', async () => {
        router.delete.mockImplementation((_url: string, options: DeleteOptions) => {
            options.onStart?.();
            options.onError?.();
            options.onFinish?.();
        });
        const wrapper = mountBlock();

        await wrapper.find('[data-subject="gone.thing:42"] button').trigger('click');

        expect(wrapper.find('[role="alert"]').text()).toBe(
            'No se ha podido deshacer. Inténtalo de nuevo.'
        );
    });

    it('does nothing while the account must verify its email', async () => {
        const wrapper = mountBlock(SUBJECTS, true);
        const button = wrapper.find('[data-subject="gone.thing:42"] button');

        expect(button.attributes('disabled')).toBeDefined();
        await button.trigger('click');
        expect(router.delete).not.toHaveBeenCalled();
    });

    it('shows a name with markup as plain text', () => {
        const wrapper = mountBlock([
            { ...SUBJECTS[1]!, label: '<img src=x onerror=alert(1)> *épica*' },
        ]);

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)> *épica*');
    });
});
