import type { KnowledgeSnippetSegment } from '@/types/knowledge';

/** One row of the site search, the same shape on the results page and in the header panel. */
export interface SearchHit {
    type: string;
    typeLabel: string;
    title: string;
    url: string;
    meta: string | null;
    /** Cloudinary public id, or null. */
    image: string | null;
    /** Already formatted in the display zone («sáb 15 nov · 10:00»), or null. */
    date: string | null;
    /** Set only when the content is past («Celebrado», …). */
    pastLabel: string | null;
    snippet: KnowledgeSnippetSegment[];
}

/** A results tab: «Todo» comes first with a null key, then each type with matches. */
export interface SearchTab {
    key: string | null;
    label: string;
    count: number;
}

export type SearchError = 'minChars';
