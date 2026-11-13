<script setup lang="ts">
import { useI18n } from 'vue-i18n';
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
            <label
                v-for="tag in tags"
                :key="tag.id"
                class="inline-flex min-h-[44px] cursor-pointer items-center gap-2 rounded-full border border-default px-3 py-1 text-sm text-base-primary has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500 dark:has-[:checked]:bg-primary-900/30"
            >
                <input
                    type="checkbox"
                    class="h-4 w-4 rounded border-default text-primary-600 focus:ring-primary-500"
                    :checked="selected.includes(tag.id)"
                    @change="toggle(tag.id)"
                />
                {{ tag.name }}
            </label>
        </div>
    </fieldset>
</template>
