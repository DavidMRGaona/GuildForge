<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import MailMandatoryList from '@/components/profile/MailMandatoryList.vue';
import MailPreferenceRow from '@/components/profile/MailPreferenceRow.vue';
import type { MailPreferenceSection } from '@/types/mailPreferences';
import { resolveMailSectionIcon } from '@/utils/icons';

/**
 * A section of the «Correos» tab as a card: icon, name and «2 de 3 activados» on top, the
 * optional categories each with its control, and the ones that are always sent as a list at the end.
 */
interface Props {
    section: MailPreferenceSection;
    /** Current choice of every category, by key. */
    choices: Record<string, boolean>;
    busy?: boolean;
    /** Translated errors by category key. */
    errors?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), { busy: false, errors: () => ({}) });

const emit = defineEmits<{ change: [key: string, value: boolean] }>();

const { t } = useI18n();

const headingId = computed(() => `mail-section-${props.section.key.replace(/[^a-z0-9-]/gi, '-')}`);
const icon = computed(() => resolveMailSectionIcon(props.section.icon));

const optional = computed(() => props.section.categories.filter((category) => !category.mandatory));
const mandatory = computed(() => props.section.categories.filter((category) => category.mandatory));

function isOn(key: string, saved: boolean): boolean {
    return props.choices[key] ?? saved;
}

const enabledCount = computed(
    () => optional.value.filter((category) => isOn(category.key, category.enabled)).length
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
                :model-value="isOn(category.key, category.enabled)"
                :busy="busy"
                :error="errors[category.key] ?? ''"
                @update:model-value="emit('change', category.key, $event)"
            />
        </ul>

        <div v-if="mandatory.length > 0" class="border-t border-default bg-muted px-4 py-3 sm:px-5">
            <MailMandatoryList :categories="mandatory" />
        </div>
    </section>
</template>
