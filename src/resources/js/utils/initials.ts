/**
 * Up to two initials of a name, for an avatar without a photo: the first letter of its first and last
 * words («Ana María Prueba» → «AP»). Whole characters, so a letter outside the basic plane or with its
 * accent is never cut in half; empty for a blank name.
 */
export function initialsOf(name: string | null | undefined): string {
    const words = (name ?? '')
        .trim()
        .split(/\s+/u)
        .map((word) => Array.from(word.replace(/^[^\p{L}\p{N}]+/u, ''))[0] ?? '')
        .filter((letter) => letter !== '');

    if (words.length === 0) {
        return '';
    }

    const first = words[0] ?? '';
    const last = words.length > 1 ? (words[words.length - 1] ?? '') : '';

    return (first + last).toLocaleUpperCase();
}
