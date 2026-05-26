<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import axios from 'axios';
import Swal2 from 'sweetalert2';

// Base Components
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';

// Icons
import {
    MapIcon,
    PlayIcon,
    PauseIcon,
    ArrowPathIcon,
    InformationCircleIcon,
    ChartBarIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline';

// Leaflet
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

interface Vehicle {
    id: number;
    registration: string;
    vehicle_model: string;
}

interface Position {
    lat: number;
    lng: number;
    speed: number;
    heading: number;
    ignition: boolean;
    odometer: number;
    time: string;
}

interface Stats {
    total_distance_km: number;
    max_speed_kmh: number;
    avg_speed_kmh: number;
    stops_count: number;
}

const props = defineProps<{
    vehicles: Vehicle[];
}>();

// Map & Layer states
const mapContainer = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let polyline: L.Polyline | null = null;
let animatedMarker: L.Marker | null = null;

// Search filter parameters
const searchForm = useForm({
    machine_id: null as number | null,
    start_time: (() => {
        const d = new Date();
        d.setHours(0, 0, 0, 0);
        return d;
    })(),
    end_time: new Date()
});

// Playback states
const isLoading = ref(false);
const pathPoints = ref<Position[]>([]);
const stats = ref<Stats | null>(null);

const isPlaying = ref(false);
const currentIndex = ref(0);
const playSpeed = ref(1); // multiplier (1x, 2x, 5x, 10x)
let animationTimer: any = null;

const vehicleOptions = ref(props.vehicles.map(v => ({
    label: `${v.registration} (${v.vehicle_model})`,
    value: v.id
})));

const initMap = () => {
    if (!mapContainer.value) return;

    map = L.map(mapContainer.value).setView([19.0760, 72.8777], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
};

const destroyMap = () => {
    if (map) {
        map.remove();
        map = null;
    }
    polyline = null;
    animatedMarker = null;
};

// SVG rotated arrow marker icon
const createMovingIcon = (heading: number, ignition: boolean) => {
    const colorClass = ignition ? '#10b981' : '#f43f5e';
    const svgContent = `
        <div style="transform: rotate(${heading}deg); transition: transform 0.1s linear;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="36" height="36">
                <circle cx="12" cy="12" r="10" fill="white" stroke="${colorClass}" stroke-width="3" />
                <path d="M12 4l-4 8h8z" fill="${colorClass}"/>
                <circle cx="12" cy="13" r="2.5" fill="${colorClass}"/>
            </svg>
        </div>
    `;

    return L.divIcon({
        html: svgContent,
        className: 'moving-marker-icon',
        iconSize: [36, 36],
        iconAnchor: [18, 18]
    });
};

const handleQueryPlayback = async () => {
    if (!searchForm.machine_id) {
        Swal2.fire({ icon: 'warning', title: 'Vehicle Required', text: 'Please select a vehicle to query.' });
        return;
    }

    isLoading.value = true;
    isPlaying.value = false;
    currentIndex.value = 0;
    if (animationTimer) clearInterval(animationTimer);

    // Clear previous map visuals
    if (polyline && map) map.removeLayer(polyline);
    if (animatedMarker && map) map.removeLayer(animatedMarker);
    polyline = null;
    animatedMarker = null;

    try {
        const response = await axios.get(route('gps.playback-data'), {
            params: {
                machine_id: searchForm.machine_id,
                start_time: searchForm.start_time.toISOString(),
                end_time: searchForm.end_time.toISOString()
            }
        });

        if (response.data.success) {
            pathPoints.value = response.data.positions;
            stats.value = response.data.stats;

            if (pathPoints.value.length === 0) {
                Swal2.fire({ icon: 'info', title: 'No Data Found', text: 'No coordinate logs registered for this vehicle in the selected time window.' });
            } else {
                plotRoute();
            }
        }
    } catch (e: any) {
        Log;
        Swal2.fire({ icon: 'error', title: 'Query Failed', text: e.response?.data?.message || 'Error fetching telemetry data.' });
    } finally {
        isLoading.value = false;
    }
};

const plotRoute = () => {
    if (!map || pathPoints.value.length === 0) return;

    const latlngs = pathPoints.value.map(p => [p.lat, p.lng] as [number, number]);

    // Draw route polyline
    polyline = L.polyline(latlngs, {
        color: '#6366f1',
        weight: 5,
        opacity: 0.7,
        lineCap: 'round',
        lineJoin: 'round'
    }).addTo(map);

    // Zoom map to fit the full route
    map.fitBounds(polyline.getBounds(), { padding: [40, 40] });

    // Draw initial vehicle marker at start position
    const startPoint = pathPoints.value[0];
    animatedMarker = L.marker([startPoint.lat, startPoint.lng], {
        icon: createMovingIcon(startPoint.heading, startPoint.ignition)
    }).addTo(map);

    animatedMarker.bindPopup(`<b>Start Location</b><br>${startPoint.time}`);
};

const togglePlayback = () => {
    if (pathPoints.value.length === 0) return;

    if (isPlaying.value) {
        // Pause
        isPlaying.value = false;
        if (animationTimer) clearInterval(animationTimer);
    } else {
        // Play
        isPlaying.value = true;
        playRouteAnimation();
    }
};

const playRouteAnimation = () => {
    if (animationTimer) clearInterval(animationTimer);

    // Interval speed: 1000ms / speed factor
    const baseInterval = 600;
    const intervalMs = Math.max(baseInterval / playSpeed.value, 50);

    animationTimer = setInterval(() => {
        if (currentIndex.value >= pathPoints.value.length - 1) {
            // Finished route
            isPlaying.value = false;
            clearInterval(animationTimer);
            Swal2.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Playback complete', showConfirmButton: false, timer: 1500 });
            return;
        }

        currentIndex.value++;
        const nextPoint = pathPoints.value[currentIndex.value];
        
        if (animatedMarker && map) {
            animatedMarker.setLatLng([nextPoint.lat, nextPoint.lng]);
            animatedMarker.setIcon(createMovingIcon(nextPoint.heading, nextPoint.ignition));
            animatedMarker.setPopupContent(`<b>Time:</b> ${nextPoint.time}<br><b>Speed:</b> ${nextPoint.speed} km/h<br><b>Heading:</b> ${nextPoint.heading}°`);
            
            // Auto pan map to keep vehicle centered
            map.panTo([nextPoint.lat, nextPoint.lng]);
        }
    }, intervalMs);
};

const changeSpeed = (speed: number) => {
    playSpeed.value = speed;
    if (isPlaying.value) {
        // Restart timer with new speed interval
        playRouteAnimation();
    }
};

const resetPlayback = () => {
    isPlaying.value = false;
    if (animationTimer) clearInterval(animationTimer);
    currentIndex.value = 0;
    
    if (pathPoints.value.length > 0 && animatedMarker && map) {
        const startPoint = pathPoints.value[0];
        animatedMarker.setLatLng([startPoint.lat, startPoint.lng]);
        animatedMarker.setIcon(createMovingIcon(startPoint.heading, startPoint.ignition));
        map.panTo([startPoint.lat, startPoint.lng]);
    }
};

onMounted(() => {
    nextTick(() => {
        initMap();
    });
});

onUnmounted(() => {
    if (animationTimer) clearInterval(animationTimer);
    destroyMap();
});
</script>

<template>
    <AppLayout title="GPS Playback">
        <template #header><ModuleSubTopNav /></template>

        <div class="h-[calc(100vh-140px)] flex flex-col lg:flex-row overflow-hidden">
            <!-- Sidebar: Parameters and Stats -->
            <div class="w-full lg:w-96 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col overflow-y-auto">
                <div class="p-6 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2 mb-6 pb-2 border-b border-slate-50 dark:border-slate-800/80">
                        <MapIcon class="w-5 h-5 text-indigo-500" />
                        <span class="text-xs font-black uppercase text-gray-800 dark:text-gray-100 tracking-wider">Route History Playback</span>
                    </div>

                    <!-- Query Parameters Form -->
                    <form @submit.prevent="handleQueryPlayback" class="space-y-4">
                        <div class="field-group">
                            <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Select Fleet Vehicle *</label>
                            <BaseSelect v-model="searchForm.machine_id" :options="vehicleOptions" optionLabel="label" optionValue="value" placeholder="Select vehicle" />
                        </div>
                        <div class="field-group">
                            <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Start Date & Time *</label>
                            <BaseDatePicker v-model="searchForm.start_time" showTime hourFormat="24" placeholder="Start Date" />
                        </div>
                        <div class="field-group">
                            <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">End Date & Time *</label>
                            <BaseDatePicker v-model="searchForm.end_time" showTime hourFormat="24" placeholder="End Date" />
                        </div>

                        <BaseButton 
                            label="Query Route" 
                            type="submit"
                            class="w-full"
                            :loading="isLoading"
                        />
                    </form>
                </div>

                <!-- Stats panel -->
                <div v-if="stats && pathPoints.length > 0" class="p-6 border-b border-slate-100 dark:border-slate-800 space-y-5">
                    <div class="flex items-center gap-2 pb-2 border-b border-slate-50 dark:border-slate-800/80">
                        <ChartBarIcon class="w-5 h-5 text-indigo-500" />
                        <span class="text-xs font-black uppercase text-gray-800 dark:text-gray-100 tracking-wider">Trip Statistics</span>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 rounded-xl bg-slate-50/80 dark:bg-slate-950/40 border border-slate-100/50 dark:border-slate-900/30 flex flex-col gap-1.5">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">Distance</span>
                            <span class="text-sm font-mono font-bold text-slate-800 dark:text-slate-200">{{ stats.total_distance_km }} km</span>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-50/80 dark:bg-slate-950/40 border border-slate-100/50 dark:border-slate-900/30 flex flex-col gap-1.5">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">Stops</span>
                            <span class="text-sm font-mono font-bold text-slate-800 dark:text-slate-200">{{ stats.stops_count }} Stops</span>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-50/80 dark:bg-slate-950/40 border border-slate-100/50 dark:border-slate-900/30 flex flex-col gap-1.5">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">Max Speed</span>
                            <span class="text-sm font-mono font-bold text-slate-800 dark:text-slate-200 text-rose-500">{{ stats.max_speed_kmh }} km/h</span>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-50/80 dark:bg-slate-950/40 border border-slate-100/50 dark:border-slate-900/30 flex flex-col gap-1.5">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">Avg Speed</span>
                            <span class="text-sm font-mono font-bold text-slate-800 dark:text-slate-200">{{ stats.avg_speed_kmh }} km/h</span>
                        </div>
                    </div>
                </div>

                <!-- Playback controls -->
                <div v-if="pathPoints.length > 0" class="p-6 space-y-4">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300">
                        <span>Playback Progress:</span>
                        <span class="font-mono text-indigo-650">{{ currentIndex + 1 }} / {{ pathPoints.length }}</span>
                    </div>

                    <!-- Progress Bar -->
                    <div class="w-full bg-slate-100 dark:bg-slate-950 h-2 rounded-full overflow-hidden border border-slate-200/50 dark:border-slate-800/40">
                        <div class="bg-indigo-650 h-full transition-all duration-100" 
                             :style="{ width: `${((currentIndex + 1) / pathPoints.length) * 100}%` }"
                        ></div>
                    </div>

                    <!-- Controls buttons -->
                    <div class="flex items-center justify-center gap-3">
                        <button 
                            @click="resetPlayback"
                            class="flex items-center justify-center w-10 h-10 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-650 dark:text-slate-350 cursor-pointer"
                            title="Restart Playback"
                        >
                            <ArrowPathIcon class="w-4 h-4" />
                        </button>

                        <button 
                            @click="togglePlayback"
                            class="flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-650 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-100 dark:shadow-none cursor-pointer"
                            :title="isPlaying ? 'Pause Playback' : 'Play Playback'"
                        >
                            <PauseIcon v-if="isPlaying" class="w-5 h-5" />
                            <PlayIcon v-else class="w-5 h-5 stroke-[3px]" />
                        </button>

                        <div class="flex items-center gap-1 bg-slate-50 dark:bg-slate-950/80 p-1.5 border border-slate-100 dark:border-slate-800 rounded-xl text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            <button 
                                v-for="speed in [1, 2, 5, 10]" 
                                :key="speed"
                                @click="changeSpeed(speed)"
                                class="px-2 py-1 rounded-lg transition-all cursor-pointer"
                                :class="[
                                    playSpeed === speed 
                                        ? 'bg-indigo-650 text-white font-black' 
                                        : 'hover:bg-slate-100/50 dark:hover:bg-slate-900/40'
                                ]"
                            >
                                {{ speed }}x
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Map Area -->
            <div class="flex-1 bg-slate-100 dark:bg-slate-950 relative flex h-full">
                <div ref="mapContainer" class="absolute inset-0 z-10 w-full h-full"></div>

                <!-- Floating warning if no queries are run -->
                <div v-if="pathPoints.length === 0" class="absolute top-4 left-4 right-4 sm:left-auto z-20 bg-white/95 dark:bg-slate-900/95 backdrop-blur-sm border border-slate-100 dark:border-slate-800/80 p-4 rounded-2xl shadow-xl flex items-start gap-3 max-w-sm">
                    <ExclamationTriangleIcon class="w-5 h-5 text-indigo-500 shrink-0" />
                    <div>
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-100 uppercase tracking-tight">No Route Loaded</div>
                        <div class="text-[10px] text-slate-400 mt-0.5 leading-normal uppercase">Select a vehicle and date range on the left, then click "Query Route" to begin route tracking playback.</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.moving-marker-icon {
    background: transparent !important;
    border: none !important;
}
</style>
