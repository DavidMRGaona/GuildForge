<script setup lang="ts">
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { ContactFormData } from '@/types/models';
import BaseButton from '@/components/ui/BaseButton.vue';

// Define flash interface for type safety
interface FlashMessages {
    success?: string;
    error?: string;
}

// Composables
const { t } = useI18n();
const page = usePage<{ flash: FlashMessages }>();

// Form state
const form = useForm<ContactFormData>({
    name: '',
    email: '',
    message: '',
    website: '', // Honeypot field
});

// Computed
const flashSuccess = computed(() => page.props.flash?.success);

// Methods
const submit = (): void => {
    form.post('/contacto', {
        onSuccess: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <div class="bg-white rounded-lg shadow-md p-6 h-full">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">
            {{ t('about.contact.form.title') }}
        </h2>

        <!-- Success message -->
        <div
            v-if="flashSuccess"
            class="mb-6 p-4 bg-green-50 border border-green-200 rounded-md"
            role="alert"
        >
            <p class="text-green-800 text-sm">
                {{ flashSuccess }}
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Name field -->
            <div>
                <label
                    for="contact-name"
                    class="block text-sm font-medium text-gray-700 mb-1"
                >
                    {{ t('about.contact.form.name') }}
                    <span class="text-red-500" aria-label="required">*</span>
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
                    class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 disabled:bg-gray-100 disabled:cursor-not-allowed transition-colors"
                    :class="{
                        'border-red-500 focus:ring-red-500 focus:border-red-500':
                            form.errors.name,
                    }"
                />
                <p
                    v-if="form.errors.name"
                    id="name-error"
                    class="mt-1 text-sm text-red-600"
                    role="alert"
                >
                    {{ form.errors.name }}
                </p>
            </div>

            <!-- Email field -->
            <div>
                <label
                    for="contact-email"
                    class="block text-sm font-medium text-gray-700 mb-1"
                >
                    {{ t('about.contact.email') }}
                    <span class="text-red-500" aria-label="required">*</span>
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
                    class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 disabled:bg-gray-100 disabled:cursor-not-allowed transition-colors"
                    :class="{
                        'border-red-500 focus:ring-red-500 focus:border-red-500':
                            form.errors.email,
                    }"
                />
                <p
                    v-if="form.errors.email"
                    id="email-error"
                    class="mt-1 text-sm text-red-600"
                    role="alert"
                >
                    {{ form.errors.email }}
                </p>
            </div>

            <!-- Message field -->
            <div>
                <label
                    for="contact-message"
                    class="block text-sm font-medium text-gray-700 mb-1"
                >
                    {{ t('about.contact.form.message') }}
                    <span class="text-red-500" aria-label="required">*</span>
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
                    class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 disabled:bg-gray-100 disabled:cursor-not-allowed transition-colors resize-y"
                    :class="{
                        'border-red-500 focus:ring-red-500 focus:border-red-500':
                            form.errors.message,
                    }"
                />
                <p
                    v-if="form.errors.message"
                    id="message-error"
                    class="mt-1 text-sm text-red-600"
                    role="alert"
                >
                    {{ form.errors.message }}
                </p>
            </div>

            <!-- Honeypot field (hidden from users, visible to bots) -->
            <div class="absolute left-[-9999px] w-px h-px overflow-hidden" aria-hidden="true">
                <label for="contact-website">
                    Website
                </label>
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
