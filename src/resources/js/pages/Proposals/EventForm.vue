<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import RichTextEditor from '@/components/form/RichTextEditor.vue';
import FormDatePicker from '@/components/form/FormDatePicker.vue';
import FormTimePicker from '@/components/form/FormTimePicker.vue';
import ProposalField from '@/components/proposals/ProposalField.vue';
import ProposalFormActions from '@/components/proposals/ProposalFormActions.vue';
import ProposalImageField from '@/components/proposals/ProposalImageField.vue';
import ProposalModuleRequests from '@/components/proposals/ProposalModuleRequests.vue';
import ProposalTagsField from '@/components/proposals/ProposalTagsField.vue';
import { useImpersonationReadOnly } from '@/composables/useImpersonationReadOnly';
import { useRoutes } from '@/composables/useRoutes';
import { initialRequests } from '@/utils/proposalRequests';
import type { EventProposalFormProps } from '@/types/proposals';

/**
 * Proposes an event from the web (core#579): saved as a draft or sent to review. The dates are the
 * wall times people read in the display zone; the server converts them. No prices nor download
 * links: whoever reviews sets them. Below it, what the enabled modules let people ask for (core#581).
 */
const props = defineProps<EventProposalFormProps>();

const { t } = useI18n();
const routes = useRoutes();
// «Solo mirar» refuses every write with a 403: say so instead of Inertia's error dialog
const readOnly = useImpersonationReadOnly();

const form = useForm({
    title: props.proposal?.title ?? '',
    description: props.proposal?.body ?? '',
    location: props.proposal?.location ?? '',
    start_date: props.proposal?.startDate ?? '',
    start_time: props.proposal?.startTime ?? '18:00',
    end_date: props.proposal?.endDate ?? '',
    end_time: props.proposal?.endTime ?? '21:00',
    image: null as File | null,
    remove_image: false,
    tags: [...(props.proposal?.tagIds ?? [])],
    note: props.proposal?.note ?? '',
    requests: initialRequests(props.moduleRequests, props.proposal?.requests ?? []),
});

function send(submit: boolean): void {
    // A PUT with a file goes as a POST that says so: PHP only reads uploads from a POST body
    const editing = props.proposal !== null;
    const url = editing
        ? routes.proposals.events.update(props.proposal!.id)
        : routes.proposals.events.store;

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

function onStartDate(value: string): void {
    form.start_date = value;
    // A single-day event is the usual one: the end follows until it is set on its own
    if (form.end_date === '' || form.end_date < value) {
        form.end_date = value;
    }
}
</script>

<template>
    <div data-testid="page-band" class="page-band">
        <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                {{ proposal ? t('proposals.event.editTitle') : t('proposals.event.title') }}
            </h1>
            <p class="mt-2 text-lg text-base-secondary">{{ t('proposals.event.subtitle') }}</p>
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

        <div class="grid gap-6 sm:grid-cols-2">
            <ProposalField
                :label="t('proposals.form.startDate')"
                for="proposal-start-date"
                :error="form.errors.start_date"
                required
            >
                <FormDatePicker
                    id="proposal-start-date"
                    :model-value="form.start_date"
                    :error="form.errors.start_date"
                    @update:model-value="onStartDate"
                />
            </ProposalField>
            <ProposalField
                :label="t('proposals.form.startTime')"
                for="proposal-start-time"
                :error="form.errors.start_time"
                required
            >
                <FormTimePicker
                    id="proposal-start-time"
                    v-model="form.start_time"
                    :error="form.errors.start_time"
                />
            </ProposalField>
            <ProposalField
                :label="t('proposals.form.endDate')"
                for="proposal-end-date"
                :error="form.errors.end_date"
                required
            >
                <FormDatePicker
                    id="proposal-end-date"
                    v-model="form.end_date"
                    :min="form.start_date || undefined"
                    :error="form.errors.end_date"
                />
            </ProposalField>
            <ProposalField
                :label="t('proposals.form.endTime')"
                for="proposal-end-time"
                :error="form.errors.end_time"
                required
            >
                <FormTimePicker
                    id="proposal-end-time"
                    v-model="form.end_time"
                    :error="form.errors.end_time"
                />
            </ProposalField>
        </div>

        <ProposalField
            :label="t('proposals.form.location')"
            for="proposal-location"
            :error="form.errors.location"
        >
            <template #default="{ errorId }">
                <input
                    id="proposal-location"
                    v-model="form.location"
                    type="text"
                    maxlength="255"
                    :aria-describedby="errorId"
                    class="block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary focus:border-primary-500 focus:outline-none focus:ring-primary-500"
                />
            </template>
        </ProposalField>

        <ProposalField
            :label="t('proposals.form.description')"
            :error="form.errors.description"
            required
        >
            <template #default="{ errorId }">
                <RichTextEditor
                    v-model="form.description"
                    :label="t('proposals.form.description')"
                    :described-by="errorId ?? null"
                    :invalid="Boolean(form.errors.description)"
                />
            </template>
        </ProposalField>

        <ProposalImageField
            v-model:file="form.image"
            v-model:removed="form.remove_image"
            :current-public-id="proposal?.imagePublicId ?? null"
            :label="t('proposals.form.image')"
            :error="form.errors.image"
        />

        <ProposalTagsField v-model="form.tags" :tags="tags" />

        <ProposalField
            :label="t('proposals.form.note')"
            for="proposal-note"
            :hint="t('proposals.form.noteHint')"
            :error="form.errors.note"
        >
            <template #default="{ hintId, errorId }">
                <textarea
                    id="proposal-note"
                    v-model="form.note"
                    rows="3"
                    maxlength="5000"
                    :aria-describedby="[hintId, errorId].filter(Boolean).join(' ') || undefined"
                    class="block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary focus:border-primary-500 focus:outline-none focus:ring-primary-500"
                />
            </template>
        </ProposalField>

        <ProposalModuleRequests
            v-model="form.requests"
            :definitions="moduleRequests"
            :errors="form.errors as Record<string, string | undefined>"
        />

        <p class="text-sm text-base-muted">{{ t('proposals.event.pricesNote') }}</p>

        <ProposalFormActions
            :processing="form.processing"
            @draft="send(false)"
            @submit="send(true)"
        />
    </form>
</template>
