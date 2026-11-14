import { onBeforeUnmount, ref, watch, type CSSProperties, type Ref } from 'vue';

/** Space between the field and its list, in pixels. */
const GAP = 4;

/** Tallest the list grows (`max-h-60`): below this room under the field, it may open upwards. */
const LIST_MAX_HEIGHT = 240;

/** A template ref: an element, or a component whose root is one. */
export type PinnedTarget = HTMLElement | { $el?: unknown } | null | undefined;

function elementOf(target: PinnedTarget): HTMLElement | null {
    if (target instanceof HTMLElement) {
        return target;
    }

    return target?.$el instanceof HTMLElement ? target.$el : null;
}

/**
 * Pins the open list of a dropdown to its field with fixed positioning (core#636), so no scrolling
 * parent clips it: a `<dialog>` or a card with `overflow: auto` cut it at its edge. The list stays
 * where it is in the DOM, because a modal `<dialog>` paints above anything teleported to `body`.
 * It opens under the field, or above it when there is no room below and more above, and follows
 * the field while the page or any parent scrolls or the window resizes.
 *
 * An ancestor with a transform, filter, `contain` or `will-change` makes `fixed` count from that
 * ancestor instead of the window. A zero-size probe, fixed at (0,0) next to the list, tells where
 * that origin really is, and the list is shifted by it. The list itself is not measured: its
 * opening transition scales it, which moves its box.
 */
export function usePinnedPanel(
    anchor: Ref<PinnedTarget>,
    panel: Ref<PinnedTarget>
): { style: Ref<CSSProperties> } {
    // Fixed from the first paint: in the flow, the list would grow a centred dialog and move the
    // field for the very measurement that places it
    const style = ref<CSSProperties>({ position: 'fixed' });

    /** Where `fixed` (0,0) lands for the list: the window's corner, or a containing ancestor's. */
    function origin(list: HTMLElement): { x: number; y: number } {
        const parent = list.parentElement;
        if (parent === null) {
            return { x: 0, y: 0 };
        }

        const probe = document.createElement('span');
        probe.setAttribute('data-pinned-probe', '');
        probe.style.cssText = 'position:fixed;left:0;top:0;width:0;height:0;visibility:hidden';
        parent.insertBefore(probe, list);
        const corner = probe.getBoundingClientRect();
        probe.remove();

        return { x: corner.left, y: corner.top };
    }

    function place(): void {
        const field = elementOf(anchor.value);
        const list = elementOf(panel.value);
        if (field === null || list === null) {
            return;
        }

        const box = field.getBoundingClientRect();
        const below = window.innerHeight - box.bottom;
        const upwards = below < LIST_MAX_HEIGHT + GAP && box.top > below;
        const top = upwards ? box.top - GAP - list.offsetHeight : box.bottom + GAP;
        const from = origin(list);

        style.value = {
            position: 'fixed',
            left: `${box.left - from.x}px`,
            top: `${top - from.y}px`,
            width: `${box.width}px`,
        };
    }

    let listening = false;

    function listen(on: boolean): void {
        if (on === listening) {
            return;
        }
        listening = on;
        // Capture: scroll does not bubble, and the scrolling parent can be any of them
        const method = on ? 'addEventListener' : 'removeEventListener';
        window[method]('scroll', place, true);
        window[method]('resize', place);
    }

    watch(
        () => elementOf(panel.value),
        (open) => {
            listen(open !== null);
            if (open === null) {
                style.value = { position: 'fixed' };

                return;
            }
            place();
            // Once more after the browser lays out what opening the list changed
            requestAnimationFrame(place);
        },
        { flush: 'post' }
    );

    onBeforeUnmount(() => listen(false));

    return { style };
}
