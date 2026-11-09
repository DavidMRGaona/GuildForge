<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { MailDelivery } from '@/types/mailPreferences';

/**
 * How a digestible category arrives (core#333): at once, in the daily digest or not at all.
 * Fills the `control` slot of `MailPreferenceRow`, which gives it the ids of the category's
 * name and description. Native radios, drawn like the site's (the same as the pause block).
 */
interface Props {
    modelValue: MailDelivery;
    /** Prefix for the radio group name and the hint id; unique per category. */
    baseId: string;
    labelId: string;
    describedBy: string;
    locked?: boolean;
    /** When the daily digest goes out (`HH:MM`). */
    digestTime?: string;
}

const props = withDefaults(defineProps<Props>(), { locked: false, digestTime: '08:00' });

const emit = defineEmits<{ 'update:modelValue': [value: MailDelivery] }>();

const DELIVERIES: readonly MailDelivery[] = ['immediate', 'digest', 'none'];

const { t } = useI18n();

const hintId = computed(() => `${props.baseId}-digest-hint`);
const fullDescribedBy = computed(() =>
    [props.describedBy, props.modelValue === 'digest' ? hintId.value : null]
        .filter((id): id is string => id !== null && id !== '')
        .join(' ')
);

function choose(delivery: MailDelivery): void {
    if (props.locked || delivery === props.modelValue) {
        return;
    }

    emit('update:modelValue', delivery);
}
</script>

<template>
    <!-- Always under the text, full width: beside it, three choices would squeeze the description -->
    <fieldset
        class="min-w-0 basis-full"
        :aria-labelledby="labelId"
        :aria-describedby="fullDescribedBy"
        data-testid="mail-delivery"
    >
        <div class="flex flex-col sm:flex-row sm:flex-wrap sm:gap-x-5">
            <label
                v-for="delivery in DELIVERIES"
                :key="delivery"
                class="group flex min-h-[44px] items-center gap-3 text-sm text-base-primary"
                :class="locked ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'"
            >
                <input
                    type="radio"
                    :name="`${baseId}-delivery`"
                    :value="delivery"
                    :checked="modelValue === delivery"
                    :disabled="locked"
                    class="peer sr-only"
                    @change="choose(delivery)"
                />
                <span
                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 bg-surface transition-all duration-150 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-surface"
                    :class="
                        modelValue === delivery
                            ? 'border-primary-600 dark:border-primary-500'
                            : 'border-neutral-300 group-hover:border-primary-400 dark:border-neutral-600 dark:group-hover:border-primary-500'
                    "
                    aria-hidden="true"
                >
                    <span
                        class="h-2.5 w-2.5 rounded-full bg-primary-600 transition-all duration-150 dark:bg-primary-500"
                        :class="
                            modelValue === delivery ? 'scale-100 opacity-100' : 'scale-0 opacity-0'
                        "
                    />
                </span>
                <span class="select-none whitespace-nowrap">
                    {{ t(`auth.profile.mail.delivery.${delivery}`) }}
                </span>
            </label>
        </div>
        <p v-if="modelValue === 'digest'" :id="hintId" class="mt-1 text-sm text-base-secondary">
            {{ t('auth.profile.mail.delivery.digestHint', { time: digestTime }) }}
        </p>
    </fieldset>
</template>
