<script setup lang="ts">
import { computed } from 'vue';
import type { ProfileTabIcon } from '@/types/profile';
import { resolveProfileTabIcon } from '@/utils/icons';

interface Props {
    icon: ProfileTabIcon;
}

const props = defineProps<Props>();

// Modules send the icon name as a string: an unknown one shows a neutral icon instead of nothing
const iconComponent = computed(() => resolveProfileTabIcon(props.icon));
</script>

<template>
    <component :is="iconComponent" aria-hidden="true" />
</template>
