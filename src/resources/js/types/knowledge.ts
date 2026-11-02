export type KnowledgeBadge = 'new' | 'updated' | null;

export interface KnowledgeCategory {
    id: string;
    label: string;
    icon: string;
    guideCount: number;
}

export interface KnowledgeGuideSummary {
    id: string;
    title: string;
    summary: string;
    categoryId: string;
    badge: KnowledgeBadge;
}

export interface KnowledgeSnippetSegment {
    text: string;
    match: boolean;
}

export interface KnowledgeSearchHit {
    id: string;
    title: string;
    categoryId: string;
    snippet: KnowledgeSnippetSegment[];
}

export interface KnowledgeGuide {
    id: string;
    title: string;
    summary: string;
    category: KnowledgeCategory;
    html: string;
    badge: KnowledgeBadge;
    related: KnowledgeGuideSummary[];
    siblings: KnowledgeGuideSummary[];
}
