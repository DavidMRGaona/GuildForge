<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import { LMap, LTileLayer, LMarker, LPopup } from '@vue-leaflet/vue-leaflet';

interface LocationData {
    name: string;
    address: string;
    lat: number;
    lng: number;
    zoom: number;
}

const props = defineProps<{
    location: LocationData | null;
}>();

const { t } = useI18n();

const center = computed((): [number, number] => {
    if (!props.location) return [0, 0];
    return [props.location.lat, props.location.lng];
});

const markerIcon = L.icon({
    iconUrl,
    iconRetinaUrl,
    shadowUrl,
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
});
</script>

<template>
    <div class="h-full w-full">
        <LMap
            v-if="location"
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
