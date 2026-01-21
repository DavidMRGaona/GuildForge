import { ref, readonly, type DeepReadonly, type Ref } from 'vue';

export type NotificationType = 'success' | 'error' | 'info' | 'warning';

export interface Notification {
    id: string;
    type: NotificationType;
    message: string;
    timeout?: number;
}

export interface UseNotificationsReturn {
    notifications: DeepReadonly<Ref<Notification[]>>;
    addNotification: (type: NotificationType, message: string, timeout?: number) => string;
    removeNotification: (id: string) => void;
    clearAll: () => void;
    success: (message: string, timeout?: number) => string;
    error: (message: string, timeout?: number) => string;
    info: (message: string, timeout?: number) => string;
    warning: (message: string, timeout?: number) => string;
}

const notifications = ref<Notification[]>([]);

const DEFAULT_TIMEOUT = 5000;

function generateId(): string {
    return `notification-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;
}

function addNotification(
    type: NotificationType,
    message: string,
    timeout: number = DEFAULT_TIMEOUT
): string {
    const id = generateId();

    notifications.value.push({
        id,
        type,
        message,
        timeout,
    });

    if (timeout > 0) {
        window.setTimeout(() => {
            removeNotification(id);
        }, timeout);
    }

    return id;
}

function removeNotification(id: string): void {
    const index = notifications.value.findIndex((n) => n.id === id);
    if (index !== -1) {
        notifications.value.splice(index, 1);
    }
}

function clearAll(): void {
    notifications.value = [];
}

function success(message: string, timeout?: number): string {
    return addNotification('success', message, timeout);
}

function error(message: string, timeout?: number): string {
    return addNotification('error', message, timeout);
}

function info(message: string, timeout?: number): string {
    return addNotification('info', message, timeout);
}

function warning(message: string, timeout?: number): string {
    return addNotification('warning', message, timeout);
}

export function useNotifications(): UseNotificationsReturn {
    return {
        notifications: readonly(notifications),
        addNotification,
        removeNotification,
        clearAll,
        success,
        error,
        info,
        warning,
    };
}
