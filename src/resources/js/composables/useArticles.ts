import { useI18n } from 'vue-i18n';
import type { Article } from '@/types/models';

interface UseArticlesReturn {
    formatPublishedDate: (dateString: string) => string;
    getExcerpt: (article: Article, maxLength?: number) => string;
    getAuthorDisplayName: (article: Article) => string;
}

export function useArticles(): UseArticlesReturn {
    const { locale } = useI18n();
    function formatPublishedDate(dateString: string): string {
        const date = new Date(dateString);
        return date.toLocaleDateString(locale.value, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    }

    function getExcerpt(article: Article, maxLength = 150): string {
        if (article.excerpt) {
            return article.excerpt;
        }

        const cleanContent = article.content.replace(/<[^>]*>/g, '');

        if (cleanContent.length <= maxLength) {
            return cleanContent;
        }

        return cleanContent.slice(0, maxLength).trim() + '...';
    }

    function getAuthorDisplayName(article: Article): string {
        if (article.author.displayName && article.author.displayName.trim() !== '') {
            return article.author.displayName;
        }
        return article.author.name;
    }

    return {
        formatPublishedDate,
        getExcerpt,
        getAuthorDisplayName,
    };
}
