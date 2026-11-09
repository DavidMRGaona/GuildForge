<script setup lang="ts">
import { computed } from 'vue';
import MailPreferenceRow from '@/components/profile/MailPreferenceRow.vue';
import type { MailPreferenceSection } from '@/types/mailPreferences';

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

const headingId = computed(() => `mail-section-${props.section.key.replace(/[^a-z0-9-]/gi, '-')}`);
</script>

<template>
    <section :aria-labelledby="headingId">
        <h3 :id="headingId" class="text-base font-semibold text-base-primary">
            {{ section.label }}
        </h3>
        <ul class="mt-1 divide-y divide-default">
            <MailPreferenceRow
                v-for="category in section.categories"
                :key="category.key"
                :category="category"
                :model-value="choices[category.key] ?? category.enabled"
                :busy="busy"
                :error="errors[category.key] ?? ''"
                @update:model-value="emit('change', category.key, $event)"
            />
        </ul>
    </section>
</template>
