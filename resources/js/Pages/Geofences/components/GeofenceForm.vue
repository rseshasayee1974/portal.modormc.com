<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import { MapIcon, TrashIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps<{
    form: any;
    shapes: { label: string; value: string }[];
    resetForm: () => void;
    submit: () => void;
}>();

const mapContainer = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let currentLayer: L.Layer | null = null;

// Drawing states
const drawingPoints = ref<[number, number][]>([]);
const circleCenter = ref<[number, number] | null>(null);
const circleRadius = ref<number>(200); // meters

const initMap = () => {
    if (!mapContainer.value) return;

    // Default center (Mumbai)
    const centerLat = 19.0760;
    const centerLng = 72.8777;

    map = L.map(mapContainer.value).setView([centerLat, centerLng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Map click handler for drawing
    map.on('click', (e: L.LeafletMouseEvent) => {
        const { lat, lng } = e.latlng;

        if (props.form.shape === 'circle') {
            circleCenter.value = [lat, lng];
            drawCurrentShape();
        } else if (props.form.shape === 'polygon') {
            drawingPoints.value.push([lat, lng]);
            drawCurrentShape();
        }
    });
};

const destroyMap = () => {
    if (map) {
        map.remove();
        map = null;
    }
    currentLayer = null;
};

const drawCurrentShape = () => {
    if (!map) return;

    if (currentLayer) {
        map.removeLayer(currentLayer);
        currentLayer = null;
    }

    if (props.form.shape === 'circle' && circleCenter.value) {
        currentLayer = L.circle(circleCenter.value, {
            radius: circleRadius.value,
            color: '#6366f1',
            fillColor: '#818cf8',
            fillOpacity: 0.4
        }).addTo(map);

        props.form.coordinates = {
            center: { lat: circleCenter.value[0], lng: circleCenter.value[1] },
            radius: circleRadius.value
        };
    } 
    else if (props.form.shape === 'polygon' && drawingPoints.value.length > 0) {
        if (drawingPoints.value.length === 1) {
            currentLayer = L.marker(drawingPoints.value[0]).addTo(map);
        } else {
            currentLayer = L.polygon(drawingPoints.value, {
                color: '#6366f1',
                fillColor: '#818cf8',
                fillOpacity: 0.4
            }).addTo(map);
        }

        props.form.coordinates = drawingPoints.value.map(pt => ({ lat: pt[0], lng: pt[1] }));
    }
};

const clearDrawing = () => {
    if (map && currentLayer) {
        map.removeLayer(currentLayer);
        currentLayer = null;
    }
    drawingPoints.value = [];
    circleCenter.value = null;
    props.form.coordinates = null;
};

watch(() => props.form.shape, () => {
    clearDrawing();
});

watch(circleRadius, () => {
    if (props.form.shape === 'circle' && circleCenter.value) {
        drawCurrentShape();
    }
});

onMounted(() => {
    nextTick(() => {
        initMap();
    });
});

onUnmounted(() => {
    destroyMap();
});
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Configuration Form -->
        <div class="lg:col-span-4 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800/80 p-6 rounded-2xl shadow-sm flex flex-col justify-between">
            <div class="space-y-6">
                <div class="flex items-center gap-2 mb-6 pb-4 border-b border-gray-100 dark:border-gray-800">
                    <MapIcon class="w-5 h-5 text-indigo-500" />
                    <span class="text-xs font-black uppercase text-gray-800 dark:text-gray-100 tracking-wider">Configure New Geofence</span>
                </div>

                <div class="space-y-4">
                    <div class="field-group">
                        <BaseInput v-model="form.name" label="Geofence Name *" placeholder="Main Entry Gate, Site Zone A..." :error="form.errors.name" />
                    </div>
                    <div class="field-group">
                        <BaseInput v-model="form.description" label="Description" placeholder="Notes about this zone..." :error="form.errors.description" />
                    </div>
                    <div class="field-group">
                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1.5 block">Boundary Shape *</label>
                        <BaseSelect v-model="form.shape" :options="shapes" optionLabel="label" optionValue="value" :error="form.errors.shape" />
                    </div>

                    <!-- Circular Settings -->
                    <div v-if="form.shape === 'circle'" class="p-4 bg-indigo-50/50 dark:bg-indigo-950/10 border border-indigo-100/50 dark:border-indigo-900/30 rounded-xl space-y-4">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-700 dark:text-slate-350">Radius:</span>
                            <span class="font-mono font-bold text-indigo-650">{{ circleRadius }} meters</span>
                        </div>
                        <input 
                            type="range" 
                            min="50" 
                            max="2000" 
                            step="50" 
                            v-model.number="circleRadius" 
                            class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-indigo-650"
                        />
                        <div class="text-[10px] text-slate-400 font-bold leading-normal uppercase inline-flex items-start gap-1">
                            <InformationCircleIcon class="w-4 h-4 text-indigo-500 shrink-0" />
                            <span>Click map to place circle center</span>
                        </div>
                    </div>

                    <!-- Polygon Settings -->
                    <div v-else class="p-4 bg-indigo-50/50 dark:bg-indigo-950/10 border border-indigo-100/50 dark:border-indigo-900/30 rounded-xl space-y-3">
                        <div class="text-[10px] text-slate-400 font-bold leading-normal uppercase inline-flex items-start gap-1">
                            <InformationCircleIcon class="w-4 h-4 text-indigo-500 shrink-0" />
                            <span>Click multiple points on the map to define polygon corners</span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="font-bold text-slate-700 dark:text-slate-350">Corners Selected:</span>
                            <span class="font-mono font-bold text-indigo-650">{{ drawingPoints.length }}</span>
                        </div>
                    </div>

                    <button 
                        type="button" 
                        @click="clearDrawing"
                        class="w-full h-10 border border-slate-200 hover:border-red-400 hover:bg-red-50/20 text-slate-500 hover:text-red-500 font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer flex items-center justify-center gap-2"
                    >
                        <TrashIcon class="w-4 h-4" />
                        Clear Drawing
                    </button>
                </div>
            </div>

            <BaseFormActions 
                :loading="form.processing"
                save-label="Save Geofence"
                mode="create"
                class="pt-6 border-t border-gray-100 dark:border-gray-800 mt-6"
                @cancel="resetForm"
                @submit="submit"
            />
        </div>

        <!-- The Interactive Drawing Map -->
        <div class="lg:col-span-8 bg-slate-100 dark:bg-slate-950 rounded-2xl overflow-hidden border border-slate-200/50 relative min-h-[500px] flex">
            <div ref="mapContainer" class="absolute inset-0 z-10 w-full h-full"></div>
        </div>
    </div>
</template>
