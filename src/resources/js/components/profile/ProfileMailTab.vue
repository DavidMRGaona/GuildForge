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
import MutedMailSubjectsBlock from '@/components/profile/MutedMailSubjectsBlock.vue';
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
// Laravel names the errors of the submitted map `preferences.<category key>`
const ERROR_PREFIX = 'preferences.';

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
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const generalError = ref<string | null>(null);

// A reload brings the saved state again (after saving, or from another tab of the browser)
watch(saved, (state) => {
    choices.value = { ...state };
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
const hasChanges = computed(() => Object.keys(changes.value).length > 0);
const locked = computed(() => processing.value || gate.blocked.value);

function setChoice(key: string, value: MailDelivery): void {
    choices.value = { ...choices.value, [key]: value };
    errors.value = {};
    generalError.value = null;
}

function save(): void {
    if (!hasChanges.value || locked.value) {
        return;
    }

    errors.value = {};
    generalError.value = null;

    router.put(
        ENDPOINT,
        { preferences: changes.value },
        {
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
                for (const [field, message] of Object.entries(received)) {
                    if (field.startsWith(ERROR_PREFIX)) {
                        byCategory[field.slice(ERROR_PREFIX.length)] = message;
                    }
                }
                errors.value = byCategory;
                generalError.value =
                    Object.keys(byCategory).length === 0 ? t('auth.profile.mail.error') : null;
            },
        }
    );
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

            <div class="space-y-4" data-testid="mail-preference-sections">
                <MailPreferenceSection
                    v-for="section in sections"
                    :key="section.key"
                    :section="section"
                    :choices="choices"
                    :busy="locked"
                    :errors="errors"
                    :digest-time="digestTime"
                    @change="setChoice"
                />
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
