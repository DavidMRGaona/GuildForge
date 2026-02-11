<script setup lang="ts">
import { computed, toRef } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import type { HeroSlide } from '@/types/models';
import { useHeroSlider } from '@/composables/useHeroSlider';
import { buildFullScreenHeroImageUrl } from '@/utils/cloudinary';

interface Props {
    slides: HeroSlide[];
    autoplayInterval?: number;
    showArrows?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    autoplayInterval: 5000,
    showArrows: true,
});

const { t } = useI18n();

const slidesRef = toRef(props, 'slides');
const { currentIndex, currentSlide, hasMultipleSlides, next, prev, goTo, pause, resume } =
    useHeroSlider(slidesRef, props.autoplayInterval);

const currentImageUrl = computed<string | null>(() => {
    if (!currentSlide.value) return null;
    return buildFullScreenHeroImageUrl(currentSlide.value.imagePublicId);
});

const hasSlides = computed(() => props.slides.length > 0);
</script>

<template>
    <section
        role="region"
        :aria-label="t('home.heroSlider')"
        class="relative h-[80vh] min-h-[500px] overflow-hidden"
        @mouseenter="pause"
        @mouseleave="resume"
    >
        <!-- Fallback gradient when no slides -->
        <template v-if="!hasSlides">
            <div class="absolute inset-0 bg-gradient-to-r from-primary-500 to-primary-600">
                <div class="flex h-full items-center justify-center">
                    <div class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                        <h1
                            class="font-display text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl"
                        >
                            {{ t('home.welcome') }}
                        </h1>
                        <p class="mt-4 text-xl font-light text-primary-50 sm:text-2xl">
                            {{ t('home.subtitle') }}
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <!-- Slides with background images -->
        <template v-else>
            <!-- Background image with fade transition -->
            <Transition
                enter-active-class="transition-opacity duration-700"
                leave-active-class="transition-opacity duration-700 absolute inset-0"
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
                mode="out-in"
            >
                <div v-if="currentSlide" :key="currentSlide.id" class="absolute inset-0">
                    <!-- Background image -->
                    <div
                        v-if="currentImageUrl"
                        class="absolute inset-0 bg-cover bg-center"
                        :style="{ backgroundImage: `url(${currentImageUrl})` }"
                    />
                    <!-- Fallback gradient if no image -->
                    <div
                        v-else
                        class="absolute inset-0 bg-gradient-to-r from-primary-500 to-primary-600"
                    />
                    <!-- Dark overlay -->
                    <div
                        class="absolute inset-0 bg-gradient-to-b from-black/50 via-black/40 to-black/70"
                    />
                </div>
            </Transition>

            <!-- Content with slide transition -->
            <div class="relative flex h-full items-center justify-center">
                <Transition
                    enter-active-class="transition-all duration-500"
                    leave-active-class="transition-all duration-500 absolute inset-0"
                    enter-from-class="opacity-0 translate-y-4"
                    leave-to-class="opacity-0 -translate-y-4"
                    mode="out-in"
                >
                    <div
                        v-if="currentSlide"
                        :key="'content-' + currentSlide.id"
                        class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8"
                    >
                        <h1
                            class="font-display text-4xl font-bold tracking-tight text-white [text-shadow:0_2px_10px_rgba(0,0,0,0.5)] sm:text-5xl lg:text-6xl"
                        >
                            {{ currentSlide.title }}
                        </h1>
                        <p
                            v-if="currentSlide.subtitle"
                            class="mt-4 text-xl font-light text-neutral-200 sm:text-2xl"
                        >
                            {{ currentSlide.subtitle }}
                        </p>
                        <div v-if="currentSlide.buttonText && currentSlide.buttonUrl" class="mt-8">
                            <Link
                                :href="currentSlide.buttonUrl"
                                class="inline-flex items-center rounded-lg bg-primary-500 px-6 py-3 text-lg font-semibold text-white transition-colors hover:bg-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 focus:ring-offset-neutral-900"
                            >
                                {{ currentSlide.buttonText }}
                            </Link>
                        </div>
                    </div>
                </Transition>
            </div>

            <!-- Navigation arrows -->
            <template v-if="hasMultipleSlides && showArrows">
                <!-- Previous arrow -->
                <button
                    type="button"
                    class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-black/30 p-3 text-white backdrop-blur-sm transition-colors hover:bg-black/50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-neutral-900"
                    :aria-label="t('common.previous')"
                    @click="prev"
                >
                    <ChevronLeft class="h-6 w-6" aria-hidden="true" />
                </button>

                <!-- Next arrow -->
                <button
                    type="button"
                    class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-black/30 p-3 text-white backdrop-blur-sm transition-colors hover:bg-black/50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-neutral-900"
                    :aria-label="t('common.next')"
                    @click="next"
                >
                    <ChevronRight class="h-6 w-6" aria-hidden="true" />
                </button>
            </template>

            <!-- Navigation dots -->
            <div
                v-if="hasMultipleSlides"
                class="absolute bottom-8 left-1/2 flex -translate-x-1/2 gap-2"
                role="tablist"
                :aria-label="t('common.slideNavigation')"
            >
                <button
                    v-for="(slide, index) in slides"
                    :key="slide.id"
                    type="button"
                    role="tab"
                    :aria-selected="index === currentIndex"
                    :aria-label="t('common.goToSlide', { number: index + 1 })"
                    class="h-3 w-3 rounded-full transition-all focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-neutral-900"
                    :class="[
                        index === currentIndex ? 'bg-white w-6' : 'bg-white/50 hover:bg-white/70',
                    ]"
                    @click="goTo(index)"
                />
            </div>
        </template>
    </section>
</template>
