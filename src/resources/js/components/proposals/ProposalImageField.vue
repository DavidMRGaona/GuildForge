<script setup lang="ts">
import { ImageUp } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import ProposalField from './ProposalField.vue';
import { buildCardImageUrl } from '@/utils/cloudinary';

/**
 * Picks the image of a proposal. It goes with the form (the server stores it in the folder of the
 * type): the current one is kept unless another is picked or «Quitar imagen» is marked.
 */
const props = defineProps<{
    /** Stored image of the proposal, if any. */
    currentPublicId: string | null;
    label: string;
    error?: string | undefined;
}>();

const file = defineModel<File | null>('file', { required: true });
const removed = defineModel<boolean>('removed', { required: true });

const { t } = useI18n();
const id = useId();
const input = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);

const currentUrl = computed(() =>
    removed.value ? null : buildCardImageUrl(props.currentPublicId)
);
const shownUrl = computed(() => previewUrl.value ?? currentUrl.value);
const hasStoredImage = computed(() => props.currentPublicId !== null && !removed.value);
const hasImage = computed(() => file.value !== null || hasStoredImage.value);

function revoke(): void {
    if (previewUrl.value !== null) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
}

function onPick(event: Event): void {
    const picked = (event.target as HTMLInputElement).files?.[0] ?? null;
    revoke();
    file.value = picked;
    if (picked !== null) {
        previewUrl.value = URL.createObjectURL(picked);
        removed.value = false;
    }
}

function remove(): void {
    revoke();
    file.value = null;
    removed.value = true;
    if (input.value) {
        input.value.value = '';
    }
}

onBeforeUnmount(revoke);
</script>

<template>
    <ProposalField :label="label" :for="id" :error="error" :hint="t('proposals.form.imageHint')">
        <template #default="{ hintId, errorId }">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                <img
                    v-if="shownUrl"
                    :src="shownUrl"
                    alt=""
                    class="h-32 w-full max-w-xs rounded-md border border-default object-cover sm:w-48"
                />
                <div class="min-w-0">
                    <!--
                        The browser control writes its own texts in the browser language: it stays
                        as the focusable control (sr-only) and the label next to it is what shows
                    -->
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                        <input
                            :id="id"
                            ref="input"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            :aria-describedby="
                                [hintId, errorId].filter(Boolean).join(' ') || undefined
                            "
                            :aria-invalid="error ? 'true' : undefined"
                            class="peer sr-only"
                            @change="onPick"
                        />
                        <label
                            :for="id"
                            data-testid="image-picker"
                            class="inline-flex min-h-[44px] cursor-pointer items-center gap-2 rounded-md bg-muted px-4 py-2 text-sm font-medium text-base-primary transition-colors hover:bg-neutral-200 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500 peer-focus-visible:ring-offset-2 dark:hover:bg-neutral-700 dark:peer-focus-visible:ring-offset-neutral-900"
                        >
                            <ImageUp class="h-4 w-4" aria-hidden="true" />
                            <span>{{
                                hasImage
                                    ? t('proposals.form.changeImage')
                                    : t('proposals.form.chooseImage')
                            }}</span>
                        </label>
                        <span
                            v-if="!hasStoredImage || file !== null"
                            data-testid="image-file-name"
                            class="min-w-0 truncate text-sm text-base-muted"
                        >
                            {{ file?.name ?? t('proposals.form.noImageChosen') }}
                        </span>
                    </div>
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
                </div>
            </div>
        </template>
    </ProposalField>
</template>
