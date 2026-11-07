import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, nextTick, shallowRef, type Ref } from 'vue';
import { mount } from '@vue/test-utils';
import { useFitsViewportHeight } from '../useFitsViewportHeight';

type ObserverCallback = () => void;

let observerCallback: ObserverCallback | null = null;

class FakeResizeObserver {
    constructor(callback: ObserverCallback) {
        observerCallback = callback;
    }

    observe(): void {}

    disconnect(): void {
        observerCallback = null;
    }
}

function mountWithHeight(
    height: number,
    margin = 0
): { fits: Readonly<Ref<boolean>>; setHeight: (h: number) => void } {
    let fits: Readonly<Ref<boolean>> | null = null;
    let elementHeight = height;

    const Harness = defineComponent({
        setup() {
            const target = shallowRef<HTMLElement | null>(null);
            fits = useFitsViewportHeight(target, margin);
            return { target };
        },
        template: '<div ref="target" />',
    });

    const wrapper = mount(Harness);
    Object.defineProperty(wrapper.element, 'offsetHeight', { get: () => elementHeight });

    return {
        fits: fits as unknown as Readonly<Ref<boolean>>,
        setHeight: (h: number): void => {
            elementHeight = h;
        },
    };
}

beforeEach(() => {
    vi.stubGlobal('ResizeObserver', FakeResizeObserver);
    window.innerHeight = 800;
});

afterEach(() => {
    vi.unstubAllGlobals();
    observerCallback = null;
});

describe('useFitsViewportHeight', () => {
    it('fits when the element and its margin are no taller than the window', async () => {
        const { fits } = mountWithHeight(700, 64);
        observerCallback?.();
        await nextTick();

        expect(fits.value).toBe(true);
    });

    it('does not fit when the element and its margin are taller than the window', async () => {
        const { fits } = mountWithHeight(760, 64);
        observerCallback?.();
        await nextTick();

        expect(fits.value).toBe(false);
    });

    it('checks again when the element grows', async () => {
        const { fits, setHeight } = mountWithHeight(500);
        observerCallback?.();
        await nextTick();
        expect(fits.value).toBe(true);

        setHeight(900);
        observerCallback?.();
        await nextTick();

        expect(fits.value).toBe(false);
    });

    it('checks again when the window is resized', async () => {
        const { fits } = mountWithHeight(700);
        observerCallback?.();
        await nextTick();
        expect(fits.value).toBe(true);

        window.innerHeight = 600;
        window.dispatchEvent(new Event('resize'));
        await nextTick();

        expect(fits.value).toBe(false);
    });

    it('assumes it does not fit without ResizeObserver', async () => {
        vi.stubGlobal('ResizeObserver', undefined);
        const { fits } = mountWithHeight(100);
        await nextTick();

        expect(fits.value).toBe(false);
    });
});
