<script setup lang="ts">
import { BellRing, Smartphone } from '@lucide/vue';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import HelpLink from '@/components/knowledge/HelpLink.vue';
import type { PushDevice } from '@/types/mailPreferences';
import {
    removeDevice,
    sendTestNotice,
    subscribeThisDevice,
    syncThisDevice,
    thisDevicePushState,
    type TestNoticeOutcome,
    type ThisDevicePushState,
} from '@/utils/webPush';

/**
 * «Avisos en tus dispositivos» in the «Avisos» tab (core#804): turns push on for this browser (the
 * browser asks for permission only on that click), says why it cannot when it cannot, lists the
 * account's devices with «Quitar», and sends a test notice to this one. Which notices are pushed is
 * chosen per category below; these switches apply to every device listed here.
 */
interface Props {
    devices: PushDevice[];
    /** VAPID public key of the site (shared prop `webPush`). */
    publicKey: string;
    userId: string;
    /** The account must verify its email first: the endpoints are closed to it. */
    blocked?: boolean;
    /** How many suggested categories are not pushed now, following the current choices. */
    suggestedOff?: number;
}

const props = withDefaults(defineProps<Props>(), { blocked: false, suggestedOff: 0 });

const emit = defineEmits<{ 'enable-suggested': [] }>();

const BROWSERS = ['chrome', 'edge', 'firefox', 'safari', 'opera', 'samsung'];
const PLATFORMS = ['android', 'ios', 'macos', 'windows', 'linux', 'chromeos'];

const { t, locale } = useI18n();

/** Null while it is being worked out. */
const state = ref<ThisDevicePushState | null>(null);
const enabling = ref(false);
const enableFailed = ref(false);
/** Id of the device being removed, so only its button shows the spinner. */
const removing = ref<string | null>(null);
const removeFailed = ref(false);
const testing = ref(false);
const testOutcome = ref<TestNoticeOutcome | null>(null);

const hasCurrent = computed(() => props.devices.some((device) => device.current));
const canTest = computed(() => state.value === 'subscribed' && hasCurrent.value);

const dateFormat = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            timeZone: 'UTC',
        })
);

/** «1 de diciembre de 2026», from the display-zone day the instant carries. */
function dayOf(instant: string | null): string | null {
    // Already in the display zone: its first ten characters are that day, formatted in UTC so the
    // browser's own zone cannot move it
    const day = instant?.slice(0, 10);
    if (day === undefined || !/^\d{4}-\d{2}-\d{2}$/.test(day)) {
        return null;
    }

    return dateFormat.value.format(new Date(`${day}T00:00:00Z`));
}

function deviceName(device: PushDevice): string {
    const browser = BROWSERS.includes(device.browser) ? device.browser : 'other';
    const platform = PLATFORMS.includes(device.platform) ? device.platform : 'other';

    return t('auth.profile.mail.push.devices.name', {
        browser: t(`auth.profile.mail.push.browsers.${browser}`),
        platform: t(`auth.profile.mail.push.platforms.${platform}`),
    });
}

function reloadDevices(): void {
    router.reload({ only: ['mailPreferences'] });
}

onMounted(async () => {
    state.value = await thisDevicePushState();

    // Subscribed, but this session has not told the site yet (the page was built first): tell it
    // now, then show this device marked. A browser the site does not know for this account is
    // dropped by the sync, so the state is worked out again
    if (state.value === 'subscribed' && !hasCurrent.value) {
        await syncThisDevice(props.publicKey, props.userId);
        state.value = await thisDevicePushState();
        if (state.value === 'subscribed') {
            reloadDevices();
        }
    }
});

async function enable(): Promise<void> {
    if (enabling.value || props.blocked) {
        return;
    }

    enabling.value = true;
    enableFailed.value = false;
    testOutcome.value = null;

    try {
        if (await subscribeThisDevice(props.publicKey, props.userId)) {
            state.value = 'subscribed';
            reloadDevices();
            return;
        }

        // Refused in the browser's own prompt: say how to undo it; anything else failed
        state.value = await thisDevicePushState();
        enableFailed.value = state.value !== 'denied';
    } finally {
        enabling.value = false;
    }
}

async function remove(device: PushDevice): Promise<void> {
    if (removing.value !== null || props.blocked) {
        return;
    }

    removing.value = device.id;
    removeFailed.value = false;
    testOutcome.value = null;

    try {
        if (!(await removeDevice(device.id, props.userId, device.current))) {
            removeFailed.value = true;
        }
        if (device.current) {
            state.value = await thisDevicePushState();
        }
        reloadDevices();
    } finally {
        removing.value = null;
    }
}

async function test(): Promise<void> {
    if (testing.value || props.blocked) {
        return;
    }

    testing.value = true;
    testOutcome.value = null;

    try {
        testOutcome.value = await sendTestNotice(props.userId);
        if (testOutcome.value === 'gone') {
            state.value = await thisDevicePushState();
            reloadDevices();
        }
    } finally {
        testing.value = false;
    }
}

const testMessage = computed(() => {
    switch (testOutcome.value) {
        case 'sent':
            return t('auth.profile.mail.push.testSent');
        case 'gone':
            return t('auth.profile.mail.push.testGone');
        case 'unavailable':
            return t('auth.profile.mail.push.testUnavailable');
        default:
            return null;
    }
});
</script>

<template>
    <section
        aria-labelledby="push-devices-title"
        class="space-y-4 rounded-lg border border-default bg-surface p-4 sm:p-5"
        data-testid="push-devices"
    >
        <div class="flex items-center gap-2">
            <BellRing class="h-5 w-5 shrink-0 text-base-secondary" aria-hidden="true" />
            <h3 id="push-devices-title" class="text-base font-semibold text-base-primary">
                {{ t('auth.profile.mail.push.title') }}
            </h3>
            <HelpLink guide="core.site.push-notifications" />
        </div>
        <p class="text-sm text-base-secondary">{{ t('auth.profile.mail.push.description') }}</p>

        <!-- This browser: what it can do, and the button only where it can -->
        <div v-if="state !== null" class="space-y-2" data-testid="push-this-device">
            <p
                v-if="state === 'unsupported'"
                class="text-sm text-base-secondary"
                data-state="unsupported"
            >
                {{ t('auth.profile.mail.push.unsupported') }}
            </p>
            <p
                v-else-if="state === 'home-screen'"
                class="text-sm text-base-secondary"
                data-state="home-screen"
            >
                {{ t('auth.profile.mail.push.homeScreen') }}
            </p>
            <p v-else-if="state === 'denied'" class="text-sm text-warning" data-state="denied">
                {{ t('auth.profile.mail.push.denied') }}
            </p>
            <p
                v-else-if="state === 'subscribed'"
                class="text-sm text-success"
                data-state="subscribed"
            >
                {{ t('auth.profile.mail.push.subscribed') }}
            </p>
            <BaseButton
                v-else
                variant="secondary"
                :loading="enabling"
                :disabled="blocked"
                data-action="enable"
                @click="enable"
            >
                {{ t('auth.profile.mail.push.enable') }}
            </BaseButton>
            <p v-if="enableFailed" role="alert" class="text-sm text-error">
                {{ t('auth.profile.mail.push.enableError') }}
            </p>
        </div>

        <div class="space-y-2">
            <h4 class="text-sm font-semibold text-base-primary">
                {{ t('auth.profile.mail.push.devices.title') }}
            </h4>
            <p v-if="devices.length === 0" class="text-sm text-base-secondary">
                {{ t('auth.profile.mail.push.devices.empty') }}
            </p>
            <ul v-else class="divide-y divide-[var(--color-border)]">
                <li
                    v-for="device in devices"
                    :key="device.id"
                    class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3"
                    :data-device="device.id"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <Smartphone
                            class="mt-0.5 h-5 w-5 shrink-0 text-base-secondary"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-base-primary">
                                {{ deviceName(device) }}
                                <span
                                    v-if="device.current"
                                    class="ml-1 rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary dark:bg-primary-900/30"
                                >
                                    {{ t('auth.profile.mail.push.devices.current') }}
                                </span>
                            </p>
                            <p class="text-sm text-base-secondary">
                                <template v-if="dayOf(device.lastUsedAt)">
                                    {{
                                        t('auth.profile.mail.push.devices.lastUsed', {
                                            date: dayOf(device.lastUsedAt),
                                        })
                                    }}
                                </template>
                                <template v-else-if="dayOf(device.createdAt)">
                                    {{
                                        t('auth.profile.mail.push.devices.added', {
                                            date: dayOf(device.createdAt),
                                        })
                                    }}
                                </template>
                            </p>
                        </div>
                    </div>
                    <BaseButton
                        variant="ghost"
                        size="sm"
                        :loading="removing === device.id"
                        :disabled="blocked || (removing !== null && removing !== device.id)"
                        :aria-label="
                            t('auth.profile.mail.push.devices.removeLabel', {
                                device: deviceName(device),
                            })
                        "
                        data-action="remove"
                        @click="remove(device)"
                    >
                        {{ t('auth.profile.mail.push.devices.remove') }}
                    </BaseButton>
                </li>
            </ul>
            <p v-if="removeFailed" role="alert" class="text-sm text-error">
                {{ t('auth.profile.mail.push.devices.removeError') }}
            </p>
        </div>

        <div
            v-if="canTest || (devices.length > 0 && suggestedOff > 0)"
            class="flex flex-wrap items-center gap-3 border-t border-default pt-4"
        >
            <BaseButton
                v-if="canTest"
                variant="outline"
                size="sm"
                :loading="testing"
                :disabled="blocked"
                data-action="test"
                @click="test"
            >
                {{ t('auth.profile.mail.push.test') }}
            </BaseButton>
            <BaseButton
                v-if="devices.length > 0 && suggestedOff > 0"
                variant="outline"
                size="sm"
                :disabled="blocked"
                aria-describedby="push-suggested-hint"
                data-action="enable-suggested"
                @click="emit('enable-suggested')"
            >
                {{ t('auth.profile.mail.push.suggested') }}
            </BaseButton>
            <p
                v-if="devices.length > 0 && suggestedOff > 0"
                id="push-suggested-hint"
                class="basis-full text-sm text-base-secondary"
            >
                {{ t('auth.profile.mail.push.suggestedHint') }}
            </p>
        </div>
        <p v-if="testMessage" role="status" class="text-sm text-base-secondary">
            {{ testMessage }}
        </p>
    </section>
</template>
