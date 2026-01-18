import { ref, computed, onMounted, onUnmounted, type Ref, type ComputedRef } from 'vue';
import type { Photo } from '@/types/models';

interface UseLightboxReturn {
    isOpen: Ref<boolean>;
    currentIndex: Ref<number>;
    currentPhoto: ComputedRef<Photo | null>;
    open: (index: number) => void;
    close: () => void;
    next: () => void;
    prev: () => void;
    goTo: (index: number) => void;
}

export function useLightbox(photos: Ref<Photo[]>): UseLightboxReturn {
    const isOpen = ref(false);
    const currentIndex = ref(0);

    const currentPhoto = computed<Photo | null>(() => {
        if (photos.value.length === 0) {
            return null;
        }
        return photos.value[currentIndex.value] ?? null;
    });

    function open(index: number): void {
        currentIndex.value = index;
        isOpen.value = true;
        document.body.style.overflow = 'hidden';
    }

    function close(): void {
        isOpen.value = false;
        document.body.style.overflow = '';
    }

    function next(): void {
        if (photos.value.length === 0) return;
        currentIndex.value = (currentIndex.value + 1) % photos.value.length;
    }

    function prev(): void {
        if (photos.value.length === 0) return;
        currentIndex.value = (currentIndex.value - 1 + photos.value.length) % photos.value.length;
    }

    function goTo(index: number): void {
        if (index >= 0 && index < photos.value.length) {
            currentIndex.value = index;
        }
    }

    function handleKeydown(e: globalThis.KeyboardEvent): void {
        if (!isOpen.value) return;

        switch (e.key) {
            case 'ArrowLeft':
                prev();
                break;
            case 'ArrowRight':
                next();
                break;
            case 'Escape':
                close();
                break;
        }
    }

    onMounted(() => {
        window.addEventListener('keydown', handleKeydown);
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeydown);
        document.body.style.overflow = '';
    });

    return {
        isOpen,
        currentIndex,
        currentPhoto,
        open,
        close,
        next,
        prev,
        goTo,
    };
}
