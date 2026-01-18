<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import 'leaflet/dist/leaflet.css';
import { LMap, LTileLayer, LMarker, LPopup } from '@vue-leaflet/vue-leaflet';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useMap } from '@/composables/useMap';

const { t } = useI18n();

const { location, isLoading, error, getLeafletIcon } = useMap({ autoLoad: true });
const markerIcon = getLeafletIcon();

const center = computed((): [number, number] => {
    if (!location.value) return [0, 0];
    return [location.value.lat, location.value.lng];
});

const errorMessage = computed((): string => {
    return error.value ? t('about.location.error') : '';
});
</script>

<template>
    <div class="h-full w-full">
        <LoadingSpinner v-if="isLoading" />

        <div
            v-else-if="errorMessage"
            class="flex items-center justify-center h-full text-red-600"
        >
            {{ errorMessage }}
        </div>

        <LMap
            v-else-if="location"
            role="region"
            :aria-label="t('about.location.title')"
            :zoom="location.zoom"
            :center="center"
            :use-global-leaflet="false"
            class="h-full w-full z-0"
        >
            <LTileLayer
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                layer-type="base"
                name="OpenStreetMap"
                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            />
            <LMarker :lat-lng="center" :icon="markerIcon">
                <LPopup>
                    <div class="text-sm">
                        <strong>{{ location.name }}</strong>
                        <br />
                        {{ location.address }}
                    </div>
                </LPopup>
            </LMarker>
        </LMap>
    </div>
</template>
