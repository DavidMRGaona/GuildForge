import { onMounted, onUnmounted, readonly, ref } from 'vue';
import type { Ref, ShallowRef } from 'vue';

/**
 * Tells whether `target`, plus `margin` pixels, is no taller than the window.
 *
 * Meant for a column that stays in view while the page scrolls (`position: sticky`): a sticky
 * column taller than the window would hide its bottom until the end of the page, so it should
 * only stick when it fits. The element is checked when its size changes and when the window is
 * resized. Without `ResizeObserver` it is assumed not to fit, the layout that always works.
 */
export function useFitsViewportHeight(
    target: Readonly<ShallowRef<HTMLElement | null>>,
    margin = 0
): Readonly<Ref<boolean>> {
    const fits = ref(false);
    let observer: ResizeObserver | null = null;

    function check(): void {
        const element = target.value;
        fits.value = element !== null && element.offsetHeight + margin <= window.innerHeight;
    }

    onMounted(() => {
        if (typeof ResizeObserver === 'undefined' || target.value === null) {
            return;
        }

        observer = new ResizeObserver(check);
        observer.observe(target.value);
        window.addEventListener('resize', check);
        check();
    });

    onUnmounted(() => {
        observer?.disconnect();
        observer = null;
        window.removeEventListener('resize', check);
    });

    return readonly(fits);
}
