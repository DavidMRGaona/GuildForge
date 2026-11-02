import { onBeforeUnmount, onMounted } from 'vue';

const TYPING_TARGETS = 'input, textarea, select, [contenteditable]:not([contenteditable="false"])';

/**
 * Calls `open` when «/» is pressed while the person is not typing in a field.
 *
 * Shift is allowed: on a Spanish keyboard «/» is Shift+7. The key is only swallowed when it
 * opens the search, so pages and fields that use it keep working.
 */
export function useSearchShortcut(open: () => void, isOpen: () => boolean): void {
    function onKeydown(event: KeyboardEvent): void {
        if (event.key !== '/' || isOpen() || event.defaultPrevented || event.isComposing) {
            return;
        }
        if (event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }
        if (event.target instanceof Element && event.target.closest(TYPING_TARGETS)) {
            return;
        }
        event.preventDefault();
        open();
    }

    onMounted(() => document.addEventListener('keydown', onKeydown));
    onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
}
