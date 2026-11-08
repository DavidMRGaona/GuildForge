import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SocialLinks from '../SocialLinks.vue';
import type { SocialMediaLinks } from '@/types/models';

const ALL: (keyof SocialMediaLinks)[] = [
    'facebook',
    'instagram',
    'twitter',
    'discord',
    'tiktok',
    'bluesky',
    'telegram',
    'whatsapp',
    'youtube',
    'twitch',
];

function linksFor(count: number): SocialMediaLinks {
    return Object.fromEntries(
        ALL.slice(0, count).map((key) => [key, `https://example.test/${key}`])
    );
}

function maxWidthFor(count: number): string {
    const wrapper = mount(SocialLinks, { props: { links: linksFor(count), balanced: true } });
    return (wrapper.element as HTMLElement).style.maxWidth;
}

describe('SocialLinks', () => {
    it('renders nothing without links', () => {
        const wrapper = mount(SocialLinks, { props: { links: {}, balanced: true } });
        expect(wrapper.find('a').exists()).toBe(false);
    });

    it('shows WhatsApp, YouTube and Twitch with their names', () => {
        const wrapper = mount(SocialLinks, {
            props: {
                links: {
                    whatsapp: 'https://wa.me/1',
                    youtube: 'https://youtube.com/@a',
                    twitch: 'https://twitch.tv/a',
                },
            },
        });
        const labels = wrapper.findAll('a').map((a) => a.attributes('aria-label'));
        expect(labels).toEqual(['WhatsApp', 'YouTube', 'Twitch']);
        expect(
            wrapper.findAll('svg path').every((p) => (p.attributes('d') ?? '').length > 20)
        ).toBe(true);
    });

    it('skips networks with an empty address', () => {
        const wrapper = mount(SocialLinks, {
            props: { links: { discord: '', telegram: 'https://t.me/a' } },
        });
        expect(wrapper.findAll('a')).toHaveLength(1);
    });

    it.each([
        [1, 1],
        [2, 2],
        [5, 5],
        [6, 3],
        [7, 4],
        [8, 4],
        [9, 5],
        [10, 5],
    ])('lays %i networks in rows of at most %i', (count, columns) => {
        expect(maxWidthFor(count)).toBe(`calc(${columns} * 2.75rem + ${columns - 1} * 0.75rem)`);
    });

    it('uses 32 px tiles for the small size', () => {
        const wrapper = mount(SocialLinks, {
            props: { links: linksFor(10), balanced: true, size: 'sm' },
        });
        expect((wrapper.element as HTMLElement).style.maxWidth).toBe(
            'calc(5 * 2rem + 4 * 0.75rem)'
        );
    });

    it('gives balanced icons a 44 px touch target', () => {
        const wrapper = mount(SocialLinks, { props: { links: linksFor(3), balanced: true } });
        expect(wrapper.find('a').classes()).toEqual(expect.arrayContaining(['h-11', 'w-11']));
    });
});
