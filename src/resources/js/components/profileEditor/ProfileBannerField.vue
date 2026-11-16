<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useId } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ImagePlus, LoaderCircle, Trash2 } from '@lucide/vue';
import { buildImageUrl } from '@/utils/cloudinary';
import type { BannerChange, ProfileEditor } from '@/types/profileEditor';

/**
 * «Banner»: uploaded as soon as it is chosen, with the chosen image shown meanwhile, or taken away.
 * Type and size are checked here for a quick answer and again on the server, which stores it; the
 * 1500 × 500 crop is only how it is shown. The page reloads its props after each change, so the
 * preview behind shows the new banner, and the rest of the editor keeps what is being written.
 * A change on its way is told to the panel (`reportPending`), which asks before leaving and cancels it
 * once leaving was accepted (`cancelOnClose`, core#759). Both are functions rather than events, so the
 * end of a request still reaches the panel if this field was drawn again meanwhile.
 */
const props = defineProps<{
    editor: ProfileEditor;
    /** Hands the panel what cancels the request on its way; returns what to call once it finished */
    cancelOnClose: (cancel: () => void) => () => void;
    /** A change is on its way (`upload`, `remove`), or none any more */
    reportPending: (change: BannerChange | null) => void;
}>();

const { t } = useI18n();
const uid = useId();

const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const input = ref<HTMLInputElement | null>(null);
const localPreview = ref<string | null>(null);
const pending = ref<BannerChange | null>(null);
const busy = computed(() => pending.value !== null);
const error = ref<string | null>(null);

function setPending(change: BannerChange | null): void {
    pending.value = change;
    props.reportPending(change);
}

/** The request on its way is done: closing the panel no longer has to cancel it */
let finished: (() => void) | null = null;

function track(cancel: () => void): void {
    finished?.();
    finished = props.cancelOnClose(cancel);
}

function untrack(): void {
    finished?.();
    finished = null;
}

const sizeMb = computed(() => Math.round(props.editor.limits.bannerKilobytes / 1024));
const storedUrl = computed(() =>
    buildImageUrl(props.editor.bannerPublicId, {
        width: 750,
        height: 250,
        crop: 'fill',
        gravity: 'auto',
    })
);
const shownUrl = computed(() => localPreview.value ?? storedUrl.value);
const accept = computed(() => MIME_TYPES.join(','));

function forgetLocalPreview(): void {
    if (localPreview.value) {
        URL.revokeObjectURL(localPreview.value);
        localPreview.value = null;
    }
}

function choose(): void {
    input.value?.click();
}

function onChosen(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    // The same file can be chosen again after an error
    target.value = '';

    if (!file) {
        return;
    }

    if (!MIME_TYPES.includes(file.type)) {
        error.value = t('publicProfile.editor.banner.wrongType');
        return;
    }

    if (file.size > props.editor.limits.bannerKilobytes * 1024) {
        error.value = t('publicProfile.editor.banner.tooBig', { size: sizeMb.value });
        return;
    }

    error.value = null;
    forgetLocalPreview();
    localPreview.value = URL.createObjectURL(file);
    setPending('upload');

    router.post(
        props.editor.urls.banner,
        { banner: file },
        {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onCancelToken: (token) => track(() => token.cancel()),
            onError: (errors) => {
                error.value = errors.banner ?? t('publicProfile.editor.banner.failed');
            },
            onFinish: () => {
                untrack();
                setPending(null);
                forgetLocalPreview();
            },
        }
    );
}

function remove(): void {
    error.value = null;
    setPending('remove');

    router.delete(props.editor.urls.banner, {
        preserveScroll: true,
        preserveState: true,
        onCancelToken: (token) => track(() => token.cancel()),
        onFinish: () => {
            untrack();
            setPending(null);
        },
    });
}

onBeforeUnmount(forgetLocalPreview);
</script>

<template>
    <div role="group" :aria-labelledby="`${uid}-label`" :aria-describedby="`${uid}-help`">
        <p :id="`${uid}-label`" class="text-sm font-semibold text-base-primary">
            {{ t('publicProfile.editor.banner.label') }}
        </p>
        <div
            class="relative mt-2 aspect-[3/1] overflow-hidden rounded-lg border border-default bg-muted"
            data-testid="banner-preview"
        >
            <img
                v-if="shownUrl"
                :src="shownUrl"
                :alt="t('publicProfile.editor.banner.previewAlt')"
                class="h-full w-full object-cover"
            />
            <p
                v-else
                class="flex h-full items-center justify-center px-4 text-center text-xs text-base-muted"
            >
                {{ t('publicProfile.editor.banner.none') }}
            </p>
            <div
                v-if="busy"
                class="absolute inset-0 flex items-center justify-center bg-black/40 text-white"
                role="status"
            >
                <LoaderCircle
                    class="h-6 w-6 animate-spin motion-reduce:animate-none"
                    aria-hidden="true"
                />
                <span class="sr-only">{{ t('publicProfile.editor.banner.uploading') }}</span>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-base-primary inset-ring inset-ring-(--color-border) transition-colors hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-surface) disabled:opacity-50 motion-reduce:transition-none"
                :disabled="busy"
                data-testid="banner-upload"
                @click="choose"
            >
                <ImagePlus class="h-4 w-4" aria-hidden="true" />
                {{
                    editor.bannerPublicId
                        ? t('publicProfile.editor.banner.replace')
                        : t('publicProfile.editor.banner.upload')
                }}
            </button>
            <button
                v-if="editor.bannerPublicId"
                type="button"
                class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-red-700 transition-colors hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-surface) disabled:opacity-50 motion-reduce:transition-none dark:text-red-400 dark:hover:bg-red-950/40"
                :disabled="busy"
                data-testid="banner-remove"
                @click="remove"
            >
                <Trash2 class="h-4 w-4" aria-hidden="true" />
                {{ t('publicProfile.editor.banner.remove') }}
            </button>
            <input
                ref="input"
                type="file"
                class="sr-only"
                tabindex="-1"
                aria-hidden="true"
                :accept="accept"
                data-testid="banner-input"
                @change="onChosen"
            />
        </div>
        <p
            v-if="error"
            class="mt-2 text-sm text-red-600 dark:text-red-400"
            role="alert"
            data-testid="banner-error"
        >
            {{ error }}
        </p>
        <p :id="`${uid}-help`" class="mt-2 text-xs text-base-muted">
            {{ t('publicProfile.editor.banner.help', { size: sizeMb }) }}
        </p>
    </div>
</template>
