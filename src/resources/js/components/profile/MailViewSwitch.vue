<script setup lang="ts">
import { LayoutGrid, Rows3 } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import type { MailPreferencesView } from '@/composables/useMailPreferencesView';

/** Two buttons to read the sections as cards or as tabs; the pressed one is the current view. */
interface Props {
    modelValue: MailPreferencesView;
}

defineProps<Props>();

const emit = defineEmits<{ 'update:modelValue': [view: MailPreferencesView] }>();

const { t } = useI18n();

const OPTIONS = [
    { view: 'cards', icon: LayoutGrid },
    { view: 'tabs', icon: Rows3 },
] as const;
</script>

<template>
    <div
        role="group"
        :aria-label="t('auth.profile.mail.view.label')"
        class="inline-flex rounded-lg border border-default bg-surface p-0.5"
        data-testid="mail-view-switch"
    >
        <button
            v-for="option in OPTIONS"
            :key="option.view"
            type="button"
            :aria-pressed="modelValue === option.view"
            class="flex min-h-[36px] items-center gap-1.5 rounded-md px-3 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            :class="
                modelValue === option.view
                    ? 'bg-primary-100 text-primary dark:bg-primary-900/30'
                    : 'text-base-secondary hover:bg-muted'
            "
            @click="emit('update:modelValue', option.view)"
        >
            <component :is="option.icon" class="h-4 w-4" aria-hidden="true" />
            {{ t(`auth.profile.mail.view.${option.view}`) }}
        </button>
    </div>
</template>
