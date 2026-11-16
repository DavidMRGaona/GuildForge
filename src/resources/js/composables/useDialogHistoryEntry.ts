import { onBeforeUnmount, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

export interface DialogHistoryEntry {
    /**
     * A visit elsewhere is leaving the page: it takes the dialog's entry instead of adding one after it,
     * so going back from it lands on the page and not on the page again. Pass the visit `before` gives.
     */
    handOver(visit: { replace?: boolean }): void;
    /**
     * Goes back to the page's own entry before a request that answers with the page again (saving): that
     * page takes the page's entry, so nothing is left to go back to. Resolved once the browser is there.
     */
    release(): Promise<void>;
    /** Puts the dialog's entry back, after a request that `release()` made room for left it open */
    restore(): void;
}

/**
 * An entry of its own in the browser's history while a modal dialog is open (core#743), so going back,
 * the natural way to close a panel that fills a phone's screen, closes it instead of leaving the page.
 * Inertia 3 never fires `before` for Back or Forward and would swap the page under the dialog, so this
 * listens first (capture) and keeps the event from it whenever the dialog decides.
 *
 * `onLeave(toPage)` is asked when the person goes back or forward from the dialog's entry: `toPage` is
 * true when it lands on the page under the dialog (close it then), false when it lands on another page
 * (Inertia takes that one). Answering false keeps them: the dialog's entry is put back.
 *
 * The entry is the page's (same URL and Inertia state), never marked: Inertia rewrites the whole state
 * of the current entry when it saves the scroll, so whether the entry is still the dialog's is known
 * here, not read from the history. Closing the dialog any other way goes back over its entry, and the
 * page's entry is given the state Inertia kept last (a new banner, say), so it never goes stale.
 */
export function useDialogHistoryEntry(onLeave: (toPage: boolean) => boolean): DialogHistoryEntry {
    let pageHref = '';
    /** What Inertia keeps in the history for the page: copied to the dialog's entry and back */
    let pageState: unknown = null;
    /** Whether the current history entry is the dialog's */
    let current = false;
    let mounted = false;
    /** A Back the dialog asked for itself: the page under it must not hear of it */
    let ownBack: (() => void) | null = null;

    function push(): void {
        window.history.pushState(pageState, '', pageHref);
        current = true;
    }

    function stopListening(): void {
        window.removeEventListener('popstate', onPopState, true);
    }

    function onPopState(event: PopStateEvent): void {
        if (ownBack) {
            event.stopImmediatePropagation();
            window.history.replaceState(pageState, '', window.location.href);
            const done = ownBack;
            ownBack = null;
            done();
            if (!mounted) {
                stopListening();
            }
            return;
        }

        if (!current) {
            return;
        }

        current = false;
        const toPage = window.location.href === pageHref;

        if (!onLeave(toPage)) {
            event.stopImmediatePropagation();
            push();
            return;
        }

        if (toPage) {
            event.stopImmediatePropagation();
            window.history.replaceState(pageState, '', pageHref);
        }
    }

    /** Requests the dialog makes keep the page on its entry: remember the latest for the page's own */
    const stopTracking = router.on('finish', () => {
        if (current) {
            pageState = window.history.state as unknown;
        }
    });

    function release(): Promise<void> {
        if (!current) {
            return Promise.resolve();
        }

        pageState = window.history.state as unknown;
        current = false;

        return new Promise((resolve) => {
            ownBack = resolve;
            window.history.back();
        });
    }

    onMounted(() => {
        mounted = true;
        pageHref = window.location.href;
        pageState = window.history.state as unknown;
        window.addEventListener('popstate', onPopState, true);
        push();
    });

    onBeforeUnmount(() => {
        mounted = false;
        stopTracking();

        if (current) {
            void release();
            return;
        }

        if (!ownBack) {
            stopListening();
        }
    });

    return {
        handOver(visit): void {
            if (current) {
                visit.replace = true;
                current = false;
            }
        },
        release,
        restore(): void {
            if (mounted && !current && !ownBack) {
                pageState = window.history.state as unknown;
                push();
            }
        },
    };
}
