<script setup lang="ts">
import { router, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import type { AuthLayoutProps } from '@/types/layouts';

interface UnsubscribeCategory {
    label: string;
    mandatory: boolean;
    mandatoryReason: string | null;
}

interface Props {
    category: UnsubscribeCategory;
    unsubscribed: boolean;
    /** Signed address of this page: POST unsubscribes, DELETE undoes */
    actionUrl: string;
    preferencesUrl: string;
}

interface FlashProps {
    flash?: { success?: string | null };
}

const props = defineProps<Props>();

const { t } = useI18n();
const page = usePage();

defineOptions({ layout: AuthLayout });

const title = computed(() => {
    if (props.category.mandatory) {
        return t('mailUnsubscribe.mandatoryTitle');
    }

    return props.unsubscribed ? t('mailUnsubscribe.doneTitle') : t('mailUnsubscribe.title');
});

setLayoutProps<AuthLayoutProps>({
    title: title.value,
    showBackLink: false,
});

const processing = ref(false);
const successMessage = computed(() => (page.props as FlashProps).flash?.success ?? null);

// Each visit mounts the page again, so the layout title follows the new state
const send = (method: 'post' | 'delete'): void => {
    processing.value = true;
    router[method](
        props.actionUrl,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        }
    );
};
</script>

<template>
    <div class="space-y-6">
        <div v-if="successMessage" role="status" class="rounded-md bg-success-light p-4">
            <p class="text-sm text-success">{{ successMessage }}</p>
        </div>

        <template v-if="category.mandatory">
            <p class="text-sm text-base-primary">
                {{ t('mailUnsubscribe.mandatory', { category: category.label }) }}
            </p>
            <p v-if="category.mandatoryReason" class="text-sm text-base-secondary">
                {{ category.mandatoryReason }}
            </p>
        </template>

        <template v-else-if="unsubscribed">
            <p role="status" class="text-sm text-base-primary">
                {{ t('mailUnsubscribe.done', { category: category.label }) }}
            </p>
            <BaseButton
                variant="secondary"
                class="w-full"
                :disabled="processing"
                @click="send('delete')"
            >
                {{ processing ? t('common.loading') : t('mailUnsubscribe.undo') }}
            </BaseButton>
        </template>

        <template v-else>
            <p class="text-sm text-base-primary">
                {{ t('mailUnsubscribe.confirm', { category: category.label }) }}
            </p>
            <p class="text-sm text-base-secondary">
                {{ t('mailUnsubscribe.confirmHint') }}
            </p>
            <BaseButton
                variant="primary"
                class="w-full"
                :disabled="processing"
                @click="send('post')"
            >
                {{ processing ? t('common.loading') : t('mailUnsubscribe.submit') }}
            </BaseButton>
        </template>

        <p class="text-center text-sm">
            <a :href="preferencesUrl" class="font-medium text-primary hover:opacity-80">
                {{ t('mailUnsubscribe.choose') }}
            </a>
        </p>
    </div>
</template>
