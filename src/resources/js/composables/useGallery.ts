import { useI18n } from 'vue-i18n';
import type { Gallery, Photo } from '@/types/models';

interface UseGalleryReturn {
    formatGalleryDate: (dateString: string) => string;
    getPhotoCount: (gallery: Gallery) => string;
    getCoverPhoto: (gallery: Gallery) => Photo | null;
    getGalleryExcerpt: (description: string | null, maxLength?: number) => string;
}

export function useGallery(): UseGalleryReturn {
    const { t, locale } = useI18n();

    function formatGalleryDate(dateString: string): string {
        const date = new Date(dateString);
        return date.toLocaleDateString(locale.value, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    }

    function getPhotoCount(gallery: Gallery): string {
        const count = gallery.photoCount ?? gallery.photos?.length ?? 0;
        return `${count} ${t('gallery.photos')}`;
    }

    function getCoverPhoto(gallery: Gallery): Photo | null {
        if (gallery.photos && gallery.photos.length > 0) {
            return gallery.photos[0] ?? null;
        }
        return null;
    }

    function getGalleryExcerpt(description: string | null, maxLength = 150): string {
        if (!description) {
            return '';
        }

        if (description.length <= maxLength) {
            return description;
        }

        return description.slice(0, maxLength).trim() + '...';
    }

    return {
        formatGalleryDate,
        getPhotoCount,
        getCoverPhoto,
        getGalleryExcerpt,
    };
}
