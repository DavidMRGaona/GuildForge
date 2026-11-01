<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoutes } from '@/composables/useRoutes';
import { csrfHeaders } from '@/utils/csrf';

const props = defineProps<{
    guideId: string;
}>();

const { t } = useI18n();
const routes = useRoutes();

const sending = ref(false);
const voted = ref(false);

async function vote(helpful: boolean): Promise<void> {
    if (sending.value || voted.value) {
        return;
    }
    sending.value = true;

    try {
        const response = await fetch(routes.knowledge.vote(props.guideId), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify({ helpful }),
        });
        if (response.ok) {
            voted.value = true;
        }
    } catch {
        // A failed vote is not worth bothering the reader: the buttons stay so they can try again
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <section
        class="mt-10 border-t border-default pt-6"
        :aria-label="t('knowledge.feedback.question')"
    >
        <p v-if="voted" role="status" class="text-sm text-base-secondary">
            {{ t('knowledge.feedback.thanks') }}
        </p>
        <div v-else class="flex flex-wrap items-center gap-3">
            <p class="text-sm font-medium text-base-primary">
                {{ t('knowledge.feedback.question') }}
            </p>
            <button
                type="button"
                class="rounded-md border border-default px-3 py-1 text-sm text-base-primary hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-60"
                :disabled="sending"
                @click="vote(true)"
            >
                {{ t('knowledge.feedback.yes') }}
            </button>
            <button
                type="button"
                class="rounded-md border border-default px-3 py-1 text-sm text-base-primary hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-60"
                :disabled="sending"
                @click="vote(false)"
            >
                {{ t('knowledge.feedback.no') }}
            </button>
        </div>
    </section>
</template>
