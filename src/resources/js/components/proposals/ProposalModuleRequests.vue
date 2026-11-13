<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import ProposalField from '@/components/proposals/ProposalField.vue';
import { visibleFields } from '@/utils/proposalRequests';
import type {
    ProposalRequestDefinition,
    ProposalRequestField,
    ProposalRequestState,
    ProposalRequestValue,
} from '@/types/proposals';

/**
 * The requests of the enabled modules below the event proposal form (core#581): a question that
 * opens each one and, once ticked, its fields. Drawn from what the server declares, so modules
 * bring no Vue; the server checks every value again and its errors come keyed
 * `requests.<key>.<field>`.
 */
const props = defineProps<{
    definitions: ProposalRequestDefinition[];
    errors: Record<string, string | undefined>;
}>();

const requests = defineModel<Record<string, ProposalRequestState>>({ required: true });

const { t } = useI18n();

const inputClass =
    'block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary focus:border-primary-500 focus:outline-none focus:ring-primary-500';

function stateOf(key: string): ProposalRequestState {
    return requests.value[key] ?? { wanted: false, values: {} };
}

function setWanted(key: string, wanted: boolean): void {
    requests.value = { ...requests.value, [key]: { ...stateOf(key), wanted } };
}

function setValue(key: string, name: string, value: ProposalRequestValue): void {
    const state = stateOf(key);
    requests.value = {
        ...requests.value,
        [key]: { ...state, values: { ...state.values, [name]: value } },
    };
}

function shown(definition: ProposalRequestDefinition): ProposalRequestField[] {
    const names = visibleFields(definition.fields, stateOf(definition.key).values);

    return definition.fields.filter((field) => names.includes(field.name));
}

function valueOf(key: string, name: string): ProposalRequestValue {
    return stateOf(key).values[name] ?? null;
}

function errorOf(key: string, name: string): string | undefined {
    return props.errors[`requests.${key}.${name}`];
}

function idOf(key: string, name: string): string {
    return `proposal-request-${key.replace(/[^a-z0-9]+/g, '-')}-${name}`;
}

function inputText(event: Event): string {
    return event.target instanceof HTMLInputElement || event.target instanceof HTMLSelectElement
        ? event.target.value
        : '';
}

function inputChecked(event: Event): boolean {
    return event.target instanceof HTMLInputElement && event.target.checked;
}
</script>

<template>
    <section
        v-if="definitions.length > 0"
        class="space-y-4 rounded-lg border border-default p-4 sm:p-6"
        aria-labelledby="proposal-requests-heading"
        data-testid="proposal-requests"
    >
        <div>
            <h2 id="proposal-requests-heading" class="text-lg font-semibold text-base-primary">
                {{ t('proposals.requests.heading') }}
            </h2>
            <p class="mt-1 text-sm text-base-muted">{{ t('proposals.requests.intro') }}</p>
        </div>

        <fieldset
            v-for="definition in definitions"
            :key="definition.key"
            class="space-y-4 border-t border-default pt-4 first-of-type:border-t-0 first-of-type:pt-0"
            :data-testid="`proposal-request-${definition.key}`"
        >
            <legend class="sr-only">{{ definition.label }}</legend>
            <label class="flex min-h-[44px] cursor-pointer items-center gap-3 text-base-primary">
                <input
                    type="checkbox"
                    class="h-5 w-5 rounded border-default text-primary-600 focus:ring-primary-500"
                    :checked="stateOf(definition.key).wanted"
                    data-testid="proposal-request-wanted"
                    @change="setWanted(definition.key, inputChecked($event))"
                />
                <span class="font-medium">{{ definition.question }}</span>
            </label>

            <div v-if="stateOf(definition.key).wanted" class="grid gap-4 sm:grid-cols-2">
                <template v-for="field in shown(definition)" :key="field.name">
                    <div v-if="field.type === 'boolean'" class="sm:col-span-2">
                        <label
                            class="flex min-h-[44px] cursor-pointer items-center gap-3 text-sm text-base-secondary"
                        >
                            <input
                                :id="idOf(definition.key, field.name)"
                                type="checkbox"
                                class="h-4 w-4 rounded border-default text-primary-600 focus:ring-primary-500"
                                :checked="valueOf(definition.key, field.name) === true"
                                @change="setValue(definition.key, field.name, inputChecked($event))"
                            />
                            {{ field.label }}
                        </label>
                        <p v-if="field.helperText" class="text-sm text-base-muted">
                            {{ field.helperText }}
                        </p>
                        <p
                            v-if="errorOf(definition.key, field.name)"
                            role="alert"
                            class="mt-1 text-sm text-error"
                        >
                            {{ errorOf(definition.key, field.name) }}
                        </p>
                    </div>

                    <ProposalField
                        v-else
                        :label="field.label"
                        :for="idOf(definition.key, field.name)"
                        :hint="field.helperText ?? undefined"
                        :error="errorOf(definition.key, field.name)"
                        :required="field.required"
                        :class="field.type === 'text' ? 'sm:col-span-2' : undefined"
                    >
                        <template #default="{ hintId, errorId }">
                            <select
                                v-if="field.type === 'select'"
                                :id="idOf(definition.key, field.name)"
                                :value="valueOf(definition.key, field.name) ?? ''"
                                :required="field.required"
                                :aria-describedby="
                                    [hintId, errorId].filter(Boolean).join(' ') || undefined
                                "
                                :aria-invalid="errorId ? 'true' : undefined"
                                :class="inputClass"
                                @change="setValue(definition.key, field.name, inputText($event))"
                            >
                                <option value="" :disabled="field.required">
                                    {{ t('proposals.requests.choose') }}
                                </option>
                                <option
                                    v-for="option in field.options"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <input
                                v-else
                                :id="idOf(definition.key, field.name)"
                                :type="field.type === 'number' ? 'number' : 'text'"
                                :inputmode="field.type === 'number' ? 'numeric' : undefined"
                                :step="field.type === 'number' ? 1 : undefined"
                                :min="
                                    field.type === 'number' ? (field.min ?? undefined) : undefined
                                "
                                :max="
                                    field.type === 'number' ? (field.max ?? undefined) : undefined
                                "
                                :maxlength="
                                    field.type === 'text'
                                        ? (field.maxLength ?? undefined)
                                        : undefined
                                "
                                :value="valueOf(definition.key, field.name) ?? ''"
                                :required="field.required"
                                :aria-describedby="
                                    [hintId, errorId].filter(Boolean).join(' ') || undefined
                                "
                                :aria-invalid="errorId ? 'true' : undefined"
                                :class="inputClass"
                                @input="setValue(definition.key, field.name, inputText($event))"
                            />
                        </template>
                    </ProposalField>
                </template>
            </div>
        </fieldset>
    </section>
</template>
