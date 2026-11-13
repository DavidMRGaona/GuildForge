<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import FormCheckbox from '@/components/form/FormCheckbox.vue';
import FormNumberInput from '@/components/form/FormNumberInput.vue';
import FormSelect from '@/components/form/FormSelect.vue';
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
    /** Early warnings by request key, from what is already saved; they never block sending. */
    warnings?: Record<string, string[]>;
}>();

const requests = defineModel<Record<string, ProposalRequestState>>({ required: true });

const { t } = useI18n();

const textClass =
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
    return event.target instanceof HTMLInputElement ? event.target.value : '';
}

/** A number field keeps the text the server reads: '' when empty, never NaN. */
function numberOf(value: ProposalRequestValue): number | null {
    if (typeof value === 'number') {
        return value;
    }
    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
}

function choiceOf(value: ProposalRequestValue): string | number | null {
    return typeof value === 'boolean' ? null : value;
}

function describedBy(hintId: string | undefined, errorId: string | undefined): string | undefined {
    return [hintId, errorId].filter(Boolean).join(' ') || undefined;
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
            <div class="flex min-h-[44px] items-center">
                <FormCheckbox
                    :model-value="stateOf(definition.key).wanted"
                    data-testid="proposal-request-wanted"
                    @update:model-value="setWanted(definition.key, $event)"
                >
                    <span class="text-base font-medium text-base-primary">
                        {{ definition.question }}
                    </span>
                </FormCheckbox>
            </div>

            <ul
                v-if="
                    stateOf(definition.key).wanted && (warnings?.[definition.key] ?? []).length > 0
                "
                class="space-y-1 rounded-md bg-muted p-3 text-sm text-warning"
                data-testid="proposal-request-warnings"
            >
                <li v-for="warning in warnings?.[definition.key]" :key="warning">
                    {{ t('proposals.requests.warning', { text: warning }) }}
                </li>
            </ul>

            <div v-if="stateOf(definition.key).wanted" class="grid gap-4 sm:grid-cols-2">
                <template v-for="field in shown(definition)" :key="field.name">
                    <div v-if="field.type === 'boolean'" class="sm:col-span-2">
                        <div class="flex min-h-[44px] items-center">
                            <FormCheckbox
                                :id="idOf(definition.key, field.name)"
                                :model-value="valueOf(definition.key, field.name) === true"
                                :label="field.label"
                                @update:model-value="setValue(definition.key, field.name, $event)"
                            />
                        </div>
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
                            <FormSelect
                                v-if="field.type === 'select'"
                                :id="idOf(definition.key, field.name)"
                                :model-value="choiceOf(valueOf(definition.key, field.name))"
                                :options="field.options"
                                :placeholder="t('proposals.requests.choose')"
                                :error="errorOf(definition.key, field.name) ?? ''"
                                :described-by="describedBy(hintId, errorId)"
                                @update:model-value="
                                    setValue(definition.key, field.name, $event ?? '')
                                "
                            />
                            <FormNumberInput
                                v-else-if="field.type === 'number'"
                                :id="idOf(definition.key, field.name)"
                                :model-value="numberOf(valueOf(definition.key, field.name))"
                                :min="field.min ?? undefined"
                                :max="field.max ?? undefined"
                                :required="field.required"
                                :error="errorOf(definition.key, field.name)"
                                :described-by="describedBy(hintId, errorId)"
                                @update:model-value="
                                    setValue(
                                        definition.key,
                                        field.name,
                                        $event === null ? '' : String($event)
                                    )
                                "
                            />
                            <input
                                v-else
                                :id="idOf(definition.key, field.name)"
                                type="text"
                                :maxlength="field.maxLength ?? undefined"
                                :value="valueOf(definition.key, field.name) ?? ''"
                                :required="field.required"
                                :aria-describedby="describedBy(hintId, errorId)"
                                :aria-invalid="errorId ? 'true' : undefined"
                                :class="textClass"
                                @input="setValue(definition.key, field.name, inputText($event))"
                            />
                        </template>
                    </ProposalField>
                </template>
            </div>
        </fieldset>
    </section>
</template>
