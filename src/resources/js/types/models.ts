export interface User {
    id: string;
    name: string;
    displayName: string;
    email: string;
    avatarPublicId: string | null;
    role: UserRole;
    createdAt: string;
    updatedAt: string;
}

export type UserRole = 'admin' | 'editor' | 'member';

export interface Tag {
    id: string;
    name: string;
    slug: string;
    color: string;
    parentId: string | null;
}

export interface Event {
    id: string;
    title: string;
    slug: string;
    description: string;
    startDate: string;
    endDate: string | null;
    location: string | null;
    imagePublicId: string | null;
    memberPrice: number | null;
    nonMemberPrice: number | null;
    isPublished: boolean;
    createdAt: string;
    updatedAt: string;
    tags: Tag[];
}

export interface Article {
    id: string;
    title: string;
    slug: string;
    content: string;
    excerpt: string | null;
    featuredImagePublicId: string | null;
    isPublished: boolean;
    publishedAt: string | null;
    author: User;
    createdAt: string;
    updatedAt: string;
    tags: Tag[];
}

export interface Gallery {
    id: string;
    title: string;
    slug: string;
    description: string | null;
    coverImagePublicId: string | null;
    isPublished: boolean;
    photos?: Photo[];
    photoCount?: number;
    createdAt: string;
    updatedAt: string;
    tags: Tag[];
}

export interface Photo {
    id: string;
    imagePublicId: string;
    caption: string | null;
    sortOrder: number;
}

export interface HeroSlide {
    id: string;
    title: string;
    subtitle: string | null;
    buttonText: string | null;
    buttonUrl: string | null;
    imagePublicId: string | null;
}

export interface EventFilters {
    search?: string;
    upcoming?: boolean;
    tags?: string[];
    page?: number;
}

export interface ArticleFilters {
    search?: string;
    authorId?: string;
    tags?: string[];
    page?: number;
}

export interface GalleryFilters {
    search?: string;
    tags?: string[];
    page?: number;
}

export interface CalendarEvent {
    id: string;
    title: string;
    slug: string;
    description: string;
    start: string;
    end: string | null;
    location: string | null;
    imagePublicId: string | null;
    memberPrice: number | null;
    nonMemberPrice: number | null;
    url: string;
    backgroundColor: string;
    tags: Tag[];
}

export type ActivityIcon =
    | 'dice'
    | 'sword'
    | 'book'
    | 'users'
    | 'calendar'
    | 'map'
    | 'trophy'
    | 'puzzle'
    | 'sparkles'
    | 'heart';

export interface Activity {
    icon: ActivityIcon;
    title: string;
    description: string;
}

export interface JoinStep {
    title: string;
    description: string | null;
}

export interface ContactFormData {
    name: string;
    email: string;
    message: string;
    website: string; // Honeypot field - should be empty
}

export interface SocialMediaLinks {
    facebook?: string | undefined;
    instagram?: string | undefined;
    twitter?: string | undefined;
    discord?: string | undefined;
    tiktok?: string | undefined;
}