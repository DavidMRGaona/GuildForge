import { describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProfileLayoutList from '../ProfileLayoutList.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { ProfileLayoutChoice } from '@/types/profileEditor';
import { editorFixture } from './fixtures';

function mountList(choices: ProfileLayoutChoice[]): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });
    const wrapper = mount(ProfileLayoutList, {
        attachTo: document.body,
        props: {
            legend: 'Secciones',
            help: 'Ayuda',
            items: editorFixture().sections,
            modelValue: choices,
            'onUpdate:modelValue': (value: ProfileLayoutChoice[]) =>
                wrapper.setProps({ modelValue: value }),
        },
        global: { plugins: [i18n] },
    });

    return wrapper;
}

function choicesOf(wrapper: VueWrapper): ProfileLayoutChoice[] {
    return (wrapper.props() as { modelValue: ProfileLayoutChoice[] }).modelValue;
}

const CHOICES: ProfileLayoutChoice[] = [
    { key: 'core.about', enabled: true },
    { key: 'forum.latest', enabled: false },
];

describe('ProfileLayoutList', () => {
    it('lists each item with its switch, description and move buttons', () => {
        const wrapper = mountList(CHOICES);
        const items = wrapper.findAll('[data-testid="layout-item"]');

        expect(items.map((item) => item.attributes('data-key'))).toEqual([
            'core.about',
            'forum.latest',
        ]);
        expect(items[0]?.text()).toContain('Lo que cuentas de ti.');
        expect(items[0]?.get('[role="switch"]').attributes('aria-checked')).toBe('true');
        expect(items[1]?.get('[role="switch"]').attributes('aria-checked')).toBe('false');
        expect(items[0]?.get('[data-testid="move-up"]').attributes('disabled')).toBeDefined();
        expect(items[1]?.get('[data-testid="move-down"]').attributes('disabled')).toBeDefined();
        expect(items[0]?.get('[data-testid="move-down"]').attributes('aria-label')).toBe(
            'Bajar «Sobre mí»'
        );
        wrapper.unmount();
    });

    it('turns an item on and off', async () => {
        const wrapper = mountList(CHOICES);

        await wrapper.findAll('[role="switch"]')[1]?.trigger('click');

        expect(choicesOf(wrapper)).toEqual([
            { key: 'core.about', enabled: true },
            { key: 'forum.latest', enabled: true },
        ]);
        wrapper.unmount();
    });

    it('moves with the buttons, keeps the focus on the moved item and says where it is', async () => {
        const wrapper = mountList(CHOICES);

        await wrapper.findAll('[data-testid="move-down"]')[0]?.trigger('click');
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(choicesOf(wrapper).map((choice) => choice.key)).toEqual([
            'forum.latest',
            'core.about',
        ]);
        expect(wrapper.get('[data-testid="layout-announcement"]').text()).toBe(
            '«Sobre mí»: posición 2 de 2'
        );
        // At the end its «down» button is disabled: the focus goes to «up» of the same item
        const moved = wrapper.findAll('[data-testid="layout-item"]')[1];
        expect(document.activeElement).toBe(moved?.get('[data-testid="move-up"]').element);
        wrapper.unmount();
    });
});
