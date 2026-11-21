import { describe, expect, it, vi } from 'vitest';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach } from 'vitest';
import { createI18n } from 'vue-i18n';
import ProfileBadges from '../ProfileBadges.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import { profileBadgeIconMap } from '@/utils/icons';
import { profileBadgeColourClasses } from '@/utils/profileBadges';
import type { ProfileBadge, ProfileBadgeSize } from '@/types/profileSections';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        name: 'InertiaLink',
        props: ['href'],
        template: '<a data-inertia :href="href"><slot /></a>',
    },
}));

enableAutoUnmount(afterEach);

function badge(overrides: Partial<ProfileBadge> = {}): ProfileBadge {
    return {
        key: 'achievements.first',
        label: 'Primera partida',
        description: 'Jugó su primera partida en la asociación.',
        icon: 'trophy',
        colour: 'amber',
        imageUrl: null,
        url: null,
        ...overrides,
    };
}

function mountBadges(badges: ProfileBadge[], size?: ProfileBadgeSize): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(ProfileBadges, {
        attachTo: document.body,
        props: size === undefined ? { badges } : { badges, size },
        global: { plugins: [i18n] },
    });
}

function tipOf(wrapper: VueWrapper, key: string): HTMLElement {
    const control = wrapper.get(`[data-badge="${key}"]`);
    const id = control.attributes('aria-describedby');
    expect(id).toBeDefined();

    return document.getElementById(id ?? '') as HTMLElement;
}

describe('ProfileBadges', () => {
    it('draws nothing without badges', () => {
        expect(mountBadges([]).find('ul').exists()).toBe(false);
    });

    it('lists the badges in order, named by their label, with their colour', () => {
        const wrapper = mountBadges([
            badge(),
            badge({ key: 'achievements.veteran', label: 'Veterana', colour: 'violet' }),
        ]);

        const list = wrapper.get('ul');
        expect(list.attributes('aria-label')).toBe('Insignias destacadas');
        expect(list.findAll('li').map((item) => item.get('[data-badge]').text())).toEqual([
            'Primera partida',
            'Veterana',
        ]);
        expect(wrapper.get('[data-badge="achievements.veteran"]').classes()).toEqual(
            expect.arrayContaining(profileBadgeColourClasses.violet.split(' '))
        );
    });

    it('gives the description to screen readers and shows it on focus, hover and a tap', async () => {
        const wrapper = mountBadges([badge()]);
        const control = wrapper.get('[data-badge="achievements.first"]');
        const tip = tipOf(wrapper, 'achievements.first');

        expect(control.element.tagName).toBe('BUTTON');
        expect(control.attributes('type')).toBe('button');
        expect(tip.textContent?.trim()).toBe('Jugó su primera partida en la asociación.');
        expect(tip.getAttribute('role')).toBe('tooltip');
        expect(tip.classList.contains('sr-only')).toBe(true);

        await control.trigger('focus');
        expect(tip.classList.contains('sr-only')).toBe(false);
        await control.trigger('blur');
        expect(tip.classList.contains('sr-only')).toBe(true);

        await wrapper.get('li').trigger('mouseenter');
        expect(tip.classList.contains('sr-only')).toBe(false);
        await wrapper.get('li').trigger('mouseleave');
        expect(tip.classList.contains('sr-only')).toBe(true);

        await control.trigger('click');
        expect(tip.classList.contains('sr-only')).toBe(false);
    });

    it('closes the tooltip with Escape and with a tap elsewhere', async () => {
        const wrapper = mountBadges([badge()]);
        const control = wrapper.get('[data-badge="achievements.first"]');
        const tip = tipOf(wrapper, 'achievements.first');

        await control.trigger('click');
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();
        expect(tip.classList.contains('sr-only')).toBe(true);

        await control.trigger('click');
        document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
        await wrapper.vm.$nextTick();
        expect(tip.classList.contains('sr-only')).toBe(true);
    });

    it('opens a badge with a link: the site through Inertia, anything else as a plain link', () => {
        const wrapper = mountBadges([
            badge({ url: '/logros/primera' }),
            badge({ key: 'achievements.out', url: 'https://example.test/logro' }),
        ]);

        const inside = wrapper.get('[data-badge="achievements.first"]');
        expect(inside.attributes('href')).toBe('/logros/primera');
        expect(inside.attributes('data-inertia')).toBeDefined();
        const outside = wrapper.get('[data-badge="achievements.out"]');
        expect(outside.element.tagName).toBe('A');
        expect(outside.attributes('href')).toBe('https://example.test/logro');
        expect(outside.attributes('data-inertia')).toBeUndefined();
        expect(outside.attributes('aria-describedby')).toBeDefined();
    });

    it('keeps a badge with nothing more to tell as plain text', () => {
        const wrapper = mountBadges([badge({ description: '' })]);

        const control = wrapper.get('[data-badge="achievements.first"]');
        expect(control.element.tagName).toBe('SPAN');
        expect(control.attributes('aria-describedby')).toBeUndefined();
        expect(wrapper.find('[role="tooltip"]').exists()).toBe(false);
    });

    it('draws the icon by default and the image instead when there is one', () => {
        const withIcon = mountBadges([badge()]);
        expect(withIcon.find('[data-badge] svg').attributes('aria-hidden')).toBe('true');
        expect(withIcon.find('[data-badge] img').exists()).toBe(false);

        const withImage = mountBadges([
            badge({ imageUrl: 'https://res.cloudinary.com/demo/b.png' }),
        ]);
        const image = withImage.get('[data-badge] img');
        expect(image.attributes('src')).toBe('https://res.cloudinary.com/demo/b.png');
        // The label is right beside it
        expect(image.attributes('alt')).toBe('');
        expect(withImage.find('[data-badge] svg').exists()).toBe(false);
    });

    it('draws only icons when small, with the label as their name and in the tooltip', async () => {
        const wrapper = mountBadges(
            [
                badge(),
                badge({
                    key: 'achievements.image',
                    imageUrl: 'https://res.cloudinary.com/demo/b.png',
                }),
            ],
            'sm'
        );

        const icon = wrapper.get('[data-badge="achievements.first"]');
        expect(icon.get('.sr-only').text()).toBe('Primera partida');
        expect(wrapper.get('[data-badge="achievements.image"] img').attributes('alt')).toBe(
            'Primera partida'
        );

        await icon.trigger('focus');
        const tip = tipOf(wrapper, 'achievements.first');
        expect(tip.textContent).toContain('Primera partida');
        // A screen reader already has the label as the name: only the description describes it
        expect(tip.querySelector('[aria-hidden="true"]')?.textContent?.trim()).toBe(
            'Primera partida'
        );
    });

    it('still tells the label of a small badge without description', () => {
        const wrapper = mountBadges([badge({ description: '' })], 'sm');

        const control = wrapper.get('[data-badge="achievements.first"]');
        expect(control.element.tagName).toBe('BUTTON');
        expect(control.attributes('aria-describedby')).toBeUndefined();
        expect(wrapper.get('[role="tooltip"]').text()).toBe('Primera partida');
    });

    it('draws an icon it does not know as an award', () => {
        const wrapper = mountBadges([badge({ icon: 'not-an-icon' })]);
        const known = mountBadges([badge({ icon: 'award' })]);

        expect(wrapper.get('[data-badge] svg').html()).toBe(known.get('[data-badge] svg').html());
        expect(Object.keys(profileBadgeIconMap)).toContain('award');
    });
});
