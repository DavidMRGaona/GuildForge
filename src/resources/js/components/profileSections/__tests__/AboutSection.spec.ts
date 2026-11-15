import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import AboutSection from '../AboutSection.vue';

describe('AboutSection', () => {
    it('draws each line and keeps the line breaks', () => {
        const wrapper = mount(AboutSection, {
            props: {
                lines: [
                    [{ text: 'Dirijo los martes.', href: null }],
                    [],
                    [{ text: 'Y juego los jueves.', href: null }],
                ],
            },
        });

        expect(wrapper.findAll('br')).toHaveLength(2);
        expect(wrapper.text()).toContain('Dirijo los martes.');
        expect(wrapper.text()).toContain('Y juego los jueves.');
    });

    it('draws links with nofollow, ugc and noopener', () => {
        const wrapper = mount(AboutSection, {
            props: {
                lines: [
                    [
                        { text: 'Mi blog: ', href: null },
                        { text: 'https://example.com', href: 'https://example.com' },
                    ],
                ],
            },
        });

        const link = wrapper.get('a');
        expect(link.attributes('href')).toBe('https://example.com');
        expect(link.attributes('rel')).toBe('nofollow ugc noopener');
        expect(link.text()).toBe('https://example.com');
    });

    it('never draws what the owner wrote as HTML', () => {
        const wrapper = mount(AboutSection, {
            props: {
                lines: [
                    [
                        {
                            text: '<img src=x onerror="alert(1)"><script>alert(1)</script>',
                            href: null,
                        },
                    ],
                ],
            },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.find('script').exists()).toBe(false);
        expect(wrapper.text()).toBe('<img src=x onerror="alert(1)"><script>alert(1)</script>');
    });
});
