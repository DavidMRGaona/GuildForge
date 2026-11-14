<script setup lang="ts">
import { useId } from 'vue';
import { useI18n } from 'vue-i18n';
import FormCheckbox from '@/components/form/FormCheckbox.vue';
import type { Tag } from '@/types/models';

/**
 * Additional tags of a proposal: subtags only, as in the panel (core#654). Next to the category
 * field they read «Etiquetas adicionales»; on their own they say who picks the category.
 */
const props = withDefaults(defineProps<{ tags: Tag[]; withCategory?: boolean }>(), {
    withCategory: false,
});

const selected = defineModel<string[]>({ required: true });

const { t } = useI18n();
const hintId = `${useId()}-hint`;

function toggle(id: string): void {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((current) => current !== id)
        : [...selected.value, id];
}
</script>

<template>
    <fieldset v-if="tags.length > 0" :aria-describedby="props.withCategory ? undefined : hintId">
        <legend class="block text-sm font-medium text-base-secondary">
            {{ props.withCategory ? t('proposals.form.additionalTags') : t('proposals.form.tags') }}
        </legend>
        <p v-if="!props.withCategory" :id="hintId" class="mt-1 text-sm text-base-muted">
            {{ t('proposals.form.tagsHint') }}
        </p>
        <div class="mt-2 flex flex-wrap gap-2">
            <!-- The site checkbox inside a pill; py-3 keeps the 44 px touch target -->
            <FormCheckbox
                v-for="tag in tags"
                :key="tag.id"
                :model-value="selected.includes(tag.id)"
                class="rounded-full border border-default px-3 py-3 transition-colors has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/30"
                @update:model-value="toggle(tag.id)"
            >
                <span class="text-base-primary">{{ tag.name }}</span>
            </FormCheckbox>
        </div>
    </fieldset>
</template>
