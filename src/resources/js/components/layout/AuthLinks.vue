<script setup lang="ts">
import { LogIn, UserPlus } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';
import { useRoutes } from '@/composables/useRoutes';

interface Props {
    mobile?: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'navigate'): void;
}>();

const { t } = useI18n();
const { authSettings } = useAuth();
const routes = useRoutes();

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
                :href="routes.auth.login"
                class="flex items-center px-3 py-2 text-base font-medium text-base-secondary hover:bg-muted hover:text-base-primary rounded-md"
                @click="handleNavigate"
            >
                <LogIn class="mr-3 h-5 w-5" aria-hidden="true" />
                {{ t('auth.login.title') }}
            </Link>
            <Link
                v-if="authSettings.registrationEnabled"
                :href="routes.auth.register"
                class="flex items-center px-3 py-2 text-base font-medium text-base-secondary hover:bg-muted hover:text-base-primary rounded-md"
                @click="handleNavigate"
            >
                <UserPlus class="mr-3 h-5 w-5" aria-hidden="true" />
                {{ t('auth.register.title') }}
            </Link>
        </div>
    </template>

    <!-- Desktop layout -->
    <template v-else>
        <div class="flex items-center space-x-2">
            <Link
                v-if="authSettings.registrationEnabled"
                :href="routes.auth.register"
                :class="
                    authSettings.loginEnabled
                        ? 'rounded-md px-3 py-2 text-sm font-medium text-base-primary hover:bg-muted'
                        : 'rounded-md bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600'
                "
            >
                {{ t('auth.register.title') }}
            </Link>
            <Link
                v-if="authSettings.loginEnabled"
                :href="routes.auth.login"
                class="rounded-md bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600"
            >
                {{ t('auth.login.title') }}
            </Link>
        </div>
    </template>
</template>
