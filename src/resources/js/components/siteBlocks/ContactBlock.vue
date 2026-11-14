<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

/**
 * Footer block `core.contact`: the contact details of «Nosotros» and a link to its form (core#697).
 */
defineProps<{
    email: string | null;
    phone: string | null;
    address: string | null;
    contactUrl: string | null;
}>();

const { t } = useI18n();

const linkClasses =
    'text-neutral-300 transition-colors hover:text-primary dark:text-neutral-400 dark:hover:text-primary';
</script>

<template>
    <address class="space-y-2 text-sm not-italic text-neutral-300 dark:text-neutral-400">
        <p v-if="email">
            <a :href="`mailto:${email}`" :class="[linkClasses, 'break-words']">{{ email }}</a>
        </p>
        <p v-if="phone">
            <a :href="`tel:${phone.replace(/\s+/g, '')}`" :class="linkClasses">{{ phone }}</a>
        </p>
        <p v-if="address" class="whitespace-pre-line">{{ address }}</p>
        <p v-if="contactUrl">
            <Link
                :href="contactUrl"
                :class="[linkClasses, 'font-medium underline underline-offset-4']"
            >
                {{ t('layout.contactForm') }}
            </Link>
        </p>
    </address>
</template>
