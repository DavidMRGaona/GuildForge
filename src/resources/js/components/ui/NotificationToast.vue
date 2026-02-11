<script setup lang="ts">
import { X } from 'lucide-vue-next';
import {
    useNotifications,
    type Notification,
    type NotificationType,
} from '@/composables/useNotifications';
import { notificationIconMap } from '@/utils/icons';

const { notifications, removeNotification } = useNotifications();

const colorClasses: Record<NotificationType, { bg: string; icon: string; text: string }> = {
    success: {
        bg: 'bg-success-light',
        icon: 'text-success',
        text: 'text-success',
    },
    error: {
        bg: 'bg-error-light',
        icon: 'text-error',
        text: 'text-error',
    },
    warning: {
        bg: 'bg-warning-light',
        icon: 'text-warning',
        text: 'text-warning',
    },
    info: {
        bg: 'bg-info-light',
        icon: 'text-info',
        text: 'text-info',
    },
};

function getColors(notification: Notification) {
    return colorClasses[notification.type];
}

function getIcon(notification: Notification) {
    return notificationIconMap[notification.type];
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="notifications.length > 0"
            aria-live="assertive"
            class="pointer-events-none fixed inset-0 z-50 flex flex-col items-end px-4 py-6 sm:p-6"
        >
            <TransitionGroup
                tag="div"
                class="flex w-full flex-col items-center space-y-4 sm:items-end"
                enter-active-class="transition ease-out duration-300"
                enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-for="notification in notifications"
                    :key="notification.id"
                    class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-lg shadow-lg ring-1 ring-black/5 dark:ring-white/10"
                    :class="getColors(notification).bg"
                >
                    <div class="p-4">
                        <div class="flex items-start">
                            <div class="shrink-0">
                                <component
                                    :is="getIcon(notification)"
                                    class="h-6 w-6"
                                    :class="getColors(notification).icon"
                                    aria-hidden="true"
                                />
                            </div>
                            <div class="ml-3 w-0 flex-1 pt-0.5">
                                <p
                                    class="text-sm font-medium"
                                    :class="getColors(notification).text"
                                >
                                    {{ notification.message }}
                                </p>
                            </div>
                            <div class="ml-4 flex shrink-0">
                                <button
                                    type="button"
                                    class="inline-flex rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                                    :class="getColors(notification).text"
                                    @click="removeNotification(notification.id)"
                                >
                                    <span class="sr-only">Close</span>
                                    <X class="h-5 w-5" aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
