<script setup lang="ts">
import { Lock } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import type { MailPreference } from '@/types/mailPreferences';

/**
 * The categories of a section that are always sent: a list with a padlock and the reason, and
 * no control, because there is nothing to choose. The reason is plain text the server wrote
 * («Siempre se envía: confirma algo que has hecho tú»), so it is read as it is.
 */
interface Props {
    categories: MailPreference[];
}

defineProps<Props>();

const { t } = useI18n();
</script>

<template>
    <ul :aria-label="t('auth.profile.mail.mandatory.label')" class="space-y-3">
        <li
            v-for="category in categories"
            :key="category.key"
            class="flex items-start gap-2.5"
            :data-mandatory-category="category.key"
        >
            <Lock class="mt-0.5 h-4 w-4 shrink-0 text-base-muted" aria-hidden="true" />
            <div class="min-w-0 text-sm">
                <p class="font-medium text-base-primary">{{ category.label }}</p>
                <p class="mt-0.5 text-base-secondary">{{ category.description }}</p>
                <p v-if="category.mandatoryReason" class="mt-0.5 text-base-secondary">
                    {{ category.mandatoryReason }}
                </p>
                <p v-if="category.site" class="mt-0.5 text-base-secondary">
                    {{
                        category.mail === false
                            ? t('auth.profile.mail.channels.siteOnly')
                            : t('auth.profile.mail.channels.alsoOnSite')
                    }}
                </p>
            </div>
        </li>
    </ul>
</template>
