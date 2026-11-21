import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, type VNode } from 'vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProfileBadges from '../ProfileBadges.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import { profileBadgeIconMap } from '@/utils/icons';
import { profileBadgeColourClasses } from '@/utils/profileBadges';
import type { ProfileBadge, ProfileBadgeSize } from '@/types/profileSections';

// Like Inertia's own Link: an `<a>` that passes its attributes and listeners on
vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        name: 'InertiaLink',
        inheritAttrs: true,
        props: { href: { type: String, required: true } },
        setup(props, { slots }) {
            return (): VNode => h('a', { href: props.href, 'data-inertia': '' }, slots.default?.());
        },
    }),
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

/** The visible tooltip, drawn in `<body>` */
function tip(): HTMLElement | null {
    return document.body.querySelector<HTMLElement>('[data-testid="profile-badge-tip"]');
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
        expect(list.findAll('[data-badge]').map((item) => item.text())).toEqual([
            'Primera partida',
            'Veterana',
        ]);
        expect(wrapper.get('[data-badge="achievements.veteran"]').classes()).toEqual(
            expect.arrayContaining(profileBadgeColourClasses.violet.split(' '))
        );
    });

    it('gives the description to screen readers once, in a hidden element', () => {
        const wrapper = mountBadges([badge()]);
        const control = wrapper.get('[data-badge="achievements.first"]');
        const id = control.attributes('aria-describedby') ?? '';
        const description = document.getElementById(id);

        expect(control.element.tagName).toBe('BUTTON');
        expect(control.attributes('type')).toBe('button');
        expect(description?.textContent?.trim()).toBe('Jugó su primera partida en la asociación.');
        expect(description?.hidden).toBe(true);
    });

    it('shows the tooltip on focus, hover and a tap, with the whole label', async () => {
        const wrapper = mountBadges([badge()]);
        const control = wrapper.get('[data-badge="achievements.first"]');

        expect(tip()).toBeNull();
        await control.trigger('focus');
        expect(tip()?.getAttribute('aria-hidden')).toBe('true');
        expect(tip()?.textContent).toContain('Primera partida');
        expect(tip()?.textContent).toContain('Jugó su primera partida en la asociación.');
        await control.trigger('blur');
        expect(tip()).toBeNull();

        await wrapper.get('li').trigger('mouseenter');
        expect(tip()).not.toBeNull();
        vi.useFakeTimers();
        await wrapper.get('li').trigger('mouseleave');
        // The pointer may move onto the tooltip before it closes
        expect(tip()).not.toBeNull();
        vi.runAllTimers();
        vi.useRealTimers();
        await wrapper.vm.$nextTick();
        expect(tip()).toBeNull();

        await control.trigger('click');
        expect(tip()).not.toBeNull();
    });

    it('keeps the tooltip open while the pointer is on it', async () => {
        const wrapper = mountBadges([badge()]);

        await wrapper.get('li').trigger('mouseenter');
        vi.useFakeTimers();
        await wrapper.get('li').trigger('mouseleave');
        tip()?.dispatchEvent(new MouseEvent('mouseenter'));
        vi.runAllTimers();
        vi.useRealTimers();
        await wrapper.vm.$nextTick();

        expect(tip()).not.toBeNull();
    });

    it('draws the tooltip outside the list, so a host that clips its content never cuts it', async () => {
        const wrapper = mountBadges([badge()]);

        await wrapper.get('[data-badge]').trigger('focus');

        expect(tip()?.parentElement).toBe(document.body);
        expect(wrapper.element.contains(tip())).toBe(false);
        expect(tip()?.classList.contains('fixed')).toBe(true);
    });

    it('goes below the badge when it fits and above when it does not, inside the window', async () => {
        vi.spyOn(HTMLElement.prototype, 'offsetWidth', 'get').mockReturnValue(200);
        vi.spyOn(HTMLElement.prototype, 'offsetHeight', 'get').mockReturnValue(60);
        const wrapper = mountBadges([badge()]);
        const control = wrapper.get('[data-badge]').element;
        const at = (top: number, left: number): void => {
            vi.spyOn(control, 'getBoundingClientRect').mockReturnValue(
                DOMRect.fromRect({ x: left, y: top, width: 100, height: 32 })
            );
        };

        at(100, 0);
        await wrapper.get('[data-badge]').trigger('focus');
        await wrapper.vm.$nextTick();
        // Below, 8 px under the badge, and pushed right to stay 8 px inside the window
        expect(tip()?.style.top).toBe('140px');
        expect(tip()?.style.left).toBe('8px');
        await wrapper.get('[data-badge]').trigger('blur');

        at(window.innerHeight - 40, 300);
        await wrapper.get('[data-badge]').trigger('focus');
        await wrapper.vm.$nextTick();
        expect(tip()?.style.top).toBe(`${window.innerHeight - 40 - 8 - 60}px`);
        expect(tip()?.style.left).toBe('250px');

        vi.restoreAllMocks();
    });

    it('closes the tooltip with Escape and with a tap elsewhere', async () => {
        const wrapper = mountBadges([badge()]);
        const control = wrapper.get('[data-badge="achievements.first"]');

        await control.trigger('click');
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();
        expect(tip()).toBeNull();

        await control.trigger('click');
        document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
        await wrapper.vm.$nextTick();
        expect(tip()).toBeNull();
    });

    it('opens a badge with a link: the site through Inertia, anything else as a plain link', async () => {
        const wrapper = mountBadges([
            badge({ url: '/logros/primera' }),
            badge({ key: 'achievements.out', url: 'https://example.test/logro' }),
        ]);

        const inside = wrapper.get('[data-badge="achievements.first"]');
        expect(inside.attributes('href')).toBe('/logros/primera');
        expect(inside.attributes('data-inertia')).toBeDefined();
        expect(inside.attributes('aria-describedby')).toBeDefined();
        const outside = wrapper.get('[data-badge="achievements.out"]');
        expect(outside.element.tagName).toBe('A');
        expect(outside.attributes('href')).toBe('https://example.test/logro');
        expect(outside.attributes('data-inertia')).toBeUndefined();

        // Keyboard focus on the link Inertia draws opens the tooltip too
        await inside.trigger('focus');
        expect(tip()?.textContent).toContain('Primera partida');
    });

    it('still opens on a badge without description, to show its whole label', async () => {
        const wrapper = mountBadges([badge({ description: '' })]);

        const control = wrapper.get('[data-badge="achievements.first"]');
        expect(control.element.tagName).toBe('BUTTON');
        expect(control.attributes('aria-describedby')).toBeUndefined();
        await control.trigger('focus');
        expect(tip()?.textContent?.trim()).toBe('Primera partida');
    });

    it('cuts a long label on the badge itself', () => {
        const wrapper = mountBadges([
            badge({ label: 'Narradora de campañas legendarias del norte' }),
        ]);

        expect(wrapper.get('[data-badge] span.truncate').classes()).toContain('max-w-48');
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
        expect(tip()?.textContent).toContain('Primera partida');
    });

    it('draws an icon it does not know as an award', () => {
        const wrapper = mountBadges([badge({ icon: 'not-an-icon' })]);
        const known = mountBadges([badge({ icon: 'award' })]);

        expect(wrapper.get('[data-badge] svg').html()).toBe(known.get('[data-badge] svg').html());
        expect(Object.keys(profileBadgeIconMap)).toContain('award');
    });
});
