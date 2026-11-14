import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import FormSelect from '../FormSelect.vue';
import type { SelectOption } from '../selectOptions';
import en from '@/locales/en';
import es from '@/locales/es';

function i18n(): ReturnType<typeof createI18n> {
    return createI18n({ legacy: false, locale: 'es', messages: { es, en } });
}

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
        global: { plugins: [i18n()] },
    });
    const button = wrapper.find('#sel').element as HTMLElement;
    button.getBoundingClientRect = (): DOMRect => rect(buttonRect);
    await wrapper.find('#sel').trigger('click');
    await flushPromises();

    return document.querySelector('[data-select-panel]') as HTMLElement;
}

describe('FormSelect', () => {
    beforeEach(() => {
        buttonRect = { top: 100, bottom: 140, left: 20, right: 320, width: 300, height: 40 };
        containingBlock = null;
        Object.defineProperty(window, 'innerHeight', { value: 800, configurable: true });
        Object.defineProperty(HTMLElement.prototype, 'offsetHeight', {
            configurable: true,
            get(this: HTMLElement): number {
                return this.hasAttribute('data-select-panel') ? 120 : 0;
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
            global: { plugins: [i18n()] },
        });
        // No layout for the field: the list cannot be placed, only kept out of the flow
        await wrapper.find('#sel').trigger('click');
        await flushPromises();

        const list = document.querySelector('[data-select-panel]') as HTMLElement;
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

    describe('search and tree (core#648)', () => {
        const categories: SelectOption[] = [
            { value: 'general', label: 'General' },
            { value: 'pbf', label: 'Partidas por foro' },
            { value: 'tables', label: 'Mesas' },
            { value: 'campaigns', label: 'Campañas' },
            { value: 'st', label: 'Stranger Things', level: 1 },
            { value: 'sea', label: 'Conspiraciones y Acero: 7º Mar', level: 1 },
            { value: 'pirates', label: 'La estatua robada', level: 1 },
            { value: 'march', label: 'Aventureros por la Marca del Este', level: 1 },
            { value: 'news', label: 'Novedades' },
        ];

        async function openWith(props: Record<string, unknown>): Promise<VueWrapper> {
            wrapper = mount(FormSelect, {
                props: {
                    modelValue: null,
                    options: categories,
                    placeholder: 'Cualquier categoría',
                    id: 'cat',
                    ...props,
                },
                attachTo: document.body,
                global: { plugins: [i18n()] },
            });
            await wrapper.find('#cat').trigger('click');
            await flushPromises();

            return wrapper;
        }

        function shownLabels(): string[] {
            return [...document.querySelectorAll<HTMLElement>('[role="option"]')].map(
                (option) => option.textContent?.trim() ?? ''
            );
        }

        function searchBox(): HTMLInputElement | null {
            return document.querySelector<HTMLInputElement>(
                '[data-select-panel] input[role="combobox"]'
            );
        }

        it('has a search box from eight options, and puts the focus in it', async () => {
            await openWith({});

            expect(searchBox()).not.toBeNull();
            expect(document.activeElement).toBe(searchBox());
        });

        it('has no search box in a short list unless asked', async () => {
            await openWith({ options: categories.slice(0, 3) });
            expect(searchBox()).toBeNull();
            wrapper?.unmount();

            await openWith({ options: categories.slice(0, 3), searchable: true });
            expect(searchBox()).not.toBeNull();
        });

        it('finds options without minding accents or case', async () => {
            await openWith({});
            const input = searchBox() as HTMLInputElement;
            input.value = 'ACERO 7o mar';
            input.dispatchEvent(new Event('input'));
            await flushPromises();

            // The parent comes along, as context
            expect(shownLabels()).toEqual(['Campañas', 'Conspiraciones y Acero: 7º Mar']);
        });

        it('lists every child when the parent is searched for', async () => {
            await openWith({});
            const input = searchBox() as HTMLInputElement;
            input.value = 'campanas';
            input.dispatchEvent(new Event('input'));
            await flushPromises();

            expect(shownLabels()).toEqual([
                'Campañas',
                'Stranger Things',
                'Conspiraciones y Acero: 7º Mar',
                'La estatua robada',
                'Aventureros por la Marca del Este',
            ]);
        });

        it('says so when nothing matches', async () => {
            await openWith({});
            const input = searchBox() as HTMLInputElement;
            input.value = 'zzz';
            input.dispatchEvent(new Event('input'));
            await flushPromises();

            expect(shownLabels()).toEqual([]);
            expect(document.querySelector('[data-select-panel]')?.textContent).toContain(
                'No se encontraron resultados'
            );
        });

        it('draws children indented, named with their parent, and divides the blocks', async () => {
            await openWith({});
            const options = [...document.querySelectorAll<HTMLElement>('[role="option"]')];
            const child = options.find(
                (option) => option.textContent?.trim() === 'Stranger Things'
            );

            expect(child?.classList.contains('pl-10')).toBe(true);
            expect(child?.getAttribute('aria-label')).toBe('Campañas › Stranger Things');
            // One divider above the block of «Campañas», one after it
            expect(document.querySelectorAll('[data-select-panel] li.border-t')).toHaveLength(2);
        });

        it('shows a chosen child with its parent in the closed field', async () => {
            wrapper = mount(FormSelect, {
                props: {
                    modelValue: 'st',
                    options: categories,
                    placeholder: 'Cualquier categoría',
                    id: 'cat',
                },
                global: { plugins: [i18n()] },
            });

            expect(wrapper.find('#cat').text()).toBe('Campañas › Stranger Things');
        });

        it('picks with the arrows and Enter, and gives the focus back', async () => {
            await openWith({});
            const input = searchBox() as HTMLInputElement;
            input.value = 'marca';
            input.dispatchEvent(new Event('input'));
            await flushPromises();
            // «Campañas» is shown first, as context; one step down is the match
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await flushPromises();

            expect(wrapper?.emitted('update:modelValue')?.[0]?.[0]).toBe('march');
            expect(document.querySelector('[data-select-panel]')).toBeNull();
            expect(document.activeElement?.id).toBe('cat');
        });

        it('closes on Escape without letting a dialog close too', async () => {
            await openWith({});
            const escape = new KeyboardEvent('keydown', {
                key: 'Escape',
                bubbles: true,
                cancelable: true,
            });
            const outer = vi.fn();
            document.body.addEventListener('keydown', outer);
            searchBox()?.dispatchEvent(escape);
            await flushPromises();
            document.body.removeEventListener('keydown', outer);

            expect(escape.defaultPrevented).toBe(true);
            expect(outer).not.toHaveBeenCalled();
            expect(document.querySelector('[data-select-panel]')).toBeNull();
        });

        it('closes when something else is pressed', async () => {
            await openWith({});
            document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
            await flushPromises();

            expect(document.querySelector('[data-select-panel]')).toBeNull();
        });

        it('jumps by typing when there is no search box', async () => {
            await openWith({ options: categories.slice(0, 3) });
            const list = document.querySelector('[role="listbox"]') as HTMLElement;
            list.dispatchEvent(new KeyboardEvent('keydown', { key: 'm', bubbles: true }));
            list.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await flushPromises();

            expect(wrapper?.emitted('update:modelValue')?.[0]?.[0]).toBe('tables');
        });

        it('opens from the keyboard', async () => {
            wrapper = mount(FormSelect, {
                props: { modelValue: null, options: categories, id: 'cat' },
                attachTo: document.body,
                global: { plugins: [i18n()] },
            });
            await wrapper.find('#cat').trigger('keydown', { key: 'ArrowDown' });
            await flushPromises();

            expect(wrapper.find('#cat').attributes('aria-expanded')).toBe('true');
        });
    });
});
