<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import FormCheckbox from '@/components/form/FormCheckbox.vue';
import type { Tag } from '@/types/models';

defineProps<{ tags: Tag[] }>();

const selected = defineModel<string[]>({ required: true });

const { t } = useI18n();

function toggle(id: string): void {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((current) => current !== id)
        : [...selected.value, id];
}
</script>

<template>
    <fieldset v-if="tags.length > 0">
        <legend class="block text-sm font-medium text-base-secondary">
            {{ t('proposals.form.tags') }}
        </legend>
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
