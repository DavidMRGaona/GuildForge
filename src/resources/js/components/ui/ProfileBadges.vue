<script setup lang="ts">
/**
 * The featured badges of a profile (core#776), public API: the profile and its card draw them with
 * `md` (icon and label), a module next to an author's name with `sm` (icons only). Each badge is
 * its image when it has one, else its icon on its colour.
 *
 * Every badge is a focusable control (a link when it has one, else a button) named by its label;
 * its description is its `aria-describedby`, so a screen reader reads it once. The tooltip (full
 * label and description) shows on hover, on keyboard focus and when a button is tapped, and closes
 * with Escape, a tap elsewhere, or when the pointer or focus leaves; the pointer may move onto it.
 * A badge with a link opens it when tapped, so on a touch screen its description is not shown.
 *
 * The tooltip is drawn in `<body>` with fixed coordinates, so a host that clips its content (a card
 * with `overflow-hidden`) never cuts it; it goes above the badge when there is no room below and
 * stays inside the window.
 */
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { resolveProfileBadgeIcon } from '@/utils/icons';
import { isInertiaPage } from '@/utils/inertiaLinks';
import { profileBadgeColourClass } from '@/utils/profileBadges';
import type { ProfileBadge, ProfileBadgeSize } from '@/types/profileSections';

const props = withDefaults(
    defineProps<{
        badges: ProfileBadge[];
        size?: ProfileBadgeSize;
    }>(),
    { size: 'md' }
);

const { t } = useI18n();
const id = useId();

/** Space kept between a tooltip, its badge and the edges of the window */
const GAP = 8;

/** How long the tooltip waits before closing, so the pointer can move from the badge onto it */
const CLOSE_DELAY = 120;

const open = ref<number | null>(null);
const position = ref<{ top: number; left: number } | null>(null);
const tip = ref<HTMLElement | null>(null);
// By index: the refs a `v-for` collects keep no order
const controls = new Map<number, HTMLElement>();
let closing: ReturnType<typeof setTimeout> | undefined;

const small = computed(() => props.size === 'sm');
const openBadge = computed(() => (open.value === null ? undefined : props.badges[open.value]));

function keep(index: number, element: unknown): void {
    const node =
        element instanceof HTMLElement
            ? element
            : element !== null && typeof element === 'object' && '$el' in element
              ? (element as { $el: unknown }).$el
              : null;

    if (node instanceof HTMLElement) {
        controls.set(index, node);
    } else {
        controls.delete(index);
    }
}

function descriptionId(index: number): string {
    return `${id}-badge-${index}`;
}

function element(badge: ProfileBadge): typeof Link | 'a' | 'button' {
    if (badge.url === null) {
        return 'button';
    }

    return isInertiaPage(badge.url) ? Link : 'a';
}

function attributes(badge: ProfileBadge, index: number): Record<string, string | undefined> {
    return {
        href: badge.url ?? undefined,
        type: badge.url === null ? 'button' : undefined,
        'aria-describedby': badge.description !== '' ? descriptionId(index) : undefined,
    };
}

function show(index: number): void {
    clearTimeout(closing);

    if (props.badges[index] !== undefined) {
        open.value = index;
    }
}

function close(): void {
    clearTimeout(closing);
    open.value = null;
}

function hideLater(index: number): void {
    clearTimeout(closing);
    closing = setTimeout(() => {
        if (open.value === index) {
            open.value = null;
        }
    }, CLOSE_DELAY);
}

function hide(index: number): void {
    if (open.value === index) {
        close();
    }
}

/** Below the badge when it fits, else above; centred on it unless that would leave the window */
function place(): void {
    const index = open.value;
    const control = index === null ? undefined : controls.get(index);

    if (control === undefined || tip.value === null) {
        position.value = null;

        return;
    }

    const box = control.getBoundingClientRect();
    const width = tip.value.offsetWidth;
    const height = tip.value.offsetHeight;
    const below = box.bottom + GAP;
    const fitsBelow = below + height <= window.innerHeight - GAP;
    const fitsAbove = box.top - GAP - height >= GAP;
    const top = fitsBelow || !fitsAbove ? below : box.top - GAP - height;
    const centred = box.left + box.width / 2 - width / 2;
    const left = Math.max(GAP, Math.min(centred, window.innerWidth - GAP - width));

    position.value = { top: Math.round(top), left: Math.round(left) };
}

function closeOnOutsidePointer(event: PointerEvent): void {
    const index = open.value;
    const control = index === null ? undefined : controls.get(index);
    const target = event.target;

    if (
        target instanceof Node &&
        control?.contains(target) !== true &&
        tip.value?.contains(target) !== true
    ) {
        close();
    }
}

function closeOnEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape' && open.value !== null) {
        close();
    }
}

function listen(on: boolean): void {
    if (on) {
        document.addEventListener('pointerdown', closeOnOutsidePointer);
        document.addEventListener('keydown', closeOnEscape);
        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);

        return;
    }

    document.removeEventListener('pointerdown', closeOnOutsidePointer);
    document.removeEventListener('keydown', closeOnEscape);
    window.removeEventListener('resize', place);
    window.removeEventListener('scroll', place, true);
}

watch(open, async (index) => {
    listen(false);
    position.value = null;

    if (index === null) {
        return;
    }

    listen(true);
    await nextTick();
    place();
});

onBeforeUnmount(() => {
    clearTimeout(closing);
    listen(false);
});
</script>

<template>
    <ul
        v-if="badges.length > 0"
        class="flex flex-wrap items-center"
        :class="small ? 'gap-1' : 'gap-2'"
        :aria-label="t('profileBadges.list')"
        data-testid="profile-badges"
    >
        <li
            v-for="(badge, index) in badges"
            :key="badge.key"
            class="min-w-0"
            @mouseenter="show(index)"
            @mouseleave="hideLater(index)"
        >
            <component
                :is="element(badge)"
                :ref="(control: unknown) => keep(index, control)"
                v-bind="attributes(badge, index)"
                class="inline-flex max-w-full items-center rounded-full font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-page)"
                :class="[
                    profileBadgeColourClass(badge.colour),
                    small ? 'h-6 w-6 justify-center' : 'h-8 gap-1.5 pr-3 text-sm',
                    !small && (badge.imageUrl ? 'pl-1' : 'pl-2.5'),
                    badge.imageUrl && small ? 'overflow-hidden' : '',
                ]"
                :data-badge="badge.key"
                @focus="show(index)"
                @blur="hide(index)"
                @click="badge.url === null ? show(index) : undefined"
            >
                <img
                    v-if="badge.imageUrl"
                    :src="badge.imageUrl"
                    :alt="small ? badge.label : ''"
                    class="shrink-0 rounded-full object-cover"
                    :class="small ? 'h-full w-full' : 'h-6 w-6'"
                    loading="lazy"
                />
                <component
                    :is="resolveProfileBadgeIcon(badge.icon)"
                    v-else
                    class="shrink-0"
                    :class="small ? 'h-3.5 w-3.5' : 'h-4 w-4'"
                    aria-hidden="true"
                />
                <span v-if="small && !badge.imageUrl" class="sr-only">{{ badge.label }}</span>
                <!-- Cut on narrow screens; the tooltip always has the whole label -->
                <span v-else-if="!small" class="max-w-48 truncate">{{ badge.label }}</span>
            </component>

            <!-- What a screen reader reads as the description: never shown, read once -->
            <span v-if="badge.description !== ''" :id="descriptionId(index)" hidden>
                {{ badge.description }}
            </span>
        </li>

        <!-- The tooltip, for the eyes only: a screen reader already has the label and the description -->
        <Teleport to="body">
            <div
                v-if="openBadge"
                ref="tip"
                class="fixed z-[60] w-max max-w-[min(18rem,calc(100vw-1rem))] rounded-lg bg-tooltip px-3 py-2 text-left text-sm whitespace-normal text-tooltip shadow-lg"
                :class="{ invisible: position === null }"
                :style="
                    position ? { top: `${position.top}px`, left: `${position.left}px` } : undefined
                "
                aria-hidden="true"
                data-testid="profile-badge-tip"
                @mouseenter="open !== null && show(open)"
                @mouseleave="open !== null && hideLater(open)"
            >
                <span class="block font-semibold break-words">{{ openBadge.label }}</span>
                <span v-if="openBadge.description !== ''" class="mt-0.5 block">
                    {{ openBadge.description }}
                </span>
            </div>
        </Teleport>
    </ul>
</template>
