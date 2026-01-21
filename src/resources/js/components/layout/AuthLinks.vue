<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';

interface Props {
    mobile?: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'navigate'): void;
}>();

const { t } = useI18n();
const { authSettings } = useAuth();

function handleNavigate(): void {
    emit('navigate');
}
</script>

<template>
    <!-- Mobile layout -->
    <template v-if="mobile">
        <div class="space-y-1">
            <Link
                v-if="authSettings.loginEnabled"
                href="/login"
                class="flex items-center px-3 py-2 text-base font-medium text-stone-600 hover:bg-stone-100 hover:text-stone-900 rounded-md dark:text-stone-300 dark:hover:bg-stone-800 dark:hover:text-white"
                @click="handleNavigate"
            >
                <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                </svg>
                {{ t('auth.login.title') }}
            </Link>
            <Link
                v-if="authSettings.registrationEnabled"
                href="/registro"
                class="flex items-center px-3 py-2 text-base font-medium text-stone-600 hover:bg-stone-100 hover:text-stone-900 rounded-md dark:text-stone-300 dark:hover:bg-stone-800 dark:hover:text-white"
                @click="handleNavigate"
            >
                <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
                {{ t('auth.register.title') }}
            </Link>
        </div>
    </template>

    <!-- Desktop layout -->
    <template v-else>
        <div class="flex items-center space-x-2">
            <Link
                v-if="authSettings.registrationEnabled"
                href="/registro"
                :class="authSettings.loginEnabled
                    ? 'rounded-md px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-100 hover:text-stone-900 dark:text-stone-300 dark:hover:bg-stone-800 dark:hover:text-white'
                    : 'rounded-md bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600'"
            >
                {{ t('auth.register.title') }}
            </Link>
            <Link
                v-if="authSettings.loginEnabled"
                href="/login"
                class="rounded-md bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600"
            >
                {{ t('auth.login.title') }}
            </Link>
        </div>
    </template>
</template>
