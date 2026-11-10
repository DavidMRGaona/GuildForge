import type { Component } from 'vue';
import type { ActivityIcon, CalendarEntryMetaIcon } from '@/types/models';
import type { ProfileTabIcon as ProfileTabIconType } from '@/types/profile';
import type { NotificationType } from '@/composables/useNotifications';
import {
    AtSign,
    Bell,
    BookOpen,
    Building2,
    Calendar,
    CalendarCheck,
    CalendarDays,
    CircleAlert,
    CircleCheck,
    CircleUserRound,
    ClipboardList,
    Clock,
    CreditCard,
    Dices,
    FileText,
    Handshake,
    Heart,
    IdCard,
    Image,
    Info,
    Lock,
    Mail,
    Map,
    MapPin,
    Megaphone,
    MessageCircle,
    MessageSquare,
    Monitor,
    Newspaper,
    Puzzle,
    Quote,
    Settings,
    Settings2,
    ShieldCheck,
    Sparkles,
    SquarePen,
    Swords,
    ThumbsUp,
    Ticket,
    TriangleAlert,
    Trophy,
    User,
    UserPlus,
    Users,
    Vote,
} from '@lucide/vue';

export const activityIconMap: Record<ActivityIcon, Component> = {
    dice: Dices,
    sword: Swords,
    book: BookOpen,
    users: Users,
    calendar: Calendar,
    map: Map,
    trophy: Trophy,
    puzzle: Puzzle,
    sparkles: Sparkles,
    heart: Heart,
};

type EmptyStateIcon = 'book' | 'calendar' | 'document' | 'photo' | 'trophy';

export const emptyStateIconMap: Record<EmptyStateIcon, Component> = {
    book: BookOpen,
    calendar: Calendar,
    document: FileText,
    photo: Image,
    trophy: Trophy,
};

export const profileTabIconMap: Record<ProfileTabIconType, Component> = {
    user: User,
    lock: Lock,
    dice: Dices,
    trophy: Trophy,
    calendar: Calendar,
    cog: Settings,
    'pencil-square': SquarePen,
    'book-open': BookOpen,
    envelope: Mail,
};

/** Resolve a module-provided tab icon, falling back to a neutral icon for names this core does not know. */
export function resolveProfileTabIcon(icon: string): Component {
    return icon in profileTabIconMap ? profileTabIconMap[icon as ProfileTabIconType] : Puzzle;
}

export const notificationIconMap: Record<NotificationType, Component> = {
    success: CircleCheck,
    error: CircleAlert,
    warning: TriangleAlert,
    info: Info,
};

export const calendarMetaIconMap: Record<CalendarEntryMetaIcon, Component> = {
    dice: Dices,
    user: User,
    users: Users,
    monitor: Monitor,
    clock: Clock,
    'map-pin': MapPin,
};

function isCalendarMetaIcon(icon: string): icon is CalendarEntryMetaIcon {
    return icon in calendarMetaIconMap;
}

/** Resolve a source-provided icon name, falling back to a neutral icon for names this core does not know. */
export function resolveCalendarMetaIcon(icon: string): Component {
    return isCalendarMetaIcon(icon) ? calendarMetaIconMap[icon] : Info;
}

/**
 * Lucide icons a module may name for its section of the «Avisos» tab (`MailCategoryDTO::$icon`).
 * A closed list keeps the bundle small: the rest of the library is never imported.
 */
export const mailSectionIconMap: Record<string, Component> = {
    bell: Bell,
    'book-open': BookOpen,
    'building-2': Building2,
    'calendar-check': CalendarCheck,
    'calendar-days': CalendarDays,
    'circle-user-round': CircleUserRound,
    'clipboard-list': ClipboardList,
    'credit-card': CreditCard,
    dices: Dices,
    handshake: Handshake,
    heart: Heart,
    'id-card': IdCard,
    mail: Mail,
    megaphone: Megaphone,
    'map-pin': MapPin,
    newspaper: Newspaper,
    'settings-2': Settings2,
    'shield-check': ShieldCheck,
    swords: Swords,
    ticket: Ticket,
    trophy: Trophy,
    users: Users,
};

/** Resolve the icon a module names for its mail section, falling back to a generic mail icon. */
export function resolveMailSectionIcon(icon: string | null): Component {
    return (
        (icon !== null && Object.hasOwn(mailSectionIconMap, icon)
            ? mailSectionIconMap[icon]
            : undefined) ?? Mail
    );
}

/**
 * Icons a module may name for its notices in the notification centre (core#491): the mail
 * section ones plus a few for conversations. A closed list, like the mail sections.
 */
export const siteNotificationIconMap: Record<string, Component> = {
    ...mailSectionIconMap,
    'at-sign': AtSign,
    'message-circle': MessageCircle,
    'message-square': MessageSquare,
    quote: Quote,
    'thumbs-up': ThumbsUp,
    'user-plus': UserPlus,
    vote: Vote,
};

/** Resolve the icon a module names for a notice, falling back to a bell. */
export function resolveSiteNotificationIcon(icon: string | null): Component {
    return (
        (icon !== null && Object.hasOwn(siteNotificationIconMap, icon)
            ? siteNotificationIconMap[icon]
            : undefined) ?? Bell
    );
}
