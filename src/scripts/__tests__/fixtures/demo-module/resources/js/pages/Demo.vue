<script setup lang="ts">
import FullCalendar from '@fullcalendar/vue3';
import { Calendar } from '@lucide/vue';
import type { User } from '@/types';
import { venueDayKey } from '@/utils/datetime';

defineProps<{ user: User }>();
const today = venueDayKey(new Date());
</script>

<template>
    <p><Calendar /> {{ user.name }} {{ today }}</p>
    <FullCalendar />
</template>
