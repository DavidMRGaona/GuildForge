<script setup lang="ts">
import { Link, useForm, usePage, setLayoutProps } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import type { AuthLayoutProps } from '@/types/layouts';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useAuth } from '@/composables/useAuth';
import { useRoutes } from '@/composables/useRoutes';

interface Props {
    email: string;
    pendingEmail: string | null;
}

interface FlashProps {
    flash?: { success?: string | null; error?: string | null };
}

const props = defineProps<Props>();

const { t } = useI18n();

defineOptions({ layout: AuthLayout });
setLayoutProps<AuthLayoutProps>({
    title: t('auth.verifyEmail.title'),
    subtitle: t('auth.verifyEmail.subtitle'),
});

const { logout } = useAuth();
const routes = useRoutes();
const page = usePage();

const form = useForm({});
const flash = computed(() => (page.props as FlashProps).flash);
const successMessage = computed(() => flash.value?.success);
const errorMessage = computed(() => flash.value?.error);
const sentToMessage = computed(() =>
    props.pendingEmail
        ? t('auth.verifyEmail.sentToPending', { email: props.pendingEmail })
        : t('auth.verifyEmail.sentTo', { email: props.email })
);

const resend = () => {
    form.post('/verificar-email/reenviar');
};
</script>

<template>
    <div v-if="successMessage" class="mb-4 rounded-md bg-success-light p-4">
        <p class="text-sm text-success">{{ successMessage }}</p>
    </div>

    <div v-if="errorMessage" role="alert" class="mb-4 rounded-md bg-error-light p-4">
        <p class="text-sm text-error">{{ errorMessage }}</p>
    </div>

    <div class="space-y-6">
        <p class="text-sm text-base-secondary">
            {{ t('auth.verifyEmail.description') }}
        </p>

        <p class="text-sm font-medium text-base-primary">
            {{ sentToMessage }}
        </p>

        <BaseButton variant="primary" class="w-full" :disabled="form.processing" @click="resend">
            {{ form.processing ? t('common.loading') : t('auth.verifyEmail.resend') }}
        </BaseButton>

        <p class="text-center text-sm text-base-secondary">
            {{ t('auth.verifyEmail.wrongEmail') }}
            <Link :href="routes.profile" class="font-medium text-primary hover:opacity-80">
                {{ t('auth.verifyEmail.changeEmail') }}
            </Link>
        </p>

        <BaseButton variant="ghost" class="w-full" @click="logout">
            {{ t('auth.verifyEmail.logout') }}
        </BaseButton>
    </div>
</template>
