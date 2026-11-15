import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { h, type FunctionalComponent } from 'vue';
import SiteBlock from '../SiteBlock.vue';
import { useSiteBlockPlacement } from '@/composables/useSiteBlockPlacement';
import type { ResolvedSiteBlock } from '@/types/siteBlocks';

const Subscribe: FunctionalComponent<{ heading: string }> = (props) =>
    h('p', { 'data-testid': 'subscribe' }, props.heading);
Subscribe.props = ['heading'];

const Placed: FunctionalComponent = (props) => {
    const placement = useSiteBlockPlacement();

    return h(
        'p',
        { 'data-testid': 'placed', 'data-received': JSON.stringify(props) },
        placement.value
    );
};

const Broken: FunctionalComponent = () => {
    throw new Error('Broken block');
};

const resolveModuleComponent = vi.fn((_module: string, path: string) =>
    path === 'components/Broken.vue'
        ? Broken
        : path === 'components/Placed.vue'
          ? Placed
          : Subscribe
);

vi.mock('@/composables/useModuleSlots', () => ({
    resolveModuleComponent: (module: string, path: string): unknown =>
        resolveModuleComponent(module, path),
}));

function block(overrides: Partial<ResolvedSiteBlock> = {}): ResolvedSiteBlock {
    return {
        key: 'newsletter.subscribe',
        module: 'newsletter',
        component: 'components/SubscribeBlock.vue',
        props: { heading: 'Suscríbete' },
        ...overrides,
    };
}

describe('SiteBlock', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        resolveModuleComponent.mockClear();
    });

    it('loads a module block from its module and binds its props', async () => {
        const wrapper = mount(SiteBlock, { props: { block: block() } });
        await flushPromises();

        expect(resolveModuleComponent).toHaveBeenCalledWith(
            'newsletter',
            'components/SubscribeBlock.vue'
        );
        expect(wrapper.get('[data-testid="subscribe"]').text()).toBe('Suscríbete');
        expect(wrapper.find('[data-site-block="newsletter.subscribe"]').exists()).toBe(true);
    });

    it.each(['footer', 'popup'] as const)(
        'tells the block it is drawn in the %s',
        async (placement) => {
            const wrapper = mount(SiteBlock, {
                props: { block: block({ component: 'components/Placed.vue' }), placement },
            });
            await flushPromises();

            expect(wrapper.get('[data-testid="placed"]').text()).toBe(placement);
            // A context, not an attribute: the block itself never receives a `placement` prop
            expect(
                JSON.parse(wrapper.get('[data-testid="placed"]').attributes('data-received'))
            ).not.toHaveProperty('placement');
        }
    );

    it('draws in the footer when nobody says otherwise', async () => {
        const wrapper = mount(SiteBlock, {
            props: { block: block({ component: 'components/Placed.vue' }) },
        });
        await flushPromises();

        expect(wrapper.get('[data-testid="placed"]').text()).toBe('footer');
    });

    it('still draws a block that never asks for its placement', async () => {
        const wrapper = mount(SiteBlock, { props: { block: block(), placement: 'popup' } });
        await flushPromises();

        expect(wrapper.get('[data-testid="subscribe"]').text()).toBe('Suscríbete');
    });

    it('keeps a failing block to itself', async () => {
        vi.spyOn(console, 'error').mockImplementation(() => undefined);
        const Page: FunctionalComponent = () =>
            h('main', [
                h(SiteBlock, { block: block({ component: 'components/Broken.vue' }) }),
                h('p', { 'data-testid': 'rest' }, 'Resto de la página'),
            ]);

        const wrapper = mount(Page);
        await flushPromises();

        expect(wrapper.get('[data-testid="rest"]').text()).toBe('Resto de la página');
        expect(wrapper.find('[data-site-block]').exists()).toBe(false);
        expect(console.error).toHaveBeenCalledWith(
            '[SiteBlock] Error in block "newsletter.subscribe":',
            'Broken block',
            expect.any(String)
        );
    });

    it('draws nothing for a core block the core does not have', async () => {
        vi.spyOn(console, 'warn').mockImplementation(() => undefined);

        const wrapper = mount(SiteBlock, {
            props: {
                block: block({
                    key: 'core.missing',
                    module: null,
                    component: 'components/siteBlocks/Missing.vue',
                }),
            },
        });
        await flushPromises();

        expect(resolveModuleComponent).not.toHaveBeenCalled();
        expect(wrapper.find('[data-site-block]').exists()).toBe(false);
        expect(console.warn).toHaveBeenCalled();
    });
});
