<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import MailDeliveryChoice from '@/components/profile/MailDeliveryChoice.vue';
import PreferenceSwitch from '@/components/profile/PreferenceSwitch.vue';
import type { MailDelivery, MailPreference } from '@/types/mailPreferences';
import { isMailed } from '@/utils/mailPreferences';

/**
 * The two ways a category with site notices reaches the person (core#491): «En la web» (the
 * bell) and «Por correo» (a switch, or the three deliveries of a digestible category). Fills the
 * `control` slot of `MailPreferenceRow`; each control is named by the category and its channel.
 * A category shown only on the site has the web switch alone.
 */
interface Props {
    category: MailPreference;
    /** Current mail choice. */
    delivery: MailDelivery;
    /** Current web switch. */
    site: boolean;
    baseId: string;
    labelId: string;
    describedBy: string;
    locked?: boolean;
    digestTime?: string;
    /** Translated error of the web switch, shown under it (the mail error goes under the text). */
    siteError?: string;
}

const props = withDefaults(defineProps<Props>(), {
    locked: false,
    digestTime: '08:00',
    siteError: '',
});

const emit = defineEmits<{
    'update:delivery': [value: MailDelivery];
    'update:site': [value: boolean];
}>();

const { t } = useI18n();

const siteLabelId = computed(() => `${props.baseId}-site`);
const mailLabelId = computed(() => `${props.baseId}-mail`);
const mailed = computed(() => isMailed(props.category));
const siteErrorId = computed(() => `${props.baseId}-site-error`);
// The web switch is described by the category and its own error, never by the mail error
const siteDescribedBy = computed(() =>
    [`${props.baseId}-description`, props.siteError ? siteErrorId.value : null]
        .filter((id): id is string => id !== null)
        .join(' ')
);
</script>

<template>
    <!-- Under the text, full width: two labelled controls side by side would squeeze the description -->
    <div class="min-w-0 basis-full space-y-2" data-testid="category-channels">
        <div class="flex flex-wrap items-center justify-between gap-x-4 sm:justify-start">
            <span :id="siteLabelId" class="min-w-24 text-sm text-base-secondary">
                {{ t('auth.profile.mail.channels.site') }}
            </span>
            <PreferenceSwitch
                :checked="site"
                :locked="locked"
                :labelled-by="`${labelId} ${siteLabelId}`"
                :described-by="siteDescribedBy"
                data-channel="site"
                @toggle="emit('update:site', !site)"
            />
            <p
                v-if="siteError"
                :id="siteErrorId"
                class="basis-full text-sm text-error"
                data-testid="site-channel-error"
            >
                {{ siteError }}
            </p>
        </div>

        <template v-if="mailed">
            <div
                v-if="category.digestible"
                class="flex flex-col gap-1 sm:flex-row sm:items-start sm:gap-4"
            >
                <span :id="mailLabelId" class="min-w-24 pt-3 text-sm text-base-secondary">
                    {{ t('auth.profile.mail.channels.mail') }}
                </span>
                <MailDeliveryChoice
                    :model-value="delivery"
                    :base-id="baseId"
                    :label-id="`${labelId} ${mailLabelId}`"
                    :described-by="describedBy"
                    :locked="locked"
                    :digest-time="digestTime"
                    @update:model-value="emit('update:delivery', $event)"
                />
            </div>
            <div v-else class="flex items-center justify-between gap-4 sm:justify-start">
                <span :id="mailLabelId" class="min-w-24 text-sm text-base-secondary">
                    {{ t('auth.profile.mail.channels.mail') }}
                </span>
                <PreferenceSwitch
                    :checked="delivery !== 'none'"
                    :locked="locked"
                    :labelled-by="`${labelId} ${mailLabelId}`"
                    :described-by="describedBy"
                    data-channel="mail"
                    @toggle="emit('update:delivery', delivery === 'none' ? 'immediate' : 'none')"
                />
            </div>
        </template>
    </div>
</template>
