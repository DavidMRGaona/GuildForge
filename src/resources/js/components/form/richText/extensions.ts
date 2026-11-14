import type { AnyExtension } from '@tiptap/core';
import { mergeAttributes } from '@tiptap/core';
import Image from '@tiptap/extension-image';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
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
 * A table as the panel stores it: `<table><tbody>…`, without the column group and widths
 * TipTap draws for editing (the panel does not keep them and the sanitiser drops them).
 */
export const EditorTable = Table.extend({
    renderHTML({ HTMLAttributes }) {
        return [
            'table',
            mergeAttributes(this.options.HTMLAttributes, HTMLAttributes),
            ['tbody', 0],
        ];
    },
    // Tab moves between cells but leaves the editor past the last one (or before the first)
    // instead of adding rows: the keyboard never gets stuck in a table
    addKeyboardShortcuts() {
        return {
            ...this.parent?.(),
            Tab: (): boolean => this.editor.commands.goToNextCell(),
            'Shift-Tab': (): boolean => this.editor.commands.goToPreviousCell(),
        };
    },
}).configure({ resizable: false });

/**
 * The alignments the panel's RichEditor writes besides the start: logical ones, so "end" is
 * right in Spanish and left in a right-to-left language. The start is the default and writes
 * nothing (aligning to it removes the alignment); justify has no button, nor in the panel, but
 * is kept when a text already has it.
 */
export const TEXT_ALIGNMENTS = ['center', 'end', 'justify'] as const;

/**
 * The toolset of the panel's article editor (core#627): headings 2 and 3, bold, italic,
 * underline, strike, subscript, superscript, links, alignment, quotes, code blocks, lists,
 * tables and images. The same HTML as the panel writes, so a text opens there without loss.
 * The page already has its h1.
 */
export function createRichTextExtensions(placeholder: () => string = () => ''): AnyExtension[] {
    return [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            blockquote: {},
            bold: {},
            italic: {},
            underline: {},
            strike: {},
            codeBlock: {},
            bulletList: {},
            orderedList: {},
            link: {
                openOnClick: false,
                autolink: false,
                HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer nofollow' },
            },
            code: false,
            horizontalRule: false,
        }),
        Subscript,
        Superscript,
        TextAlign.configure({
            types: ['heading', 'paragraph'],
            alignments: [...TEXT_ALIGNMENTS],
            defaultAlignment: null,
        }),
        EditorTable,
        TableRow,
        TableHeader,
        TableCell,
        EditorImage,
        Placeholder.configure({ placeholder: () => placeholder() }),
    ];
}
