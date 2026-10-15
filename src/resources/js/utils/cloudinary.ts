export interface CloudinaryConfig {
    cloudName: string;
    prefix: string;
}

declare global {
    var __guildforgeCloudinary: CloudinaryConfig | undefined;
}

/**
 * Publish the delivery settings shared by the backend through Inertia.
 *
 * Called once while booting the app. The value lives on `globalThis` on
 * purpose: module bundles ship their own copy of this file, so module-level
 * state would not be shared between the core bundle and a module's bundle.
 */
export function setCloudinaryConfig(config: CloudinaryConfig): void {
    globalThis.__guildforgeCloudinary = config;
}

/**
 * Resolve the delivery settings at call time.
 *
 * Reading these at runtime (instead of inlining `import.meta.env` at build
 * time) is what allows module assets to be compiled by CI into a distributable
 * package: the bundle carries no installation-specific configuration.
 * The build-time values remain as a fallback for local development.
 */
function getCloudinaryConfig(): CloudinaryConfig {
    const runtime = globalThis.__guildforgeCloudinary;

    return {
        cloudName: runtime?.cloudName || import.meta.env.VITE_CLOUDINARY_CLOUD_NAME || '',
        prefix: runtime?.prefix || import.meta.env.VITE_CLOUDINARY_PREFIX || '',
    };
}

interface CloudinaryTransformations {
    width?: number;
    height?: number;
    crop?: 'fill' | 'fit' | 'scale' | 'thumb';
    quality?: 'auto' | number;
    format?: 'auto' | 'webp' | 'jpg' | 'png';
    gravity?: 'auto' | 'face' | 'center';
    /** Keep every frame of an animated image (`fl_animated`), whatever format `f_auto` picks. */
    animated?: boolean;
}

/** Marker the backend adds before the extension of animated avatars (`AvatarPath::ANIMATED_SUFFIX`). */
const ANIMATED_AVATAR_SUFFIX = '-animated';

/**
 * Whether a stored avatar path is marked as animated: "users/<id>/<uuid>-animated.gif".
 *
 * Static avatars are delivered with plain `f_auto`; animated ones also need `fl_animated`,
 * because `f_auto` alone serves a still JPEG XL to browsers that accept it. The flag is never
 * added to static avatars: with it, `f_auto` may turn a still image into a video.
 */
export function isAnimatedAvatar(publicId: string | null | undefined): boolean {
    if (!publicId) return false;

    const fileName = publicId.substring(publicId.lastIndexOf('/') + 1);
    const lastDotIndex = fileName.lastIndexOf('.');
    const baseName = lastDotIndex > 0 ? fileName.substring(0, lastDotIndex) : fileName;

    return baseName.endsWith(ANIMATED_AVATAR_SUFFIX);
}

/**
 * Normalize the public ID:
 * - Add prefix if configured and not already present
 * - Remove file extension (Cloudinary public_id doesn't include it)
 */
function normalizePublicId(publicId: string, prefix: string): string {
    // Remove file extension
    const lastDotIndex = publicId.lastIndexOf('.');
    if (lastDotIndex > 0) {
        const extension = publicId.substring(lastDotIndex + 1).toLowerCase();
        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].includes(extension)) {
            publicId = publicId.substring(0, lastDotIndex);
        }
    }

    // Add prefix if configured and not already present
    if (prefix && !publicId.startsWith(prefix + '/')) {
        publicId = `${prefix}/${publicId}`;
    }

    return publicId;
}

export function buildImageUrl(
    publicId: string | null | undefined,
    transformations: CloudinaryTransformations = {}
): string | null {
    if (!publicId) return null;

    const { cloudName, prefix } = getCloudinaryConfig();
    const normalizedId = normalizePublicId(publicId, prefix);

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
    if (transformations.animated) parts.push('fl_animated');

    const transformationString = parts.join(',');
    return `https://res.cloudinary.com/${cloudName}/image/upload/${transformationString}/${normalizedId}`;
}

// Presets
export const buildCardImageUrl = (publicId: string | null | undefined): string | null =>
    buildImageUrl(publicId, { width: 600, height: 400, crop: 'fill' });

export const buildHeroImageUrl = (publicId: string | null | undefined): string | null =>
    buildImageUrl(publicId, { width: 1200, height: 600, crop: 'fill' });

export const buildAvatarUrl = (publicId: string | null | undefined, size = 100): string | null =>
    buildImageUrl(publicId, {
        width: size,
        height: size,
        crop: 'fill',
        gravity: 'face',
        animated: isAnimatedAvatar(publicId),
    });

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
