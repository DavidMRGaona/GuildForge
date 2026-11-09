import { describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import SubjectDiscussion from '../SubjectDiscussion.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { SubjectDiscussion as SubjectDiscussionData } from '@/types/models';

function mountBlock(discussion: SubjectDiscussionData | null): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(SubjectDiscussion, {
        props: { discussion },
        global: { plugins: [i18n] },
    });
}

const discussion: SubjectDiscussionData = {
    discussion: {
        url: 'https://forum.example.org/d/31',
        actionUrl: 'https://web.example.org/foro/entrar?return=%2Fd%2F31',
        commentCount: 4,
        lastPostedAt: '2027-01-02T10:30:00+01:00',
    },
    topics: [
        {
            label: 'Sistema de ejemplo',
            url: 'https://forum.example.org/t/sistema-de-ejemplo',
            discussionCount: 3,
            lastPostedAt: null,
        },
    ],
};

describe('SubjectDiscussion', () => {
    it('shows nothing without a discussion', () => {
        expect(mountBlock(null).find('section').exists()).toBe(false);
    });

    it('shows the messages, the last one and the comment button', () => {
        const block = mountBlock(discussion);

        expect(block.text()).toContain('En el foro');
        expect(block.text()).toContain('4 mensajes');
        expect(block.text()).toContain('2 de enero de 2027');
        const comment = block.findAll('a').find((link) => link.text() === 'Comentar en el foro');
        expect(comment?.attributes('href')).toBe(
            'https://web.example.org/foro/entrar?return=%2Fd%2F31'
        );
    });

    it('lists the topics, also without a discussion of its own', () => {
        const block = mountBlock({ discussion: null, topics: discussion.topics });

        expect(block.text()).toContain('3 temas sobre Sistema de ejemplo');
        expect(block.text()).not.toContain('Comentar en el foro');
    });
});
