import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import FormSelect from '../FormSelect.vue';

const options = [
    { value: 'a', label: 'Alfa' },
    { value: 'b', label: 'Beta' },
    { value: 'c', label: 'Gamma' },
];

let wrapper: VueWrapper | null = null;
/** Where a transformed ancestor puts `fixed` (0,0); null lays nothing out, as jsdom does. */
let containingBlock: { x: number; y: number } | null = null;
const originalRect = HTMLElement.prototype.getBoundingClientRect;
const originalHeight = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'offsetHeight');
let buttonRect = { top: 100, bottom: 140, left: 20, right: 320, width: 300, height: 40 };

function rect(r: typeof buttonRect): DOMRect {
    return { ...r, x: r.left, y: r.top, toJSON: () => r } as DOMRect;
}

async function open(): Promise<HTMLElement> {
    wrapper = mount(FormSelect, {
        props: { modelValue: null, options, placeholder: 'Elige', id: 'sel' },
        attachTo: document.body,
    });
    const button = wrapper.find('#sel').element as HTMLElement;
    button.getBoundingClientRect = (): DOMRect => rect(buttonRect);
    await wrapper.find('#sel').trigger('click');
    await flushPromises();

    return document.querySelector('[role="listbox"]') as HTMLElement;
}

describe('FormSelect', () => {
    beforeEach(() => {
        buttonRect = { top: 100, bottom: 140, left: 20, right: 320, width: 300, height: 40 };
        containingBlock = null;
        Object.defineProperty(window, 'innerHeight', { value: 800, configurable: true });
        Object.defineProperty(HTMLElement.prototype, 'offsetHeight', {
            configurable: true,
            get(this: HTMLElement): number {
                return this.getAttribute('role') === 'listbox' ? 120 : 0;
            },
        });
        HTMLElement.prototype.getBoundingClientRect = function (this: HTMLElement): DOMRect {
            if (!this.hasAttribute('data-pinned-probe') || containingBlock === null) {
                return originalRect.call(this);
            }
            const { x, y } = containingBlock;

            return rect({ top: y, bottom: y, left: x, right: x, width: 0, height: 0 });
        };
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        HTMLElement.prototype.getBoundingClientRect = originalRect;
        if (originalHeight) {
            Object.defineProperty(HTMLElement.prototype, 'offsetHeight', originalHeight);
        }
    });

    it('pins the open list under the field, so no scrolling parent clips it', async () => {
        const list = await open();

        expect(list.style.position).toBe('fixed');
        expect(list.style.top).toBe('144px');
        expect(list.style.left).toBe('20px');
        expect(list.style.width).toBe('300px');
    });

    it('opens upwards when there is no room below', async () => {
        buttonRect = { top: 700, bottom: 740, left: 20, right: 320, width: 300, height: 40 };

        const list = await open();

        // 700 - 4 of gap - 120 of list
        expect(list.style.top).toBe('576px');
    });

    it('follows the field when the page or a parent scrolls', async () => {
        const list = await open();

        buttonRect = { top: 60, bottom: 100, left: 20, right: 320, width: 300, height: 40 };
        document.body.dispatchEvent(new Event('scroll'));
        await flushPromises();

        expect(list.style.top).toBe('104px');
    });

    it('lands where it should under a transformed parent, which moves what fixed counts from', async () => {
        containingBlock = { x: 0, y: -120 };

        const list = await open();
        await flushPromises();

        // Fixed (0,0) lands 120 px above the window's corner: ask 120 lower to land at 144
        expect(list.style.top).toBe('264px');
    });

    it('is fixed before it is placed, so it never grows the dialog that holds the field', async () => {
        wrapper = mount(FormSelect, {
            props: { modelValue: null, options, placeholder: 'Elige', id: 'sel' },
            attachTo: document.body,
        });
        // No layout for the field: the list cannot be placed, only kept out of the flow
        await wrapper.find('#sel').trigger('click');
        await flushPromises();

        const list = document.querySelector('[role="listbox"]') as HTMLElement;
        expect(list.style.position).toBe('fixed');
    });

    it('still picks an option', async () => {
        await open();

        const gamma = [...document.querySelectorAll<HTMLElement>('[role="option"]')].find(
            (option) => option.textContent?.trim() === 'Gamma'
        );
        gamma?.click();
        await flushPromises();

        expect(wrapper?.emitted('update:modelValue')?.[0]?.[0]).toBe('c');
    });
});
