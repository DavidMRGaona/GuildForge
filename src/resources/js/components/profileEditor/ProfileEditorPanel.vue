<script setup lang="ts">
import { computed, nextTick, onMounted, ref, useId, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { X } from '@lucide/vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import PreferenceSwitch from '@/components/profile/PreferenceSwitch.vue';
import ProfileBannerField from './ProfileBannerField.vue';
import ProfileHandleField from './ProfileHandleField.vue';
import ProfileLayoutList from './ProfileLayoutList.vue';
import ProfileVisibilityField from './ProfileVisibilityField.vue';
import { useMediaQuery } from '@/composables/useMediaQuery';
import { useModalDialog } from '@/composables/useModalDialog';
import { codePointLength } from '@/utils/profilePreview';
import type { ProfileEditor, ProfileEditorForm } from '@/types/profileEditor';

/**
 * The owner's editor of their public profile (core#743): a panel on the right, the whole screen on a
 * phone, with the profile behind it as a preview of what is being changed (the page draws it from
 * the `preview` event). Everything but the banner is saved in one request, with an error under each
 * field and a notice once saved; the banner is uploaded or taken away on its own, at once.
 *
 * A modal dialog: Tab stays inside and Escape closes it, asking first when something would be lost.
 * The page behind only scrolls on a phone-sized screen when the panel covers it whole.
 */
const props = defineProps<{
    editor: ProfileEditor;
}>();

const emit = defineEmits<{
    close: [];
    preview: [form: ProfileEditorForm];
}>();

const FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]):not([tabindex="-1"]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/** Where each field's error is, in the order the panel shows them, to focus the first one */
const FIELD_ORDER = [
    'visibility',
    'handle',
    'bio',
    'show_membership',
    'sections',
    'stats',
] as const;

const { t } = useI18n();
const uid = useId();
const panel = ref<HTMLElement | null>(null);
const heading = ref<HTMLElement | null>(null);
const confirmDiscard = ref(false);
const isPhone = useMediaQuery('(max-width: 639px)');

const form = useForm<ProfileEditorForm>({
    handle: props.editor.handle,
    visibility: props.editor.visibility,
    bio: props.editor.bio,
    show_membership: props.editor.showMembership,
    sections: props.editor.sections.map(({ key, enabled }) => ({ key, enabled })),
    stats: props.editor.stats.map(({ key, enabled }) => ({ key, enabled })),
});

const { trapTab } = useModalDialog(() => isPhone.value, panel, FOCUSABLE);

const bioLength = computed(() => codePointLength(form.bio));
const bioTooLong = computed(() => bioLength.value > props.editor.limits.bio);
const hasErrors = computed(() => Object.keys(form.errors).length > 0);

/** The first error of a field or of any of its entries (`sections.1.key`) */
function errorOf(field: string): string | undefined {
    const errors = form.errors as Record<string, string | undefined>;

    return (
        errors[field] ??
        Object.entries(errors).find(([key]) => key.startsWith(`${field}.`))?.[1] ??
        undefined
    );
}

watch(
    () => form.data(),
    (data) => emit('preview', data),
    { deep: true, immediate: true }
);

function requestClose(): void {
    if (form.isDirty && !form.processing) {
        confirmDiscard.value = true;
        return;
    }

    emit('close');
}

function save(): void {
    if (bioTooLong.value) {
        form.setError(
            'bio',
            t('publicProfile.editor.bio.tooLong', { max: props.editor.limits.bio })
        );
        focusFirstError();
        return;
    }

    form.clearErrors();
    form.put(props.editor.urls.update, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onError: () => focusFirstError(),
    });
}

function focusFirstError(): void {
    void nextTick(() => {
        const field = FIELD_ORDER.find((name) => errorOf(name) !== undefined);
        const target = field
            ? panel.value?.querySelector<HTMLElement>(
                  `[data-field="${field}"] :is(input, textarea, button)`
              )
            : null;
        target?.focus();
    });
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && !confirmDiscard.value) {
        event.preventDefault();
        requestClose();
        return;
    }

    if (event.key === 'Tab') {
        trapTab(event);
    }
}

onMounted(() => {
    heading.value?.focus();
});
</script>

<template>
    <Teleport to="body">
        <div
            ref="panel"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="`${uid}-title`"
            :aria-describedby="`${uid}-intro`"
            class="fixed inset-0 z-[60] flex flex-col bg-surface shadow-2xl sm:inset-y-0 sm:left-auto sm:w-[28rem] sm:border-l sm:border-default lg:w-[32rem] dark:shadow-depth"
            data-testid="profile-editor"
            @keydown="onKeydown"
        >
            <header
                class="flex items-start justify-between gap-4 border-b border-default px-4 py-4 sm:px-6"
            >
                <div class="min-w-0">
                    <h2
                        :id="`${uid}-title`"
                        ref="heading"
                        tabindex="-1"
                        class="text-lg font-semibold text-base-primary focus:outline-none"
                    >
                        {{ t('publicProfile.editor.title') }}
                    </h2>
                    <p :id="`${uid}-intro`" class="mt-1 text-sm text-base-muted">
                        {{ t('publicProfile.editor.intro') }}
                    </p>
                </div>
                <button
                    type="button"
                    class="-m-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-md text-base-secondary transition-colors hover:bg-muted hover:text-base-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 motion-reduce:transition-none"
                    :aria-label="t('publicProfile.editor.close')"
                    data-testid="profile-editor-close"
                    @click="requestClose"
                >
                    <X class="h-5 w-5" aria-hidden="true" />
                </button>
            </header>

            <form
                :id="`${uid}-form`"
                class="flex-1 space-y-8 overflow-y-auto overscroll-contain px-4 py-6 sm:px-6"
                novalidate
                @submit.prevent="save"
            >
                <div data-field="visibility">
                    <ProfileVisibilityField
                        v-model="form.visibility"
                        :error="errorOf('visibility')"
                    />
                </div>

                <div data-field="handle">
                    <ProfileHandleField
                        v-model="form.handle"
                        :editor="editor"
                        :error="errorOf('handle')"
                    />
                </div>

                <div data-field="bio">
                    <label
                        :for="`${uid}-bio`"
                        class="block text-sm font-semibold text-base-primary"
                    >
                        {{ t('publicProfile.editor.bio.label') }}
                    </label>
                    <textarea
                        :id="`${uid}-bio`"
                        v-model="form.bio"
                        name="bio"
                        rows="5"
                        class="mt-2 block w-full resize-y rounded-md border bg-surface px-3 py-2 text-sm text-base-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        :class="
                            errorOf('bio') || bioTooLong
                                ? 'border-red-500 dark:border-red-400'
                                : 'border-default'
                        "
                        :aria-invalid="errorOf('bio') || bioTooLong ? true : undefined"
                        :aria-describedby="`${uid}-bio-help ${uid}-bio-counter${errorOf('bio') ? ` ${uid}-bio-error` : ''}`"
                        data-testid="bio-input"
                    />
                    <div class="mt-2 flex items-start justify-between gap-4 text-xs">
                        <p :id="`${uid}-bio-help`" class="text-base-muted">
                            {{ t('publicProfile.editor.bio.help') }}
                        </p>
                        <p
                            :id="`${uid}-bio-counter`"
                            class="shrink-0 tabular-nums"
                            :class="
                                bioTooLong
                                    ? 'font-medium text-red-600 dark:text-red-400'
                                    : 'text-base-muted'
                            "
                            data-testid="bio-counter"
                        >
                            {{
                                t('publicProfile.editor.bio.counter', {
                                    count: bioLength,
                                    max: editor.limits.bio,
                                })
                            }}
                        </p>
                    </div>
                    <p
                        v-if="errorOf('bio')"
                        :id="`${uid}-bio-error`"
                        class="mt-2 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ errorOf('bio') }}
                    </p>
                </div>

                <ProfileBannerField :editor="editor" />

                <div v-if="editor.canShowMembership" data-field="show_membership">
                    <div class="flex items-start gap-3">
                        <PreferenceSwitch
                            :checked="form.show_membership"
                            :labelled-by="`${uid}-membership`"
                            :described-by="`${uid}-membership-description`"
                            @toggle="form.show_membership = !form.show_membership"
                        />
                        <div>
                            <p
                                :id="`${uid}-membership`"
                                class="text-sm font-semibold text-base-primary"
                            >
                                {{ t('publicProfile.editor.membership.label') }}
                            </p>
                            <p
                                :id="`${uid}-membership-description`"
                                class="text-xs text-base-muted"
                            >
                                {{ t('publicProfile.editor.membership.description') }}
                            </p>
                        </div>
                    </div>
                    <p
                        v-if="errorOf('show_membership')"
                        class="mt-2 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ errorOf('show_membership') }}
                    </p>
                </div>

                <div data-field="sections">
                    <ProfileLayoutList
                        v-model="form.sections"
                        :legend="t('publicProfile.editor.sections.legend')"
                        :help="t('publicProfile.editor.sections.help')"
                        :items="editor.sections"
                        :error="errorOf('sections')"
                    />
                </div>

                <div data-field="stats">
                    <ProfileLayoutList
                        v-model="form.stats"
                        :legend="t('publicProfile.editor.stats.legend')"
                        :help="t('publicProfile.editor.stats.help')"
                        :items="editor.stats"
                        :error="errorOf('stats')"
                    />
                </div>
            </form>

            <footer class="border-t border-default px-4 py-3 sm:px-6">
                <p
                    v-if="hasErrors"
                    class="mb-3 text-sm font-medium text-red-600 dark:text-red-400"
                    role="alert"
                    data-testid="profile-editor-errors"
                >
                    {{ t('publicProfile.editor.errorsSummary') }}
                </p>
                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-md px-4 py-2 text-sm font-medium text-base-primary transition-colors hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 motion-reduce:transition-none"
                        @click="requestClose"
                    >
                        {{ t('publicProfile.editor.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :form="`${uid}-form`"
                        class="rounded-md bg-(--button-primary-bg) px-4 py-2 text-sm font-medium text-(--button-primary-text) inset-ring inset-ring-(--button-primary-border) transition-colors hover:bg-(--button-primary-hover-bg) hover:text-(--button-primary-hover-text) focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-surface) disabled:opacity-50 motion-reduce:transition-none"
                        :disabled="form.processing"
                        data-testid="profile-editor-save"
                    >
                        {{
                            form.processing
                                ? t('publicProfile.editor.saving')
                                : t('publicProfile.editor.save')
                        }}
                    </button>
                </div>
            </footer>

            <ConfirmDialog
                v-model="confirmDiscard"
                :title="t('publicProfile.editor.discardTitle')"
                :message="t('publicProfile.editor.discardMessage')"
                :confirm-label="t('publicProfile.editor.discardConfirm')"
                :cancel-label="t('publicProfile.editor.keepEditing')"
                confirm-variant="danger"
                @confirm="emit('close')"
            />
        </div>
    </Teleport>
</template>
