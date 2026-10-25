<script setup lang="ts">
import { resolveProfileTabIcon } from '@/utils/icons';

const icon = resolveProfileTabIcon('settings');
</script>

<template>
    <component :is="icon" />
</template>
