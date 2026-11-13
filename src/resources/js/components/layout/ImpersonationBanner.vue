<script setup lang="ts">
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Eye, FlaskConical } from '@lucide/vue';
import { useRoutes } from '@/composables/useRoutes';
import type { ImpersonationSharedProps } from '@/types/impersonation';

const { t } = useI18n();
const page = usePage();
const routes = useRoutes();

const impersonation = computed(
    () => (page.props.impersonation as ImpersonationSharedProps | null | undefined) ?? null
);
const isSimulate = computed<boolean>(() => impersonation.value?.mode === 'simulate');

const modeText = computed<string>(() =>
    isSimulate.value ? t('impersonationBanner.simulate') : t('impersonationBanner.readOnly')
);

// Simulate runs what the person submits, so it gets the warning colours; read-only is calmer
const bandClasses = computed<string>(() =>
    isSimulate.value ? 'border-warning bg-warning-light' : 'border-info bg-info-light'
);

const form = useForm({});

function stop(): void {
    form.post(routes.auth.stopImpersonation);
}
</script>

<template>
    <div
        v-if="impersonation"
        class="sticky top-0 z-40 border-b-2"
        :class="bandClasses"
        role="region"
        :aria-label="t('impersonationBanner.label')"
        data-testid="impersonation-banner"
        :data-mode="impersonation.mode"
    >
        <div
            class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-6 lg:px-8"
        >
            <div class="flex items-start gap-3" role="status">
                <FlaskConical
                    v-if="isSimulate"
                    class="mt-0.5 h-5 w-5 shrink-0 text-warning"
                    aria-hidden="true"
                />
                <Eye v-else class="mt-0.5 h-5 w-5 shrink-0 text-info" aria-hidden="true" />
                <div class="text-sm text-base-primary">
                    <p class="font-semibold">
                        {{ t('impersonationBanner.viewingAs', { name: impersonation.targetName }) }}
                    </p>
                    <p data-testid="impersonation-mode">{{ modeText }}</p>
                </div>
            </div>

            <button
                type="button"
                class="min-h-[44px] shrink-0 self-start rounded border border-current px-4 text-sm font-semibold text-base-primary hover:bg-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:cursor-wait disabled:opacity-70 sm:self-auto"
                :disabled="form.processing"
                @click="stop"
            >
                {{
                    form.processing
                        ? t('impersonationBanner.stopping')
                        : t('impersonationBanner.stop')
                }}
            </button>
        </div>
    </div>
</template>
