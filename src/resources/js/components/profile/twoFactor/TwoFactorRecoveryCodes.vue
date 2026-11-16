<script setup lang="ts">
import { Check, Copy, Download } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useNotifications } from '@/composables/useNotifications';

interface Props {
    codes: string[];
}

const props = defineProps<Props>();

const { t } = useI18n();
const page = usePage();
const notifications = useNotifications();
const copied = ref(false);

async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.codes.join('\n'));
        copied.value = true;
    } catch {
        notifications.error(t('auth.profile.twoFactor.copyError'));
    }
}

function download(): void {
    const siteName = (page.props as { appName?: string }).appName ?? '';
    const text = `${t('auth.profile.twoFactor.codesFile', { site: siteName })}\n\n${props.codes.join('\n')}\n`;
    const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = t('auth.profile.twoFactor.codesFileName');
    document.body.append(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <div class="space-y-3">
        <ul
            class="grid grid-cols-2 gap-2 rounded-md border border-default bg-muted p-3 font-mono text-sm text-base-primary"
            data-testid="recovery-codes"
        >
            <li v-for="code in codes" :key="code" class="select-all">{{ code }}</li>
        </ul>
        <div class="flex flex-wrap gap-2">
            <BaseButton type="button" variant="secondary" size="sm" @click="copy">
                <component :is="copied ? Check : Copy" class="mr-1.5 h-4 w-4" aria-hidden="true" />
                {{
                    copied
                        ? t('auth.profile.twoFactor.copied')
                        : t('auth.profile.twoFactor.copyCodes')
                }}
            </BaseButton>
            <BaseButton type="button" variant="secondary" size="sm" @click="download">
                <Download class="mr-1.5 h-4 w-4" aria-hidden="true" />
                {{ t('auth.profile.twoFactor.downloadCodes') }}
            </BaseButton>
        </div>
    </div>
</template>
