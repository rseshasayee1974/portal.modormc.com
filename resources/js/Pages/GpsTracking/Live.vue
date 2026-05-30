<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { useWebSocket } from '@/Composables/useWebSocket';

// Icons
import {
    MapIcon,
    SignalIcon,
    CpuChipIcon,
    MagnifyingGlassIcon,
    ArrowPathIcon
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
    is_online: boolean;
    last_ping: string;
    latitude: number | null;
    longitude: number | null;
    speed: number;
    heading: number;
    ignition: boolean;
    odometer: number;
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
const selectedVehicleId = ref<number | null>(null);
const isRefreshing = ref(false);

let map: L.Map | null = null;
const markers = ref<Record<number, L.Marker>>({});
let geofenceLayers: L.Layer[] = [];
let pollingInterval: any = null;

// Local mutable copy of vehicles
const localVehicles = ref<Vehicle[]>([...props.vehicles]);

// Filtered vehicles list
const filteredVehicles = ref<Vehicle[]>(localVehicles.value);
const updateFilters = () => {
    filteredVehicles.value = localVehicles.value.filter(v => 
        v.registration.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        v.vehicle_model.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
};

// Create a custom SVG marker based on vehicle properties (rotating arrow + status color)
const createVehicleIcon = (vehicle: Vehicle) => {
    const colorClass = vehicle.ignition ? '#10b981' : '#f43f5e'; // Green if ignition ON, Red if OFF
    const opacity = vehicle.is_online ? '1' : '0.5';

    const svgContent = `
        <div style="transform: rotate(${vehicle.heading}deg); transition: transform 0.4s ease-out; opacity: ${opacity};">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="36" height="36">
                <circle cx="12" cy="12" r="10" fill="white" stroke="${colorClass}" stroke-width="3" shadow="0 2px 4px rgba(0,0,0,0.3)"/>
                <path d="M12 4l-4 8h8z" fill="${colorClass}"/>
                <circle cx="12" cy="13" r="2.5" fill="${colorClass}"/>
            </svg>
        </div>
    `;

    return L.divIcon({
        html: svgContent,
        className: 'custom-gps-marker-container',
        iconSize: [36, 36],
        iconAnchor: [18, 18]
    });
};

const initMap = () => {
    if (!mapContainer.value) return;

    // Mumbai default coords
    let defaultLat = 19.0760;
    let defaultLng = 72.8777;

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
        
        if (markers.value[v.id]) {
            // Update existing marker position & rotation
            markers.value[v.id].setLatLng([v.latitude, v.longitude]);
            markers.value[v.id].setIcon(icon);
        } else {
            // Create new marker
            const marker = L.marker([v.latitude, v.longitude], { icon }).addTo(map!);
            
            marker.bindPopup(`
                <div class="p-2 space-y-1.5 min-w-[180px]">
                    <div class="font-bold text-slate-800 text-sm border-b pb-1 mb-1">${v.registration}</div>
                    <div class="text-xs text-slate-600">Model: <b>${v.vehicle_model}</b></div>
                    <div class="text-xs text-slate-600">Speed: <b>${v.speed} km/h</b></div>
                    <div class="text-xs text-slate-600">Ignition: <b class="${v.ignition ? 'text-emerald-600' : 'text-rose-500'}">${v.ignition ? 'ON' : 'OFF'}</b></div>
                    <div class="text-xs text-slate-600">Odometer: <b>${v.odometer.toLocaleString()} km</b></div>
                    <div class="text-[10px] text-slate-400 mt-2 pt-1 border-t">Last Update: ${v.last_ping}</div>
                </div>
            `);

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
        <template #header><ModuleSubTopNav /></template>

        <div class="h-[calc(100vh-140px)] flex flex-col lg:flex-row overflow-hidden">
            <!-- Sidebar Panel: Vehicle list -->
            <div class="w-full lg:w-96 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col">
                <!-- Search and reload -->
                <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                    <div class="relative flex-1">
                        <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                        <input 
                            type="text" 
                            v-model="searchQuery" 
                            @input="updateFilters"
                            placeholder="Search vehicle..." 
                            class="w-full h-10 pl-9 pr-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs font-bold focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                    <button 
                        @click="router.reload({ only: ['vehicles'] })" 
                        class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-500 transition-all cursor-pointer relative"
                        title="Force reload latest positions"
                    >
                        <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': isRefreshing }" />
                    </button>
                </div>

                <!-- Vehicles List -->
                <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/80">
                    <div 
                        v-for="vehicle in filteredVehicles" 
                        :key="vehicle.id"
                        @click="focusVehicle(vehicle)"
                        class="p-4 hover:bg-slate-50/50 dark:hover:bg-slate-950/40 cursor-pointer transition-all duration-200 flex flex-col gap-2.5 relative border-l-4"
                        :class="[
                            selectedVehicleId === vehicle.id 
                                ? 'bg-indigo-50/30 dark:bg-indigo-950/10 border-l-indigo-600' 
                                : 'border-l-transparent'
                        ]"
                    >
                        <div class="flex justify-between items-center">
                            <span class="font-mono text-xs font-black text-slate-800 dark:text-slate-200 uppercase">
                                {{ vehicle.registration }}
                            </span>
                            <span class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md border"
                                  :class="[
                                      vehicle.is_online
                                          ? 'text-emerald-650 bg-emerald-50 border-emerald-100'
                                          : 'text-slate-500 bg-slate-150 border-slate-200'
                                  ]"
                            >
                                {{ vehicle.is_online ? 'Online' : 'Offline' }}
                            </span>
                        </div>

                        <div class="flex justify-between items-center text-xs text-slate-500">
                            <span>{{ vehicle.vehicle_model }}</span>
                            <span class="font-bold text-slate-600 dark:text-slate-400">{{ vehicle.vehicle_type }}</span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 mt-1">
                            <div class="p-2 rounded-lg bg-slate-50/80 dark:bg-slate-950/50 border border-slate-100/50 dark:border-slate-900/30 flex items-center justify-between text-xs font-semibold">
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Speed</span>
                                <span class="font-mono text-slate-800 dark:text-slate-200">{{ vehicle.speed }} km/h</span>
                            </div>
                            <div class="p-2 rounded-lg bg-slate-50/80 dark:bg-slate-950/50 border border-slate-100/50 dark:border-slate-900/30 flex items-center justify-between text-xs font-semibold">
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Ignition</span>
                                <span class="font-bold" :class="[vehicle.ignition ? 'text-emerald-500' : 'text-rose-500']">
                                    {{ vehicle.ignition ? 'ON' : 'OFF' }}
                                </span>
                            </div>
                        </div>

                        <div v-if="vehicle.latitude === null" class="mt-2 text-[10px] font-bold text-rose-500 bg-rose-50/50 dark:bg-rose-950/10 p-2 rounded-lg border border-rose-100/30 flex items-center gap-1.5">
                            <SignalIcon class="w-4 h-4 shrink-0" />
                            <span>NO GPS COORDINATES RECEIVED YET</span>
                        </div>
                    </div>

                    <div v-if="filteredVehicles.length === 0" class="p-8 text-center text-slate-400 text-xs font-bold uppercase tracking-wider">
                        No active vehicles found
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
