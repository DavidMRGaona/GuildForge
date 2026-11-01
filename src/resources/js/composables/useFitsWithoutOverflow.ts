import { nextTick, onMounted, onUnmounted, readonly, ref, watch } from 'vue';
import type { Ref, ShallowRef, WatchSource } from 'vue';

/**
 * Tells whether `target` lays out its content without overflowing horizontally.
 *
 * Meant for a row that switches to a compact layout when its full layout does not fit (the
 * header falls back to its mobile menu). Each check renders the full layout again before
 * measuring it, so the row goes back to it as soon as it fits; Vue updates the DOM and the check
 * reads it before the browser paints, so neither layout flashes.
 *
 * The row is checked when its width changes (`target` must take its width from its container,
 * not from its content, or the switch would trigger the observer again), when the web fonts have
 * loaded and when `recheckOn` changes (different links, a longer user name...). Without
 * `ResizeObserver` the row is assumed to fit.
 */
export function useFitsWithoutOverflow(
    target: Readonly<ShallowRef<HTMLElement | null>>,
    recheckOn: WatchSource
): Readonly<Ref<boolean>> {
    const fits = ref(true);
    let observer: ResizeObserver | null = null;

    async function check(): Promise<void> {
        fits.value = true;
        await nextTick();

        const element = target.value;
        if (element !== null) {
            fits.value = element.scrollWidth <= element.clientWidth;
        }
    }

    onMounted(() => {
        if (typeof ResizeObserver === 'undefined' || target.value === null) {
            return;
        }

        observer = new ResizeObserver(() => void check());
        observer.observe(target.value);
        void document.fonts?.ready.then(() => check());
    });

    watch(recheckOn, () => {
        if (observer !== null) {
            void check();
        }
    });

    onUnmounted(() => {
        observer?.disconnect();
        observer = null;
    });

    return readonly(fits);
}
