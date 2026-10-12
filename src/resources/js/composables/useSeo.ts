import { computed } from 'vue';
import { useHead } from '@unhead/vue';
import { usePage } from '@inertiajs/vue3';
import type { SeoProps } from '@/types/seo';

/** Options of the pre-3.1 API, kept for modules built against core 3.0. */
export interface SeoOptions {
    title?: string;
    description?: string | null;
    image?: string | null;
    url?: string;
    canonical?: string;
    type?: 'website' | 'article';
}

// Type aliases, not interfaces: unhead's tag types need the implicit index
// signature (data-*) that only type aliases get.
type HeadMeta = {
    name?: string;
    property?: string;
    content: string;
};

type SeoHead = {
    title: string;
    link: { rel: string; href: string }[];
    meta: HeadMeta[];
    script: { type: string; textContent: string }[];
};

interface SeoPageProps {
    seo?: SeoProps;
    [key: string]: unknown;
}

const DESCRIPTION_MAX_LENGTH = 160;
const SERVER_TAG_SELECTOR = '[data-seo-server]';

function truncate(text: string): string {
    if (text.length <= DESCRIPTION_MAX_LENGTH) {
        return text;
    }
    return `${text.slice(0, DESCRIPTION_MAX_LENGTH - 1).trimEnd()}…`;
}

/** Same tags as resources/views/components/seo-head.blade.php. */
export function buildSeoHead(seo: SeoProps): SeoHead {
    const meta: HeadMeta[] = [
        { name: 'description', content: seo.description },
        { property: 'og:type', content: seo.type },
        { property: 'og:site_name', content: seo.siteName },
        { property: 'og:locale', content: seo.locale },
        { property: 'og:title', content: seo.title },
        { property: 'og:description', content: seo.description },
        { property: 'og:url', content: seo.canonicalUrl },
        { name: 'twitter:card', content: seo.image?.isLarge ? 'summary_large_image' : 'summary' },
        { name: 'twitter:title', content: seo.title },
        { name: 'twitter:description', content: seo.description },
    ];

    if (seo.robots) {
        meta.push({ name: 'robots', content: seo.robots });
    }

    if (seo.image) {
        meta.push(
            { property: 'og:image', content: seo.image.url },
            { property: 'og:image:alt', content: seo.image.alt },
            { name: 'twitter:image', content: seo.image.url },
            { name: 'twitter:image:alt', content: seo.image.alt }
        );
        if (seo.image.width !== null && seo.image.height !== null) {
            meta.push(
                { property: 'og:image:width', content: String(seo.image.width) },
                { property: 'og:image:height', content: String(seo.image.height) }
            );
        }
    }

    meta.push(...seo.extraMeta.map((tag) => ({ property: tag.property, content: tag.content })));

    return {
        title: seo.fullTitle,
        link: [{ rel: 'canonical', href: seo.canonicalUrl }],
        meta,
        script: seo.structuredData.map((block) => ({
            type: 'application/ld+json',
            textContent: JSON.stringify(block),
        })),
    };
}

/**
 * Remove the tags the server rendered for crawlers. From then on unhead owns
 * the head; keeping them would leave stale and duplicated tags after the
 * first client-side navigation.
 */
export function removeServerSeoTags(): void {
    document.head.querySelectorAll(SERVER_TAG_SELECTOR).forEach((element) => element.remove());
}

/**
 * Apply the server's `seo` prop on every Inertia navigation. Called once in
 * the app root; pages do not call it.
 */
export function useServerSeo(): void {
    const page = usePage<SeoPageProps>();
    const head = computed<SeoHead | null>(() => {
        // The root runs before Inertia sets the first page, so props may not exist yet
        const seo: SeoProps | undefined = page.props?.seo;
        return seo ? buildSeoHead(seo) : null;
    });

    useHead({
        title: () => head.value?.title ?? '',
        link: () => head.value?.link ?? [],
        meta: () => head.value?.meta ?? [],
        script: () => head.value?.script ?? [],
    });
}

function withLegacyOptions(seo: SeoProps, options: SeoOptions): SeoProps {
    const title = options.title ?? seo.title;
    const origin = typeof window !== 'undefined' ? window.location.origin : '';

    return {
        ...seo,
        scope: 'page',
        title,
        fullTitle: options.title ? `${options.title} - ${seo.siteName}` : seo.fullTitle,
        description: truncate(options.description ?? seo.description),
        type: options.type ?? seo.type,
        canonicalUrl: options.canonical
            ? `${origin}${options.canonical}`
            : (options.url ?? seo.canonicalUrl),
        image: options.image
            ? { url: options.image, width: null, height: null, alt: title, isLarge: true }
            : seo.image,
    };
}

/**
 * @deprecated Since core 3.1 the server sends each page's metadata as the
 * `seo` prop and the app root applies it. The options only apply while the
 * server sent the site defaults for the page (modules built for core 3.0).
 */
export function useSeo(options: SeoOptions = {}): void {
    const page = usePage<SeoPageProps>();
    const seo: SeoProps | undefined = page.props.seo;

    if (!seo || seo.scope === 'page' || Object.keys(options).length === 0) {
        return;
    }

    useHead(buildSeoHead(withLegacyOptions(seo, options)));
}
