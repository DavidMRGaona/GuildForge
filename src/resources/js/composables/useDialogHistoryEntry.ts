import { onBeforeUnmount, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { onHistoryPop } from '@/utils/historyPops';

export interface DialogHistoryEntry {
    /**
     * A visit elsewhere is leaving the page: it takes the dialog's entry instead of adding one after it,
     * so going back from it lands on the page and not on the page again. Pass the visit `before` gives.
     */
    handOver(visit: { replace?: boolean }): void;
    /**
     * A request of the dialog's own (an upload, say): if its answer is another page (a session that
     * expired sends it to sign in), that page takes the dialog's entry instead of adding one after it.
     * Answers on the same address replace the entry anyway. Pass the visit `before` gives.
     */
    keep(visit: { replace?: boolean }): void;
    /**
     * A request `keep` marked, still on its way: cancelled as soon as the dialog's entry is left (closing,
     * Back or Forward, saving, a visit elsewhere). Inertia writes the history with the `replace` it took
     * when sending, so its answer would otherwise take the entry that is current by then, the page's.
     * Pass what cancels it (`onCancelToken` gives it); call what this returns once the request finished.
     */
    cancelOnLeave(cancel: () => void): () => void;
    /**
     * Goes back to the page's own entry before a request that answers with the page again (saving): that
     * page takes the page's entry, so nothing is left to go back to. Resolved once the browser is there.
     */
    release(): Promise<void>;
    /**
     * Puts the dialog's entry back, after a request that `release()` made room for left it open: only
     * while the page is still the one in the address bar and no reload is under way
     */
    restore(): void;
}

/**
 * An entry of its own in the browser's history while a modal dialog is open (core#743), so going back,
 * the natural way to close a panel that fills a phone's screen, closes it instead of leaving the page.
 * Inertia 3 never fires `before` for Back or Forward and would swap the page under the dialog, so this
 * hears them first (`onHistoryPop`) and keeps the event from it whenever the dialog decides.
 *
 * `onLeave(toPage)` is asked when the person goes back or forward from the dialog's entry: `toPage` is
 * true when it lands on the page under the dialog (close it then), false when it lands on another page
 * (Inertia takes that one). Answering false keeps them: the dialog's entry is put back.
 *
 * The entry is the page's (same URL and Inertia state), never marked: Inertia rewrites the whole state
 * of the current entry when it saves the scroll, so whether the entry is still the dialog's is known
 * here, not read from the history. Closing the dialog any other way goes back over its entry, and the
 * page's entry is given the state Inertia kept last (a new banner, say), so it never goes stale.
 *
 * Going back several steps at once (the browser's list of pages) and staying puts the dialog's entry
 * back over that other page's: the page's own entry is gone. From then on the dialog's entry becomes
 * the page's in place (`replaceState`) instead of going back, so the address bar never shows one page
 * while another is drawn. The entry is never put back once the address is no longer the page's (Back
 * while saving) or Inertia is reloading the document (a new version answers with a 409).
 *
 * A request of the dialog's own that ends on another page (a session that expired sends the banner's
 * upload to the sign-in page) leaves the history as Inertia wrote it: nothing is copied or gone back.
 * One still on its way when the dialog's entry is left is cancelled (`cancelOnLeave`, core#759), so
 * its answer never replaces the page's entry and Back from the sign-in page skips the page.
 *
 * Known limits, which the History API leaves no way around:
 * - Reloading with the dialog open loads its entry as the page, so one Back stays on the same address.
 *   Telling it apart would need a mark in the history that Inertia's own writes would erase, and going
 *   back from a reloaded document reloads it again.
 * - Forward while saving puts the entry back and leaves a dead step: by its address alone the dialog's
 *   entry cannot be told from the page's.
 * - Closing or saving through `back()` leaves the dialog's entry behind as a forward step, holding the
 *   page as it is when closing, and as it was before saving when saving: an entry cannot be taken
 *   out of the history.
 * - Cancelling only stops the browser waiting for the answer: a request the server already got runs
 *   there to the end. A banner uploaded or taken away just before closing is then stored all the same
 *   (whole or not at all), and the page shows it on its next visit instead of at once.
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
    /** Whether the entry under the dialog's is the page's own (false after staying from another page) */
    let pageBelow = true;
    /** Inertia is loading a document again (`location`): no entry is put back */
    let reloading = false;
    let stopListening: () => void = () => undefined;
    /** Its own requests on their way, marked to replace the dialog's entry */
    const inFlight = new Set<() => void>();

    /** The dialog's entry was left: whatever it marked must not answer on another entry */
    function cancelOwnRequests(): void {
        const cancels = [...inFlight];
        inFlight.clear();
        cancels.forEach((cancel) => cancel());
    }

    /** Whether an address is the page's: the fragment (`#main-content`, say) is not another page */
    function isPage(href: string): boolean {
        return withoutFragment(href) === withoutFragment(pageHref);
    }

    function push(): void {
        window.history.pushState(pageState, '', pageHref);
        current = true;
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
        const toPage = isPage(window.location.href);

        if (!onLeave(toPage)) {
            event.stopImmediatePropagation();
            pageBelow = toPage;
            push();
            return;
        }

        cancelOwnRequests();

        if (toPage) {
            event.stopImmediatePropagation();
            window.history.replaceState(pageState, '', pageHref);
        }
    }

    /** Requests the dialog makes keep the page on its entry: remember the latest for the page's own */
    const stopTracking = router.on('finish', () => {
        if (current && isPage(window.location.href)) {
            pageState = window.history.state as unknown;
        }
    });
    const stopWatchingReloads = router.on('location', () => {
        reloading = true;
    });

    function release(): Promise<void> {
        if (!current) {
            return Promise.resolve();
        }

        current = false;
        // Before going back: its answer would land on the page's entry
        cancelOwnRequests();

        // A request of its own ended on another page: that page's entry is Inertia's now
        if (!isPage(window.location.href)) {
            return Promise.resolve();
        }

        pageState = window.history.state as unknown;

        if (!pageBelow) {
            // Another page is under it: the dialog's entry becomes the page's own, where it is
            window.history.replaceState(pageState, '', pageHref);
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            ownBack = resolve;
            window.history.back();
        });
    }

    onMounted(() => {
        mounted = true;
        pageHref = window.location.href;
        pageState = window.history.state as unknown;
        stopListening = onHistoryPop(onPopState);
        push();
    });

    onBeforeUnmount(() => {
        mounted = false;
        stopTracking();
        stopWatchingReloads();

        if (current) {
            void release();
        }

        // Unless a Back of its own is still on its way, which stops listening once it arrives
        if (!ownBack) {
            stopListening();
        }
    });

    return {
        keep(visit): void {
            if (current) {
                visit.replace = true;
            }
        },
        cancelOnLeave(cancel): () => void {
            // Only a request `keep` marked can take an entry that is not the dialog's
            if (!current) {
                return () => undefined;
            }

            inFlight.add(cancel);

            return () => {
                inFlight.delete(cancel);
            };
        },
        handOver(visit): void {
            if (current) {
                visit.replace = true;
                current = false;
                cancelOwnRequests();
            }
        },
        release,
        restore(): void {
            if (mounted && !current && !ownBack && !reloading && isPage(window.location.href)) {
                pageState = window.history.state as unknown;
                // Over the page's own entry, wherever the dialog's was before
                pageBelow = true;
                push();
            }
        },
    };
}

function withoutFragment(href: string): string {
    const url = new URL(href, window.location.origin);
    url.hash = '';

    return url.href;
}
