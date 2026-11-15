<script setup lang="ts">
import { computed, nextTick, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { ChevronDown, ChevronUp } from '@lucide/vue';
import PreferenceSwitch from '@/components/profile/PreferenceSwitch.vue';
import type { ProfileEditorLayoutItem, ProfileLayoutChoice } from '@/types/profileEditor';

/**
 * «Secciones» or «Contadores»: each with its switch, what it shows, and buttons to move it up and down,
 * so the order is set with the keyboard as well as the pointer (nothing works only by dragging). After a
 * move the focus stays on the moved item and a polite message says where it is now.
 */
const props = defineProps<{
    legend: string;
    help: string;
    /** Labels and descriptions of what is offered */
    items: ProfileEditorLayoutItem[];
    error?: string | undefined;
}>();

const model = defineModel<ProfileLayoutChoice[]>({ required: true });

const { t } = useI18n();
const uid = useId();
const announcement = ref('');
const list = ref<HTMLElement | null>(null);

const byKey = computed(() => new Map(props.items.map((item) => [item.key, item])));
const rows = computed(() =>
    model.value
        .map((choice) => ({ choice, item: byKey.value.get(choice.key) }))
        .filter(
            (row): row is { choice: ProfileLayoutChoice; item: ProfileEditorLayoutItem } =>
                row.item !== undefined
        )
);

function idOf(key: string, part: string): string {
    return `${uid}-${key.replace(/[^a-z0-9-]/gi, '-')}-${part}`;
}

function toggle(key: string): void {
    model.value = model.value.map((choice) =>
        choice.key === key ? { ...choice, enabled: !choice.enabled } : choice
    );
}

async function move(key: string, step: -1 | 1): Promise<void> {
    const from = model.value.findIndex((choice) => choice.key === key);
    const to = from + step;

    if (from < 0 || to < 0 || to >= model.value.length) {
        return;
    }

    const next = [...model.value];
    const [moved] = next.splice(from, 1);
    if (!moved) {
        return;
    }
    next.splice(to, 0, moved);
    model.value = next;

    const label = byKey.value.get(key)?.label ?? key;
    announcement.value = t('publicProfile.editor.moved', {
        label,
        position: to + 1,
        total: next.length,
    });

    // The same button on the moved item, or the other one once it reached an end
    await nextTick();
    const atEnd = step === -1 ? to === 0 : to === next.length - 1;
    const button = list.value?.querySelector<HTMLButtonElement>(
        `#${CSS.escape(idOf(key, atEnd ? (step === -1 ? 'down' : 'up') : step === -1 ? 'up' : 'down'))}`
    );
    button?.focus();
}
</script>

<template>
    <fieldset v-if="rows.length > 0" :aria-describedby="`${uid}-help`">
        <legend class="text-sm font-semibold text-base-primary">{{ legend }}</legend>
        <p :id="`${uid}-help`" class="mt-1 text-xs text-base-muted">{{ help }}</p>
        <ol
            ref="list"
            class="mt-3 divide-y divide-(--color-border) rounded-lg border border-default bg-surface"
        >
            <li
                v-for="(row, index) in rows"
                :key="row.choice.key"
                class="flex items-center gap-3 px-3 py-2"
                data-testid="layout-item"
                :data-key="row.choice.key"
            >
                <PreferenceSwitch
                    :checked="row.choice.enabled"
                    :labelled-by="idOf(row.choice.key, 'label')"
                    :described-by="row.item.description ? idOf(row.choice.key, 'description') : ''"
                    @toggle="toggle(row.choice.key)"
                />
                <div class="min-w-0 flex-1">
                    <p
                        :id="idOf(row.choice.key, 'label')"
                        class="text-sm font-medium text-base-primary"
                    >
                        {{ row.item.label }}
                    </p>
                    <p
                        v-if="row.item.description"
                        :id="idOf(row.choice.key, 'description')"
                        class="text-xs text-base-muted"
                    >
                        {{ row.item.description }}
                    </p>
                </div>
                <div class="flex shrink-0 gap-1">
                    <button
                        :id="idOf(row.choice.key, 'up')"
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-md text-base-secondary transition-colors hover:bg-muted hover:text-base-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-40 motion-reduce:transition-none"
                        :aria-label="t('publicProfile.editor.moveUp', { label: row.item.label })"
                        :disabled="index === 0"
                        data-testid="move-up"
                        @click="move(row.choice.key, -1)"
                    >
                        <ChevronUp class="h-4 w-4" aria-hidden="true" />
                    </button>
                    <button
                        :id="idOf(row.choice.key, 'down')"
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-md text-base-secondary transition-colors hover:bg-muted hover:text-base-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-40 motion-reduce:transition-none"
                        :aria-label="t('publicProfile.editor.moveDown', { label: row.item.label })"
                        :disabled="index === rows.length - 1"
                        data-testid="move-down"
                        @click="move(row.choice.key, 1)"
                    >
                        <ChevronDown class="h-4 w-4" aria-hidden="true" />
                    </button>
                </div>
            </li>
        </ol>
        <p v-if="error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ error }}</p>
        <p class="sr-only" aria-live="polite" data-testid="layout-announcement">
            {{ announcement }}
        </p>
    </fieldset>
</template>
