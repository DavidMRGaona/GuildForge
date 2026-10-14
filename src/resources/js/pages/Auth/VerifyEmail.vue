<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useAuth } from '@/composables/useAuth';
import { useRoutes } from '@/composables/useRoutes';

interface FlashProps {
    flash?: { success?: string };
}

const { t } = useI18n();
const { logout } = useAuth();
const routes = useRoutes();
const page = usePage();
const props = page.props as FlashProps;

const form = useForm({});
const successMessage = computed(() => props.flash?.success);

const resend = () => {
    form.post('/verificar-email/reenviar');
};
</script>

<template>
    <AuthLayout :title="t('auth.verifyEmail.title')" :subtitle="t('auth.verifyEmail.subtitle')">
        <div v-if="successMessage" class="mb-4 rounded-md bg-success-light p-4">
            <p class="text-sm text-success">{{ successMessage }}</p>
        </div>

        <div class="space-y-6">
            <p class="text-sm text-base-secondary">
                {{ t('auth.verifyEmail.description') }}
            </p>

            <BaseButton
                variant="primary"
                class="w-full"
                :disabled="form.processing"
                @click="resend"
            >
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
    </AuthLayout>
</template>
