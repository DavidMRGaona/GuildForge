import { useHead } from '@unhead/vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

interface SeoOptions {
    title?: string;
    description?: string | null;
    image?: string | null;
    url?: string;
    type?: 'website' | 'article';
}

function truncateText(text: string, maxLength: number): string {
    if (text.length <= maxLength) {
        return text;
    }
    return text.slice(0, maxLength).trim() + '...';
}

export function useSeo(options: SeoOptions): void {
    const { t } = useI18n();
    const page = usePage();
    const siteName = String(page.props.appName ?? '');

    const title = options.title ?? siteName;
    const fullTitle = options.title ? `${options.title} - ${siteName}` : siteName;
    const description = options.description ?? String(t('home.subtitle'));
    const truncatedDescription = truncateText(description, 160);
    const type = options.type ?? 'website';
    const url = options.url ?? (typeof window !== 'undefined' ? window.location.href : '');
    const image = options.image ?? null;

    useHead({
        title: fullTitle,
        meta: [
            { name: 'description', content: truncatedDescription },
            { property: 'og:title', content: title },
            { property: 'og:description', content: truncatedDescription },
            { property: 'og:type', content: type },
            { property: 'og:url', content: url },
            { property: 'og:site_name', content: siteName },
            { name: 'twitter:card', content: image ? 'summary_large_image' : 'summary' },
            { name: 'twitter:title', content: title },
            { name: 'twitter:description', content: truncatedDescription },
            ...(image
                ? [
                      { property: 'og:image', content: image },
                      { name: 'twitter:image', content: image },
                  ]
                : []),
        ],
    });
}
