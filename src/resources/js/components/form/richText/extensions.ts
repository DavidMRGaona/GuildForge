import type { AnyExtension } from '@tiptap/core';
import Image from '@tiptap/extension-image';
import { Placeholder } from '@tiptap/extensions';
import StarterKit from '@tiptap/starter-kit';

/**
 * The editor's image: a block with the delivery URL as `src` and the stored path as `data-id`,
 * which is how the panel's RichEditor knows an image as an attachment (core#573).
 */
export const EditorImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            id: {
                default: null,
                parseHTML: (element: HTMLElement): string | null => element.getAttribute('data-id'),
                renderHTML: (attributes: Record<string, unknown>): Record<string, string> =>
                    typeof attributes.id === 'string' ? { 'data-id': attributes.id } : {},
            },
        };
    },
}).configure({ inline: false, allowBase64: false });

/**
 * Headings 2 and 3, bold, italic, lists, links, quotes and images: what the panel editor
 * writes for those, so the HTML opens there without loss. The page already has its h1.
 */
export function createRichTextExtensions(placeholder: () => string = () => ''): AnyExtension[] {
    return [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            blockquote: {},
            bold: {},
            italic: {},
            bulletList: {},
            orderedList: {},
            link: {
                openOnClick: false,
                autolink: false,
                HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer nofollow' },
            },
            code: false,
            codeBlock: false,
            horizontalRule: false,
            strike: false,
            underline: false,
        }),
        EditorImage,
        Placeholder.configure({ placeholder: () => placeholder() }),
    ];
}
