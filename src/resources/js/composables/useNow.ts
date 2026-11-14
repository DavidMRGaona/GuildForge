import { getCurrentScope, onScopeDispose, readonly, ref } from 'vue';
import type { DeepReadonly, Ref } from 'vue';

/** How often the shared clock moves on: relative times («hace 5 minutos») change by the minute. */
export const NOW_TICK_MS = 60_000;

const now = ref(new Date());
const shared = readonly(now);
let readers = 0;
let timer: ReturnType<typeof setInterval> | null = null;

/**
 * The current instant, moved on every minute (core#669), so relative times stay true while a
 * page stays open. One clock for the whole page however many components read it: it starts
 * with the first reader and stops when the last one's scope ends. Outside a component or effect
 * scope nothing could ever stop it, so there it gives the current instant without starting it.
 */
export function useNow(): DeepReadonly<Ref<Date>> {
    if (getCurrentScope() === undefined) {
        if (readers === 0) {
            now.value = new Date();
        }

        return shared;
    }

    if (readers === 0) {
        now.value = new Date();
        timer = setInterval(() => {
            now.value = new Date();
        }, NOW_TICK_MS);
    }

    readers += 1;

    onScopeDispose(() => {
        readers -= 1;

        if (readers === 0 && timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    });

    return shared;
}
