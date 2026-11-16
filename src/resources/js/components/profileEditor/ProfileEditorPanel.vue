<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { X } from '@lucide/vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import PreferenceSwitch from '@/components/profile/PreferenceSwitch.vue';
import ProfileBannerField from './ProfileBannerField.vue';
import ProfileHandleField from './ProfileHandleField.vue';
import ProfileLayoutList from './ProfileLayoutList.vue';
import ProfileVisibilityField from './ProfileVisibilityField.vue';
import { useMediaQuery } from '@/composables/useMediaQuery';
import { useDialogHistoryEntry } from '@/composables/useDialogHistoryEntry';
import { useModalDialog } from '@/composables/useModalDialog';
import { codePointLength } from '@/utils/profilePreview';
import type { BannerChange, ProfileEditor, ProfileEditorForm } from '@/types/profileEditor';

/**
 * The owner's editor of their public profile (core#743): a panel on the right, the whole screen on a
 * phone, with the profile behind it as a preview of what is being changed (the page draws it from
 * the `preview` event). Everything but the banner is saved in one request, with an error under each
 * field and a notice once saved; the banner is uploaded or taken away on its own, at once.
 *
 * A modal dialog: the whole page behind (`#app`, header and footer included) is inert and a veil over it
 * takes the clicks, Tab stays inside, and Escape, the veil, Back or a visit elsewhere ask first when
 * changes would be lost, a banner change still on its way included (so does leaving the site). Back
 * closes it: it opens on an entry of its own in the history (`useDialogHistoryEntry`). Leaving that
 * entry, once accepted, cancels the banner's change on its way, whose answer would otherwise take the
 * page's entry (core#759); saving waits for it. The page behind still scrolls, except on a phone-sized
 * screen, where the panel covers it whole.
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

/** The page root Inertia mounts in: everything but this panel and its dialogs */
const APP_ROOT_ID = 'app';

let appRoot: HTMLElement | null = null;
let appRootWasInert = false;

/** The person accepted to leave and lose the changes: until the draft is reset, nothing asks again */
const leaving = ref(false);

/** A banner change on its way: leaving cancels it, so it counts as unsaved, and saving waits for it */
const bannerPending = ref<BannerChange | null>(null);

/** Whether something would be lost: not while the form is being saved, nor once leaving was accepted */
const unsaved = computed(
    () => (form.isDirty || bannerPending.value !== null) && !form.processing && !leaving.value
);

watch(
    () => form.isDirty || bannerPending.value !== null,
    (changed) => {
        if (!changed) {
            leaving.value = false;
        }
    }
);

/** Nothing is left to lose (the banner's change ended meanwhile): the question has nothing to ask */
watch(unsaved, (value) => {
    if (!value) {
        confirmDiscard.value = false;
    }
});

function reportBannerPending(change: BannerChange | null): void {
    bannerPending.value = change;
}

/** What a banner change on its way adds to the question before leaving */
const pendingNotice = computed(() => {
    if (bannerPending.value === 'upload') {
        return t('publicProfile.editor.banner.pendingUpload');
    }

    return bannerPending.value === 'remove' ? t('publicProfile.editor.banner.pendingRemove') : '';
});

/** The dialog's text: what would be lost */
const discardMessage = computed(() =>
    form.isDirty
        ? [t('publicProfile.editor.discardMessage'), pendingNotice.value].join(' ').trim()
        : pendingNotice.value
);

/** The editor's own requests (saving, the banner) are never a visit elsewhere */
const ownPaths = computed(
    () =>
        new Set(
            [props.editor.urls.update, props.editor.urls.banner].map(
                (url) => new URL(url, window.location.origin).pathname
            )
        )
);

/** Leaving with changes was accepted: they are gone, so nothing asks again */
function discardChanges(): void {
    // Also when the visit ends in a full reload (a new deploy answers Inertia with a 409 and the browser
    // loads the page itself): `beforeunload` must not ask either
    leaving.value = true;
    form.reset();
}

/** Leaving with changes asks first. A native dialog: it is decided at once, before anything leaves */
function mayLeave(): boolean {
    if (!unsaved.value) {
        return true;
    }

    const question = form.isDirty
        ? t('publicProfile.editor.leaveConfirm')
        : t('publicProfile.editor.leaveAnyway');

    if (!window.confirm([pendingNotice.value, question].join(' ').trim())) {
        return false;
    }

    discardChanges();

    return true;
}

/**
 * Back (or Forward) from the panel: to the page under it closes the panel, to another page leaves with
 * it; with changes, asking first either way.
 */
const historyEntry = useDialogHistoryEntry((toPage) => {
    if (!mayLeave()) {
        return false;
    }

    if (toPage) {
        emit('close');
    }

    return true;
});

/** A visit elsewhere while changes would be lost asks first; refusing cancels it */
const stopGuardingVisits = router.on('before', (event) => {
    const visit = event.detail.visit;

    if (ownPaths.value.has(visit.url.pathname)) {
        historyEntry.keep(visit);
        return;
    }

    if (!mayLeave()) {
        event.preventDefault();
        return;
    }

    historyEntry.handOver(visit);
});

/** Closing or reloading the tab with changes: the browser's own question */
function onBeforeUnload(event: BeforeUnloadEvent): void {
    if (unsaved.value) {
        event.preventDefault();
    }
}

function requestClose(): void {
    if (unsaved.value) {
        confirmDiscard.value = true;
        return;
    }

    emit('close');
}

/** Between leaving the panel's entry and sending: a second press must not save twice */
const sending = ref(false);

async function save(): Promise<void> {
    if (sending.value || form.processing || bannerPending.value !== null) {
        return;
    }

    if (bioTooLong.value) {
        form.setError(
            'bio',
            t('publicProfile.editor.bio.tooLong', { max: props.editor.limits.bio })
        );
        focusFirstError();
        return;
    }

    form.clearErrors();
    sending.value = true;
    // The saved page takes the page's own entry, so the panel's is left first: no extra step back
    await historyEntry.release();
    sending.value = false;

    let saved = false;
    form.put(props.editor.urls.update, {
        preserveScroll: true,
        onSuccess: () => {
            saved = true;
            emit('close');
        },
        onError: () => focusFirstError(),
        onFinish: () => {
            if (!saved) {
                historyEntry.restore();
            }
        },
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
    appRoot = document.getElementById(APP_ROOT_ID);
    appRootWasInert = appRoot?.hasAttribute('inert') ?? false;
    appRoot?.setAttribute('inert', '');
    window.addEventListener('beforeunload', onBeforeUnload);
    heading.value?.focus();
});

onBeforeUnmount(() => {
    if (!appRootWasInert) {
        appRoot?.removeAttribute('inert');
    }
    window.removeEventListener('beforeunload', onBeforeUnload);
    stopGuardingVisits();
});
</script>

<template>
    <Teleport to="body">
        <!-- Over the inert page: it takes the clicks meant for it, and still lets the preview show -->
        <div
            class="fixed inset-0 z-[60] bg-neutral-950/30"
            aria-hidden="true"
            data-testid="profile-editor-backdrop"
            @click="requestClose"
        />
        <div
            ref="panel"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="`${uid}-title`"
            :aria-describedby="`${uid}-intro`"
            class="fixed inset-0 z-[61] flex flex-col bg-surface shadow-2xl sm:inset-y-0 sm:left-auto sm:w-[28rem] sm:border-l sm:border-default lg:w-[32rem] dark:shadow-depth"
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

                <ProfileBannerField
                    :editor="editor"
                    :cancel-on-close="historyEntry.cancelOnLeave"
                    :report-pending="reportBannerPending"
                    :locked="form.processing || sending"
                />

                <div v-if="editor.canShowMembership" data-field="show_membership">
                    <div class="flex items-start gap-3">
                        <PreferenceSwitch
                            :checked="form.show_membership"
                            :labelled-by="`${uid}-membership`"
                            :described-by="
                                errorOf('show_membership')
                                    ? `${uid}-membership-description ${uid}-membership-error`
                                    : `${uid}-membership-description`
                            "
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
                        :id="`${uid}-membership-error`"
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
                        :disabled="form.processing || bannerPending !== null"
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
                :message="discardMessage"
                :confirm-label="t('publicProfile.editor.discardConfirm')"
                :cancel-label="t('publicProfile.editor.keepEditing')"
                confirm-variant="danger"
                @confirm="emit('close')"
            />
        </div>
    </Teleport>
</template>
