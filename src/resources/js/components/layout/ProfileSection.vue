<script setup lang="ts">
import { computed, onErrorCaptured, ref, useId, watch } from 'vue';
import type { ResolvedProfileSection } from '@/types/profileSections';
import { resolveProfileSectionComponent } from '@/utils/profileSectionComponents';

/**
 * Draws one section of a public profile resolved on the server (core#742): a card with the
 * section's title and the owner's component inside. Like SiteBlock, a section that fails to load or
 * render is left out, title included, and never breaks the profile around it.
 *
 * The component only draws the content: the title, the card and the heading level belong to the
 * page, so every section looks the same whichever module draws it.
 */
const props = defineProps<{
    section: ResolvedProfileSection;
}>();

const component = computed(() => resolveProfileSectionComponent(props.section));
const headingId = `profile-section-${useId()}`;

const error = ref<Error | null>(null);
const isDev = import.meta.env.DEV;

watch(
    () => props.section.key,
    () => {
        error.value = null;
    }
);

onErrorCaptured((captured, _instance, info) => {
    const errorObj = captured instanceof Error ? captured : new Error(String(captured));

    console.error(
        `[ProfileSection] Error in section "${props.section.key}":`,
        errorObj.message,
        `\nLifecycle: ${info}`
    );

    error.value = errorObj;

    // Prevent error from propagating — the profile renders without this section
    return false;
});
</script>

<template>
    <template v-if="component">
        <div
            v-if="error && isDev"
            class="my-1 rounded border border-dashed border-error bg-error/5 p-2 text-xs text-error"
        >
            <small>Profile section error: {{ error.message }}</small>
        </div>
        <section
            v-else-if="!error"
            :aria-labelledby="headingId"
            :data-profile-section="section.key"
            class="rounded-lg bg-surface p-5 shadow dark:shadow-depth"
        >
            <h2 :id="headingId" class="mb-3 text-lg font-semibold text-base-primary">
                {{ section.label }}
            </h2>
            <Suspense>
                <component :is="component" v-bind="section.props" />
                <template #fallback>
                    <div class="min-h-8" aria-hidden="true"></div>
                </template>
            </Suspense>
        </section>
    </template>
</template>
