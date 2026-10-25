<script setup lang="ts">
import { latLng } from 'leaflet';

const point = latLng(0, 0);
</script>

<template>
    <p>{{ point.lat }}</p>
</template>
