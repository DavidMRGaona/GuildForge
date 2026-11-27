<script setup lang="ts">
import { useForm, setLayoutProps } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import type { AuthLayoutProps } from '@/types/layouts';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useAuth } from '@/composables/useAuth';
import { useNotifications } from '@/composables/useNotifications';

/**
 * «Elige tu nombre público» (core#855): shown once to an account without a public name. The field
 * comes with a suggestion (the first word of the account name only) and nothing is saved until
 * the person confirms.
 */
const props = defineProps<{
    suggestion: string;
}>();

const { t } = useI18n();

defineOptions({ layout: AuthLayout });
setLayoutProps<AuthLayoutProps>({
    title: t('auth.choosePublicName.title'),
});

const { logout } = useAuth();
const notifications = useNotifications();

const form = useForm({
    display_name: props.suggestion,
});

const submit = (): void => {
    form.post('/perfil/nombre-publico', {
        onError: () => notifications.error(t('auth.choosePublicName.error')),
    });
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="space-y-4 text-sm text-base-secondary">
            <p>{{ t('auth.choosePublicName.why') }}</p>
            <p>{{ t('auth.choosePublicName.consent') }}</p>
        </div>

        <div>
            <label for="display_name" class="block text-sm font-medium text-base-secondary">
                {{ t('auth.choosePublicName.label') }}
            </label>
            <input
                id="display_name"
                v-model="form.display_name"
                type="text"
                required
                maxlength="255"
                autocomplete="nickname"
                :aria-invalid="form.errors.display_name !== undefined"
                :aria-describedby="form.errors.display_name ? 'display_name-error' : undefined"
                class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary placeholder-neutral-400 dark:placeholder-neutral-500 focus:border-primary-500 focus:outline-none focus:ring-primary-500"
            />
            <p
                v-if="form.errors.display_name"
                id="display_name-error"
                class="mt-1 text-sm text-error"
            >
                {{ form.errors.display_name }}
            </p>
        </div>

        <BaseButton type="submit" variant="primary" class="w-full" :disabled="form.processing">
            {{ form.processing ? t('common.loading') : t('auth.choosePublicName.submit') }}
        </BaseButton>

        <BaseButton type="button" variant="ghost" class="w-full" @click="logout()">
            {{ t('auth.choosePublicName.logout') }}
        </BaseButton>
    </form>
</template>
