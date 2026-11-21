<script setup lang="ts">
/**
 * The featured badges of a profile (core#776), public API: the profile and its card draw them with
 * `md` (icon and label), a module next to an author's name with `sm` (icons only). Each badge is
 * its image when it has one, else its icon on its colour.
 *
 * Its label is its accessible name and its description is reachable without a mouse: every badge
 * that has something more to say is a focusable control whose description (`aria-describedby`) a
 * screen reader reads, and which shows it as a tooltip on hover, on focus and on a tap, closing with
 * Escape, a tap elsewhere, when the pointer leaves or when focus does. A badge with a link opens it.
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

/** Space kept between a tooltip and the edges of the window */
const EDGE = 8;

const open = ref<number | null>(null);
const offset = ref(0);
// By index: the refs a `v-for` collects keep no order
const items = new Map<number, HTMLElement>();
const tips = new Map<number, HTMLElement>();

function keep(elements: Map<number, HTMLElement>, index: number, element: unknown): void {
    if (element instanceof HTMLElement) {
        elements.set(index, element);
    } else {
        elements.delete(index);
    }
}

const small = computed(() => props.size === 'sm');

function descriptionId(index: number): string {
    return `${id}-badge-${index}`;
}

/** Whether there is anything to tell beyond what the badge shows: its description, or its hidden label */
function hasTip(badge: ProfileBadge): boolean {
    return badge.description !== '' || small.value;
}

/** A badge with nothing to open and nothing more to tell is plain text */
function element(badge: ProfileBadge): typeof Link | 'a' | 'button' | 'span' {
    if (badge.url !== null) {
        return isInertiaPage(badge.url) ? Link : 'a';
    }

    return hasTip(badge) ? 'button' : 'span';
}

function attributes(badge: ProfileBadge, index: number): Record<string, string | undefined> {
    const tag = element(badge);

    return {
        href: badge.url ?? undefined,
        type: tag === 'button' ? 'button' : undefined,
        'aria-describedby': badge.description !== '' ? descriptionId(index) : undefined,
    };
}

function show(index: number): void {
    const badge = props.badges[index];

    if (badge === undefined || !hasTip(badge)) {
        return;
    }

    open.value = index;
}

function hide(index: number): void {
    if (open.value === index) {
        open.value = null;
    }
}

/** Keeps an open tooltip inside the window: centred on its badge unless that would cut it */
function place(): void {
    offset.value = 0;
    const index = open.value;
    const item = index === null ? undefined : items.get(index);
    const tip = index === null ? undefined : tips.get(index);

    if (item === undefined || tip === undefined) {
        return;
    }

    const box = item.getBoundingClientRect();
    const width = tip.offsetWidth;
    const wanted = box.left + box.width / 2 - width / 2;
    const fitting = Math.min(Math.max(wanted, EDGE), window.innerWidth - EDGE - width);

    offset.value = Math.round(Math.max(fitting, EDGE) - box.left);
}

function closeOnOutsidePointer(event: PointerEvent): void {
    const index = open.value;
    const item = index === null ? undefined : items.get(index);

    if (item !== undefined && event.target instanceof Node && !item.contains(event.target)) {
        open.value = null;
    }
}

function closeOnEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape' && open.value !== null) {
        open.value = null;
    }
}

watch(open, async (index) => {
    document.removeEventListener('pointerdown', closeOnOutsidePointer);
    document.removeEventListener('keydown', closeOnEscape);

    if (index === null) {
        return;
    }

    document.addEventListener('pointerdown', closeOnOutsidePointer);
    document.addEventListener('keydown', closeOnEscape);
    await nextTick();
    place();
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', closeOnOutsidePointer);
    document.removeEventListener('keydown', closeOnEscape);
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
            :ref="(element) => keep(items, index, element)"
            class="relative"
            @mouseenter="show(index)"
            @mouseleave="hide(index)"
        >
            <component
                :is="element(badge)"
                v-bind="attributes(badge, index)"
                class="inline-flex items-center rounded-full font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-page)"
                :class="[
                    profileBadgeColourClass(badge.colour),
                    small ? 'h-6 w-6 justify-center' : 'h-8 max-w-full gap-1.5 pr-3 text-sm',
                    !small && (badge.imageUrl ? 'pl-1' : 'pl-2.5'),
                    badge.imageUrl && small ? 'overflow-hidden' : '',
                ]"
                :data-badge="badge.key"
                @focus="show(index)"
                @blur="hide(index)"
                @click="element(badge) === 'button' ? show(index) : undefined"
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
                <span v-else-if="!small" class="truncate">{{ badge.label }}</span>
            </component>

            <!-- Always in the page, so a screen reader reads it; shown as a tooltip while open. The
                 padding, not a margin, keeps the pointer inside while it moves onto the tooltip -->
            <span
                v-if="hasTip(badge)"
                :id="descriptionId(index)"
                :ref="(element) => keep(tips, index, element)"
                role="tooltip"
                :class="
                    open === index
                        ? 'absolute top-full z-30 block w-max max-w-[min(16rem,calc(100vw-1rem))] pt-2 text-left text-sm font-normal whitespace-normal'
                        : 'sr-only'
                "
                :style="open === index ? { left: `${offset}px` } : undefined"
                data-testid="profile-badge-tip"
            >
                <span
                    class="block"
                    :class="{
                        'rounded-lg bg-tooltip px-3 py-2 text-tooltip shadow-lg': open === index,
                    }"
                >
                    <span v-if="small" class="block font-semibold" aria-hidden="true">
                        {{ badge.label }}
                    </span>
                    <span v-if="badge.description !== ''" class="block">{{
                        badge.description
                    }}</span>
                </span>
            </span>
        </li>
    </ul>
</template>
