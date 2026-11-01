import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick, ref, useTemplateRef } from 'vue';
import type { Ref, VNode } from 'vue';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { useFitsWithoutOverflow } from '../useFitsWithoutOverflow';

const observers: FakeResizeObserver[] = [];

class FakeResizeObserver {
    readonly observed: Element[] = [];
    disconnected = false;

    constructor(private readonly callback: () => void) {
        observers.push(this);
    }

    observe(element: Element): void {
        this.observed.push(element);
    }

    disconnect(): void {
        this.disconnected = true;
    }

    trigger(): void {
        this.callback();
    }
}

/** Width the row's content needs, read by the fake `scrollWidth` */
let contentWidth = 0;
/** Width the row has, read by the fake `clientWidth` */
let rowWidth = 0;
/** Whether the row was rendered in its full layout when it was measured */
let measuredInFullLayout: boolean[] = [];

function mountRow(trigger: Ref<number>): VueWrapper & { fits: () => boolean } {
    const result: { fits: Readonly<Ref<boolean>> | null } = { fits: null };

    const Host = defineComponent({
        setup(): () => VNode {
            const row = useTemplateRef<HTMLElement>('row');
            const fits = useFitsWithoutOverflow(row, () => trigger.value);
            result.fits = fits;

            return () => h('div', { ref: 'row', 'data-full': String(fits.value) });
        },
    });

    const wrapper = mount(Host, { attachTo: document.body });
    const element = wrapper.element as HTMLElement;
    Object.defineProperty(element, 'scrollWidth', {
        get: () => {
            measuredInFullLayout.push(element.dataset.full === 'true');
            return contentWidth;
        },
    });
    Object.defineProperty(element, 'clientWidth', { get: () => rowWidth });

    return Object.assign(wrapper, { fits: () => result.fits?.value ?? true });
}

async function settle(): Promise<void> {
    await nextTick();
    await nextTick();
}

describe('useFitsWithoutOverflow', () => {
    beforeEach(() => {
        observers.length = 0;
        measuredInFullLayout = [];
        contentWidth = 0;
        rowWidth = 0;
        vi.stubGlobal('ResizeObserver', FakeResizeObserver);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.body.innerHTML = '';
    });

    it('reports that the row fits while its content is no wider than the row', async () => {
        const wrapper = mountRow(ref(0));
        contentWidth = 900;
        rowWidth = 1000;

        observers[0]?.trigger();
        await settle();

        expect(wrapper.fits()).toBe(true);
    });

    it('reports that the row does not fit once its content overflows', async () => {
        const wrapper = mountRow(ref(0));
        contentWidth = 1080;
        rowWidth = 1024;

        observers[0]?.trigger();
        await settle();

        expect(wrapper.fits()).toBe(false);
    });

    it('measures the full layout again when the row grows', async () => {
        const wrapper = mountRow(ref(0));
        contentWidth = 1080;
        rowWidth = 1024;
        observers[0]?.trigger();
        await settle();

        rowWidth = 1280;
        measuredInFullLayout = [];
        observers[0]?.trigger();
        await settle();

        expect(measuredInFullLayout).toEqual([true]);
        expect(wrapper.fits()).toBe(true);
    });

    it('checks again when the watched source changes', async () => {
        const trigger = ref(0);
        const wrapper = mountRow(trigger);
        contentWidth = 900;
        rowWidth = 1024;
        observers[0]?.trigger();
        await settle();

        contentWidth = 1100;
        trigger.value++;
        await settle();

        expect(wrapper.fits()).toBe(false);
    });

    it('observes the row and stops observing it on unmount', () => {
        const wrapper = mountRow(ref(0));

        expect(observers[0]?.observed).toEqual([wrapper.element]);

        wrapper.unmount();

        expect(observers[0]?.disconnected).toBe(true);
    });

    it('assumes the row fits where ResizeObserver is not available', async () => {
        vi.stubGlobal('ResizeObserver', undefined);
        const wrapper = mountRow(ref(0));
        contentWidth = 1080;
        rowWidth = 1024;
        await settle();

        expect(wrapper.fits()).toBe(true);
    });
});
