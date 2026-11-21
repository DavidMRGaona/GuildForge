import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { syncThisDevice } from '@/utils/webPush';
import type { WebPushSharedProps } from '@/types/webPush';

/**
 * Keeps the site told about this browser's push subscription (core#803): once per session, while
 * push is on and someone is signed in. It never asks for permission; turning push on is a click
 * of the person's own (core#804).
 */
export function useWebPushSync(): void {
    const page = usePage();

    // Again whenever the account changes (another one signing in on this tab): each its own check
    watch(
        () =>
            [
                (page.props.webPush as WebPushSharedProps | null | undefined)?.publicKey ?? null,
                (page.props.auth as { user?: { id: string } | null } | undefined)?.user?.id ?? null,
            ] as const,
        ([publicKey, userId]) => {
            if (publicKey !== null && userId !== null) {
                void syncThisDevice(publicKey, userId);
            }
        },
        { immediate: true }
    );
}
