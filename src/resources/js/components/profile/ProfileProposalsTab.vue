<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import ProposalStatusBadge from '@/components/proposals/ProposalStatusBadge.vue';
import { useImpersonationReadOnly } from '@/composables/useImpersonationReadOnly';
import { useRoutes } from '@/composables/useRoutes';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import type { ProfileProposals, Proposal } from '@/types/proposals';

/**
 * «Mis propuestas» (core#579): the events and articles a person proposed, with their review state,
 * the reason a review gave and what they may still do, plus how much of the limits they have used.
 * The buttons only offer what the server's rule allows; the server checks again.
 */
const props = defineProps<{ proposals: ProfileProposals }>();

const { t, locale } = useI18n();
const routes = useRoutes();
const readOnly = useImpersonationReadOnly();

// «Solo mirar» refuses every write with a 403: say so instead of Inertia's error dialog
const onHttpException = (response: unknown): false | undefined =>
    readOnly.notifyIfReadOnly(response) ? false : undefined;

type Action = 'withdraw' | 'delete';

const pending = ref<{ proposal: Proposal; action: Action } | null>(null);
const confirmOpen = ref(false);
const busyId = ref<string | null>(null);

const limits = computed(() => props.proposals.limits);
const mayPropose = computed(
    () => props.proposals.canPropose.event || props.proposals.canPropose.article
);

function formatDateTime(iso: string | null): string {
    if (iso === null) {
        return '';
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: VENUE_TIMEZONE,
    }).format(new Date(iso));
}

function editUrl(proposal: Proposal): string {
    return proposal.type === 'event'
        ? routes.proposals.events.edit(proposal.id)
        : routes.proposals.articles.edit(proposal.id);
}

function datesOf(proposal: Proposal): string[] {
    const lines: string[] = [];

    if (proposal.type === 'event' && proposal.startsAt) {
        lines.push(t('proposals.list.eventStarts', { date: formatDateTime(proposal.startsAt) }));
    }
    if (proposal.submittedAt) {
        lines.push(t('proposals.list.submittedAt', { date: formatDateTime(proposal.submittedAt) }));
    }
    if (proposal.reviewedAt) {
        lines.push(t('proposals.list.reviewedAt', { date: formatDateTime(proposal.reviewedAt) }));
    }
    if (lines.length === 0 || proposal.status === 'draft') {
        lines.push(t('proposals.list.updatedAt', { date: formatDateTime(proposal.updatedAt) }));
    }

    return lines;
}

function submit(proposal: Proposal): void {
    busyId.value = proposal.id;
    router.post(
        routes.proposals.submit(proposal.type, proposal.id),
        {},
        { preserveScroll: true, onHttpException, onFinish: () => (busyId.value = null) }
    );
}

function ask(proposal: Proposal, action: Action): void {
    pending.value = { proposal, action };
    confirmOpen.value = true;
}

function confirm(): void {
    const current = pending.value;
    if (current === null) {
        return;
    }

    const { proposal, action } = current;
    busyId.value = proposal.id;
    const options = {
        preserveScroll: true,
        onHttpException,
        onFinish: () => (busyId.value = null),
    };

    if (action === 'withdraw') {
        router.post(routes.proposals.withdraw(proposal.type, proposal.id), {}, options);
    } else {
        router.delete(routes.proposals.destroy(proposal.type, proposal.id), options);
    }
}
</script>

<template>
    <section aria-labelledby="proposals-heading" class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h2 id="proposals-heading" class="text-xl font-semibold text-base-primary">
                    {{ t('proposals.list.title') }}
                </h2>
                <p class="mt-1 text-sm text-base-secondary">{{ t('proposals.list.subtitle') }}</p>
            </div>
            <div v-if="mayPropose" class="flex flex-wrap gap-2">
                <Link
                    v-if="proposals.canPropose.event"
                    :href="routes.proposals.events.create"
                    data-testid="propose-event"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-md bg-(--button-primary-bg) px-4 py-2 font-medium text-(--button-primary-text) inset-ring inset-ring-(--button-primary-border) hover:bg-(--button-primary-hover-bg) hover:text-(--button-primary-hover-text) focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                >
                    {{ t('proposals.proposeEvent') }}
                </Link>
                <Link
                    v-if="proposals.canPropose.article"
                    :href="routes.proposals.articles.create"
                    data-testid="propose-article"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-md bg-(--button-primary-bg) px-4 py-2 font-medium text-(--button-primary-text) inset-ring inset-ring-(--button-primary-border) hover:bg-(--button-primary-hover-bg) hover:text-(--button-primary-hover-text) focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                >
                    {{ t('proposals.proposeArticle') }}
                </Link>
            </div>
        </div>

        <dl
            data-testid="proposal-limits"
            class="grid gap-3 rounded-lg bg-surface p-4 shadow sm:grid-cols-3 dark:shadow-depth"
        >
            <div>
                <dt class="text-sm text-base-secondary">{{ t('proposals.limits.pending') }}</dt>
                <dd class="text-lg font-semibold text-base-primary">
                    {{ limits.pendingUsed }} / {{ limits.pendingMax }}
                </dd>
            </div>
            <div>
                <dt class="text-sm text-base-secondary">{{ t('proposals.limits.drafts') }}</dt>
                <dd class="text-lg font-semibold text-base-primary">
                    {{ limits.draftsUsed }} / {{ limits.draftsMax }}
                </dd>
            </div>
            <div>
                <dt class="text-sm text-base-secondary">{{ t('proposals.limits.hourly') }}</dt>
                <dd class="text-lg font-semibold text-base-primary">
                    {{
                        t('proposals.limits.hourlyValue', {
                            left: limits.submissionsLeft,
                            total: limits.submissionsPerHour,
                        })
                    }}
                </dd>
            </div>
        </dl>

        <p
            v-if="proposals.items.length === 0"
            data-testid="no-proposals"
            class="rounded-lg bg-surface p-8 text-center text-base-secondary shadow dark:shadow-depth"
        >
            {{ t('proposals.list.empty') }}
        </p>

        <ul v-else class="space-y-4">
            <li
                v-for="proposal in proposals.items"
                :key="`${proposal.type}-${proposal.id}`"
                data-testid="proposal-item"
                class="rounded-lg bg-surface p-4 shadow sm:p-5 dark:shadow-depth"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-medium uppercase tracking-wide text-base-muted">
                        {{ t(`proposals.type.${proposal.type}`) }}
                    </span>
                    <ProposalStatusBadge :status="proposal.status" />
                </div>

                <h3 class="mt-2 break-words text-lg font-medium text-base-primary">
                    <Link
                        v-if="proposal.publicPath"
                        :href="proposal.publicPath"
                        class="hover:underline"
                    >
                        {{ proposal.title }}
                    </Link>
                    <template v-else>{{ proposal.title }}</template>
                </h3>

                <ul class="mt-1 space-y-0.5 text-sm text-base-secondary">
                    <li v-for="line in datesOf(proposal)" :key="line">{{ line }}</li>
                </ul>

                <div
                    v-if="proposal.requests.length > 0"
                    data-testid="proposal-requests"
                    class="mt-3 text-sm"
                >
                    <p class="font-medium text-base-secondary">
                        {{ t('proposals.requests.listHeading') }}
                    </p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-5 text-base-primary">
                        <li v-for="request in proposal.requests" :key="request.key">
                            {{ request.description ?? request.label }}
                            <span v-if="!request.active" class="text-base-muted">
                                ({{ t('proposals.requests.unavailable') }})
                            </span>
                            <ul
                                v-if="!request.applied && request.warnings.length > 0"
                                class="mt-0.5 list-none space-y-0.5 pl-0 text-warning"
                                data-testid="proposal-request-warnings"
                            >
                                <li v-for="warning in request.warnings" :key="warning">
                                    {{ t('proposals.requests.warning', { text: warning }) }}
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>

                <p
                    v-if="proposal.reason"
                    data-testid="proposal-reason"
                    class="mt-3 rounded-md bg-muted p-3 text-sm text-base-primary"
                >
                    <strong>
                        {{
                            proposal.status === 'rejected'
                                ? t('proposals.list.rejectedReason')
                                : t('proposals.returnedReason')
                        }}
                    </strong>
                    {{ proposal.reason }}
                </p>

                <p
                    v-if="proposal.status === 'pending_review'"
                    class="mt-3 text-sm text-base-secondary"
                >
                    {{ t('proposals.list.pendingNote') }}
                </p>

                <div
                    v-if="
                        proposal.can.edit ||
                        proposal.can.submit ||
                        proposal.can.withdraw ||
                        proposal.can.delete
                    "
                    class="mt-4 flex flex-wrap gap-2"
                >
                    <Link
                        v-if="proposal.can.edit"
                        :href="editUrl(proposal)"
                        data-testid="edit-proposal"
                        class="inline-flex min-h-[44px] items-center justify-center rounded-md bg-muted px-4 py-2 font-medium text-base-primary hover:bg-neutral-200 focus:outline-none focus:ring-2 focus:ring-neutral-400 dark:hover:bg-neutral-700"
                    >
                        {{ t('proposals.list.edit') }}
                    </Link>
                    <BaseButton
                        v-if="proposal.can.submit"
                        :loading="busyId === proposal.id"
                        data-testid="submit-proposal"
                        @click="submit(proposal)"
                    >
                        {{ t('proposals.list.submit') }}
                    </BaseButton>
                    <BaseButton
                        v-if="proposal.can.withdraw"
                        variant="secondary"
                        :loading="busyId === proposal.id"
                        data-testid="withdraw-proposal"
                        @click="ask(proposal, 'withdraw')"
                    >
                        {{ t('proposals.list.withdraw') }}
                    </BaseButton>
                    <BaseButton
                        v-if="proposal.can.delete"
                        variant="danger"
                        :loading="busyId === proposal.id"
                        data-testid="delete-proposal"
                        @click="ask(proposal, 'delete')"
                    >
                        {{ t('proposals.list.delete') }}
                    </BaseButton>
                </div>
            </li>
        </ul>

        <ConfirmDialog
            v-model="confirmOpen"
            :title="
                pending?.action === 'delete'
                    ? t('proposals.confirm.deleteTitle')
                    : t('proposals.confirm.withdrawTitle')
            "
            :message="
                pending?.action === 'delete'
                    ? t('proposals.confirm.deleteMessage', { title: pending?.proposal.title ?? '' })
                    : t('proposals.confirm.withdrawMessage', {
                          title: pending?.proposal.title ?? '',
                      })
            "
            :confirm-label="
                pending?.action === 'delete'
                    ? t('proposals.list.delete')
                    : t('proposals.list.withdraw')
            "
            :cancel-label="t('common.cancel')"
            :confirm-variant="pending?.action === 'delete' ? 'danger' : 'primary'"
            @confirm="confirm"
        />
    </section>
</template>
