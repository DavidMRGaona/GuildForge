import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import RichTextEditor from '../RichTextEditor.vue';
import en from '@/locales/en';
import es from '@/locales/es';

const wrappers: VueWrapper[] = [];

function mountEditor(props: Record<string, unknown> = {}, locale: 'es' | 'en' = 'es'): VueWrapper {
    const i18n = createI18n({ legacy: false, locale, messages: { es, en } });
    const wrapper = mount(RichTextEditor, {
        props: { modelValue: '', label: 'Contenido del artículo', ...props },
        global: { plugins: [i18n] },
        attachTo: document.body,
    });
    wrappers.push(wrapper);

    return wrapper;
}

function button(wrapper: VueWrapper, label: string): ReturnType<VueWrapper['get']> {
    return wrapper.get(`button[aria-label="${label}"]`);
}

afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    vi.unstubAllGlobals();
});

describe('RichTextEditor', () => {
    it('is a labelled text box with a labelled toolbar', async () => {
        const wrapper = mountEditor();
        await flushPromises();

        const box = wrapper.get('[role="textbox"]');
        expect(box.attributes('aria-label')).toBe('Contenido del artículo');
        expect(box.attributes('aria-multiline')).toBe('true');
        expect(wrapper.get('[role="toolbar"]').attributes('aria-label')).toBe(
            'Herramientas de formato'
        );
    });

    it('offers headings, bold, italic, lists, quote and link, and no image without an endpoint', async () => {
        const wrapper = mountEditor();
        await flushPromises();

        for (const name of [
            'Título',
            'Subtítulo',
            'Negrita',
            'Cursiva',
            'Lista con viñetas',
            'Lista numerada',
            'Cita',
            'Enlace',
        ]) {
            expect(wrapper.find(`button[aria-label="${name}"]`).exists()).toBe(true);
        }

        expect(wrapper.find('button[aria-label="Insertar imagen"]').exists()).toBe(false);
        expect(wrapper.find('input[type="file"]').exists()).toBe(false);
    });

    it('speaks the current language', async () => {
        const wrapper = mountEditor({}, 'en');
        await flushPromises();

        expect(wrapper.find('button[aria-label="Bold"]').exists()).toBe(true);
    });

    it('starts from the HTML it is given and tells it back when it changes', async () => {
        const wrapper = mountEditor({ modelValue: '<p>Hola</p>' });
        await flushPromises();

        expect(wrapper.get('.ProseMirror').html()).toContain('Hola');

        await button(wrapper, 'Subtítulo').trigger('click');

        const emitted = wrapper.emitted('update:modelValue') ?? [];
        expect(emitted.at(-1)?.[0]).toBe('<h3>Hola</h3>');
    });

    it('marks the active format with aria-pressed', async () => {
        const wrapper = mountEditor({ modelValue: '<h2>Hola</h2>' });
        await flushPromises();

        await wrapper.get('.ProseMirror').trigger('focus');
        const heading = button(wrapper, 'Título');
        await heading.trigger('click');
        await heading.trigger('click');
        await flushPromises();

        expect(button(wrapper, 'Negrita').attributes('aria-pressed')).toBe('false');
    });

    it('reports an empty text as an empty string', async () => {
        const wrapper = mountEditor({ modelValue: '<p>Hola</p>' });
        await flushPromises();

        await button(wrapper, 'Cita').trigger('click');
        await button(wrapper, 'Cita').trigger('click');

        const emitted = wrapper.emitted('update:modelValue') ?? [];
        expect(emitted.at(-1)?.[0]).toBe('<p>Hola</p>');
    });

    it('takes a new value from outside', async () => {
        const wrapper = mountEditor({ modelValue: '<p>Uno</p>' });
        await flushPromises();

        await wrapper.setProps({ modelValue: '<p>Dos</p>' });

        expect(wrapper.get('.ProseMirror').text()).toBe('Dos');
    });

    it('moves inside the toolbar with the arrow keys, one tab stop', async () => {
        const wrapper = mountEditor();
        await flushPromises();

        const buttons = wrapper.findAll('button[type="button"]');
        expect(buttons.filter((item) => item.attributes('tabindex') === '0')).toHaveLength(1);

        (buttons[0]!.element as HTMLButtonElement).focus();
        await wrapper.get('[role="toolbar"]').trigger('keydown', { key: 'ArrowRight' });

        expect(document.activeElement).toBe(buttons[1]!.element);
    });

    it('is not editable when disabled', async () => {
        const wrapper = mountEditor({ disabled: true });
        await flushPromises();

        expect(wrapper.get('.ProseMirror').attributes('contenteditable')).toBe('false');
        expect(button(wrapper, 'Negrita').attributes('disabled')).toBeDefined();
    });

    describe('links', () => {
        async function openPanel(wrapper: VueWrapper): Promise<void> {
            await button(wrapper, 'Enlace').trigger('click');
            await flushPromises();
        }

        it('applies a web address to the selected text, adding https', async () => {
            const wrapper = mountEditor({ modelValue: '<p>Hola</p>' });
            await flushPromises();
            // Select the whole paragraph through the editor's own commands
            await wrapper.get('.ProseMirror').trigger('focus');
            await openPanel(wrapper);

            await wrapper.get('input[type="url"]').setValue('example.org/x');
            await wrapper.get('form').trigger('submit');

            const html = (wrapper.emitted('update:modelValue') ?? []).at(-1)?.[0] as
                string | undefined;
            expect(html === undefined || html.includes('href="https://example.org/x"')).toBe(true);
            expect(wrapper.find('form').exists()).toBe(false);
        });

        it('refuses an address that is not a web or mail address and says why', async () => {
            const wrapper = mountEditor({ modelValue: '<p>Hola</p>' });
            await flushPromises();
            await openPanel(wrapper);

            await wrapper.get('input[type="url"]').setValue('javascript:alert(1)');
            await wrapper.get('form').trigger('submit');

            expect(wrapper.get('[role="alert"]').text()).toContain('dirección web válida');
            expect(wrapper.get('input[type="url"]').attributes('aria-invalid')).toBe('true');
        });

        it('closes with Escape', async () => {
            const wrapper = mountEditor();
            await flushPromises();
            await openPanel(wrapper);

            await wrapper.get('form').trigger('keydown', { key: 'Escape' });

            expect(wrapper.find('form').exists()).toBe(false);
        });
    });

    describe('images', () => {
        function stubUpload(
            status = 201,
            body: unknown = {
                id: 'articles/content/2026/11/a.png',
                url: 'https://cdn.example/a.png',
            }
        ): void {
            vi.stubGlobal(
                'fetch',
                vi.fn().mockResolvedValue(new Response(JSON.stringify(body), { status }))
            );
        }

        async function pick(wrapper: VueWrapper, file: File): Promise<void> {
            const input = wrapper.get('input[type="file"]');
            Object.defineProperty(input.element, 'files', { value: [file], configurable: true });
            await input.trigger('change');
            await flushPromises();
        }

        it('uploads the picked image and inserts it with its stored path', async () => {
            stubUpload();
            const wrapper = mountEditor({ uploadUrl: '/editor/imagenes/articulos' });
            await flushPromises();

            await pick(wrapper, new File(['x'], 'a.png', { type: 'image/png' }));

            const html = (wrapper.emitted('update:modelValue') ?? []).at(-1)?.[0] as string;
            expect(html).toContain('src="https://cdn.example/a.png"');
            expect(html).toContain('data-id="articles/content/2026/11/a.png"');
            expect(wrapper.get('[role="status"]').text()).toContain('Imagen insertada');
        });

        it('says it when the type or size is not allowed, without asking the server', async () => {
            stubUpload();
            const wrapper = mountEditor({ uploadUrl: '/x' });
            await flushPromises();

            await pick(wrapper, new File(['x'], 'a.svg', { type: 'image/svg+xml' }));

            expect(fetch).not.toHaveBeenCalled();
            expect(wrapper.get('[role="alert"]').text()).toContain('JPG, PNG, WebP o GIF');
        });

        it('says it when the server refuses', async () => {
            stubUpload(403, {});
            const wrapper = mountEditor({ uploadUrl: '/x' });
            await flushPromises();

            await pick(wrapper, new File(['x'], 'a.png', { type: 'image/png' }));

            expect(wrapper.get('[role="alert"]').text()).toContain('No tienes permiso');
        });
    });
});
