export interface SeoImage {
    url: string;
    width: number | null;
    height: number | null;
    alt: string;
    isLarge: boolean;
}

export interface SeoMetaTag {
    property: string;
    content: string;
}

/** The `seo` Inertia prop, built by SeoMetadata::toArray() on the server. */
export interface SeoProps {
    scope: 'site' | 'page';
    title: string;
    fullTitle: string;
    description: string;
    canonicalUrl: string;
    type: 'website' | 'article';
    siteName: string;
    locale: string;
    image: SeoImage | null;
    robots: string | null;
    extraMeta: SeoMetaTag[];
    structuredData: Record<string, unknown>[];
}
