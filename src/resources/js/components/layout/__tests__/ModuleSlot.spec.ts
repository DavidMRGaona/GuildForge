import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import ModuleSlot from '../ModuleSlot.vue';

const Conversation = defineComponent({
    props: {
        subjectType: { type: String, required: true },
        subjectId: { type: String, required: true },
    },
    setup: (props) => (): ReturnType<typeof h> =>
        h('p', { 'data-testid': 'conversation' }, `${props.subjectType}:${props.subjectId}`),
});

const getSlotComponents = vi.fn((_name: string, slotProps: Record<string, unknown> = {}) => [
    {
        key: 'forum-Conversation-1',
        component: Conversation,
        props: { ...slotProps },
        registration: {},
    },
]);

vi.mock('@/composables/useModuleSlots', () => ({
    useModuleSlots: (): unknown => ({
        getSlotComponents,
        hasSlotComponents: (): boolean => true,
    }),
}));

describe('ModuleSlot', () => {
    it('hands the props of the page to the components in the slot', async () => {
        const wrapper = mount(ModuleSlot, {
            props: {
                name: 'subject-discussion',
                props: { subjectType: 'event', subjectId: 'abc' },
            },
        });
        await new Promise((resolve) => setTimeout(resolve));

        expect(getSlotComponents).toHaveBeenCalledWith('subject-discussion', {
            subjectType: 'event',
            subjectId: 'abc',
        });
        expect(wrapper.get('[data-testid="conversation"]').text()).toBe('event:abc');
    });
});
