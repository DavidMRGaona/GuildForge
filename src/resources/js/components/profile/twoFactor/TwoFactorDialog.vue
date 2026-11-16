<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { computed, nextTick, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import TwoFactorRecoveryCodes from '@/components/profile/twoFactor/TwoFactorRecoveryCodes.vue';
import { useNotifications } from '@/composables/useNotifications';
import { useTwoFactorApi } from '@/composables/useTwoFactorApi';
import type {
    TwoFactorMode,
    TwoFactorRecoveryCodesResponse,
    TwoFactorSetup,
} from '@/types/twoFactor';

interface Props {
    open: boolean;
    mode: TwoFactorMode;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    /** The change went through; the page reloads what it shows. */
    done: [mode: TwoFactorMode];
}>();

type Step = 'password' | 'scan' | 'code' | 'verify' | 'codes';

const ENDPOINTS = {
    enable: { begin: '/perfil/doble-factor', confirm: '/perfil/doble-factor/confirmar' },
    changeApp: {
        begin: '/perfil/doble-factor/app',
        confirm: '/perfil/doble-factor/app/confirmar',
    },
    recoveryCodes: '/perfil/doble-factor/codigos',
    disable: '/perfil/doble-factor',
} as const;

const { t } = useI18n();
const notifications = useNotifications();
const { send } = useTwoFactorApi();
const titleId = useId();
const descriptionId = useId();

const dialogRef = ref<HTMLDialogElement | null>(null);
const password = ref('');
const code = ref('');
const errors = ref<Record<string, string>>({});
const failure = ref<string | null>(null);
const processing = ref(false);
const setup = ref<TwoFactorSetup | null>(null);
const recoveryCodes = ref<string[]>([]);
const saved = ref(false);
// The server already changed something (the codes on screen are real), and `done` was already sent
const changed = ref(false);
const doneEmitted = ref(false);
const keyCopied = ref(false);
const step = ref<Step>(firstStep());

// «Activar» goes password → scan → code → the new recovery codes. Changing the app, making new
// codes and turning it off ask for the password and a code of the factor in use at once
// (changing the app goes on to scan → code of the new app, and keeps the recovery codes)
const flow = computed<Step[]>(() => {
    switch (props.mode) {
        case 'enable':
            return ['password', 'scan', 'code', 'codes'];
        case 'changeApp':
            return ['verify', 'scan', 'code'];
        case 'recoveryCodes':
            return ['verify', 'codes'];
        default:
            return ['verify'];
    }
});

function firstStep(): Step {
    return props.mode === 'enable' ? 'password' : 'verify';
}

const stepNumber = computed(() => flow.value.indexOf(step.value) + 1);
// A dialog that shows recovery codes cannot be left before saying they were kept
const dismissable = computed(() => step.value !== 'codes' || saved.value);
const showsSteps = computed(() => flow.value.length > 1);

const title = computed(() => {
    if (step.value === 'codes') {
        return t('auth.profile.twoFactor.dialog.codesTitle');
    }

    return t(`auth.profile.twoFactor.dialog.title.${props.mode}`);
});

const description = computed(() => {
    switch (step.value) {
        case 'password':
            return t('auth.profile.twoFactor.dialog.passwordIntro');
        case 'scan':
            return t(`auth.profile.twoFactor.dialog.scanIntro.${props.mode}`);
        case 'code':
            return t('auth.profile.twoFactor.dialog.codeIntro');
        case 'verify':
            return t(`auth.profile.twoFactor.dialog.verifyIntro.${props.mode}`);
        default:
            return t('auth.profile.twoFactor.dialog.codesIntro');
    }
});

const submitLabel = computed(() => {
    const key = {
        password: 'password',
        scan: 'scan',
        code: props.mode === 'changeApp' ? 'codeChangeApp' : 'codeEnable',
        verify: {
            disable: 'disable',
            recoveryCodes: 'recoveryCodes',
            changeApp: 'password',
            enable: 'password',
        }[props.mode],
        codes: 'codes',
    }[step.value];

    return t(`auth.profile.twoFactor.dialog.submit.${key}`);
});

const qrSource = computed(() =>
    setup.value === null
        ? ''
        : `data:image/svg+xml;charset=utf-8,${encodeURIComponent(setup.value.qrCodeSvg)}`
);

function reset(): void {
    password.value = '';
    code.value = '';
    errors.value = {};
    failure.value = null;
    processing.value = false;
    setup.value = null;
    recoveryCodes.value = [];
    saved.value = false;
    changed.value = false;
    doneEmitted.value = false;
    keyCopied.value = false;
    step.value = firstStep();
}

function focusFirstField(): void {
    void nextTick(() => {
        dialogRef.value?.querySelector<HTMLElement>('[data-autofocus]')?.focus();
    });
}

function close(): void {
    if (!dismissable.value || processing.value) {
        return;
    }

    emit('update:open', false);
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            reset();
            dialogRef.value?.showModal();
            focusFirstField();
        } else {
            // Nothing sensitive stays in memory once it is closed
            dialogRef.value?.close();
            reset();
        }
    }
);

function goTo(next: Step): void {
    step.value = next;
    errors.value = {};
    failure.value = null;
    focusFirstField();
}

function fail(result: { errors: Record<string, string>; message: string | null }): void {
    errors.value = result.errors;
    failure.value = result.message;
    focusFirstField();
}

function finish(): void {
    doneEmitted.value = true;
    emit('done', props.mode);
    emit('update:open', false);
}

/**
 * The browser can close a modal dialog without going through `cancel`: Chromium's close watchers
 * close it on a second Escape, and so does Android's back gesture. Without this, `open` would stay
 * true, the buttons that open it would do nothing and the recovery codes would stay in memory.
 */
function onClose(): void {
    if (!props.open) {
        // Closed by this component (the parent set `open` to false): nothing else to do
        return;
    }

    if (step.value === 'codes' && !saved.value) {
        // The codes are shown only once: the dialog does not go away before they are kept
        dialogRef.value?.showModal();
        focusFirstField();

        return;
    }

    const activated = changed.value && !doneEmitted.value;
    doneEmitted.value = doneEmitted.value || activated;
    emit('update:open', false);
    reset();

    if (activated) {
        emit('done', props.mode);
    }
}

async function submitPassword(): Promise<void> {
    processing.value = true;
    const result = await send<TwoFactorSetup>('POST', ENDPOINTS.enable.begin, {
        current_password: password.value,
    });
    processing.value = false;
    // The password is not needed again: it does not stay in the field
    if (result.ok) {
        password.value = '';
        setup.value = result.data;
        goTo('scan');

        return;
    }

    fail(result);
}

async function submitCode(): Promise<void> {
    processing.value = true;
    const endpoints = props.mode === 'changeApp' ? ENDPOINTS.changeApp : ENDPOINTS.enable;
    const result = await send<TwoFactorRecoveryCodesResponse | undefined>(
        'POST',
        endpoints.confirm,
        {
            code: code.value,
        }
    );
    processing.value = false;

    if (!result.ok) {
        fail(result);

        return;
    }

    setup.value = null;
    code.value = '';
    changed.value = true;

    if (props.mode === 'enable' && result.data !== undefined) {
        recoveryCodes.value = result.data.recoveryCodes;
        goTo('codes');

        return;
    }

    finish();
}

async function submitVerify(): Promise<void> {
    processing.value = true;
    const body = { current_password: password.value, code: code.value };

    if (props.mode === 'changeApp') {
        const started = await send<TwoFactorSetup>('POST', ENDPOINTS.changeApp.begin, body);
        processing.value = false;

        if (!started.ok) {
            fail(started);

            return;
        }

        // Both were only to prove who is changing it: the new app's code is typed next
        password.value = '';
        code.value = '';
        setup.value = started.data;
        goTo('scan');

        return;
    }

    const result =
        props.mode === 'disable'
            ? await send<undefined>('DELETE', ENDPOINTS.disable, body)
            : await send<TwoFactorRecoveryCodesResponse>('POST', ENDPOINTS.recoveryCodes, body);
    processing.value = false;

    if (!result.ok) {
        fail(result);

        return;
    }

    password.value = '';
    code.value = '';
    changed.value = true;

    if (props.mode === 'recoveryCodes' && result.data !== undefined) {
        recoveryCodes.value = result.data.recoveryCodes;
        goTo('codes');

        return;
    }

    finish();
}

async function copyKey(): Promise<void> {
    try {
        await navigator.clipboard.writeText(setup.value?.secret ?? '');
        keyCopied.value = true;
    } catch {
        notifications.error(t('auth.profile.twoFactor.copyError'));
    }
}

function submit(): void {
    if (processing.value) {
        return;
    }

    if (step.value === 'password') {
        void submitPassword();
    } else if (step.value === 'scan') {
        goTo('code');
    } else if (step.value === 'code') {
        void submitCode();
    } else if (step.value === 'verify') {
        void submitVerify();
    } else if (saved.value) {
        finish();
    }
}

function onCancel(event: Event): void {
    // Escape: a dialog showing recovery codes does not close until they are marked as saved
    event.preventDefault();
    close();
}

function onBackdropClick(event: MouseEvent): void {
    if (event.target === dialogRef.value) {
        close();
    }
}

const inputClasses =
    'mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary focus:border-primary-500 focus:outline-none focus:ring-primary-500';
</script>

<template>
    <Teleport to="body">
        <dialog
            ref="dialogRef"
            :aria-labelledby="titleId"
            :aria-describedby="descriptionId"
            class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-lg border-0 bg-transparent p-0 backdrop:bg-neutral-900/60"
            data-testid="two-factor-dialog"
            @cancel="onCancel"
            @close="onClose"
            @click="onBackdropClick"
        >
            <form
                class="w-full space-y-4 rounded-lg bg-surface p-5 shadow-xl sm:p-6 dark:shadow-depth"
                @submit.prevent="submit"
            >
                <div>
                    <p v-if="showsSteps" class="text-xs font-medium text-base-muted">
                        {{
                            t('auth.profile.twoFactor.dialog.step', {
                                current: stepNumber,
                                total: flow.length,
                            })
                        }}
                    </p>
                    <h3
                        :id="titleId"
                        tabindex="-1"
                        :data-autofocus="step === 'scan' ? '' : undefined"
                        class="text-lg font-semibold text-base-primary focus:outline-none"
                    >
                        {{ title }}
                    </h3>
                    <p :id="descriptionId" class="mt-1 text-sm text-base-secondary">
                        {{ description }}
                    </p>
                </div>

                <!-- Password -->
                <div v-if="step === 'password' || step === 'verify'">
                    <label
                        for="two-factor-password"
                        class="block text-sm font-medium text-base-secondary"
                    >
                        {{ t('auth.profile.currentPassword') }}
                    </label>
                    <input
                        id="two-factor-password"
                        v-model="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        data-autofocus
                        :class="inputClasses"
                        :aria-invalid="errors.current_password ? 'true' : undefined"
                        :aria-describedby="
                            errors.current_password ? 'two-factor-password-error' : undefined
                        "
                    />
                    <p
                        v-if="errors.current_password"
                        id="two-factor-password-error"
                        class="mt-1 text-sm text-error"
                    >
                        {{ errors.current_password }}
                    </p>
                </div>

                <!-- QR and key -->
                <div v-if="step === 'scan' && setup" class="space-y-4">
                    <div class="flex justify-center">
                        <img
                            :src="qrSource"
                            :alt="t('auth.profile.twoFactor.dialog.qrAlt')"
                            class="h-48 w-48 rounded-md border border-default bg-white p-2"
                            width="192"
                            height="192"
                        />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-base-secondary">
                            {{ t('auth.profile.twoFactor.dialog.manualKey') }}
                        </p>
                        <div class="mt-1 flex items-stretch gap-2">
                            <code
                                class="min-w-0 flex-1 select-all break-all rounded-md border border-default bg-muted px-3 py-2 font-mono text-sm text-base-primary"
                                data-testid="two-factor-key"
                            >
                                {{ setup.groupedSecret }}
                            </code>
                            <BaseButton
                                type="button"
                                variant="secondary"
                                size="sm"
                                :aria-label="t('auth.profile.twoFactor.dialog.copyKey')"
                                @click="copyKey"
                            >
                                <component
                                    :is="keyCopied ? Check : Copy"
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />
                                <span class="sr-only">{{
                                    keyCopied
                                        ? t('auth.profile.twoFactor.copied')
                                        : t('auth.profile.twoFactor.dialog.copyKey')
                                }}</span>
                            </BaseButton>
                        </div>
                    </div>
                </div>

                <!-- Code from the app (or a recovery code) -->
                <div v-if="step === 'code' || step === 'verify'">
                    <label
                        for="two-factor-code"
                        class="block text-sm font-medium text-base-secondary"
                    >
                        {{
                            step === 'code'
                                ? t('auth.profile.twoFactor.dialog.codeLabel')
                                : t('auth.profile.twoFactor.dialog.anyCodeLabel')
                        }}
                    </label>
                    <input
                        id="two-factor-code"
                        v-model="code"
                        type="text"
                        required
                        autocomplete="one-time-code"
                        :inputmode="step === 'code' ? 'numeric' : undefined"
                        :pattern="step === 'code' ? '[0-9 ]*' : undefined"
                        :maxlength="step === 'code' ? 7 : 32"
                        :data-autofocus="step === 'code' ? '' : undefined"
                        :class="[inputClasses, 'font-mono tracking-widest']"
                        :aria-invalid="errors.code ? 'true' : undefined"
                        :aria-describedby="errors.code ? 'two-factor-code-error' : undefined"
                    />
                    <p
                        v-if="errors.code"
                        id="two-factor-code-error"
                        class="mt-1 text-sm text-error"
                    >
                        {{ errors.code }}
                    </p>
                </div>

                <!-- Recovery codes, shown only now -->
                <div v-if="step === 'codes'" class="space-y-4">
                    <TwoFactorRecoveryCodes :codes="recoveryCodes" />
                    <label class="flex items-start gap-2 text-sm text-base-primary">
                        <input
                            v-model="saved"
                            type="checkbox"
                            data-autofocus
                            class="mt-0.5 h-4 w-4 rounded border-default"
                        />
                        <span>{{ t('auth.profile.twoFactor.dialog.savedCodes') }}</span>
                    </label>
                </div>

                <p
                    v-if="failure"
                    role="alert"
                    class="rounded-md bg-error-light p-3 text-sm text-error"
                >
                    {{ failure }}
                </p>

                <div class="flex flex-wrap justify-end gap-2 pt-1">
                    <BaseButton
                        v-if="step !== 'codes'"
                        type="button"
                        variant="secondary"
                        size="sm"
                        :disabled="processing"
                        @click="close"
                    >
                        {{ t('common.cancel') }}
                    </BaseButton>
                    <BaseButton
                        type="submit"
                        :variant="mode === 'disable' ? 'danger' : 'primary'"
                        size="sm"
                        :disabled="processing || (step === 'codes' && !saved)"
                    >
                        {{ processing ? t('common.loading') : submitLabel }}
                    </BaseButton>
                </div>
            </form>
        </dialog>
    </Teleport>
</template>
