<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import RichTextEditor from '@/components/form/RichTextEditor.vue';
import ProposalField from '@/components/proposals/ProposalField.vue';
import ProposalFormActions from '@/components/proposals/ProposalFormActions.vue';
import ProposalImageField from '@/components/proposals/ProposalImageField.vue';
import ProposalTagsField from '@/components/proposals/ProposalTagsField.vue';
import { useImpersonationReadOnly } from '@/composables/useImpersonationReadOnly';
import { useRoutes } from '@/composables/useRoutes';
import type { ProposalFormProps } from '@/types/proposals';

/**
 * Proposes an article from the web (core#579): saved as a draft or sent to review. Images in the
 * text go through the editor to the articles' editor image folder. The slug is made by the server.
 */
const props = defineProps<ProposalFormProps>();

const { t } = useI18n();
const routes = useRoutes();
// «Solo mirar» refuses every write with a 403: say so instead of Inertia's error dialog
const readOnly = useImpersonationReadOnly();

const form = useForm({
    title: props.proposal?.title ?? '',
    excerpt: props.proposal?.excerpt ?? '',
    content: props.proposal?.body ?? '',
    image: null as File | null,
    remove_image: false,
    tags: [...(props.proposal?.tagIds ?? [])],
});

function send(submit: boolean): void {
    // A PUT with a file goes as a POST that says so: PHP only reads uploads from a POST body
    const editing = props.proposal !== null;
    const url = editing
        ? routes.proposals.articles.update(props.proposal!.id)
        : routes.proposals.articles.store;

    form.transform((data) => ({
        ...data,
        submit,
        ...(editing ? { _method: 'put' } : {}),
    })).post(url, {
        forceFormData: true,
        preserveScroll: true,
        onHttpException: (response) => (readOnly.notifyIfReadOnly(response) ? false : undefined),
    });
}
</script>

<template>
    <div data-testid="page-band" class="page-band">
        <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                {{ proposal ? t('proposals.article.editTitle') : t('proposals.article.title') }}
            </h1>
            <p class="mt-2 text-lg text-base-secondary">{{ t('proposals.article.subtitle') }}</p>
        </div>
    </div>

    <form
        class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        novalidate
        @submit.prevent="send(false)"
    >
        <p
            v-if="proposal?.reason"
            role="status"
            class="rounded-md bg-warning-light p-4 text-sm text-warning"
        >
            <strong>{{ t('proposals.returnedReason') }}</strong> {{ proposal.reason }}
        </p>

        <ProposalField
            :label="t('proposals.form.title')"
            for="proposal-title"
            :error="form.errors.title"
            required
        >
            <template #default="{ errorId }">
                <input
                    id="proposal-title"
                    v-model="form.title"
                    type="text"
                    maxlength="255"
                    required
                    :aria-describedby="errorId"
                    :aria-invalid="form.errors.title ? 'true' : undefined"
                    class="block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary focus:border-primary-500 focus:outline-none focus:ring-primary-500"
                />
            </template>
        </ProposalField>

        <ProposalField
            :label="t('proposals.form.excerpt')"
            for="proposal-excerpt"
            :hint="t('proposals.form.excerptHint')"
            :error="form.errors.excerpt"
        >
            <template #default="{ hintId, errorId }">
                <textarea
                    id="proposal-excerpt"
                    v-model="form.excerpt"
                    rows="2"
                    maxlength="255"
                    :aria-describedby="[hintId, errorId].filter(Boolean).join(' ') || undefined"
                    class="block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary focus:border-primary-500 focus:outline-none focus:ring-primary-500"
                />
            </template>
        </ProposalField>

        <ProposalField :label="t('proposals.form.content')" :error="form.errors.content" required>
            <template #default="{ errorId }">
                <RichTextEditor
                    v-model="form.content"
                    :label="t('proposals.form.content')"
                    :upload-url="routes.proposals.articleImages"
                    :described-by="errorId ?? null"
                    :invalid="Boolean(form.errors.content)"
                />
            </template>
        </ProposalField>

        <ProposalImageField
            v-model:file="form.image"
            v-model:removed="form.remove_image"
            :current-public-id="proposal?.imagePublicId ?? null"
            :label="t('proposals.form.featuredImage')"
            :error="form.errors.image"
        />

        <ProposalTagsField v-model="form.tags" :tags="tags" />

        <ProposalFormActions
            :processing="form.processing"
            @draft="send(false)"
            @submit="send(true)"
        />
    </form>
</template>
