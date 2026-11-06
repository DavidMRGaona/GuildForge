import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { defineComponent, h, ref, type VNode } from 'vue';
import { useModalDialog } from '../useModalDialog';

const wrappers: VueWrapper[] = [];

/** A dialog with three buttons whose open state the test drives. */
function mountDialog(open = true): { wrapper: VueWrapper; open: ReturnType<typeof ref<boolean>> } {
    const isOpen = ref(open);
    const wrapper = mount(
        defineComponent({
            setup(): () => VNode {
                const container = ref<HTMLElement | null>(null);
                const { trapTab } = useModalDialog(() => isOpen.value, container, 'button');

                return () =>
                    h(
                        'div',
                        {
                            ref: container,
                            onKeydown: (event: KeyboardEvent) =>
                                event.key === 'Tab' && trapTab(event),
                        },
                        [h('button', 'Primero'), h('button', 'Segundo'), h('button', 'Último')]
                    );
            },
        }),
        { attachTo: document.body }
    );
    wrappers.push(wrapper);

    return { wrapper, open: isOpen };
}

function setScrollbarWidth(width: number): void {
    Object.defineProperty(window, 'innerWidth', { configurable: true, value: 1024 });
    Object.defineProperty(document.documentElement, 'clientWidth', {
        configurable: true,
        value: 1024 - width,
    });
}

function tab(target: Element, shiftKey = false): KeyboardEvent {
    const event = new KeyboardEvent('keydown', {
        key: 'Tab',
        shiftKey,
        bubbles: true,
        cancelable: true,
    });
    target.dispatchEvent(event);

    return event;
}

describe('useModalDialog', () => {
    beforeEach(() => {
        document.body.removeAttribute('style');
        setScrollbarWidth(0);
    });

    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
        document.body.innerHTML = '';
    });

    it('locks the page scroll while open and restores it on close', async () => {
        document.body.style.overflow = 'scroll';
        const { open } = mountDialog();

        expect(document.body.style.overflow).toBe('hidden');

        open.value = false;
        await Promise.resolve();
        expect(document.body.style.overflow).toBe('scroll');
    });

    it('keeps the width of a classic scrollbar so the page does not shift', async () => {
        setScrollbarWidth(15);
        const { open } = mountDialog();

        expect(document.body.style.paddingRight).toBe('15px');

        open.value = false;
        await Promise.resolve();
        expect(document.body.style.paddingRight).toBe('');
    });

    it('adds nothing when scrollbars take no room', () => {
        mountDialog();

        expect(document.body.style.paddingRight).toBe('');
    });

    it('releases the page only when the last of nested dialogs closes', async () => {
        const outer = mountDialog();
        const inner = mountDialog();

        inner.open.value = false;
        await Promise.resolve();
        expect(document.body.style.overflow).toBe('hidden');

        outer.open.value = false;
        await Promise.resolve();
        expect(document.body.style.overflow).toBe('');
    });

    it('releases the page when the dialog goes away while open', () => {
        const { wrapper } = mountDialog();

        wrapper.unmount();
        wrappers.splice(wrappers.indexOf(wrapper), 1);

        expect(document.body.style.overflow).toBe('');
    });

    it('wraps Tab at both ends and brings focus back in from outside', () => {
        const { wrapper } = mountDialog();
        const buttons = wrapper.findAll('button').map((button) => button.element as HTMLElement);

        buttons[2]?.focus();
        expect(tab(buttons[2] as HTMLElement).defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(buttons[0]);

        expect(tab(buttons[0] as HTMLElement, true).defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(buttons[2]);

        // In the middle the browser moves focus on its own
        buttons[1]?.focus();
        expect(tab(buttons[1] as HTMLElement).defaultPrevented).toBe(false);

        // Focus on the dialog itself (a click on a spot that takes none)
        (document.activeElement as HTMLElement | null)?.blur();
        expect(tab(wrapper.element).defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(buttons[0]);
    });
});
