import type { PaginatedResponse } from '@/types';

/** One row of the notification centre (core#491): a notice, or the unread notices of one group. */
export interface SiteNotificationItem {
    id: string;
    /** Plain text, already on one line: Vue escapes it. */
    title: string;
    body: string | null;
    /** Lucide icon name (kebab case) the module chose; null or unknown shows a bell. */
    icon: string | null;
    /** Notices gathered in the row (1 for a single one). */
    count: number;
    unread: boolean;
    /** Instant of its latest notice, ISO 8601 in the display zone. */
    notifiedAt: string | null;
    /** Opens it through the core, which marks it read and then goes where it leads. */
    href: string;
}

/** Shared prop of every page: null for guests. */
export interface SiteNotificationsSharedProps {
    unread: number;
    /** The account's real-time channel (core#493), or null without real time: the bell polls. */
    channel?: string | null;
}

/** Props of the «Avisos» page. */
export interface SiteNotificationsPageProps {
    notifications: PaginatedResponse<SiteNotificationItem>;
    unread: number;
    /** «Todos» or «Sin leer» (`?filtro=sin-leer`, core#671). */
    filter: 'all' | 'unread';
}
