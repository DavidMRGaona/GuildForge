import { ref, readonly, onMounted, type Ref } from 'vue';
import L from 'leaflet';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';

export interface MapLocation {
    name: string;
    address: string;
    lat: number;
    lng: number;
    zoom: number;
}

interface UseMapOptions {
    autoLoad?: boolean;
}

interface UseMapReturn {
    location: Readonly<Ref<MapLocation | null>>;
    isLoading: Readonly<Ref<boolean>>;
    error: Readonly<Ref<string | null>>;
    loadLocation: () => Promise<void>;
    getOpenStreetMapUrl: (lat?: number, lng?: number, zoom?: number) => string;
    getLeafletIcon: () => L.Icon;
    invalidateCache: () => void;
}

// Module-level cache for location data
let cachedLocation: MapLocation | null = null;
let loadPromise: Promise<void> | null = null;

export function useMap(options: UseMapOptions = {}): UseMapReturn {
    const { autoLoad = false } = options;

    const location = ref<MapLocation | null>(cachedLocation);
    const isLoading = ref(!cachedLocation);
    const error = ref<string | null>(null);

    async function loadLocation(): Promise<void> {
        // If already cached, use it
        if (cachedLocation) {
            location.value = cachedLocation;
            isLoading.value = false;
            return;
        }

        // If already loading, wait for the existing promise
        if (loadPromise) {
            await loadPromise;
            location.value = cachedLocation;
            isLoading.value = false;
            return;
        }

        // Start loading
        isLoading.value = true;
        error.value = null;

        loadPromise = (async (): Promise<void> => {
            try {
                const response = await fetch('/api/settings/location');
                if (!response.ok) {
                    throw new Error('Failed to fetch location');
                }
                cachedLocation = await response.json() as MapLocation;
                location.value = cachedLocation;
            } catch {
                error.value = 'Error loading location';
                throw error.value;
            } finally {
                isLoading.value = false;
                loadPromise = null;
            }
        })();

        await loadPromise;
    }

    function getOpenStreetMapUrl(lat?: number, lng?: number, zoom?: number): string {
        const useLat = lat ?? location.value?.lat;
        const useLng = lng ?? location.value?.lng;
        const useZoom = zoom ?? location.value?.zoom ?? 15;

        if (useLat === undefined || useLng === undefined) {
            return 'https://www.openstreetmap.org';
        }

        return `https://www.openstreetmap.org/?mlat=${useLat}&mlon=${useLng}#map=${useZoom}/${useLat}/${useLng}`;
    }

    function getLeafletIcon(): L.Icon {
        return L.icon({
            iconUrl,
            iconRetinaUrl,
            shadowUrl,
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41],
        });
    }

    function invalidateCache(): void {
        cachedLocation = null;
        loadPromise = null;
    }

    if (autoLoad) {
        onMounted((): void => {
            loadLocation().catch((): void => {
                // Error already captured in error ref
            });
        });
    }

    return {
        location: readonly(location),
        isLoading: readonly(isLoading),
        error: readonly(error),
        loadLocation,
        getOpenStreetMapUrl,
        getLeafletIcon,
        invalidateCache,
    };
}
