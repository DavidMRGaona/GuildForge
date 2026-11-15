import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { h, type FunctionalComponent } from 'vue';
import ProfileSection from '../ProfileSection.vue';
import type { ResolvedProfileSection } from '@/types/profiles';

const LatestPosts: FunctionalComponent<{ count: number }> = (props) =>
    h('p', { 'data-testid': 'latest' }, `${props.count} mensajes`);
LatestPosts.props = ['count'];

const Broken: FunctionalComponent = () => {
    throw new Error('Broken section');
};

const resolveModuleComponent = vi.fn((_module: string, path: string) =>
    path === 'components/Broken.vue' ? Broken : LatestPosts
);

vi.mock('@/composables/useModuleSlots', () => ({
    resolveModuleComponent: (module: string, path: string): unknown =>
        resolveModuleComponent(module, path),
}));

function section(overrides: Partial<ResolvedProfileSection> = {}): ResolvedProfileSection {
    return {
        key: 'forum.latest_posts',
        module: 'forum',
        component: 'components/LatestPostsSection.vue',
        region: 'main',
        label: 'Últimos mensajes',
        props: { count: 3 },
        ...overrides,
    };
}

describe('ProfileSection', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        resolveModuleComponent.mockClear();
    });

    it('draws a module section under its title and binds its props', async () => {
        const wrapper = mount(ProfileSection, { props: { section: section() } });
        await flushPromises();

        expect(resolveModuleComponent).toHaveBeenCalledWith(
            'forum',
            'components/LatestPostsSection.vue'
        );
        const card = wrapper.get('[data-profile-section="forum.latest_posts"]');
        const heading = card.get('h2');
        expect(heading.text()).toBe('Últimos mensajes');
        expect(card.attributes('aria-labelledby')).toBe(heading.attributes('id'));
        expect(wrapper.get('[data-testid="latest"]').text()).toBe('3 mensajes');
    });

    it('draws a core section from the core build', async () => {
        const wrapper = mount(ProfileSection, {
            props: {
                section: section({
                    key: 'core.about',
                    module: null,
                    component: 'components/profileSections/AboutSection.vue',
                    region: 'aside',
                    label: 'Sobre mí',
                    props: { lines: [[{ text: 'Hola', href: null }]] },
                }),
            },
        });
        await flushPromises();

        expect(resolveModuleComponent).not.toHaveBeenCalled();
        // The core section is loaded on demand from the core build
        await vi.waitFor(async () => {
            await flushPromises();
            expect(wrapper.get('[data-profile-section="core.about"] p').text()).toBe('Hola');
        });
    });

    it('keeps a failing section to itself, title included', async () => {
        vi.spyOn(console, 'error').mockImplementation(() => undefined);
        const Page: FunctionalComponent = () =>
            h('main', [
                h(ProfileSection, { section: section({ component: 'components/Broken.vue' }) }),
                h('p', { 'data-testid': 'rest' }, 'Resto del perfil'),
            ]);

        const wrapper = mount(Page);
        await flushPromises();

        expect(wrapper.get('[data-testid="rest"]').text()).toBe('Resto del perfil');
        expect(wrapper.find('[data-profile-section]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Últimos mensajes');
        expect(console.error).toHaveBeenCalledWith(
            '[ProfileSection] Error in section "forum.latest_posts":',
            'Broken section',
            expect.any(String)
        );
    });

    it('draws nothing for a core section the core does not have', async () => {
        vi.spyOn(console, 'warn').mockImplementation(() => undefined);

        const wrapper = mount(ProfileSection, {
            props: {
                section: section({
                    key: 'core.missing',
                    module: null,
                    component: 'components/profileSections/Missing.vue',
                }),
            },
        });
        await flushPromises();

        expect(wrapper.find('[data-profile-section]').exists()).toBe(false);
        expect(console.warn).toHaveBeenCalled();
    });
});
