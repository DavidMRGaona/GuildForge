import { onBeforeUnmount, watch, type Ref } from 'vue';

interface LockedBody {
    overflow: string;
    paddingRight: string;
}

let locked: LockedBody | null = null;
let lockCount = 0;

/**
 * Stops the page behind from scrolling. With classic scrollbars (desktop), hiding the body's
 * scrollbar would widen the page and shift it sideways, so the scrollbar's width is kept as
 * padding. Nested dialogs share one lock: the page is restored when the last one closes.
 */
function lockBodyScroll(): void {
    lockCount++;
    if (lockCount > 1) {
        return;
    }
    const body = document.body;
    const scrollbar = window.innerWidth - document.documentElement.clientWidth;
    locked = { overflow: body.style.overflow, paddingRight: body.style.paddingRight };
    if (scrollbar > 0) {
        const current = Number.parseFloat(window.getComputedStyle(body).paddingRight) || 0;
        body.style.paddingRight = `${current + scrollbar}px`;
    }
    body.style.overflow = 'hidden';
}

function unlockBodyScroll(): void {
    if (lockCount === 0) {
        return;
    }
    lockCount--;
    if (lockCount > 0 || locked === null) {
        return;
    }
    document.body.style.overflow = locked.overflow;
    document.body.style.paddingRight = locked.paddingRight;
    locked = null;
}

/**
 * What a modal dialog does to the page while it is open: the page behind does not scroll, and
 * Tab stays inside the dialog.
 *
 * `trapTab` is called from the dialog's own keydown handler with every Tab: it wraps focus at
 * both ends, and brings it back in when it is on nothing in the dialog (after a click on a spot
 * that takes no focus).
 */
export function useModalDialog(
    isOpen: () => boolean,
    container: Ref<HTMLElement | null>,
    focusable: string
): { trapTab: (event: KeyboardEvent) => void } {
    let holdsLock = false;

    function setLocked(lock: boolean): void {
        if (lock && !holdsLock) {
            lockBodyScroll();
            holdsLock = true;
        } else if (!lock && holdsLock) {
            unlockBodyScroll();
            holdsLock = false;
        }
    }

    watch(isOpen, setLocked, { immediate: true });
    onBeforeUnmount(() => setLocked(false));

    function trapTab(event: KeyboardEvent): void {
        const elements = Array.from(
            container.value?.querySelectorAll<HTMLElement>(focusable) ?? []
        );
        const first = elements[0];
        const last = elements[elements.length - 1];
        if (!first || !last) {
            event.preventDefault();
            return;
        }
        const current = elements.indexOf(document.activeElement as HTMLElement);
        if (current === -1 || document.activeElement === (event.shiftKey ? first : last)) {
            event.preventDefault();
            (event.shiftKey ? last : first).focus();
        }
    }

    return { trapTab };
}
