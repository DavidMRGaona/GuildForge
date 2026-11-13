<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useId } from 'vue';
import { I18nT, useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import ProposalField from './ProposalField.vue';
import { buildCardImageUrl } from '@/utils/cloudinary';

/**
 * Picks the image of a proposal, by dropping it on the zone or browsing for it. It goes with the
 * form (the server stores it in the folder of the type): the current one is kept unless another
 * is picked or «Quitar imagen» is marked. Types and size mirror the server rule (EditorImages),
 * which checks them again: here they only spare a round trip.
 */
const props = defineProps<{
    /** Stored image of the proposal, if any. */
    currentPublicId: string | null;
    label: string;
    error?: string | undefined;
}>();

const file = defineModel<File | null>('file', { required: true });
const removed = defineModel<boolean>('removed', { required: true });

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const MAX_BYTES = 2048 * 1024;

const { t } = useI18n();
const id = useId();
const input = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);
const dragging = ref(false);
const refused = ref(false);

const currentUrl = computed(() =>
    removed.value ? null : buildCardImageUrl(props.currentPublicId)
);
const shownUrl = computed(() => previewUrl.value ?? currentUrl.value);
const hasImage = computed(
    () => file.value !== null || (props.currentPublicId !== null && !removed.value)
);
const shownError = computed(() => (refused.value ? t('proposals.form.imageInvalid') : props.error));

function revoke(): void {
    if (previewUrl.value !== null) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
}

function clearInput(): void {
    if (input.value) {
        input.value.value = '';
    }
}

/** A dropped file skips the input's `accept`, and either can be too big: refuse it here. */
function take(picked: File | null): void {
    if (picked === null) {
        return;
    }

    if (!ACCEPTED_TYPES.includes(picked.type) || picked.size > MAX_BYTES) {
        refused.value = true;
        clearInput();

        return;
    }

    refused.value = false;
    revoke();
    file.value = picked;
    previewUrl.value = URL.createObjectURL(picked);
    removed.value = false;
}

function onPick(event: Event): void {
    take((event.target as HTMLInputElement).files?.[0] ?? null);
}

/** Leaving for a child (the preview, the text) still counts as over the zone. */
function onDragLeave(event: DragEvent): void {
    const zone = event.currentTarget as Node;
    if (!(event.relatedTarget instanceof Node && zone.contains(event.relatedTarget))) {
        dragging.value = false;
    }
}

function onDrop(event: DragEvent): void {
    dragging.value = false;
    take(event.dataTransfer?.files[0] ?? null);
}

function remove(): void {
    revoke();
    refused.value = false;
    file.value = null;
    removed.value = true;
    clearInput();
}

onBeforeUnmount(revoke);
</script>

<template>
    <ProposalField
        :label="label"
        :for="id"
        :error="shownError"
        :hint="t('proposals.form.imageHint')"
    >
        <template #default="{ hintId, errorId }">
            <!--
                The browser control writes its own texts in the browser language: it stays as the
                focusable control (sr-only) and the drop zone, its second label, is what shows
            -->
            <input
                :id="id"
                ref="input"
                type="file"
                :accept="ACCEPTED_TYPES.join(',')"
                :aria-describedby="[hintId, errorId].filter(Boolean).join(' ') || undefined"
                :aria-invalid="shownError ? 'true' : undefined"
                class="peer sr-only"
                @change="onPick"
            />
            <label
                :for="id"
                data-testid="image-dropzone"
                :data-dragging="dragging ? 'true' : undefined"
                class="flex min-h-[58px] cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border px-4 py-4 text-center transition-colors peer-focus-visible:border-primary-500 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500"
                :class="
                    dragging
                        ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                        : shownError
                          ? 'border-error bg-surface'
                          : 'border-default bg-surface hover:border-strong'
                "
                @dragenter.prevent="dragging = true"
                @dragover.prevent="dragging = true"
                @dragleave.prevent="onDragLeave"
                @drop.prevent="onDrop"
            >
                <img
                    v-if="shownUrl"
                    :src="shownUrl"
                    alt=""
                    class="h-32 w-full max-w-xs rounded-md object-cover"
                />
                <span class="min-w-0 max-w-full text-base-muted">
                    <I18nT
                        :keypath="
                            hasImage ? 'proposals.form.dropOtherImage' : 'proposals.form.dropImage'
                        "
                        tag="span"
                        scope="global"
                    >
                        <template #browse>
                            <span class="font-medium text-primary">{{
                                t('proposals.form.browse')
                            }}</span>
                        </template>
                    </I18nT>
                    <span
                        v-if="file"
                        data-testid="image-file-name"
                        class="mt-1 block truncate text-base-muted"
                    >
                        {{ file.name }}
                    </span>
                </span>
            </label>
            <BaseButton
                v-if="hasImage"
                type="button"
                variant="ghost"
                size="sm"
                class="mt-2"
                @click="remove"
            >
                {{ t('proposals.form.removeImage') }}
            </BaseButton>
        </template>
    </ProposalField>
</template>
