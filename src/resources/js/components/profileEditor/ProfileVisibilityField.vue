<script setup lang="ts">
import { useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { Eye, Globe, Lock, Users } from '@lucide/vue';
import type { ProfileVisibility } from '@/types/profileEditor';

/**
 * «Quién puede verlo»: the four audiences as radio cards, each with a line that explains it. Native
 * radios, so the arrow keys move between them and a screen reader hears one group.
 */
defineProps<{
    error?: string | undefined;
}>();

const model = defineModel<ProfileVisibility>({ required: true });

const { t } = useI18n();
const uid = useId();

const OPTIONS: { value: ProfileVisibility; icon: typeof Globe }[] = [
    { value: 'public', icon: Globe },
    { value: 'members', icon: Users },
    { value: 'association', icon: Eye },
    { value: 'hidden', icon: Lock },
];
</script>

<template>
    <fieldset
        :aria-describedby="error ? `${uid}-error` : undefined"
        :aria-invalid="error ? true : undefined"
    >
        <legend class="text-sm font-semibold text-base-primary">
            {{ t('publicProfile.editor.visibility.legend') }}
        </legend>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <label
                v-for="option in OPTIONS"
                :key="option.value"
                class="relative flex cursor-pointer gap-3 rounded-lg border border-default bg-surface p-3 transition-colors hover:border-primary-400 has-checked:border-primary-500 has-checked:bg-primary-50 has-focus-visible:ring-2 has-focus-visible:ring-primary-500 has-focus-visible:ring-offset-2 has-focus-visible:ring-offset-(--color-bg-surface) motion-reduce:transition-none dark:has-checked:bg-primary-950/40"
            >
                <input
                    v-model="model"
                    type="radio"
                    :name="`${uid}-visibility`"
                    :value="option.value"
                    class="sr-only"
                    :aria-describedby="`${uid}-${option.value}-description`"
                    data-testid="visibility-option"
                />
                <component
                    :is="option.icon"
                    class="mt-0.5 h-5 w-5 shrink-0"
                    :class="
                        model === option.value
                            ? 'text-primary-600 dark:text-primary-400'
                            : 'text-base-muted'
                    "
                    aria-hidden="true"
                />
                <span class="min-w-0">
                    <span class="block text-sm font-medium text-base-primary">
                        {{ t(`publicProfile.editor.visibility.${option.value}.label`) }}
                    </span>
                    <span
                        :id="`${uid}-${option.value}-description`"
                        class="mt-0.5 block text-xs text-base-muted"
                    >
                        {{ t(`publicProfile.editor.visibility.${option.value}.description`) }}
                    </span>
                </span>
            </label>
        </div>
        <p v-if="error" :id="`${uid}-error`" class="mt-2 text-sm text-red-600 dark:text-red-400">
            {{ error }}
        </p>
    </fieldset>
</template>
