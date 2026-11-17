<script setup lang="ts">
import { APP_LOCALE } from '@/Utils/locale';
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { useWebSocket } from '@/Composables/useWebSocket';

// Icons
import {
    MapIcon,
    MapPinIcon,
    SignalIcon,
    CpuChipIcon,
    MagnifyingGlassIcon,
    ArrowPathIcon,
    ClockIcon,
    ShieldCheckIcon,
    BoltIcon
} from '@heroicons/vue/24/outline';

// Leaflet
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

interface Vehicle {
    id: number;
    registration: string;
    vehicle_model: string;
    vehicle_type: string;
    imei: string;
    device_id?: string;
    vehicle_id?: string;
    is_online: boolean;
    last_ping: string;
    latitude: number | null;
    longitude: number | null;
    speed: number;
    heading: number;
    ignition: boolean;
    odometer: number;
    vehicle_status?: string;
    address?: string | null;
    fuel_litre?: number | null;
    inside_geofence?: boolean;
    raw_status?: string | null;
    expiry_date?: string | null;
    onboard_date?: string | null;
    server_time?: number | null;
}

interface Geofence {
    id: number;
    name: string;
    shape: string;
    coordinates: any;
}

const props = defineProps<{
    vehicles: Vehicle[];
    geofences: Geofence[];
}>();

const mapContainer = ref<HTMLElement | null>(null);
const searchQuery = ref('');
const selectedStatusFilter = ref<string>('ALL');
const selectedVehicleId = ref<number | null>(null);
const isRefreshing = ref(false);

let map: L.Map | null = null;
const markers = ref<Record<number, L.Marker>>({});
let geofenceLayers: L.Layer[] = [];

// Local mutable copy of vehicles
const localVehicles = ref<Vehicle[]>([...props.vehicles]);

// Compass direction helper
const getCompassDirection = (deg: number): string => {
    const directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
    const idx = Math.round(((deg % 360) + 360) % 360 / 22.5) % 16;
    return directions[idx];
};

// Normalized vehicle status helper
const getVehicleStatus = (vehicle: Vehicle): string => {
    if (vehicle.vehicle_status) {
        return vehicle.vehicle_status.toUpperCase();
    }
    if (vehicle.speed > 0) return 'RUNNING';
    if (vehicle.ignition) return 'IDLE';
    return 'PARKED';
};

// Status styling helper
const getStatusBadge = (vehicle: Vehicle) => {
    const status = getVehicleStatus(vehicle);
    if (status === 'RUNNING' || status === 'MOVING') {
        return {
            label: 'RUNNING',
            badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800'
        };
    }
    if (status === 'IDLE') {
        return {
            label: 'IDLE',
            badgeClass: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800'
        };
    }
    if (status === 'PARKED') {
        return {
            label: 'PARKED',
            badgeClass: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-400 dark:border-sky-800'
        };
    }
    if (status === 'STOPPED') {
        return {
            label: 'STOPPED',
            badgeClass: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-800'
        };
    }
    return {
        label: status,
        badgeClass: 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'
    };
};

// Summary metrics computed
const counts = computed(() => {
    const total = localVehicles.value.length;
    let running = 0;
    let parked = 0;
    let idle = 0;
    let offline = 0;

    localVehicles.value.forEach(v => {
        if (!v.is_online) {
            offline++;
        } else {
            const st = getVehicleStatus(v);
            if (st === 'RUNNING' || st === 'MOVING') running++;
            else if (st === 'IDLE') idle++;
            else parked++;
        }
    });

    return { total, running, parked, idle, offline };
});

// Filtered vehicles list
const filteredVehicles = ref<Vehicle[]>(localVehicles.value);
const updateFilters = () => {
    const q = searchQuery.value.toLowerCase().trim();
    filteredVehicles.value = localVehicles.value.filter(v => {
        const matchesQuery = !q || (
            v.registration.toLowerCase().includes(q) ||
            v.vehicle_model.toLowerCase().includes(q) ||
            (v.vehicle_type && v.vehicle_type.toLowerCase().includes(q)) ||
            (v.imei && v.imei.toLowerCase().includes(q)) ||
            (v.address && v.address.toLowerCase().includes(q)) ||
            (v.vehicle_status && v.vehicle_status.toLowerCase().includes(q))
        );

        if (!matchesQuery) return false;

        if (selectedStatusFilter.value === 'ALL') return true;
        if (selectedStatusFilter.value === 'OFFLINE') return !v.is_online;
        if (selectedStatusFilter.value === 'RUNNING') return v.is_online && (getVehicleStatus(v) === 'RUNNING' || getVehicleStatus(v) === 'MOVING');
        if (selectedStatusFilter.value === 'PARKED') return v.is_online && getVehicleStatus(v) === 'PARKED';
        if (selectedStatusFilter.value === 'IDLE') return v.is_online && getVehicleStatus(v) === 'IDLE';

        return true;
    });
};

// Create a custom SVG marker based on vehicle properties (rotating arrow + status color)
const createVehicleIcon = (vehicle: Vehicle) => {
    let colorClass = '#64748b'; // slate default
    const status = getVehicleStatus(vehicle);
    if (status === 'RUNNING' || status === 'MOVING') {
        colorClass = '#10b981'; // emerald green
    } else if (status === 'IDLE') {
        colorClass = '#f59e0b'; // amber
    } else if (status === 'PARKED') {
        colorClass = '#0284c7'; // sky blue
    } else if (status === 'STOPPED') {
        colorClass = '#64748b';
    }

    const opacity = vehicle.is_online ? '1' : '0.55';
    const heading = vehicle.heading || 0;

    const svgContent = `
        <div style="transform: rotate(${heading}deg); transition: transform 0.4s ease-out; opacity: ${opacity};">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="38" height="38">
                <circle cx="12" cy="12" r="10" fill="white" stroke="${colorClass}" stroke-width="3" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.25));"/>
                <path d="M12 4l-4.5 8.5h9z" fill="${colorClass}"/>
                <circle cx="12" cy="13.5" r="2.5" fill="${colorClass}"/>
            </svg>
        </div>
    `;

    return L.divIcon({
        html: svgContent,
        className: 'custom-gps-marker-container',
        iconSize: [38, 38],
        iconAnchor: [19, 19]
    });
};

const initMap = () => {
    if (!mapContainer.value) return;

    // Default coords (e.g. Chennai / plant centroid)
    let defaultLat = 89.0368;
    let defaultLng = 10.2310;

    // Center around the first vehicle with valid coordinates
    const firstActive = localVehicles.value.find(v => v.latitude !== null && v.longitude !== null);
    if (firstActive && firstActive.latitude && firstActive.longitude) {
        defaultLat = firstActive.latitude;
        defaultLng = firstActive.longitude;
    }

    map = L.map(mapContainer.value).setView([defaultLat, defaultLng], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    renderGeofences();
    renderVehicles();
};

const renderGeofences = () => {
    if (!map) return;

    // Clear old geofences
    geofenceLayers.forEach(l => map?.removeLayer(l));
    geofenceLayers = [];

    props.geofences.forEach(g => {
        let layer: L.Layer | null = null;
        if (g.shape === 'circle' && g.coordinates?.center) {
            layer = L.circle([g.coordinates.center.lat, g.coordinates.center.lng], {
                radius: g.coordinates.radius,
                color: '#6366f1',
                fillColor: '#818cf8',
                fillOpacity: 0.15,
                dashArray: '5, 5'
            }).addTo(map!);
        } else if (g.shape === 'polygon' && g.coordinates) {
            const pts = g.coordinates.map((pt: any) => [pt.lat ?? pt[0], pt.lng ?? pt[1]]);
            layer = L.polygon(pts, {
                color: '#6366f1',
                fillColor: '#818cf8',
                fillOpacity: 0.15,
                dashArray: '5, 5'
            }).addTo(map!);
        }

        if (layer) {
            layer.bindPopup(`<b>Geofence: ${g.name}</b>`);
            geofenceLayers.push(layer);
        }
    });
};

const renderVehicles = () => {
    if (!map) return;

    localVehicles.value.forEach(v => {
        if (v.latitude === null || v.longitude === null) return;

        const icon = createVehicleIcon(v);
        const st = getVehicleStatus(v);
        const compass = getCompassDirection(v.heading || 0);

        const popupContent = `
            <div class="p-2 space-y-1.5 min-w-[220px] max-w-[280px]">
                <div class="flex items-center justify-between border-b pb-1 mb-1">
                    <span class="font-bold text-slate-800 text-sm tracking-tight">${v.registration}</span>
                    <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded border ${v.is_online ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-slate-500 bg-slate-100 border-slate-200'}">
                        ${st}
                    </span>
                </div>
                <div class="text-xs text-slate-600 flex justify-between">
                    <span>${v.vehicle_model}</span>
                    <span class="font-semibold text-slate-500">${v.vehicle_type}</span>
                </div>
                <div class="grid grid-cols-2 gap-1 text-xs text-slate-600 pt-1">
                    <div>Speed: <b>${v.speed} km/h</b></div>
                    <div>Ignition: <b class="${v.ignition ? 'text-emerald-600' : 'text-rose-500'}">${v.ignition ? 'ON' : 'OFF'}</b></div>
                </div>
                <div class="grid grid-cols-2 gap-1 text-xs text-slate-600">
                    <div>Odo: <b>${Number(v.odometer || 0).toFixed(1)} km</b></div>
                    <div>Bearing: <b>${v.heading || 0}° ${compass}</b></div>
                </div>
                ${v.address ? `
                    <div class="text-[11px] text-slate-600 bg-slate-50 p-1.5 rounded border border-slate-200/60 leading-snug mt-1">
                        📍 ${v.address}
                    </div>
                ` : ''}
                <div class="text-[10px] text-slate-400 mt-2 pt-1 border-t flex justify-between items-center">
                    <span>IMEI: ${v.imei}</span>
                    <a href="https://www.google.com/maps?q=${v.latitude},${v.longitude}" target="_blank" class="text-indigo-600 hover:underline font-semibold">View Maps ↗</a>
                </div>
            </div>
        `;

        if (markers.value[v.id]) {
            // Update existing marker position, rotation, and popup content
            markers.value[v.id].setLatLng([v.latitude, v.longitude]);
            markers.value[v.id].setIcon(icon);
            markers.value[v.id].setPopupContent(popupContent);
        } else {
            // Create new marker
            const marker = L.marker([v.latitude, v.longitude], { icon }).addTo(map!);
            marker.bindPopup(popupContent);
            markers.value[v.id] = marker;
        }
    });
};

const focusVehicle = (vehicle: Vehicle) => {
    if (vehicle.latitude === null || vehicle.longitude === null || !map) return;

    selectedVehicleId.value = vehicle.id;
    map.setView([vehicle.latitude, vehicle.longitude], 15);

    const marker = markers.value[vehicle.id];
    if (marker) {
        marker.openPopup();
    }
};

// Watch Inertia props changes to sync with local state (e.g. initial load or force reloads)
watch(() => props.vehicles, (newVehicles) => {
    localVehicles.value = [...newVehicles];
    renderVehicles();
    updateFilters();
}, { deep: true });

// Fallback REST polling via Inertia reload
const fetchVehiclesFallback = () => {
    isRefreshing.value = true;
    router.reload({
        only: ['vehicles'],
        onSuccess: () => {
            isRefreshing.value = false;
        },
        onFinish: () => {
            isRefreshing.value = false;
        }
    });
};

// WebSocket event message handler
const handleWebSocketMessage = (data: any) => {
    if (data.event === 'GpsLocationUpdated' && data.vehicle) {
        const updatedVehicle: Vehicle = data.vehicle;
        const index = localVehicles.value.findIndex(v => v.id === updatedVehicle.id);
        if (index !== -1) {
            localVehicles.value[index] = updatedVehicle;
        } else {
            localVehicles.value.push(updatedVehicle);
        }
        renderVehicles();
        updateFilters();

        // If a vehicle is selected, adjust map view
        if (selectedVehicleId.value === updatedVehicle.id) {
            if (updatedVehicle.latitude !== null && updatedVehicle.longitude !== null && map) {
                map.panTo([updatedVehicle.latitude, updatedVehicle.longitude]);
            }
        }
    }
};

// Hook up WebSocket connection with REST fallback
const { isConnected } = useWebSocket({
    channel: 'gps-tracking',
    onMessage: handleWebSocketMessage,
    fallbackPoll: fetchVehiclesFallback,
    pollIntervalMs: 10000
});

onMounted(() => {
    nextTick(() => {
        initMap();
        updateFilters();
    });
});

onUnmounted(() => {
    if (map) {
        map.remove();
        map = null;
    }
});

</script>

<template>
    <AppLayout title="Live Fleet Tracking">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="h-[calc(100vh-140px)] flex flex-col lg:flex-row overflow-hidden">
            <!-- Sidebar Panel: Vehicle list -->
            <div
                class="w-full lg:w-[410px] bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col shrink-0">
                <!-- Search and reload -->
                <div class="p-3.5 border-b border-slate-100 dark:border-slate-800 flex flex-col gap-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <div class="relative flex-1">
                            <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                            <input type="text" v-model="searchQuery" @input="updateFilters"
                                placeholder="Search vehicle, address, IMEI..."
                                class="w-full h-10 pl-9 pr-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs font-bold focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <button @click="fetchVehiclesFallback"
                            class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-500 transition-all cursor-pointer relative"
                            title="Force reload latest positions">
                            <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': isRefreshing }" />
                        </button>
                    </div>

                    <!-- Quick Status Filter Pills -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 text-[10px] font-black uppercase">
                        <button @click="selectedStatusFilter = 'ALL'; updateFilters()"
                            class="px-2.5 py-1 rounded-lg border transition-all cursor-pointer"
                            :class="[selectedStatusFilter === 'ALL' ? 'bg-slate-900 text-white border-slate-900 dark:bg-white dark:text-slate-900' : 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400']">
                            All ({{ counts.total }})
                        </button>
                        <button @click="selectedStatusFilter = 'RUNNING'; updateFilters()"
                            class="px-2 py-1 rounded-lg border transition-all cursor-pointer"
                            :class="[selectedStatusFilter === 'RUNNING' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400']">
                            Running ({{ counts.running }})
                        </button>
                        <button @click="selectedStatusFilter = 'PARKED'; updateFilters()"
                            class="px-2 py-1 rounded-lg border transition-all cursor-pointer"
                            :class="[selectedStatusFilter === 'PARKED' ? 'bg-sky-600 text-white border-sky-600' : 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-400']">
                            Parked ({{ counts.parked }})
                        </button>
                        <button @click="selectedStatusFilter = 'IDLE'; updateFilters()"
                            class="px-2 py-1 rounded-lg border transition-all cursor-pointer"
                            :class="[selectedStatusFilter === 'IDLE' ? 'bg-amber-600 text-white border-amber-600' : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400']">
                            Idle ({{ counts.idle }})
                        </button>
                        <button @click="selectedStatusFilter = 'OFFLINE'; updateFilters()"
                            class="px-2 py-1 rounded-lg border transition-all cursor-pointer"
                            :class="[selectedStatusFilter === 'OFFLINE' ? 'bg-slate-700 text-white border-slate-700' : 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400']">
                            Offline ({{ counts.offline }})
                        </button>
                    </div>
                </div>

                <!-- Vehicles List -->
                <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/80">
                    <div v-for="vehicle in filteredVehicles" :key="vehicle.id" @click="focusVehicle(vehicle)"
                        class="p-4 hover:bg-slate-50/70 dark:hover:bg-slate-950/40 cursor-pointer transition-all duration-200 flex flex-col gap-2 relative border-l-4"
                        :class="[
                            selectedVehicleId === vehicle.id
                                ? 'bg-indigo-50/40 dark:bg-indigo-950/20 border-l-indigo-600'
                                : 'border-l-transparent'
                        ]">
                        <!-- Row 1: Registration and Status Badges -->
                        <div class="flex justify-between items-center gap-2">
                            <span
                                class="font-mono text-sm font-black text-slate-800 dark:text-slate-200 uppercase tracking-tight">
                                {{ vehicle.registration }}
                            </span>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span
                                    class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md border"
                                    :class="getStatusBadge(vehicle).badgeClass">
                                    {{ getStatusBadge(vehicle).label }}
                                </span>
                                <span
                                    class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md border"
                                    :class="[
                                        vehicle.is_online
                                            ? 'text-emerald-700 bg-emerald-50 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400'
                                            : 'text-slate-500 bg-slate-150 border-slate-200 dark:bg-slate-800 dark:text-slate-400'
                                    ]">
                                    {{ vehicle.is_online ? 'Online' : 'Offline' }}
                                </span>
                            </div>
                        </div>

                        <!-- Row 2: Model, Type, and Device ID -->
                        <div class="flex justify-between items-center text-xs text-slate-500">
                            <div class="flex items-center gap-1.5">
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ vehicle.vehicle_model
                                    }}</span>
                                <span class="text-slate-300 dark:text-slate-700">•</span>
                                <span class="font-semibold text-slate-500">{{ vehicle.vehicle_type }}</span>
                            </div>
                            <span class="text-[10px] font-mono text-slate-400">ID: {{ vehicle.device_id || vehicle.imei
                                }}</span>
                        </div>

                        <!-- Row 3: 4-Cell Telemetry Stats Grid -->
                        <div class="grid grid-cols-4 gap-1.5 mt-1">
                            <div
                                class="p-2 rounded-lg bg-slate-50/90 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-900/40 flex flex-col text-center">
                                <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Speed</span>
                                <span class="font-mono text-xs font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{
                                    vehicle.speed }} <span class="text-[9px] font-normal">km/h</span></span>
                            </div>
                            <div
                                class="p-2 rounded-lg bg-slate-50/90 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-900/40 flex flex-col text-center">
                                <span
                                    class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Ignition</span>
                                <span class="font-mono text-xs font-bold mt-0.5"
                                    :class="[vehicle.ignition ? 'text-emerald-600' : 'text-rose-500']">
                                    {{ vehicle.ignition ? 'ON' : 'OFF' }}
                                </span>
                            </div>
                            <div
                                class="p-2 rounded-lg bg-slate-50/90 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-900/40 flex flex-col text-center">
                                <span
                                    class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Odometer</span>
                                <span class="font-mono text-xs font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                                    {{ Number(vehicle.odometer || 0).toLocaleString(APP_LOCALE, {
                                        maximumFractionDigits:
                                    1 }) }}
                                </span>
                            </div>
                            <div
                                class="p-2 rounded-lg bg-slate-50/90 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-900/40 flex flex-col text-center">
                                <span
                                    class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Bearing</span>
                                <span class="font-mono text-xs font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                                    {{ vehicle.heading || 0 }}° <span class="text-[9px] text-slate-500">{{
                                        getCompassDirection(vehicle.heading || 0) }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Row 4: Live Address Display -->
                        <div v-if="vehicle.address"
                            class="text-[11px] text-slate-600 dark:text-slate-400 bg-slate-50/80 dark:bg-slate-950/50 p-2 rounded-lg border border-slate-150 dark:border-slate-800/80 flex items-start gap-1.5 leading-snug">
                            <MapPinIcon class="w-3.5 h-3.5 text-indigo-500 shrink-0 mt-0.5" />
                            <span class="line-clamp-2">{{ vehicle.address }}</span>
                        </div>

                        <!-- Row 5: Badges (Geofence, Fuel) & Last Updated -->
                        <div class="flex items-center justify-between text-[10px] text-slate-400 pt-0.5">
                            <div class="flex items-center gap-1.5">
                                <span v-if="vehicle.inside_geofence"
                                    class="inline-flex items-center gap-1 text-[9px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded">
                                    <ShieldCheckIcon class="w-3 h-3 text-emerald-600" />
                                    Inside Geofence
                                </span>
                                <span v-if="vehicle.fuel_litre !== null && vehicle.fuel_litre !== undefined"
                                    class="inline-flex items-center gap-1 text-[9px] font-bold text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">
                                    Fuel: {{ vehicle.fuel_litre }} L
                                </span>
                            </div>
                            <div class="flex items-center gap-1 text-slate-400 ml-auto">
                                <ClockIcon class="w-3 h-3 shrink-0" />
                                <span>{{ vehicle.last_ping }}</span>
                            </div>
                        </div>

                        <!-- Row 6: Warning if coordinates are missing -->
                        <div v-if="vehicle.latitude === null || vehicle.longitude === null"
                            class="mt-1 text-[10px] font-bold text-rose-500 bg-rose-50/50 dark:bg-rose-950/10 p-2 rounded-lg border border-rose-100/30 flex items-center gap-1.5">
                            <SignalIcon class="w-4 h-4 shrink-0" />
                            <span>NO GPS COORDINATES RECEIVED YET</span>
                        </div>
                    </div>

                    <div v-if="filteredVehicles.length === 0"
                        class="p-8 text-center text-slate-400 text-xs font-bold uppercase tracking-wider">
                        No active vehicles found...
                    </div>
                </div>
            </div>

            <!-- Map Container -->
            <div class="flex-1 bg-slate-100 dark:bg-slate-950 relative flex h-full">
                <div ref="mapContainer" class="absolute inset-0 z-10 w-full h-full"></div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
/* Leaflet DivIcon styling correction */
.custom-gps-marker-container {
    background: transparent !important;
    border: none !important;
}
</style>
