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
                    chooseImage: 'Elegir imagen',
                    changeImage: 'Cambiar imagen',
                    noImageChosen: 'Ningún archivo elegido',
                    removeImage: 'Quitar imagen',
                },
            },
        },
    },
});

function mountField(currentPublicId: string | null = null): VueWrapper {
    return mount(ProposalImageField, {
        props: {
            currentPublicId,
            label: 'Imagen',
            file: null,
            removed: false,
            'onUpdate:file': (value: File | null) => wrapperRef?.setProps({ file: value }),
        },
        global: { plugins: [i18n] },
    });
}

let wrapperRef: VueWrapper | null = null;

async function pick(wrapper: VueWrapper, file: File): Promise<void> {
    const input = wrapper.find('input[type="file"]');
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true });
    await input.trigger('change');
}

describe('ProposalImageField', () => {
    beforeEach(() => {
        vi.stubGlobal('URL', { createObjectURL: () => 'blob:preview', revokeObjectURL: vi.fn() });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        wrapperRef = null;
    });

    it('hides the browser file control and draws its own picker', () => {
        const wrapper = mountField();
        const input = wrapper.find('input[type="file"]');

        expect(input.classes()).toContain('sr-only');
        const picker = wrapper.find(
            `label[for="${input.attributes('id')}"][data-testid="image-picker"]`
        );
        expect(picker.text()).toBe('Elegir imagen');
        expect(wrapper.find('[data-testid="image-file-name"]').text()).toBe(
            'Ningún archivo elegido'
        );
    });

    it('offers to change the image when the proposal already has one', () => {
        const wrapper = mountField('events/2026/11/poster');

        expect(wrapper.find('[data-testid="image-picker"]').text()).toBe('Cambiar imagen');
        expect(wrapper.find('[data-testid="image-file-name"]').exists()).toBe(false);
    });

    it('shows the name of the picked file and hands it to the form', async () => {
        const wrapper = mountField();
        wrapperRef = wrapper;

        await pick(wrapper, new File(['x'], 'cartel.png', { type: 'image/png' }));

        expect(wrapper.emitted('update:file')?.[0]?.[0]).toBeInstanceOf(File);
        expect(wrapper.find('[data-testid="image-file-name"]').text()).toBe('cartel.png');
        expect(wrapper.find('[data-testid="image-picker"]').text()).toBe('Cambiar imagen');
    });
});
