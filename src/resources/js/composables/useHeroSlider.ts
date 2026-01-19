import { ref, computed, onMounted, onUnmounted, type Ref, type ComputedRef } from 'vue';
import type { HeroSlide } from '@/types/models';

interface UseHeroSliderReturn {
    currentIndex: Ref<number>;
    currentSlide: ComputedRef<HeroSlide | null>;
    isPlaying: Ref<boolean>;
    hasMultipleSlides: ComputedRef<boolean>;
    next: () => void;
    prev: () => void;
    goTo: (index: number) => void;
    pause: () => void;
    resume: () => void;
}

export function useHeroSlider(
    slides: Ref<HeroSlide[]>,
    autoplayInterval: number = 5000
): UseHeroSliderReturn {
    const currentIndex = ref(0);
    const isPlaying = ref(true);
    let intervalId: number | null = null;

    const currentSlide = computed<HeroSlide | null>(() => {
        if (slides.value.length === 0) {
            return null;
        }
        return slides.value[currentIndex.value] ?? null;
    });

    const hasMultipleSlides = computed<boolean>(() => slides.value.length > 1);

    function next(): void {
        if (slides.value.length === 0) return;
        currentIndex.value = (currentIndex.value + 1) % slides.value.length;
    }

    function prev(): void {
        if (slides.value.length === 0) return;
        currentIndex.value = (currentIndex.value - 1 + slides.value.length) % slides.value.length;
    }

    function goTo(index: number): void {
        if (index >= 0 && index < slides.value.length) {
            currentIndex.value = index;
        }
    }

    function startAutoplay(): void {
        if (intervalId !== null) return;
        if (!hasMultipleSlides.value) return;

        intervalId = window.setInterval(() => {
            if (isPlaying.value) {
                next();
            }
        }, autoplayInterval);
    }

    function stopAutoplay(): void {
        if (intervalId !== null) {
            window.clearInterval(intervalId);
            intervalId = null;
        }
    }

    function pause(): void {
        isPlaying.value = false;
    }

    function resume(): void {
        isPlaying.value = true;
    }

    onMounted(() => {
        startAutoplay();
    });

    onUnmounted(() => {
        stopAutoplay();
    });

    return {
        currentIndex,
        currentSlide,
        isPlaying,
        hasMultipleSlides,
        next,
        prev,
        goTo,
        pause,
        resume,
    };
}
