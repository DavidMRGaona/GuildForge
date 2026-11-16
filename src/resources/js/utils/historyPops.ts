/**
 * The browser's Back and Forward (`popstate`), heard before anything else on the page, Inertia included
 * (core#743). Chrome runs the `popstate` listeners of `window` in the order they were added, capture or
 * not, and Inertia adds its own when the app starts, so a component that listens later can never keep
 * the event from it. The app calls `listenToHistoryFirst()` before Inertia starts; a handler stops the
 * event with `stopImmediatePropagation()`. Capture as well, for browsers that run capture listeners
 * first at the target.
 */
type HistoryPopHandler = (event: PopStateEvent) => void;

const handlers = new Set<HistoryPopHandler>();
let listening = false;

function dispatch(event: PopStateEvent): void {
    for (const handler of [...handlers]) {
        handler(event);
    }
}

/** Starts listening, once: before Inertia starts, so this runs first */
export function listenToHistoryFirst(): void {
    if (listening || typeof window === 'undefined') {
        return;
    }

    listening = true;
    window.addEventListener('popstate', dispatch, true);
}

/** Hears every Back and Forward before the page does; returns how to stop */
export function onHistoryPop(handler: HistoryPopHandler): () => void {
    listenToHistoryFirst();
    handlers.add(handler);

    return () => {
        handlers.delete(handler);
    };
}
