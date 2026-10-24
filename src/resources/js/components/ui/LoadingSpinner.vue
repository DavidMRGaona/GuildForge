<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue';
import { useI18n } from 'vue-i18n';

interface Props {
    size?: 'sm' | 'md' | 'lg';
    color?: string;
}

withDefaults(defineProps<Props>(), {
    size: 'md',
    color: 'text-primary',
});

const { t } = useI18n();

const sizeClasses: Record<NonNullable<Props['size']>, string> = {
    sm: 'h-4 w-4',
    md: 'h-8 w-8',
    lg: 'h-12 w-12',
};
</script>

<template>
    <div role="status" :aria-label="t('common.loading')">
        <LoaderCircle :class="[sizeClasses[size], color, 'animate-spin']" aria-hidden="true" />
        <span class="sr-only">{{ t('common.loading') }}</span>
    </div>
</template>
