/**
 * Calculate relative luminance of a hex color.
 * Used to determine if text should be dark or light.
 *
 * @see https://www.w3.org/TR/WCAG20/#relativeluminancedef
 */
export function getLuminance(hex: string): number {
    const matches = hex.replace('#', '').match(/.{2}/g);
    if (!matches || matches.length < 3) return 0;

    const rgb = matches.map((c) => {
        const value = parseInt(c, 16) / 255;
        return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
    }) as [number, number, number];

    return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
}

/**
 * Determine if text should be dark or light based on background color.
 *
 * @returns '#111827' (dark) for light backgrounds, '#ffffff' for dark backgrounds
 */
export function getContrastTextColor(hex: string): string {
    const luminance = getLuminance(hex);
    return luminance > 0.179 ? '#111827' : '#ffffff';
}
