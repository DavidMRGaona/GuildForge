<script setup lang="ts">
import { useId } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Tag } from '@/types/models';

/**
 * The category of a proposal, one of the type's root tags (core#654). Only whoever may publish the
 * type directly gets it: sending publishes at once, so they pick what a reviewer would. Same pills
 * as the tags field, with one choice.
 */
defineProps<{
    categories: Tag[];
    error?: string | undefined;
}>();

const selected = defineModel<string | null>({ required: true });

const { t } = useI18n();
const uid = useId();
</script>

<template>
    <fieldset
        v-if="categories.length > 0"
        :aria-describedby="error ? `${uid}-error` : undefined"
        :aria-invalid="error ? 'true' : undefined"
    >
        <legend class="block text-sm font-medium text-base-secondary">
            {{ t('proposals.form.category') }}
            <span class="text-error" aria-hidden="true">*</span>
        </legend>
        <div class="mt-2 flex flex-wrap gap-2">
            <!-- py-3 keeps the 44 px touch target; the ring shows keyboard focus on the hidden radio -->
            <label
                v-for="category in categories"
                :key="category.id"
                class="inline-flex cursor-pointer items-center rounded-full border border-default px-3 py-3 transition-colors has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500 dark:has-[:checked]:bg-primary-900/30"
            >
                <input
                    v-model="selected"
                    type="radio"
                    :name="`${uid}-category`"
                    :value="category.id"
                    class="sr-only"
                />
                <span class="text-base-primary">{{ category.name }}</span>
            </label>
        </div>
        <p v-if="error" :id="`${uid}-error`" role="alert" class="mt-1 text-sm text-error">
            {{ error }}
        </p>
    </fieldset>
</template>
