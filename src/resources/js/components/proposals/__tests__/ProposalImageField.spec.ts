import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProposalImageField from '../ProposalImageField.vue';

const i18n = createI18n({
    legacy: false,
    locale: 'es',
    missingWarn: false,
    fallbackWarn: false,
    messages: {
        es: {
            proposals: {
                form: {
                    imageHint: 'JPG, PNG, WebP o GIF de hasta 2 MB.',
                    dropImage: 'Arrastra y suelta tu imagen o {browse}',
                    dropOtherImage: 'Arrastra otra imagen o {browse} para cambiarla',
                    browse: 'examina',
                    imageInvalid: 'Elige una imagen JPG, PNG, WebP o GIF de hasta 2 MB.',
                    removeImage: 'Quitar imagen',
                },
            },
        },
    },
});

let wrapper: VueWrapper;

function mountField(currentPublicId: string | null = null): VueWrapper {
    wrapper = mount(ProposalImageField, {
        props: {
            currentPublicId,
            label: 'Imagen',
            file: null,
            removed: false,
            'onUpdate:file': (value: File | null) => wrapper.setProps({ file: value }),
        },
        global: { plugins: [i18n] },
    });

    return wrapper;
}

function image(name = 'cartel.png', bytes = 10, type = 'image/png'): File {
    return new File([new Uint8Array(bytes)], name, { type });
}

async function pick(file: File): Promise<void> {
    const input = wrapper.find('input[type="file"]');
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true });
    await input.trigger('change');
}

async function drop(file: File): Promise<void> {
    await wrapper.find('[data-testid="image-dropzone"]').trigger('drop', {
        dataTransfer: { files: [file] },
    });
}

describe('ProposalImageField', () => {
    beforeEach(() => {
        vi.stubGlobal('URL', { createObjectURL: () => 'blob:preview', revokeObjectURL: vi.fn() });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('hides the browser file control behind a drop zone that opens it', () => {
        mountField();
        const input = wrapper.find('input[type="file"]');
        const zone = wrapper.find('[data-testid="image-dropzone"]');

        expect(input.classes()).toContain('sr-only');
        expect(zone.element.tagName).toBe('LABEL');
        expect(zone.attributes('for')).toBe(input.attributes('id'));
        expect(zone.text()).toContain('Arrastra y suelta tu imagen o examina');
    });

    it('offers to replace the image when the proposal already has one', () => {
        mountField('events/2026/11/poster');

        const zone = wrapper.find('[data-testid="image-dropzone"]');
        expect(zone.text()).toContain('Arrastra otra imagen o examina para cambiarla');
        expect(zone.find('img').exists()).toBe(true);
    });

    it('takes a picked file and shows its name', async () => {
        mountField();

        await pick(image());

        expect(wrapper.emitted('update:file')?.[0]?.[0]).toBeInstanceOf(File);
        expect(wrapper.find('[data-testid="image-file-name"]').text()).toBe('cartel.png');
    });

    it('takes a dropped file and marks the zone while dragging', async () => {
        mountField();
        const zone = wrapper.find('[data-testid="image-dropzone"]');

        await zone.trigger('dragenter');
        expect(zone.attributes('data-dragging')).toBe('true');

        await drop(image('mesa.webp', 10, 'image/webp'));

        expect(zone.attributes('data-dragging')).toBeUndefined();
        expect((wrapper.emitted('update:file')?.[0]?.[0] as File).name).toBe('mesa.webp');
    });

    it('refuses a file of another type or over 2 MB, and says why', async () => {
        mountField();

        await drop(image('bases.pdf', 10, 'application/pdf'));
        await pick(image('enorme.png', 2048 * 1024 + 1));

        expect(wrapper.emitted('update:file')).toBeUndefined();
        expect(wrapper.find('[role="alert"]').text()).toBe(
            'Elige una imagen JPG, PNG, WebP o GIF de hasta 2 MB.'
        );

        await drop(image());
        expect(wrapper.emitted('update:file')).toHaveLength(1);
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });
});
