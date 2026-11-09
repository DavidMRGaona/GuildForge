<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { MailDelivery, MailPreferenceSection } from '@/types/mailPreferences';
import { countOptionalOn, optionalCategories } from '@/utils/mailPreferences';

/**
 * One tab per section, each with «activados/total» (the optional categories that are on), in
 * the manner of the permissions screen. It is the tab list only: the parent draws the panel
 * of the selected section under it (`panelId` is the id that panel must have).
 */
interface Props {
    sections: MailPreferenceSection[];
    /** Current choice of every category, by key. */
    choices: Record<string, MailDelivery>;
    /** Key of the selected section. */
    modelValue: string;
    /** Id of the panel the tabs control. */
    panelId: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{ 'update:modelValue': [key: string] }>();

const { t } = useI18n();

const buttons = ref<HTMLButtonElement[]>([]);

const tabs = computed(() =>
    props.sections.map((section) => {
        const total = optionalCategories(section).length;
        const enabled = countOptionalOn(section, props.choices);

        return {
            key: section.key,
            id: tabId(section.key),
            label: section.label,
            total,
            enabled,
            // Read once by screen readers («2 de 3 activados»); the «2/3» chip is only for the eyes
            summary: t('auth.profile.mail.section.summary', { enabled, total }, total),
        };
    })
);

function tabId(key: string): string {
    return `mail-tab-${key.replace(/[^a-z0-9-]/gi, '-')}`;
}

function select(key: string): void {
    emit('update:modelValue', key);
}

/** Arrow keys, Home and End move between the tabs and select the one reached. */
function onKeydown(event: KeyboardEvent, index: number): void {
    const last = tabs.value.length - 1;
    const target: Record<string, number> = {
        ArrowRight: index === last ? 0 : index + 1,
        ArrowLeft: index === 0 ? last : index - 1,
        Home: 0,
        End: last,
    };
    const next = target[event.key];

    if (next === undefined) {
        return;
    }

    event.preventDefault();
    const tab = tabs.value[next];
    if (tab !== undefined) {
        select(tab.key);
        buttons.value[next]?.focus();
    }
}
</script>

<template>
    <div
        role="tablist"
        :aria-label="t('auth.profile.mail.view.sections')"
        class="flex gap-1 overflow-x-auto border-b border-default"
    >
        <button
            v-for="(tab, index) in tabs"
            :id="tab.id"
            :key="tab.key"
            ref="buttons"
            type="button"
            role="tab"
            :aria-selected="modelValue === tab.key"
            :aria-controls="panelId"
            :tabindex="modelValue === tab.key ? 0 : -1"
            class="relative flex shrink-0 items-center gap-2 whitespace-nowrap px-3 py-3 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"
            :class="
                modelValue === tab.key
                    ? 'text-primary'
                    : 'text-base-muted hover:text-base-secondary'
            "
            @click="select(tab.key)"
            @keydown="onKeydown($event, index)"
        >
            <span>{{ tab.label }}</span>
            <template v-if="tab.total > 0">
                <span
                    class="rounded-full border border-default bg-muted px-1.5 py-0.5 text-xs font-semibold text-base-secondary"
                    aria-hidden="true"
                >
                    {{ tab.enabled }}/{{ tab.total }}
                </span>
                <span class="sr-only">{{ tab.summary }}</span>
            </template>
            <span
                v-if="modelValue === tab.key"
                class="absolute inset-x-0 bottom-0 h-0.5 bg-primary-500"
                aria-hidden="true"
            />
        </button>
    </div>
</template>
