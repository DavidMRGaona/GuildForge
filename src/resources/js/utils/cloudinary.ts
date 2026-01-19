const CLOUDINARY_CLOUD_NAME = import.meta.env.VITE_CLOUDINARY_CLOUD_NAME ?? '';
const CLOUDINARY_PREFIX = import.meta.env.VITE_CLOUDINARY_PREFIX ?? '';

interface CloudinaryTransformations {
    width?: number;
    height?: number;
    crop?: 'fill' | 'fit' | 'scale' | 'thumb';
    quality?: 'auto' | number;
    format?: 'auto' | 'webp' | 'jpg' | 'png';
    gravity?: 'auto' | 'face' | 'center';
}

/**
 * Normalize the public ID:
 * - Add prefix if configured and not already present
 * - Remove file extension (Cloudinary public_id doesn't include it)
 */
function normalizePublicId(publicId: string): string {
    // Remove file extension
    const lastDotIndex = publicId.lastIndexOf('.');
    if (lastDotIndex > 0) {
        const extension = publicId.substring(lastDotIndex + 1).toLowerCase();
        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].includes(extension)) {
            publicId = publicId.substring(0, lastDotIndex);
        }
    }

    // Add prefix if configured and not already present
    if (CLOUDINARY_PREFIX && !publicId.startsWith(CLOUDINARY_PREFIX + '/')) {
        publicId = `${CLOUDINARY_PREFIX}/${publicId}`;
    }

    return publicId;
}

export function buildImageUrl(
    publicId: string | null | undefined,
    transformations: CloudinaryTransformations = {}
): string | null {
    if (!publicId) return null;

    const normalizedId = normalizePublicId(publicId);

    const parts: string[] = [];
    if (transformations.width) parts.push(`w_${transformations.width}`);
    if (transformations.height) parts.push(`h_${transformations.height}`);
    if (transformations.crop) parts.push(`c_${transformations.crop}`);
    if (transformations.gravity) parts.push(`g_${transformations.gravity}`);
    if (transformations.quality) parts.push(`q_${transformations.quality}`);
    if (transformations.format) parts.push(`f_${transformations.format}`);

    // Always add auto quality and format for optimization
    if (!transformations.quality) parts.push('q_auto');
    if (!transformations.format) parts.push('f_auto');

    const transformationString = parts.join(',');
    return `https://res.cloudinary.com/${CLOUDINARY_CLOUD_NAME}/image/upload/${transformationString}/${normalizedId}`;
}

// Presets
export const buildCardImageUrl = (publicId: string | null | undefined): string | null =>
    buildImageUrl(publicId, { width: 600, height: 400, crop: 'fill' });

export const buildHeroImageUrl = (publicId: string | null | undefined): string | null =>
    buildImageUrl(publicId, { width: 1200, height: 600, crop: 'fill' });

export const buildAvatarUrl = (publicId: string | null | undefined, size = 100): string | null =>
    buildImageUrl(publicId, { width: size, height: size, crop: 'fill', gravity: 'face' });

export const buildGalleryImageUrl = (publicId: string | null | undefined): string | null =>
    buildImageUrl(publicId, { width: 800, height: 600, crop: 'fit' });

export const buildMosaicLargeUrl = (publicId: string | null | undefined): string | null =>
    buildImageUrl(publicId, { width: 800, height: 600, crop: 'fill' });

export const buildMosaicSmallUrl = (publicId: string | null | undefined): string | null =>
    buildImageUrl(publicId, { width: 400, height: 300, crop: 'fill' });

export const buildLightboxImageUrl = (publicId: string | null | undefined): string | null =>
    buildImageUrl(publicId, { width: 1920, crop: 'fit' });

export const buildFullScreenHeroImageUrl = (publicId: string | null | undefined): string | null =>
    buildImageUrl(publicId, { width: 1920, height: 1080, crop: 'fill', gravity: 'auto' });
