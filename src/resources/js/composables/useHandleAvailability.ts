import { onBeforeUnmount, ref, watch, type Ref } from 'vue';
import type { HandleAvailabilityStatus } from '@/types/profileEditor';

/** Wait after the last keystroke before asking the server */
export const HANDLE_CHECK_DELAY_MS = 400;

const HANDLE_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

interface HandleLimits {
    min: number;
    max: number;
}

/** As the server normalises it: trimmed and lowercase */
export function normaliseHandle(value: string): string {
    return value.trim().toLowerCase();
}

/** Whether it has the shape of an address, before asking the server whether it is free */
export function isWellFormedHandle(value: string, limits: HandleLimits): boolean {
    return value.length >= limits.min && value.length <= limits.max && HANDLE_PATTERN.test(value);
}

/**
 * Whether the address being typed can be chosen (core#743). The shape is checked here; a free one is
 * asked of the server a moment after the last keystroke, one request at a time (a newer value cancels
 * the one in flight). The server never says whose an unavailable address is, and it is only a hint:
 * saving checks again.
 */
export function useHandleAvailability(
    handle: Ref<string>,
    options: { url: string; current: string; limits: HandleLimits; enabled: () => boolean }
): { status: Ref<HandleAvailabilityStatus> } {
    const status = ref<HandleAvailabilityStatus>('idle');
    let timer: ReturnType<typeof setTimeout> | null = null;
    let controller: AbortController | null = null;

    function cancelPending(): void {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
        controller?.abort();
        controller = null;
    }

    async function ask(value: string): Promise<void> {
        const current = new AbortController();
        controller = current;

        try {
            const response = await fetch(`${options.url}?direccion=${encodeURIComponent(value)}`, {
                headers: { Accept: 'application/json' },
                signal: current.signal,
            });
            if (!response.ok) {
                throw new Error(`Availability answered ${response.status}`);
            }
            const body = (await response.json()) as { status?: unknown };
            if (current.signal.aborted) {
                return;
            }
            const known: HandleAvailabilityStatus[] = [
                'available',
                'current',
                'unavailable',
                'malformed',
            ];
            status.value = known.includes(body.status as HandleAvailabilityStatus)
                ? (body.status as HandleAvailabilityStatus)
                : 'failed';
        } catch {
            if (!current.signal.aborted) {
                status.value = 'failed';
            }
        } finally {
            if (controller === current) {
                controller = null;
            }
        }
    }

    watch(handle, (raw) => {
        cancelPending();

        if (!options.enabled()) {
            status.value = 'idle';
            return;
        }

        const value = normaliseHandle(raw);

        if (value === options.current) {
            status.value = 'current';
            return;
        }

        if (!isWellFormedHandle(value, options.limits)) {
            status.value = 'malformed';
            return;
        }

        status.value = 'checking';
        timer = setTimeout(() => {
            timer = null;
            void ask(value);
        }, HANDLE_CHECK_DELAY_MS);
    });

    onBeforeUnmount(cancelPending);

    return { status };
}
