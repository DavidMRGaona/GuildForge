<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import CategoryChannels from '@/components/profile/CategoryChannels.vue';
import MailDeliveryChoice from '@/components/profile/MailDeliveryChoice.vue';
import MailMandatoryList from '@/components/profile/MailMandatoryList.vue';
import MailPreferenceRow from '@/components/profile/MailPreferenceRow.vue';
import type { MailDelivery, MailPreferenceSection } from '@/types/mailPreferences';
import { resolveMailSectionIcon } from '@/utils/icons';
import { countOptionalOn, hasSiteNotices, optionalCategories } from '@/utils/mailPreferences';

/**
 * A section of the «Avisos» tab as a card: icon, name and «2 de 3 activados» on top, the
 * optional categories each with its control, and the ones that are always sent as a list at the end.
 * A category with site notices gets its two channels, «En la web» and «Por correo» (core#491).
 */
interface Props {
    section: MailPreferenceSection;
    /** Current choice of every category, by key. */
    choices: Record<string, MailDelivery>;
    /** Current web switch of the categories with site notices, by key (core#491). */
    siteChoices?: Record<string, boolean>;
    busy?: boolean;
    /** Translated errors of the mail choice, by category key. */
    errors?: Record<string, string>;
    /** Translated errors of the web switch, by category key (core#491). */
    siteErrors?: Record<string, string>;
    /** When the daily digest goes out (`HH:MM`), for the categories that offer it (core#333). */
    digestTime?: string;
}

const props = withDefaults(defineProps<Props>(), {
    busy: false,
    siteChoices: () => ({}),
    errors: () => ({}),
    siteErrors: () => ({}),
    digestTime: '08:00',
});

const emit = defineEmits<{
    change: [key: string, value: MailDelivery];
    'site-change': [key: string, value: boolean];
}>();

const { t } = useI18n();

const headingId = computed(() => `mail-section-${props.section.key.replace(/[^a-z0-9-]/gi, '-')}`);
const icon = computed(() => resolveMailSectionIcon(props.section.icon));

const optional = computed(() => optionalCategories(props.section));
const mandatory = computed(() => props.section.categories.filter((category) => category.mandatory));

function choiceOf(key: string, saved: MailDelivery): MailDelivery {
    return props.choices[key] ?? saved;
}

function siteChoiceOf(key: string, saved: boolean | undefined): boolean {
    return props.siteChoices[key] ?? saved === true;
}

// Received at once or in the daily digest: everything but «no recibir» counts as on
const enabledCount = computed(() =>
    countOptionalOn(props.section, props.choices, props.siteChoices)
);
// Plural on the total («1 de 1 activado», «2 de 3 activados»)
const summary = computed(() =>
    t(
        'auth.profile.mail.section.summary',
        { enabled: enabledCount.value, total: optional.value.length },
        optional.value.length
    )
);
</script>

<template>
    <section
        :aria-labelledby="headingId"
        class="overflow-hidden rounded-lg border border-default bg-surface"
        :data-section="section.key"
    >
        <header class="flex items-center gap-3 px-4 py-3 sm:px-5">
            <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary dark:bg-primary-900/30"
                aria-hidden="true"
            >
                <component :is="icon" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <h3 :id="headingId" class="text-base font-semibold text-base-primary">
                    {{ section.label }}
                </h3>
                <p
                    v-if="optional.length > 0"
                    class="text-sm text-base-secondary"
                    data-testid="mail-section-summary"
                >
                    {{ summary }}
                </p>
            </div>
        </header>

        <ul
            v-if="optional.length > 0"
            class="divide-y divide-[var(--color-border)] border-t border-default px-4 sm:px-5"
        >
            <MailPreferenceRow
                v-for="category in optional"
                :key="category.key"
                :category="category"
                :model-value="choiceOf(category.key, category.delivery)"
                :busy="busy"
                :error="errors[category.key] ?? ''"
                @update:model-value="emit('change', category.key, $event)"
            >
                <template v-if="hasSiteNotices(category)" #control="control">
                    <CategoryChannels
                        :category="category"
                        :delivery="choiceOf(category.key, category.delivery)"
                        :site="siteChoiceOf(category.key, category.siteEnabled)"
                        :site-error="siteErrors[category.key] ?? ''"
                        :base-id="control.baseId"
                        :label-id="control.labelId"
                        :described-by="control.describedBy"
                        :locked="control.locked"
                        :digest-time="digestTime"
                        @update:delivery="emit('change', category.key, $event)"
                        @update:site="emit('site-change', category.key, $event)"
                    />
                </template>
                <template v-else-if="category.digestible" #control="control">
                    <MailDeliveryChoice
                        :model-value="choiceOf(category.key, category.delivery)"
                        :base-id="control.baseId"
                        :label-id="control.labelId"
                        :described-by="control.describedBy"
                        :locked="control.locked"
                        :digest-time="digestTime"
                        @update:model-value="emit('change', category.key, $event)"
                    />
                </template>
            </MailPreferenceRow>
        </ul>

        <div v-if="mandatory.length > 0" class="border-t border-default bg-muted px-4 py-3 sm:px-5">
            <MailMandatoryList :categories="mandatory" />
        </div>
    </section>
</template>
