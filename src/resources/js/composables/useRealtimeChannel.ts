import { computed, onScopeDispose, readonly, ref, toValue, watch } from 'vue';
import type { ComputedRef, MaybeRefOrGetter, Ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    acquireChannel,
    bindChannel,
    channelStatus,
    configureRealtime,
    releaseChannel,
    whisperOn,
} from '@/utils/realtime/connection';
import type { RawListener } from '@/utils/realtime/client';
import type {
    RealtimeListener,
    RealtimePayload,
    RealtimeSharedProps,
    RealtimeStatus,
} from '@/types/realtime';

export type { RealtimeListener, RealtimePayload, RealtimeStatus } from '@/types/realtime';

export interface UseRealtimeChannelReturn {
    /** Where the subscription stands (see RealtimeStatus). */
    status: Readonly<Ref<RealtimeStatus>>;
    /**
     * True while messages arrive: the page may stop polling. When it turns true again after being
     * false, messages may have been missed: ask for what is new once (catch up).
     */
    live: ComputedRef<boolean>;
    /**
     * Sends a client event (`client-<event>`) to the others on the channel, e.g. «typing…».
     * Nothing checks it on the server: send only the sender's own ids, and never trust what
     * arrives. False when the channel is not live.
     */
    whisper: (event: string, data: Record<string, unknown>) => boolean;
    /** Listens to the client events of the others; returns what stops it. */
    onWhisper: (event: string, listener: RealtimeListener) => () => void;
}

interface SharedProps {
    realtime?: unknown;
    auth?: { user?: { id?: unknown } | null } | null;
}

function readConfig(value: unknown): RealtimeSharedProps | null {
    if (typeof value !== 'object' || value === null) {
        return null;
    }
    const config = value as Partial<RealtimeSharedProps>;

    return typeof config.key === 'string' &&
        config.key !== '' &&
        typeof config.host === 'string' &&
        config.host !== '' &&
        typeof config.port === 'number' &&
        typeof config.tls === 'boolean' &&
        typeof config.authEndpoint === 'string'
        ? {
              key: config.key,
              host: config.host,
              port: config.port,
              tls: config.tls,
              authEndpoint: config.authEndpoint,
          }
        : null;
}

function toPayload(value: unknown): RealtimePayload {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
        ? Object.freeze({ ...(value as Record<string, unknown>) })
        : Object.freeze({});
}

/**
 * Listens to a private real-time channel (core#493) while the component (or effect scope) lives.
 *
 * The channel name has no `private-` prefix (`forum.discussion.<id>`); null, or a page without
 * real time (not configured, nobody signed in), means `off` and nothing connects. Event names are
 * exact (`post.created`). Messages carry only ids and numbers: fetch the content through the usual
 * request, which checks permissions again.
 *
 * Polling stays the page's own: keep it while `live` is false and stop it while true.
 *
 * ```ts
 * const { live } = useRealtimeChannel(() => `forum.discussion.${id}`, {
 *     'post.created': ({ number }) => fetchNewPosts(Number(number)),
 * });
 * ```
 */
export function useRealtimeChannel(
    channel: MaybeRefOrGetter<string | null | undefined>,
    listeners: Readonly<Record<string, RealtimeListener>> = {}
): UseRealtimeChannelReturn {
    const page = usePage();
    const config = computed(() => readConfig((page.props as SharedProps).realtime));
    const account = computed(() => (page.props as SharedProps).auth?.user?.id ?? null);
    const name = computed(() => {
        const value = toValue(channel);

        return typeof value === 'string' && value !== '' ? value : null;
    });

    const subscribed = ref<string | null>(null);
    const unbinders: Array<() => void> = [];
    const whispers = new Set<{ event: string; callback: RawListener }>();
    const whisperUnbinders = new Map<{ event: string; callback: RawListener }, () => void>();

    function bindWhisper(entry: { event: string; callback: RawListener }): void {
        if (subscribed.value !== null) {
            whisperUnbinders.set(
                entry,
                bindChannel(subscribed.value, entry.event, entry.callback, true)
            );
        }
    }

    function detach(): void {
        if (subscribed.value === null) {
            return;
        }
        for (const undo of unbinders.splice(0)) {
            undo();
        }
        for (const undo of whisperUnbinders.values()) {
            undo();
        }
        whisperUnbinders.clear();
        releaseChannel(subscribed.value);
        subscribed.value = null;
    }

    function attach(channelName: string): void {
        acquireChannel(channelName);
        subscribed.value = channelName;
        for (const [event, listener] of Object.entries(listeners)) {
            unbinders.push(
                bindChannel(channelName, event, (payload) => listener(toPayload(payload)))
            );
        }
        for (const entry of whispers) {
            bindWhisper(entry);
        }
    }

    watch(
        [config, account, name],
        ([settings, user, channelName]) => {
            configureRealtime(settings, user);
            const wanted = settings === null ? null : channelName;
            if (wanted !== subscribed.value) {
                detach();
                if (wanted !== null) {
                    attach(wanted);
                }
            }
        },
        { immediate: true }
    );

    onScopeDispose(detach);

    const status = computed<RealtimeStatus>(() => channelStatus(subscribed.value));

    return {
        status: readonly(status),
        live: computed(() => status.value === 'live'),
        whisper: (event, data) =>
            subscribed.value === null ? false : whisperOn(subscribed.value, event, data),
        onWhisper: (event, listener) => {
            const entry = {
                event,
                callback: (payload: unknown): void => listener(toPayload(payload)),
            };
            whispers.add(entry);
            bindWhisper(entry);

            return () => {
                whispers.delete(entry);
                whisperUnbinders.get(entry)?.();
                whisperUnbinders.delete(entry);
            };
        },
    };
}
