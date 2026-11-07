<script setup lang="ts">
import { CalendarDays, Check, Copy, ExternalLink, Info, ShieldAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseCard from '@/components/ui/BaseCard.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import HelpLink from '@/components/knowledge/HelpLink.vue';
import { useNotifications } from '@/composables/useNotifications';
import { useVerificationGate } from '@/composables/useVerificationGate';
import { csrfHeaders } from '@/utils/csrf';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import type { CalendarFeedAddresses, CalendarFeedStatus } from '@/types/calendarFeed';

interface Props {
    calendarFeed: CalendarFeedStatus;
}

const props = defineProps<Props>();

const ENDPOINT = '/perfil/calendario';

const { t, locale } = useI18n();
const notifications = useNotifications();
// POST /perfil/calendario is closed to accounts that must verify their email
const gate = useVerificationGate();

const issuedAt = ref<string | null>(props.calendarFeed.issuedAt);
// Only known right after creating it: the server keeps a hash, never the address
const addresses = ref<CalendarFeedAddresses | null>(null);
const processing = ref(false);
const copiedKey = ref<'webcal' | 'https' | null>(null);
const confirmRegenerate = ref(false);
const confirmRevoke = ref(false);

const hasAddress = computed(() => issuedAt.value !== null);

// Chrome, Firefox, Edge, Opera and the Google app on iPhone do not hand webcal:// to Calendar
const isIosOtherBrowser =
    typeof navigator !== 'undefined' && /CriOS|FxiOS|EdgiOS|OPiOS|GSA\//.test(navigator.userAgent);

const issuedAtLabel = computed(() =>
    issuedAt.value === null
        ? ''
        : new Date(issuedAt.value).toLocaleDateString(locale.value, {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
              timeZone: VENUE_TIMEZONE,
          })
);

const addressRows = computed(() =>
    addresses.value === null
        ? []
        : [
              {
                  key: 'webcal' as const,
                  label: t('auth.profile.calendarFeed.webcalLabel'),
                  url: addresses.value.webcalUrl,
              },
              {
                  key: 'https' as const,
                  label: t('auth.profile.calendarFeed.httpsLabel'),
                  url: addresses.value.feedUrl,
              },
          ]
);

const subscribeLinkClass =
    'inline-flex min-h-[44px] items-center gap-2 rounded-md bg-muted px-4 py-2 text-sm font-medium text-base-primary hover:bg-neutral-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:hover:bg-neutral-700 dark:focus:ring-offset-page';

const externalSubscribeLinks = computed(() =>
    addresses.value === null
        ? []
        : [
              {
                  label: t('auth.profile.calendarFeed.subscribeGoogle'),
                  url: addresses.value.googleUrl,
              },
              {
                  label: t('auth.profile.calendarFeed.subscribeOutlook'),
                  url: addresses.value.outlookUrl,
              },
          ]
);

async function send(method: 'POST' | 'DELETE'): Promise<Response | null> {
    processing.value = true;

    try {
        const response = await fetch(ENDPOINT, {
            method,
            headers: { Accept: 'application/json', ...csrfHeaders() },
            credentials: 'same-origin',
        });

        if (gate.isVerificationRequired(response)) {
            gate.goToNotice();

            return null;
        }

        if (!response.ok) {
            throw new Error(`Unexpected status ${response.status}`);
        }

        return response;
    } catch (error) {
        console.error('Personal calendar address request failed:', error);
        notifications.error(t('auth.profile.calendarFeed.error'));

        return null;
    } finally {
        processing.value = false;
    }
}

async function createAddress(): Promise<void> {
    if (gate.blocked.value) {
        return;
    }

    const response = await send('POST');
    if (response === null) {
        return;
    }

    const body = (await response.json()) as CalendarFeedAddresses;
    addresses.value = body;
    issuedAt.value = body.issuedAt ?? new Date().toISOString();
    copiedKey.value = null;
    notifications.success(t('auth.profile.calendarFeed.created'));
}

async function revokeAddress(): Promise<void> {
    const response = await send('DELETE');
    if (response === null) {
        return;
    }

    addresses.value = null;
    issuedAt.value = null;
    notifications.success(t('auth.profile.calendarFeed.revoked'));
}

async function copy(key: 'webcal' | 'https', url: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(url);
        copiedKey.value = key;
    } catch {
        notifications.error(t('auth.profile.calendarFeed.copyError'));
    }
}

function selectAll(event: FocusEvent): void {
    (event.target as HTMLInputElement).select();
}
</script>

<template>
    <BaseCard>
        <template #header>
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary dark:bg-primary-900/30"
                >
                    <CalendarDays class="h-5 w-5" aria-hidden="true" />
                </div>
                <h2 class="text-lg font-semibold text-base-primary">
                    {{ t('auth.profile.calendarFeed.title') }}
                </h2>
                <HelpLink guide="core.events.personal-calendar" />
            </div>
        </template>

        <div class="space-y-4">
            <p class="text-sm text-base-secondary">
                {{ t('auth.profile.calendarFeed.intro') }}
            </p>
            <p class="text-sm text-base-secondary">
                {{ t('auth.profile.calendarFeed.window') }}
            </p>

            <p
                v-if="gate.blocked.value"
                id="calendar-feed-verification-required"
                class="text-sm text-warning"
            >
                {{ t('auth.profile.calendarFeed.verifyFirst') }}
                <a :href="gate.noticeUrl" class="font-medium underline">{{
                    gate.linkLabel.value
                }}</a>
            </p>

            <!-- Just created: the only moment the address can be shown -->
            <div v-if="addresses !== null" class="space-y-4" data-testid="calendar-feed-addresses">
                <div
                    role="note"
                    class="flex gap-3 rounded-lg border border-warning/40 bg-warning-light p-4"
                >
                    <ShieldAlert class="mt-0.5 h-5 w-5 shrink-0 text-warning" aria-hidden="true" />
                    <div class="space-y-1 text-sm text-base-primary">
                        <p class="font-medium">
                            {{ t('auth.profile.calendarFeed.privateWarning') }}
                        </p>
                        <p>{{ t('auth.profile.calendarFeed.shownOnce') }}</p>
                    </div>
                </div>

                <div v-for="row in addressRows" :key="row.key">
                    <label
                        :for="`calendar-feed-${row.key}`"
                        class="block text-sm font-medium text-base-secondary"
                    >
                        {{ row.label }}
                    </label>
                    <div class="mt-1 flex gap-2">
                        <input
                            :id="`calendar-feed-${row.key}`"
                            type="text"
                            readonly
                            :value="row.url"
                            class="min-w-0 flex-1 rounded-md border border-default bg-muted px-3 py-2 font-mono text-xs text-base-primary"
                            @focus="selectAll"
                        />
                        <BaseButton
                            variant="secondary"
                            size="sm"
                            :aria-label="
                                t('auth.profile.calendarFeed.copyAddress', { label: row.label })
                            "
                            @click="copy(row.key, row.url)"
                        >
                            <Check
                                v-if="copiedKey === row.key"
                                class="h-4 w-4"
                                aria-hidden="true"
                            />
                            <Copy v-else class="h-4 w-4" aria-hidden="true" />
                            <span class="ml-1.5">{{
                                copiedKey === row.key
                                    ? t('auth.profile.calendarFeed.copied')
                                    : t('auth.profile.calendarFeed.copy')
                            }}</span>
                        </BaseButton>
                    </div>
                </div>

                <div data-testid="calendar-feed-subscribe" class="space-y-2">
                    <p class="text-sm font-medium text-base-secondary">
                        {{ t('auth.profile.calendarFeed.subscribeIn') }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <a :href="addresses.webcalUrl" :class="subscribeLinkClass">
                            <CalendarDays class="h-4 w-4" aria-hidden="true" />
                            {{ t('auth.profile.calendarFeed.subscribeApple') }}
                        </a>
                        <a
                            v-for="link in externalSubscribeLinks"
                            :key="link.label"
                            :href="link.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            :class="subscribeLinkClass"
                        >
                            <ExternalLink class="h-4 w-4" aria-hidden="true" />
                            {{ link.label }}
                            <span class="sr-only"
                                >({{ t('auth.profile.calendarFeed.opensInNewTab') }})</span
                            >
                        </a>
                    </div>
                    <p
                        v-if="isIosOtherBrowser"
                        data-testid="calendar-feed-ios-hint"
                        class="flex gap-2 text-sm text-base-secondary"
                    >
                        <Info class="mt-0.5 h-4 w-4 shrink-0 text-info" aria-hidden="true" />
                        {{ t('auth.profile.calendarFeed.iosOtherBrowserHint') }}
                    </p>
                </div>
            </div>

            <!-- An address exists, created earlier: it cannot be shown again -->
            <p v-else-if="hasAddress" class="text-sm text-base-primary">
                {{ t('auth.profile.calendarFeed.active', { date: issuedAtLabel }) }}
            </p>

            <div class="flex flex-wrap gap-3 pt-2">
                <BaseButton
                    v-if="!hasAddress"
                    :loading="processing"
                    :disabled="gate.blocked.value"
                    :aria-describedby="
                        gate.blocked.value ? 'calendar-feed-verification-required' : undefined
                    "
                    @click="createAddress"
                >
                    {{ t('auth.profile.calendarFeed.create') }}
                </BaseButton>
                <template v-else>
                    <BaseButton
                        variant="secondary"
                        :loading="processing"
                        :disabled="gate.blocked.value"
                        @click="confirmRegenerate = true"
                    >
                        {{ t('auth.profile.calendarFeed.regenerate') }}
                    </BaseButton>
                    <BaseButton
                        variant="danger"
                        :disabled="processing"
                        @click="confirmRevoke = true"
                    >
                        {{ t('auth.profile.calendarFeed.revoke') }}
                    </BaseButton>
                </template>
            </div>
        </div>

        <ConfirmDialog
            v-model="confirmRegenerate"
            :title="t('auth.profile.calendarFeed.regenerateTitle')"
            :message="t('auth.profile.calendarFeed.regenerateMessage')"
            :confirm-label="t('auth.profile.calendarFeed.regenerateConfirm')"
            :cancel-label="t('auth.profile.calendarFeed.cancel')"
            @confirm="createAddress"
        />
        <ConfirmDialog
            v-model="confirmRevoke"
            :title="t('auth.profile.calendarFeed.revokeTitle')"
            :message="t('auth.profile.calendarFeed.revokeMessage')"
            :confirm-label="t('auth.profile.calendarFeed.revokeConfirm')"
            :cancel-label="t('auth.profile.calendarFeed.cancel')"
            confirm-variant="danger"
            @confirm="revokeAddress"
        />
    </BaseCard>
</template>
