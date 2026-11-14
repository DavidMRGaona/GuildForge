<script setup lang="ts">
import { useForm, Link, setLayoutProps } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import type { AuthLayoutProps } from '@/types/layouts';
import BaseButton from '@/components/ui/BaseButton.vue';
import FormCheckbox from '@/components/form/FormCheckbox.vue';
import { useAuth } from '@/composables/useAuth';
import { useNotifications } from '@/composables/useNotifications';
import { useRoutes } from '@/composables/useRoutes';
import type { RegisterFormData, RegistrationField } from '@/types/models';

const props = withDefaults(
    defineProps<{
        /** Optional fields added by modules (core#699) */
        registrationFields?: RegistrationField[];
    }>(),
    { registrationFields: () => [] }
);

const { t } = useI18n();

defineOptions({ layout: AuthLayout });
setLayoutProps<AuthLayoutProps>({
    title: t('auth.register.title'),
    subtitle: t('auth.register.subtitle'),
});

const { authSettings } = useAuth();
const notifications = useNotifications();
const routes = useRoutes();

/** The page always sends the module fields, even when there are none */
type RegisterForm = RegisterFormData & { registration_fields: Record<string, boolean> };

const form = useForm<RegisterForm>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    // Always unticked: ticking one is the person's explicit choice
    registration_fields: Object.fromEntries(
        props.registrationFields.map((field): [string, boolean] => [field.key, false])
    ),
});

/**
 * Keys are `<module>.<name>` in kebab case, which never holds `--`: the dot becomes `--`, so
 * `event.registrations-x` and `event-registrations.x` keep different ids
 */
function fieldId(field: RegistrationField): string {
    return `registration-field-${field.key.replaceAll('.', '--')}`;
}

/** Errors of module fields are keyed `registration_fields.<key>` */
function fieldError(field: RegistrationField): string | undefined {
    const errors: Record<string, string | undefined> = form.errors;

    return errors[`registration_fields.${field.key}`];
}

function setField(field: RegistrationField, ticked: boolean): void {
    form.registration_fields[field.key] = ticked;
}

/** Ids of the description and the error, '' when there is neither */
function fieldDescribedBy(field: RegistrationField): string {
    const ids = [
        field.description !== null ? `${fieldId(field)}-description` : null,
        fieldError(field) !== undefined ? `${fieldId(field)}-error` : null,
    ].filter((id): id is string => id !== null);

    return ids.join(' ');
}

const submit = () => {
    form.post(routes.auth.register, {
        onFinish: () => form.reset('password', 'password_confirmation'),
        onError: () => notifications.error(t('auth.register.error')),
    });
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div>
            <label for="name" class="block text-sm font-medium text-base-secondary">
                {{ t('auth.register.name') }}
            </label>
            <input
                id="name"
                v-model="form.name"
                type="text"
                required
                autocomplete="name"
                class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary placeholder-neutral-400 dark:placeholder-neutral-500 focus:border-primary-500 focus:outline-none focus:ring-primary-500"
            />
            <p v-if="form.errors.name" class="mt-1 text-sm text-error">
                {{ form.errors.name }}
            </p>
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-base-secondary">
                {{ t('auth.register.email') }}
            </label>
            <input
                id="email"
                v-model="form.email"
                type="email"
                required
                autocomplete="email"
                class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary placeholder-neutral-400 dark:placeholder-neutral-500 focus:border-primary-500 focus:outline-none focus:ring-primary-500"
            />
            <p v-if="form.errors.email" class="mt-1 text-sm text-error">
                {{ form.errors.email }}
            </p>
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-base-secondary">
                {{ t('auth.register.password') }}
            </label>
            <input
                id="password"
                v-model="form.password"
                type="password"
                required
                autocomplete="new-password"
                class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary placeholder-neutral-400 dark:placeholder-neutral-500 focus:border-primary-500 focus:outline-none focus:ring-primary-500"
            />
            <p v-if="form.errors.password" class="mt-1 text-sm text-error">
                {{ form.errors.password }}
            </p>
        </div>

        <div>
            <label
                for="password_confirmation"
                class="block text-sm font-medium text-base-secondary"
            >
                {{ t('auth.register.passwordConfirm') }}
            </label>
            <input
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                required
                autocomplete="new-password"
                class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary placeholder-neutral-400 dark:placeholder-neutral-500 focus:border-primary-500 focus:outline-none focus:ring-primary-500"
            />
        </div>

        <div v-if="props.registrationFields.length > 0" class="space-y-4">
            <div v-for="field in props.registrationFields" :key="field.key">
                <FormCheckbox
                    :id="fieldId(field)"
                    :model-value="form.registration_fields[field.key] === true"
                    :label="field.label"
                    :describedby="fieldDescribedBy(field)"
                    :invalid="fieldError(field) !== undefined"
                    @update:model-value="setField(field, $event)"
                />
                <p
                    v-if="field.description !== null"
                    :id="`${fieldId(field)}-description`"
                    class="mt-1 pl-8 text-sm text-base-muted"
                >
                    {{ field.description }}
                </p>
                <p
                    v-if="fieldError(field) !== undefined"
                    :id="`${fieldId(field)}-error`"
                    class="mt-1 pl-8 text-sm text-error"
                >
                    {{ fieldError(field) }}
                </p>
            </div>
        </div>

        <div>
            <BaseButton type="submit" variant="primary" class="w-full" :disabled="form.processing">
                {{ form.processing ? t('common.loading') : t('auth.register.submit') }}
            </BaseButton>
        </div>

        <p v-if="authSettings.loginEnabled" class="text-center text-sm text-base-secondary">
            {{ t('auth.register.hasAccount') }}
            <Link :href="routes.auth.login" class="font-medium text-primary hover:opacity-80">
                {{ t('auth.register.loginLink') }}
            </Link>
        </p>
    </form>
</template>
