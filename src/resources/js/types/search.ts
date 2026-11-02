import type { KnowledgeSnippetSegment } from '@/types/knowledge';

export interface SearchResult {
    title: string;
    url: string;
    context: string | null;
    snippet: KnowledgeSnippetSegment[];
}

export interface SearchSource {
    key: string;
    label: string;
    results: SearchResult[];
}
