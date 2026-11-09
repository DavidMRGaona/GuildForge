<script setup lang="ts">
import { router, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import type { AuthLayoutProps } from '@/types/layouts';

interface MutedSubject {
    label: string;
    /** Kind of thing («Campaña»), when its module still names it. */
    typeLabel: string | null;
}

interface Props {
    subject: MutedSubject;
    muted: boolean;
    /** Signed address of this page: POST mutes, DELETE undoes */
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

// Confirming or undoing redirects back to this same page, which Inertia reuses: the title has
// to follow the new state instead of being set once
watchEffect(() => {
    setLayoutProps<AuthLayoutProps>({
        title: props.muted ? t('mailMuteSubject.doneTitle') : t('mailMuteSubject.title'),
        showBackLink: false,
    });
});

const processing = ref(false);
const successMessage = computed(() => (page.props as FlashProps).flash?.success ?? null);

const send = (method: 'post' | 'delete'): void => {
    processing.value = true;
    const options = {
        preserveScroll: true,
        onFinish: (): void => {
            processing.value = false;
        },
    };

    // router.delete() takes no data argument: its second argument is the options
    if (method === 'post') {
        router.post(props.actionUrl, {}, options);
    } else {
        router.delete(props.actionUrl, options);
    }
};
</script>

<template>
    <div class="space-y-6">
        <div v-if="successMessage" role="status" class="rounded-md bg-success-light p-4">
            <p class="text-sm text-success">{{ successMessage }}</p>
        </div>

        <div class="rounded-md border border-default p-4">
            <p
                v-if="subject.typeLabel"
                class="text-xs font-medium uppercase tracking-wide text-base-secondary"
            >
                {{ subject.typeLabel }}
            </p>
            <p class="break-words text-base font-medium text-base-primary">{{ subject.label }}</p>
        </div>

        <template v-if="muted">
            <p role="status" class="text-sm text-base-primary">
                {{ t('mailMuteSubject.done', { subject: subject.label }) }}
            </p>
            <BaseButton
                variant="secondary"
                class="w-full"
                :disabled="processing"
                @click="send('delete')"
            >
                {{ processing ? t('common.loading') : t('mailMuteSubject.undo') }}
            </BaseButton>
        </template>

        <template v-else>
            <p class="text-sm text-base-primary">
                {{ t('mailMuteSubject.confirm', { subject: subject.label }) }}
            </p>
            <p class="text-sm text-base-secondary">
                {{ t('mailMuteSubject.confirmHint') }}
            </p>
            <BaseButton
                variant="primary"
                class="w-full"
                :disabled="processing"
                @click="send('post')"
            >
                {{ processing ? t('common.loading') : t('mailMuteSubject.submit') }}
            </BaseButton>
        </template>

        <p class="text-center text-sm">
            <a :href="preferencesUrl" class="font-medium text-primary hover:opacity-80">
                {{ t('mailMuteSubject.choose') }}
            </a>
        </p>
    </div>
</template>
