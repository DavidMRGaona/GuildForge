export * from './models';
export * from './inertia';

export interface PaginatedResponse<T> {
    data: T[];
    meta: {
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
}

export interface ApiResponse<T> {
    data: T;
    message?: string;
}

export interface FlashMessages {
    success?: string;
    error?: string;
}