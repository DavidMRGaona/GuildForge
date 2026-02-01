<script setup lang="ts">
import { ref, watch } from 'vue';
import BaseButton from './BaseButton.vue';

interface Props {
    modelValue: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    confirmVariant?: 'primary' | 'danger';
}

const props = withDefaults(defineProps<Props>(), {
    confirmLabel: 'Confirmar',
    cancelLabel: 'Cancelar',
    confirmVariant: 'primary',
});

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    confirm: [];
    cancel: [];
}>();

// eslint-disable-next-line no-undef
const dialogRef = ref<HTMLDialogElement | null>(null);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            dialogRef.value?.showModal();
        } else {
            dialogRef.value?.close();
        }
    }
);

function handleConfirm(): void {
    emit('confirm');
    emit('update:modelValue', false);
}

function handleCancel(): void {
    emit('cancel');
    emit('update:modelValue', false);
}

function handleBackdropClick(event: MouseEvent): void {
    if (event.target === dialogRef.value) {
        handleCancel();
    }
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        handleCancel();
    }
}
</script>

<template>
    <Teleport to="body">
        <dialog
            ref="dialogRef"
            class="fixed inset-0 m-auto max-w-md rounded-lg border-0 bg-transparent p-0 backdrop:bg-neutral-900/60"
            @click="handleBackdropClick"
            @keydown="handleKeydown"
        >
            <div
                class="w-full max-w-md rounded-lg bg-surface p-6 shadow-xl dark:shadow-neutral-900/50"
            >
                <h3 class="text-lg font-semibold text-base-primary">
                    {{ title }}
                </h3>
                <p class="mt-2 text-base-secondary">
                    {{ message }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <BaseButton
                        variant="secondary"
                        size="sm"
                        @click="handleCancel"
                    >
                        {{ cancelLabel }}
                    </BaseButton>
                    <BaseButton
                        :variant="confirmVariant"
                        size="sm"
                        @click="handleConfirm"
                    >
                        {{ confirmLabel }}
                    </BaseButton>
                </div>
            </div>
        </dialog>
    </Teleport>
</template>
