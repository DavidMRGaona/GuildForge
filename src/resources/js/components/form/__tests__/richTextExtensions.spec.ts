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

    it('writes underline, strike, subscript and superscript as the panel editor does', () => {
        const html = '<p><u>u</u> <s>s</s> <sub>1</sub> <sup>2</sup></p>';

        expect(htmlOf(html)).toBe(html);
    });

    it('aligns paragraphs and headings with the logical values the panel editor uses', () => {
        const html =
            '<h2 style="text-align: center;">T</h2><h3 style="text-align: end;">S</h3>' +
            '<p style="text-align: center;">a</p><p style="text-align: end;">b</p><p>c</p>';

        expect(htmlOf(html)).toBe(html);
    });

    it('reads the alignment back from the sanitised HTML', () => {
        expect(
            htmlOf('<p style="text-align:end;">a</p><h2 style="text-align:center;">b</h2>')
        ).toBe('<p style="text-align: end;">a</p><h2 style="text-align: center;">b</h2>');
    });

    it('writes no alignment for the start, the default, and none it does not know', () => {
        expect(htmlOf('<p style="text-align: start">a</p><p style="text-align: right">b</p>')).toBe(
            '<p>a</p><p>b</p>'
        );
    });

    it('writes code blocks as the panel editor does', () => {
        const html = '<pre><code>if (a &lt; b) {}</code></pre>';

        expect(htmlOf(html)).toBe(html);
    });

    it('writes tables as the panel editor does, without the widths of the editing view', () => {
        const html =
            '<table><tbody><tr><th><p>H</p></th><th><p>I</p></th></tr>' +
            '<tr><td colspan="2"><p>c</p></td></tr></tbody></table>';

        expect(htmlOf(html)).toBe(html);
        // The panel writes the spans of one too; they mean the same
        expect(
            htmlOf(
                '<table><tbody><tr><th rowspan="1" colspan="1"><p>H</p></th></tr></tbody></table>'
            )
        ).toBe('<table><tbody><tr><th><p>H</p></th></tr></tbody></table>');
    });

    it('moves between cells with Tab and lets it leave after the last one, adding no rows', () => {
        const editor = new Editor({
            element: document.createElement('div'),
            extensions: createRichTextExtensions(),
            content: '<table><tbody><tr><th><p>a</p></th><th><p>b</p></th></tr></tbody></table>',
        });
        editor.commands.setTextSelection(3);
        const handled = (): boolean => {
            const event = new KeyboardEvent('keydown', {
                key: 'Tab',
                bubbles: true,
                cancelable: true,
            });
            editor.view.dom.dispatchEvent(event);

            return event.defaultPrevented;
        };

        expect(handled()).toBe(true);
        expect(handled()).toBe(false);
        expect(editor.getHTML().match(/<tr>/g)).toHaveLength(1);
        editor.destroy();
    });

    it('drops what is outside the toolset', () => {
        const html = htmlOf(
            '<h1>A</h1><p><code>z</code> <mark>m</mark></p><hr><iframe src="https://e.example"></iframe>'
        );

        expect(html).not.toMatch(/<h1|<code|<mark|<hr|<iframe/);
    });

    it('refuses images embedded in the text', () => {
        expect(htmlOf('<img src="data:image/png;base64,AAAA">')).not.toContain('data:image');
    });
});
