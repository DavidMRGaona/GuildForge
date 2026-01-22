<script setup lang="ts">
import {
    useNotifications,
    type Notification,
    type NotificationType,
} from '@/composables/useNotifications';

const { notifications, removeNotification } = useNotifications();

const iconPaths: Record<NotificationType, string> = {
    success: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    error: 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z',
    warning:
        'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
    info: 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z',
};

const colorClasses: Record<NotificationType, { bg: string; icon: string; text: string }> = {
    success: {
        bg: 'bg-green-50 dark:bg-green-900/30',
        icon: 'text-green-400 dark:text-green-300',
        text: 'text-green-800 dark:text-green-200',
    },
    error: {
        bg: 'bg-red-50 dark:bg-red-900/30',
        icon: 'text-red-400 dark:text-red-300',
        text: 'text-red-800 dark:text-red-200',
    },
    warning: {
        bg: 'bg-amber-50 dark:bg-amber-900/30',
        icon: 'text-amber-400 dark:text-amber-300',
        text: 'text-amber-800 dark:text-amber-200',
    },
    info: {
        bg: 'bg-blue-50 dark:bg-blue-900/30',
        icon: 'text-blue-400 dark:text-blue-300',
        text: 'text-blue-800 dark:text-blue-200',
    },
};

function getColors(notification: Notification) {
    return colorClasses[notification.type];
}

function getIconPath(notification: Notification) {
    return iconPaths[notification.type];
}
</script>

<template>
    <Teleport to="body">
        <div
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
                                <svg
                                    class="h-6 w-6"
                                    :class="getColors(notification).icon"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        :d="getIconPath(notification)"
                                    />
                                </svg>
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
                                    class="inline-flex rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
                                    :class="getColors(notification).text"
                                    @click="removeNotification(notification.id)"
                                >
                                    <span class="sr-only">Close</span>
                                    <svg
                                        class="h-5 w-5"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
