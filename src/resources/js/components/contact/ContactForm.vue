<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { ContactFormData } from '@/types/models';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useNotifications } from '@/composables/useNotifications';
import { useFlashMessages } from '@/composables/useFlashMessages';
import { useRoutes } from '@/composables/useRoutes';

// Composables
const { t } = useI18n();
const notifications = useNotifications();
const { success: flashSuccess } = useFlashMessages();
const routes = useRoutes();

// Form state
const form = useForm<ContactFormData>({
    name: '',
    email: '',
    message: '',
    website: '', // Honeypot field
});

// Methods
const submit = (): void => {
    form.post(routes.contact, {
        onSuccess: () => {
            form.reset();
        },
        onError: () => notifications.error(t('about.contact.form.error')),
    });
};
</script>

<template>
    <div
        class="bg-surface rounded-lg shadow-md p-6 h-full dark:shadow-neutral-900/50"
    >
        <h2 class="text-2xl font-bold text-base-primary mb-6">
            {{ t('about.contact.form.title') }}
        </h2>

        <!-- Success message -->
        <div
            v-if="flashSuccess"
            class="mb-6 p-4 bg-success-light border border-success rounded-md"
            role="alert"
        >
            <p class="text-success text-sm">
                {{ flashSuccess }}
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Name field -->
            <div>
                <label
                    for="contact-name"
                    class="block text-sm font-medium text-base-secondary mb-1"
                >
                    {{ t('about.contact.form.name') }}
                    <span class="text-error" aria-label="required">*</span>
                </label>
                <input
                    id="contact-name"
                    v-model="form.name"
                    type="text"
                    name="name"
                    required
                    :placeholder="t('about.contact.form.namePlaceholder')"
                    :disabled="form.processing"
                    :aria-invalid="!!form.errors.name"
                    :aria-describedby="form.errors.name ? 'name-error' : undefined"
                    class="w-full px-4 py-2 border border-default rounded-md shadow-sm bg-surface text-base-primary placeholder-base-muted focus:ring-2 focus:ring-primary-500 focus:border-primary-500 disabled:bg-muted disabled:cursor-not-allowed disabled:text-base-muted transition-colors"
                    :class="{
                        'border-error focus:ring-error focus:border-error': form.errors.name,
                    }"
                />
                <p
                    v-if="form.errors.name"
                    id="name-error"
                    class="mt-1 text-sm text-error"
                    role="alert"
                >
                    {{ form.errors.name }}
                </p>
            </div>

            <!-- Email field -->
            <div>
                <label
                    for="contact-email"
                    class="block text-sm font-medium text-base-secondary mb-1"
                >
                    {{ t('about.contact.email') }}
                    <span class="text-error" aria-label="required">*</span>
                </label>
                <input
                    id="contact-email"
                    v-model="form.email"
                    type="email"
                    name="email"
                    required
                    :placeholder="t('about.contact.form.emailPlaceholder')"
                    :disabled="form.processing"
                    :aria-invalid="!!form.errors.email"
                    :aria-describedby="form.errors.email ? 'email-error' : undefined"
                    class="w-full px-4 py-2 border border-default rounded-md shadow-sm bg-surface text-base-primary placeholder-base-muted focus:ring-2 focus:ring-primary-500 focus:border-primary-500 disabled:bg-muted disabled:cursor-not-allowed disabled:text-base-muted transition-colors"
                    :class="{
                        'border-error focus:ring-error focus:border-error': form.errors.email,
                    }"
                />
                <p
                    v-if="form.errors.email"
                    id="email-error"
                    class="mt-1 text-sm text-error"
                    role="alert"
                >
                    {{ form.errors.email }}
                </p>
            </div>

            <!-- Message field -->
            <div>
                <label
                    for="contact-message"
                    class="block text-sm font-medium text-base-secondary mb-1"
                >
                    {{ t('about.contact.form.message') }}
                    <span class="text-error" aria-label="required">*</span>
                </label>
                <textarea
                    id="contact-message"
                    v-model="form.message"
                    name="message"
                    required
                    rows="5"
                    :placeholder="t('about.contact.form.messagePlaceholder')"
                    :disabled="form.processing"
                    :aria-invalid="!!form.errors.message"
                    :aria-describedby="form.errors.message ? 'message-error' : undefined"
                    class="w-full px-4 py-2 border border-default rounded-md shadow-sm bg-surface text-base-primary placeholder-base-muted focus:ring-2 focus:ring-primary-500 focus:border-primary-500 disabled:bg-muted disabled:cursor-not-allowed disabled:text-base-muted transition-colors resize-y"
                    :class="{
                        'border-error focus:ring-error focus:border-error': form.errors.message,
                    }"
                />
                <p
                    v-if="form.errors.message"
                    id="message-error"
                    class="mt-1 text-sm text-error"
                    role="alert"
                >
                    {{ form.errors.message }}
                </p>
            </div>

            <!-- Honeypot field (hidden from users, visible to bots) -->
            <div class="absolute left-[-9999px] w-px h-px overflow-hidden" aria-hidden="true">
                <label for="contact-website"> Website </label>
                <input
                    id="contact-website"
                    v-model="form.website"
                    type="text"
                    name="website"
                    tabindex="-1"
                    autocomplete="off"
                />
            </div>

            <!-- Submit button -->
            <div class="flex justify-end">
                <BaseButton
                    type="submit"
                    variant="primary"
                    size="lg"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    {{
                        form.processing
                            ? t('about.contact.form.sending')
                            : t('about.contact.form.submit')
                    }}
                </BaseButton>
            </div>
        </form>
    </div>
</template>
