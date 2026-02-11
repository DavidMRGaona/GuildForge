<script setup lang="ts">
import { computed } from 'vue';
import type { ProfileTabIcon } from '@/types/profile';
import { profileTabIconMap } from '@/utils/icons';

interface Props {
    icon: ProfileTabIcon;
}

const props = defineProps<Props>();

const iconComponent = computed(() => profileTabIconMap[props.icon]);
</script>

<template>
    <component :is="iconComponent" aria-hidden="true" />
</template>
