<script setup lang="ts">
import { Mail } from '@lucide/vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import HelpLink from '@/components/knowledge/HelpLink.vue';
import MailPauseBlock from '@/components/profile/MailPauseBlock.vue';
import MailPreferenceSection from '@/components/profile/MailPreferenceSection.vue';
import MailSectionTabs from '@/components/profile/MailSectionTabs.vue';
import MailViewSwitch from '@/components/profile/MailViewSwitch.vue';
import MutedMailSubjectsBlock from '@/components/profile/MutedMailSubjectsBlock.vue';
import { useMailPreferencesView } from '@/composables/useMailPreferencesView';
import { useVerificationGate } from '@/composables/useVerificationGate';
import type {
    MailDelivery,
    MailPause,
    MailPreferenceSection as Section,
    MutedMailSubject,
} from '@/types/mailPreferences';

interface Props {
    sections: Section[];
    pause: MailPause;
    /** Things muted from a mail footer (core#334). */
    mutedSubjects?: MutedMailSubject[];
    /** When the daily digest goes out, `HH:MM` in the display zone (core#333). */
    digestTime?: string;
}

const props = withDefaults(defineProps<Props>(), { mutedSubjects: () => [], digestTime: '08:00' });

const ENDPOINT = '/perfil/correos';
// Laravel names the errors of the submitted maps `preferences.<category key>` (mail) and
// `site.<category key>` (notification centre, core#491)
const ERROR_PREFIX = 'preferences.';
const SITE_ERROR_PREFIX = 'site.';

const { t } = useI18n();
const page = usePage();
// The endpoint is closed to accounts that must verify their email (like every signed-in route)
const gate = useVerificationGate();

/** What the server has saved, by category key. */
const saved = computed<Record<string, MailDelivery>>(() => {
    const state: Record<string, MailDelivery> = {};
    for (const section of props.sections) {
        for (const category of section.categories) {
            state[category.key] = category.delivery;
        }
    }

    return state;
});

/** Saved web switch of the optional categories with site notices (core#491). */
const siteSaved = computed<Record<string, boolean>>(() => {
    const state: Record<string, boolean> = {};
    for (const section of props.sections) {
        for (const category of section.categories) {
            if (category.site === true && !category.mandatory) {
                state[category.key] = category.siteEnabled === true;
            }
        }
    }

    return state;
});

// Cards (all at a glance) or tabs (one section at a time); the choices and the save are the same
const { view } = useMailPreferencesView();
const selectedKey = ref<string>(props.sections[0]?.key ?? '');
const selectedSection = computed(
    () => props.sections.find((section) => section.key === selectedKey.value) ?? props.sections[0]
);
const PANEL_ID = 'mail-section-panel';

/** Categories that offer the daily digest: they send their delivery, the others the switch. */
const digestible = computed<Set<string>>(() => {
    const keys = new Set<string>();
    for (const section of props.sections) {
        for (const category of section.categories) {
            if (category.digestible) {
                keys.add(category.key);
            }
        }
    }

    return keys;
});

const choices = ref<Record<string, MailDelivery>>({ ...saved.value });
const siteChoices = ref<Record<string, boolean>>({ ...siteSaved.value });
const processing = ref(false);
/** Translated errors by category key: the mail choice and the web switch each keep their own. */
const errors = ref<Record<string, string>>({});
const siteErrors = ref<Record<string, string>>({});
const generalError = ref<string | null>(null);

// A reload brings the saved state again (after saving, or from another tab of the browser)
watch(saved, (state) => {
    choices.value = { ...state };
});
watch(siteSaved, (state) => {
    siteChoices.value = { ...state };
});

/** Only what differs from the saved state travels: the server keeps the rest as it is. */
const changes = computed<Record<string, boolean | MailDelivery>>(() => {
    const diff: Record<string, boolean | MailDelivery> = {};
    for (const [key, value] of Object.entries(choices.value)) {
        if (saved.value[key] !== undefined && saved.value[key] !== value) {
            diff[key] = digestible.value.has(key) ? value : value !== 'none';
        }
    }

    return diff;
});
const siteChanges = computed<Record<string, boolean>>(() => {
    const diff: Record<string, boolean> = {};
    for (const [key, value] of Object.entries(siteChoices.value)) {
        if (siteSaved.value[key] !== undefined && siteSaved.value[key] !== value) {
            diff[key] = value;
        }
    }

    return diff;
});
const hasChanges = computed(
    () => Object.keys(changes.value).length > 0 || Object.keys(siteChanges.value).length > 0
);
const locked = computed(() => processing.value || gate.blocked.value);

function setChoice(key: string, value: MailDelivery): void {
    choices.value = { ...choices.value, [key]: value };
    errors.value = {};
    siteErrors.value = {};
    generalError.value = null;
}

function setSiteChoice(key: string, value: boolean): void {
    siteChoices.value = { ...siteChoices.value, [key]: value };
    errors.value = {};
    siteErrors.value = {};
    generalError.value = null;
}

/** Only the maps that changed travel: the server keeps the rest as it is. */
function payload(): Record<string, Record<string, boolean | MailDelivery>> {
    const data: Record<string, Record<string, boolean | MailDelivery>> = {};
    if (Object.keys(changes.value).length > 0) {
        data.preferences = changes.value;
    }
    if (Object.keys(siteChanges.value).length > 0) {
        data.site = siteChanges.value;
    }

    return data;
}

function save(): void {
    if (!hasChanges.value || locked.value) {
        return;
    }

    errors.value = {};
    siteErrors.value = {};
    generalError.value = null;

    router.put(ENDPOINT, payload(), {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            processing.value = true;
        },
        onFinish: () => {
            processing.value = false;
        },
        onError: (received) => {
            const byCategory: Record<string, string> = {};
            const siteByCategory: Record<string, string> = {};
            for (const [field, message] of Object.entries(received)) {
                if (field.startsWith(ERROR_PREFIX)) {
                    byCategory[field.slice(ERROR_PREFIX.length)] = message;
                } else if (field.startsWith(SITE_ERROR_PREFIX)) {
                    siteByCategory[field.slice(SITE_ERROR_PREFIX.length)] = message;
                }
            }
            errors.value = byCategory;
            siteErrors.value = siteByCategory;
            generalError.value =
                Object.keys(byCategory).length === 0 && Object.keys(siteByCategory).length === 0
                    ? t('auth.profile.mail.error')
                    : null;
        },
    });
}
</script>

<template>
    <BaseCard>
        <template #header>
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary dark:bg-primary-900/30"
                >
                    <Mail class="h-5 w-5" aria-hidden="true" />
                </div>
                <h2 class="text-lg font-semibold text-base-primary">
                    {{ t('auth.profile.mail.title') }}
                </h2>
                <HelpLink guide="core.account.mail-preferences" />
            </div>
        </template>

        <form class="space-y-6" novalidate @submit.prevent="save">
            <p class="text-sm text-base-secondary">
                {{ t('auth.profile.mail.intro', { appName: page.props.appName }) }}
            </p>

            <p
                v-if="gate.blocked.value"
                id="mail-preferences-verification-required"
                class="text-sm text-warning"
            >
                {{ t('auth.profile.mail.verifyFirst') }}
                <a :href="gate.noticeUrl" class="font-medium underline">{{
                    gate.linkLabel.value
                }}</a>
            </p>

            <!-- Pause of every optional mail (core#331), above the per-category choices -->
            <div data-slot="mail-pause">
                <MailPauseBlock :pause="pause" :blocked="gate.blocked.value" />
            </div>

            <div v-if="sections.length > 1" class="flex justify-end">
                <MailViewSwitch v-model="view" />
            </div>

            <div v-if="view === 'cards'" class="space-y-4" data-testid="mail-preference-sections">
                <MailPreferenceSection
                    v-for="section in sections"
                    :key="section.key"
                    :section="section"
                    :choices="choices"
                    :site-choices="siteChoices"
                    :busy="locked"
                    :errors="errors"
                    :site-errors="siteErrors"
                    :digest-time="digestTime"
                    @change="setChoice"
                    @site-change="setSiteChoice"
                />
            </div>

            <div v-else-if="selectedSection" data-testid="mail-preference-tabs">
                <MailSectionTabs
                    v-model="selectedKey"
                    :sections="sections"
                    :choices="choices"
                    :site-choices="siteChoices"
                    :panel-id="PANEL_ID"
                />
                <div
                    :id="PANEL_ID"
                    role="tabpanel"
                    tabindex="0"
                    :aria-labelledby="`mail-tab-${selectedSection.key.replace(/[^a-z0-9-]/gi, '-')}`"
                    class="mt-4 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                >
                    <MailPreferenceSection
                        :key="selectedSection.key"
                        :section="selectedSection"
                        :choices="choices"
                        :site-choices="siteChoices"
                        :busy="locked"
                        :errors="errors"
                        :site-errors="siteErrors"
                        :digest-time="digestTime"
                        @change="setChoice"
                        @site-change="setSiteChoice"
                    />
                </div>
            </div>

            <p v-if="generalError" role="alert" class="text-sm text-error">{{ generalError }}</p>

            <div class="flex flex-wrap items-center gap-3 border-t border-default pt-4">
                <BaseButton
                    type="submit"
                    :loading="processing"
                    :disabled="!hasChanges || gate.blocked.value"
                    :aria-describedby="
                        gate.blocked.value ? 'mail-preferences-verification-required' : undefined
                    "
                >
                    {{ t('auth.profile.mail.save') }}
                </BaseButton>
                <p
                    v-if="hasChanges && !processing"
                    class="text-sm text-base-secondary"
                    role="status"
                >
                    {{ t('auth.profile.mail.unsaved') }}
                </p>
            </div>
        </form>

        <!-- Things muted from a mail footer (core#334): each one is undone at once, outside the form -->
        <div class="mt-8 border-t border-default pt-6">
            <MutedMailSubjectsBlock :subjects="mutedSubjects" :blocked="gate.blocked.value" />
        </div>
    </BaseCard>
</template>
