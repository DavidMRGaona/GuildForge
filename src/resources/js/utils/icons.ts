import type { Component } from 'vue';
import type { ActivityIcon, CalendarEntryMetaIcon } from '@/types/models';
import type { ProfileTabIcon as ProfileTabIconType } from '@/types/profile';
import type { NotificationType } from '@/composables/useNotifications';
import {
    AtSign,
    Award,
    Ban,
    Bell,
    BookOpen,
    Building2,
    Calendar,
    CalendarCheck,
    CalendarDays,
    Castle,
    ChartBar,
    CircleAlert,
    CircleCheck,
    CircleUserRound,
    ClipboardCheck,
    ClipboardList,
    Clock,
    Compass,
    CreditCard,
    Crown,
    Dices,
    Feather,
    FileText,
    Flag,
    Flame,
    Gem,
    HandHeart,
    Handshake,
    Heart,
    Hourglass,
    IdCard,
    Image,
    Images,
    Info,
    Lock,
    Mail,
    Map,
    MapPin,
    Medal,
    Megaphone,
    MessageCircle,
    MessageSquare,
    Monitor,
    Mountain,
    Newspaper,
    Puzzle,
    Quote,
    Rocket,
    ScrollText,
    Settings,
    Settings2,
    Shield,
    ShieldCheck,
    Sparkles,
    Sprout,
    SquarePen,
    Star,
    Sword,
    Swords,
    Target,
    ThumbsUp,
    Ticket,
    TriangleAlert,
    Trophy,
    User,
    UserPlus,
    Users,
    Vote,
    WandSparkles,
    Zap,
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
    'id-card': IdCard,
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
 * section ones plus a few for conversations, reviews and moderation. A closed list, like the
 * mail sections; the server keeps the same names in `SiteNotificationIcons::NAMES` and a test
 * checks both lists match (core#667).
 */
export const siteNotificationIconMap: Record<string, Component> = {
    ...mailSectionIconMap,
    'at-sign': AtSign,
    ban: Ban,
    'chart-bar': ChartBar,
    'circle-check': CircleCheck,
    'clipboard-check': ClipboardCheck,
    'file-text': FileText,
    flag: Flag,
    images: Images,
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

/**
 * Icons a module may give a profile badge (core#776). A closed list, like the bell's: the server
 * keeps the same names in `ProfileBadgeIcons::NAMES` and a test checks both lists match.
 */
export const profileBadgeIconMap: Record<string, Component> = {
    award: Award,
    'book-open': BookOpen,
    'calendar-check': CalendarCheck,
    castle: Castle,
    compass: Compass,
    crown: Crown,
    dices: Dices,
    feather: Feather,
    flag: Flag,
    flame: Flame,
    gem: Gem,
    'hand-heart': HandHeart,
    handshake: Handshake,
    heart: Heart,
    hourglass: Hourglass,
    map: Map,
    'map-pin': MapPin,
    medal: Medal,
    'message-square': MessageSquare,
    mountain: Mountain,
    puzzle: Puzzle,
    rocket: Rocket,
    'scroll-text': ScrollText,
    shield: Shield,
    sparkles: Sparkles,
    sprout: Sprout,
    star: Star,
    sword: Sword,
    swords: Swords,
    target: Target,
    ticket: Ticket,
    trophy: Trophy,
    users: Users,
    vote: Vote,
    'wand-sparkles': WandSparkles,
    zap: Zap,
};

/** Resolve the icon of a profile badge, falling back to an award. */
export function resolveProfileBadgeIcon(icon: string): Component {
    return (
        (Object.hasOwn(profileBadgeIconMap, icon) ? profileBadgeIconMap[icon] : undefined) ?? Award
    );
}
