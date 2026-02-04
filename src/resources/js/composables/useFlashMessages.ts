import { computed, type ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { FlashMessages } from '@/types';

interface PageProps {
    flash?: FlashMessages;
    [key: string]: unknown;
}

interface UseFlashMessagesReturn {
    success: ComputedRef<string | undefined>;
    error: ComputedRef<string | undefined>;
    hasMessages: ComputedRef<boolean>;
}

export function useFlashMessages(): UseFlashMessagesReturn {
    const page = usePage<PageProps>();

    const success = computed(() => page.props.flash?.success);
    const error = computed(() => page.props.flash?.error);
    const hasMessages = computed(() => !!success.value || !!error.value);

    return {
        success,
        error,
        hasMessages,
    };
}
