<script setup lang="ts">
import { useNotifications } from '../../../../../../../resources/js/composables/useNotifications';

const { success } = useNotifications();
</script>

<template>
    <button type="button" @click="success('Saved')">Save</button>
</template>
