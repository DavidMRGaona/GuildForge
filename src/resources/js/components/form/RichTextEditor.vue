<script setup lang="ts">
import type { Editor } from '@tiptap/core';
import type { EditorProps } from '@tiptap/pm/view';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    Bold,
    Heading2,
    Heading3,
    ImagePlus,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Quote,
    Redo2,
    Undo2,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { createRichTextExtensions } from './richText/extensions';
import {
    checkImageFile,
    EDITOR_IMAGE_TYPES,
    ImageUploadError,
    normalizeLinkHref,
    uploadEditorImage,
} from '@/utils/richText';

/**
 * Rich text editor for the public site (core#573). It writes the same HTML as the panel's
 * RichEditor for headings (2 and 3), bold, italic, lists, links, quotes and images, so a text
 * written here opens there without loss. The server sanitises it again: never trust this HTML.
 *
 * Images go to `uploadUrl` (null offers none) and come back with the stored path, kept in the
 * image's `data-id`.
 */
const props = withDefaults(
    defineProps<{
        /** The HTML */
        modelValue: string;
        /** Accessible name of the writing area */
        label: string;
        placeholder?: string;
        /** Endpoint that stores an image and answers `{ id, url }`; null offers no images */
        uploadUrl?: string | null;
        disabled?: boolean;
        /** Id of the element that describes the field (hint or error), for screen readers */
        describedBy?: string | null;
        invalid?: boolean;
    }>(),
    { placeholder: '', uploadUrl: null, disabled: false, describedBy: null, invalid: false }
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const { t } = useI18n();
const uid = useId();

type Panel = 'link' | null;

const panel = ref<Panel>(null);
const linkValue = ref('');
const linkInvalid = ref(false);
const linkInput = ref<HTMLInputElement | null>(null);
const toolbar = ref<HTMLElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(0);
const status = ref('');
const problem = ref('');
const imageAlt = ref('');
const imageSelected = ref(false);
const tick = ref(0);

/** The HTML to store: TipTap keeps an empty paragraph after the last block so people can keep typing */
const serialize = (html: string): string => html.replace(/(?:<p><\/p>)+$/, '');

function buildEditorProps(): EditorProps {
    return {
        attributes: {
            role: 'textbox',
            'aria-multiline': 'true',
            'aria-label': props.label,
            ...(props.describedBy ? { 'aria-describedby': props.describedBy } : {}),
            ...(props.invalid ? { 'aria-invalid': 'true' } : {}),
            class: 'rich-text-editor__content prose max-w-none px-3 py-2 focus:outline-none',
        },
        handlePaste: (_view, event) => takeFiles(event.clipboardData?.files ?? null),
        handleDrop: (_view, event) => takeFiles(event.dataTransfer?.files ?? null),
    };
}

const editor = useEditor({
    content: props.modelValue,
    editable: !props.disabled,
    extensions: createRichTextExtensions(() => props.placeholder),
    editorProps: buildEditorProps(),
    onUpdate: ({ editor: current }) => {
        const html = current.getHTML();
        emit('update:modelValue', serialize(html));
    },
    onTransaction: ({ editor: current }) => {
        tick.value++;
        imageSelected.value = current.isActive('image');
        imageAlt.value = imageSelected.value
            ? String(current.getAttributes('image').alt ?? '')
            : '';
    },
});

watch(
    () => props.modelValue,
    (value) => {
        const current = editor.value;

        if (
            current !== undefined &&
            !current.isDestroyed &&
            value !== serialize(current.getHTML())
        ) {
            current.commands.setContent(value, { emitUpdate: false });
        }
    }
);

watch(
    () => [props.label, props.describedBy, props.invalid],
    () => editor.value?.setOptions({ editorProps: buildEditorProps() })
);

watch(
    () => props.disabled,
    (disabled) => editor.value?.setEditable(!disabled)
);

onBeforeUnmount(() => editor.value?.destroy());

interface Button {
    id: string;
    label: string;
    icon: Component;
    run: () => void;
    active: () => boolean;
    disabled?: () => boolean;
    shortcut?: string;
}

const chain = (current: Editor) => current.chain().focus();

const buttons = computed<Button[]>(() => {
    void tick.value;
    const current = editor.value;

    if (current === undefined) {
        return [];
    }

    const list: Button[] = [
        {
            id: 'heading2',
            label: t('richEditor.heading2'),
            icon: Heading2,
            run: () => chain(current).toggleHeading({ level: 2 }).run(),
            active: () => current.isActive('heading', { level: 2 }),
        },
        {
            id: 'heading3',
            label: t('richEditor.heading3'),
            icon: Heading3,
            run: () => chain(current).toggleHeading({ level: 3 }).run(),
            active: () => current.isActive('heading', { level: 3 }),
        },
        {
            id: 'bold',
            label: t('richEditor.bold'),
            icon: Bold,
            shortcut: 'Ctrl+B',
            run: () => chain(current).toggleBold().run(),
            active: () => current.isActive('bold'),
        },
        {
            id: 'italic',
            label: t('richEditor.italic'),
            icon: Italic,
            shortcut: 'Ctrl+I',
            run: () => chain(current).toggleItalic().run(),
            active: () => current.isActive('italic'),
        },
        {
            id: 'bulletList',
            label: t('richEditor.bulletList'),
            icon: List,
            run: () => chain(current).toggleBulletList().run(),
            active: () => current.isActive('bulletList'),
        },
        {
            id: 'orderedList',
            label: t('richEditor.orderedList'),
            icon: ListOrdered,
            run: () => chain(current).toggleOrderedList().run(),
            active: () => current.isActive('orderedList'),
        },
        {
            id: 'blockquote',
            label: t('richEditor.blockquote'),
            icon: Quote,
            run: () => chain(current).toggleBlockquote().run(),
            active: () => current.isActive('blockquote'),
        },
        {
            id: 'link',
            label: t('richEditor.link'),
            icon: LinkIcon,
            shortcut: 'Ctrl+K',
            run: openLink,
            active: () => current.isActive('link'),
        },
    ];

    if (props.uploadUrl !== null) {
        list.push({
            id: 'image',
            label: t('richEditor.image'),
            icon: ImagePlus,
            run: () => fileInput.value?.click(),
            active: () => false,
        });
    }

    list.push(
        {
            id: 'undo',
            label: t('richEditor.undo'),
            icon: Undo2,
            shortcut: 'Ctrl+Z',
            run: () => chain(current).undo().run(),
            active: () => false,
            disabled: () => !current.can().undo(),
        },
        {
            id: 'redo',
            label: t('richEditor.redo'),
            icon: Redo2,
            shortcut: 'Ctrl+Y',
            run: () => chain(current).redo().run(),
            active: () => false,
            disabled: () => !current.can().redo(),
        }
    );

    return list;
});

/** Roving focus: the toolbar is one tab stop, arrows move inside it */
function onToolbarKeydown(event: KeyboardEvent): void {
    const items = Array.from(
        toolbar.value?.querySelectorAll<HTMLButtonElement>('button:not([disabled])') ?? []
    );
    const index = items.indexOf(document.activeElement as HTMLButtonElement);

    if (index === -1) {
        return;
    }

    const last = items.length - 1;
    const target =
        event.key === 'ArrowRight'
            ? index === last
                ? 0
                : index + 1
            : event.key === 'ArrowLeft'
              ? index === 0
                  ? last
                  : index - 1
              : event.key === 'Home'
                ? 0
                : event.key === 'End'
                  ? last
                  : null;

    if (target !== null) {
        event.preventDefault();
        items[target]?.focus();
    }
}

const activeStop = ref('heading2');

function openLink(): void {
    const current = editor.value;

    if (current === undefined) {
        return;
    }

    linkValue.value = String(current.getAttributes('link').href ?? '');
    linkInvalid.value = false;
    panel.value = 'link';
    void nextTick(() => linkInput.value?.focus());
}

function closePanel(): void {
    panel.value = null;
    editor.value?.commands.focus();
}

function applyLink(): void {
    const current = editor.value;
    const href = normalizeLinkHref(linkValue.value);

    if (current === undefined) {
        return;
    }

    if (href === null) {
        linkInvalid.value = true;
        linkInput.value?.focus();

        return;
    }

    chain(current).extendMarkRange('link').setLink({ href }).run();
    closePanel();
}

function removeLink(): void {
    const current = editor.value;

    if (current !== undefined) {
        chain(current).extendMarkRange('link').unsetLink().run();
    }

    closePanel();
}

function onEditorKeydown(event: KeyboardEvent): void {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        openLink();
    }
}

function onAltInput(): void {
    editor.value?.chain().updateAttributes('image', { alt: imageAlt.value }).run();
}

/** Takes the images of a paste or drop; true when it did, so the editor leaves the event alone */
function takeFiles(files: FileList | null): boolean {
    if (props.uploadUrl === null || files === null) {
        return false;
    }

    const images = Array.from(files).filter((file) => file.type.startsWith('image/'));

    images.forEach((file) => void insertImage(file));

    return images.length > 0;
}

function onFilePicked(event: Event): void {
    const input = event.target as HTMLInputElement;

    Array.from(input.files ?? []).forEach((file) => void insertImage(file));
    input.value = '';
}

async function insertImage(file: File): Promise<void> {
    const current = editor.value;
    const url = props.uploadUrl;

    if (current === undefined || url === null) {
        return;
    }

    problem.value = '';
    const refused = checkImageFile(file);

    if (refused !== null) {
        problem.value = t(`richEditor.imageErrors.${refused}`);

        return;
    }

    uploading.value++;
    status.value = t('richEditor.imageUploading');

    try {
        const image = await uploadEditorImage(url, file);

        if (current.isDestroyed) {
            return;
        }

        chain(current)
            .setImage({ src: image.url, alt: '', id: image.id } as { src: string; alt: string })
            .run();
        status.value = t('richEditor.imageInserted');
    } catch (error) {
        status.value = '';
        problem.value = t(
            `richEditor.imageErrors.${error instanceof ImageUploadError ? error.reason : 'failed'}`
        );
    } finally {
        uploading.value--;
    }
}

const accept = EDITOR_IMAGE_TYPES.join(',');
const linkErrorId = `${uid}-link-error`;
const altHintId = `${uid}-alt-hint`;
</script>

<template>
    <div
        class="rich-text-editor rounded-lg border bg-surface transition-colors duration-150 focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500"
        :class="[invalid ? 'border-red-500' : '', disabled ? 'opacity-60' : '']"
    >
        <div
            ref="toolbar"
            role="toolbar"
            :aria-label="t('richEditor.toolbar')"
            aria-orientation="horizontal"
            class="flex flex-wrap items-center gap-1 border-b p-1.5"
            @keydown="onToolbarKeydown"
        >
            <button
                v-for="button in buttons"
                :key="button.id"
                type="button"
                :aria-label="button.label"
                :title="button.shortcut ? `${button.label} (${button.shortcut})` : button.label"
                :aria-pressed="
                    button.id === 'undo' || button.id === 'redo' || button.id === 'image'
                        ? undefined
                        : button.active()
                "
                :aria-expanded="button.id === 'link' ? panel === 'link' : undefined"
                :disabled="disabled || (button.disabled?.() ?? false)"
                :tabindex="button.id === activeStop ? 0 : -1"
                class="inline-flex size-9 items-center justify-center rounded-md text-base-secondary transition-colors hover:bg-muted hover:text-base-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-40 aria-pressed:bg-primary-100 aria-pressed:text-primary-700 dark:aria-pressed:bg-primary-950 dark:aria-pressed:text-primary-300"
                @focus="activeStop = button.id"
                @click="button.run()"
            >
                <component :is="button.icon" class="size-4" aria-hidden="true" />
            </button>
        </div>

        <form
            v-if="panel === 'link'"
            class="flex flex-wrap items-start gap-2 border-b p-2"
            novalidate
            @submit.prevent="applyLink"
            @keydown.esc.stop.prevent="closePanel"
        >
            <div class="min-w-48 flex-1">
                <label :for="`${uid}-link`" class="sr-only">{{
                    t('richEditor.linkAddress')
                }}</label>
                <input
                    :id="`${uid}-link`"
                    ref="linkInput"
                    v-model="linkValue"
                    type="url"
                    inputmode="url"
                    autocomplete="off"
                    placeholder="https://"
                    :aria-invalid="linkInvalid"
                    :aria-describedby="linkInvalid ? linkErrorId : undefined"
                    class="w-full rounded-md border bg-surface px-3 py-1.5 text-sm text-base-primary focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
                />
                <p
                    v-if="linkInvalid"
                    :id="linkErrorId"
                    role="alert"
                    class="mt-1 text-sm text-red-600 dark:text-red-400"
                >
                    {{ t('richEditor.linkInvalid') }}
                </p>
            </div>
            <button
                type="submit"
                class="rounded-md bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
            >
                {{ t('richEditor.linkApply') }}
            </button>
            <button
                v-if="editor?.isActive('link')"
                type="button"
                class="rounded-md border px-3 py-1.5 text-sm text-base-primary hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                @click="removeLink"
            >
                {{ t('richEditor.linkRemove') }}
            </button>
            <button
                type="button"
                class="rounded-md px-3 py-1.5 text-sm text-base-secondary hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                @click="closePanel"
            >
                {{ t('richEditor.linkCancel') }}
            </button>
        </form>

        <div v-if="imageSelected && uploadUrl !== null" class="border-b p-2">
            <label :for="`${uid}-alt`" class="block text-sm font-medium text-base-primary">{{
                t('richEditor.imageAlt')
            }}</label>
            <input
                :id="`${uid}-alt`"
                v-model="imageAlt"
                type="text"
                maxlength="200"
                :aria-describedby="altHintId"
                class="mt-1 w-full rounded-md border bg-surface px-3 py-1.5 text-sm text-base-primary focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
                @input="onAltInput"
            />
            <p :id="altHintId" class="mt-1 text-xs text-base-muted">
                {{ t('richEditor.imageAltHint') }}
            </p>
        </div>

        <div @keydown.capture="onEditorKeydown">
            <EditorContent v-if="editor" :editor="editor" />
        </div>

        <input
            v-if="uploadUrl !== null"
            ref="fileInput"
            type="file"
            class="sr-only"
            tabindex="-1"
            aria-hidden="true"
            :accept="accept"
            multiple
            @change="onFilePicked"
        />

        <div class="sr-only" role="status" aria-live="polite">{{ status }}</div>
        <p
            v-if="problem"
            role="alert"
            class="border-t px-3 py-2 text-sm text-red-600 dark:text-red-400"
        >
            {{ problem }}
        </p>
    </div>
</template>

<style scoped>
.rich-text-editor :deep(.ProseMirror p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    color: var(--color-text-muted, #756f6b);
    float: left;
    height: 0;
    pointer-events: none;
    color: var(--color-text-muted, #756f6b);
}

.rich-text-editor :deep(.ProseMirror img) {
    max-width: 100%;
    height: auto;
}

.rich-text-editor :deep(.ProseMirror img.ProseMirror-selectednode) {
    outline: 2px solid var(--color-primary-500, #f59e0b);
    outline-offset: 2px;
}

.rich-text-editor :deep(.ProseMirror) {
    min-height: 10rem;
}
</style>
