<script setup lang="ts">
import { computed, watch, ref, nextTick, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Photo } from '@/types/models';
import { buildLightboxImageUrl } from '@/utils/cloudinary';

interface Props {
    photos: Photo[];
    currentIndex: number;
    isOpen: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    next: [];
    prev: [];
}>();

const { t } = useI18n();

const lightboxRef = ref<HTMLElement | null>(null);
const closeButtonRef = ref<HTMLButtonElement | null>(null);

const currentPhoto = computed<Photo | null>(() => {
    if (props.photos.length === 0) {
        return null;
    }
    return props.photos[props.currentIndex] ?? null;
});

const currentPhotoUrl = computed<string | null>(() => {
    if (!currentPhoto.value) return null;
    return buildLightboxImageUrl(currentPhoto.value.imagePublicId);
});

const hasMultiplePhotos = computed(() => props.photos.length > 1);

const photoCounterText = computed(() => {
    return t('a11y.photoCounter', {
        current: props.currentIndex + 1,
        total: props.photos.length,
    });
});

function handleKeydown(event: KeyboardEvent): void {
    if (!props.isOpen) return;

    switch (event.key) {
        case 'Escape':
            emit('close');
            break;
        case 'ArrowLeft':
            if (hasMultiplePhotos.value) emit('prev');
            break;
        case 'ArrowRight':
            if (hasMultiplePhotos.value) emit('next');
            break;
        case 'Tab':
            trapFocus(event);
            break;
    }
}

function trapFocus(event: KeyboardEvent): void {
    if (!lightboxRef.value) return;

    const focusableElements = lightboxRef.value.querySelectorAll<HTMLElement>(
        'button:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    if (event.shiftKey && document.activeElement === firstElement) {
        event.preventDefault();
        lastElement?.focus();
    } else if (!event.shiftKey && document.activeElement === lastElement) {
        event.preventDefault();
        firstElement?.focus();
    }
}

watch(
    () => props.isOpen,
    async (isOpen) => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
            await nextTick();
            closeButtonRef.value?.focus();
        } else {
            document.body.style.overflow = '';
        }
    }
);

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="props.isOpen && currentPhoto"
                ref="lightboxRef"
                role="dialog"
                aria-modal="true"
                :aria-label="t('gallery.title') + ': ' + (currentPhoto.caption ?? '')"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/90"
                @click.self="emit('close')"
            >
                <!-- Live region for screen readers -->
                <p aria-live="polite" class="sr-only">
                    {{ photoCounterText }}
                </p>

                <!-- Close button -->
                <button
                    ref="closeButtonRef"
                    type="button"
                    class="absolute right-4 top-4 rounded-full p-3 text-white transition-colors hover:bg-white/10 hover:text-stone-300 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-stone-900"
                    :aria-label="t('gallery.close')"
                    @click="emit('close')"
                >
                    <svg
                        class="h-8 w-8"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>

                <!-- Previous arrow -->
                <button
                    v-if="hasMultiplePhotos"
                    type="button"
                    class="absolute left-4 rounded-full p-3 text-white transition-colors hover:bg-white/10 hover:text-stone-300 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-stone-900"
                    :aria-label="t('gallery.previous')"
                    @click="emit('prev')"
                >
                    <svg
                        class="h-10 w-10"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                </button>

                <!-- Photo -->
                <img
                    v-if="currentPhotoUrl"
                    :src="currentPhotoUrl"
                    :alt="currentPhoto.caption ?? ''"
                    class="max-h-[90vh] max-w-[90vw] object-contain"
                />

                <!-- Next arrow -->
                <button
                    v-if="hasMultiplePhotos"
                    type="button"
                    class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full p-3 text-white transition-colors hover:bg-white/10 hover:text-stone-300 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-stone-900"
                    :aria-label="t('gallery.next')"
                    @click="emit('next')"
                >
                    <svg
                        class="h-10 w-10"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 5l7 7-7 7"
                        />
                    </svg>
                </button>

                <!-- Caption and counter -->
                <div class="absolute bottom-4 left-0 right-0 text-center text-white">
                    <p v-if="currentPhoto.caption" class="mb-2 text-lg">
                        {{ currentPhoto.caption }}
                    </p>
                    <p class="text-sm text-white/70">
                        {{ props.currentIndex + 1 }} {{ t('gallery.photoOf') }}
                        {{ props.photos.length }}
                    </p>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
