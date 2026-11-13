import { Editor } from '@tiptap/core';
import { describe, expect, it } from 'vitest';
import { createRichTextExtensions } from '../richText/extensions';

function htmlOf(content: string): string {
    const editor = new Editor({
        element: document.createElement('div'),
        extensions: createRichTextExtensions(),
        content,
    });
    const html = editor.getHTML();
    editor.destroy();

    return html;
}

/**
 * What the panel's RichEditor stores for the same things: this is the HTML it opens without loss.
 */
describe('the editor HTML', () => {
    it('writes the same markup as the panel editor for the toolset', () => {
        const html =
            '<h2>Título</h2><h3>Sub</h3><p>Hola <strong>negrita</strong> <em>cursiva</em></p>' +
            '<ul><li><p>uno</p></li></ul><ol><li><p>dos</p></li></ol><blockquote><p>cita</p></blockquote>';

        expect(htmlOf(html)).toBe(html);
    });

    it('writes links the way the panel editor does', () => {
        expect(htmlOf('<p><a href="https://example.org/x">enlace</a></p>')).toBe(
            '<p><a target="_blank" rel="noopener noreferrer nofollow" href="https://example.org/x">enlace</a></p>'
        );
    });

    it('keeps the stored path of an image in data-id next to src', () => {
        const html = htmlOf(
            '<img src="https://cdn.example/a.png" data-id="articles/content/2026/11/a.png" alt="Foto">'
        );

        expect(html).toContain('src="https://cdn.example/a.png"');
        expect(html).toContain('data-id="articles/content/2026/11/a.png"');
        expect(html).toContain('alt="Foto"');
    });

    it('drops what is outside the toolset', () => {
        const html = htmlOf(
            '<h1>A</h1><p><s>x</s> <u>y</u> <code>z</code></p><pre><code>c</code></pre><hr><iframe src="https://e.example"></iframe>'
        );

        expect(html).not.toMatch(/<h1|<s>|<u>|<code|<pre|<hr|<iframe/);
    });

    it('refuses images embedded in the text', () => {
        expect(htmlOf('<img src="data:image/png;base64,AAAA">')).not.toContain('data:image');
    });
});
