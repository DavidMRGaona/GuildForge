import type { Component } from 'vue';
import type { ActivityIcon } from '@/types/models';
import type { ProfileTabIcon as ProfileTabIconType } from '@/types/profile';
import type { NotificationType } from '@/composables/useNotifications';
import {
    BookOpen,
    Calendar,
    CircleAlert,
    CircleCheck,
    Dices,
    FileText,
    Heart,
    Image,
    Info,
    Lock,
    Map,
    Puzzle,
    Settings,
    Sparkles,
    SquarePen,
    Swords,
    TriangleAlert,
    Trophy,
    User,
    Users,
} from 'lucide-vue-next';

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
};

export const notificationIconMap: Record<NotificationType, Component> = {
    success: CircleCheck,
    error: CircleAlert,
    warning: TriangleAlert,
    info: Info,
};
