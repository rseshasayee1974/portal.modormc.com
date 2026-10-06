<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

// Components
import Dialog from 'primevue/dialog';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import InwardTruckSelect from './InwardTruckSelect.vue';

import { CheckCircleIcon, ScaleIcon, PrinterIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';
import { useWeighbridge } from '@/Composables/useWeighbridge';

const props = withDefaults(defineProps<{ inward: any; vehicles?: any[]; units?: any[] }>(), {
    vehicles: () => [],
    units: () => [],
});
const data = ref({ ...props.inward });
const createdTrucks = ref<any[]>([]);
const truckOptions = computed(() => [
    ...props.vehicles,
    ...createdTrucks.value.filter(truck => !props.vehicles.some(vehicle => vehicle.value === truck.value)),
]);
const detailsSaving = ref(false);
const detailsErrors = ref<Record<string, string>>({});
const isBilled = computed(() => Boolean(data.value.is_billed));
const recalculateConversion = () => {
    if (Number(data.value.convert_volume) > 0) {
        data.value.conversion_quantity = Number((Number(data.value.received_qty || 0) / Number(data.value.convert_volume)).toFixed(4));
    }
};
const truckLabel = computed(() => truckOptions.value.find(vehicle => Number(vehicle.value) === Number(data.value.truck_id))?.label || (data.value.truck_id ? data.value.truck?.registration : 'External Vehicle'));
watch(() => props.inward, (inward) => { data.value = { ...inward }; });
const page = usePage();
const isManualWeightDisabled = computed(() => page.props.custom_settings?.batching?.manual_weight == 1);
const { isScaleConnected, captureWeight, captureCameraSnap } = useWeighbridge();
const imageModalVisible = ref(false);
const imageModalSrc = ref('');
const imageModalTitle = ref('');

const openImageModal = (src: string, title: string = 'Weight Snapshot') => {
    imageModalSrc.value = src;
    imageModalTitle.value = title;
    imageModalVisible.value = true;
};

const formatImageUrl = (img: any) => {
    if (!img) return null;
    if (typeof img === 'string') {
        if (img.startsWith('data:image') || img.startsWith('http://') || img.startsWith('https://')) return img;
        if (img.startsWith('/storage/')) return img;
        return '/storage/' + img.replace(/^\/+/, '');
    }
    if (img.url) {
        if (img.url.startsWith('data:image') || img.url.startsWith('http://') || img.url.startsWith('https://')) return img.url;
        if (img.url.startsWith('/storage/')) return img.url;
        return '/storage/' + String(img.url).replace(/^\/+/, '');
    }
    if (img.image_path) {
        const path = String(img.image_path).replace(/^\/+/, '');
        if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:image')) return path;
        return '/storage/' + path;
    }
    return null;
};

const getGrossPhotoUrl = (inward: any) => {
    if (!inward) return null;
    if (inward._loaded_snap) return inward._loaded_snap;
    if (inward.loaded_weight_photo) return formatImageUrl(inward.loaded_weight_photo);
    const img = inward.loaded_weight_image || inward.loadedWeightImage;
    return formatImageUrl(img);
};

const getTarePhotoUrl = (inward: any) => {
    if (!inward) return null;
    if (inward._empty_snap) return inward._empty_snap;
    if (inward.empty_weight_photo) return formatImageUrl(inward.empty_weight_photo);
    const img = inward.empty_weight_image || inward.emptyWeightImage;
    return formatImageUrl(img);
};

const saveGrossWeight = (inward: any, newWeight?: any, photo?: string | null) => {
    if (isBilled.value) return;
    const payload: any = {};
    if (newWeight !== undefined && newWeight !== null && newWeight !== '') {
        payload.truck_loaded = Number(newWeight);
    }
    const snapPhoto = photo === undefined ? inward._loaded_snap : photo;
    if (snapPhoto) {
        payload.loaded_weight_photo = snapPhoto;
    }
    if (Object.keys(payload).length === 0) return;

    router.post(route('inwards.update-weight', inward.id), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Gross weight & snapshot saved', showConfirmButton: false, timer: 1500 });
        },
        onError: (errs: any) => {
            console.error('Update gross weight error:', errs);
            const msg = typeof errs === 'object' ? Object.values(errs).flat().join(', ') : 'Failed to save gross weight.';
            Swal.fire('Error', msg, 'error');
        }
    });
};

const saveEmptyWeight = (inward: any, newWeight?: any, photo?: string | null) => {
    if (isBilled.value) return;
    const payload: any = {};
    if (newWeight !== undefined && newWeight !== null && newWeight !== '') {
        payload.truck_empty = Number(newWeight);
    }
    const snapPhoto = photo === undefined ? inward._empty_snap : photo;
    if (snapPhoto) {
        payload.empty_weight_photo = snapPhoto;
    }
    if (Object.keys(payload).length === 0) return;

    router.post(route('inwards.update-weight', inward.id), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Tare weight & snapshot saved', showConfirmButton: false, timer: 1500 });
        },
        onError: (errs: any) => {
            console.error('Update tare weight error:', errs);
            const msg = typeof errs === 'object' ? Object.values(errs).flat().join(', ') : 'Failed to save tare weight.';
            Swal.fire('Error', msg, 'error');
        }
    });
};

const captureTareWeight = async (inward: any) => {
    if (isBilled.value) return;
    await captureWeight(async (w: number) => {
        inward.truck_empty = w;

        let snap: string | null = null;
        const customSettings: any = page.props.custom_settings || {};
        if (customSettings?.batching?.camera == 1 && (customSettings?.batching?.camera_url || customSettings?.batching?.camera_url_1 || customSettings?.batching?.camera_url_2)) {
            const cameraUrl = customSettings.batching.camera_url_1 || customSettings.batching.camera_url || customSettings.batching.camera_url_2;
            try {
                snap = await captureCameraSnap(cameraUrl);
                inward._empty_snap = snap;
            } catch (err) {
                console.error('Tare camera capture failed:', err);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'warning',
                    title: 'Tare weight captured, but camera failed',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }

        saveEmptyWeight(inward, w, snap);
    });
};

const captureGrossWeight = async (inward: any) => {
    if (isBilled.value) return;
    await captureWeight(async (w: number) => {
        inward.truck_loaded = w;

        let snap: string | null = null;
        const customSettings: any = page.props.custom_settings || {};
        if (customSettings?.batching?.camera == 1 && (customSettings?.batching?.camera_url || customSettings?.batching?.camera_url_1 || customSettings?.batching?.camera_url_2)) {
            const cameraUrl = customSettings.batching.camera_url_2 || customSettings.batching.camera_url || customSettings.batching.camera_url_1;
            try {
                snap = await captureCameraSnap(cameraUrl);
                inward._loaded_snap = snap;
            } catch (err) {
                console.error('Gross camera capture failed:', err);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'warning',
                    title: 'Gross weight captured, but camera failed',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }

        saveGrossWeight(inward, w, snap);
    });
};

const saveInwardDetails = () => {
    const payload: Record<string, any> = {};
    for (const field of ['truck_id', 'received_qty', 'uom_id', 'convert_volume', 'conversion_uom_id', 'conversion_quantity', 'truck_loaded', 'truck_empty']) {
        const value = data.value[field] ?? null;
        const original = props.inward[field] ?? null;
        if (value !== original && (value === null || original === null || Number(value) !== Number(original))) {
            payload[field] = value;
        }
    }
    if (data.value._loaded_snap) payload.loaded_weight_photo = data.value._loaded_snap;
    if (data.value._empty_snap) payload.empty_weight_photo = data.value._empty_snap;
    if (!Object.keys(payload).length || detailsSaving.value) return;
    detailsErrors.value = {};
    detailsSaving.value = true;
    router.post(route('inwards.update-weight', data.value.id), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Inward details saved', showConfirmButton: false, timer: 1500 }),
        onError: (errors) => { detailsErrors.value = errors; },
        onFinish: () => { detailsSaving.value = false; },
    });
};
</script>

<template>
    <div class="p-3 bg-slate-50/60 border border-slate-200 rounded-xl shadow-xs space-y-3">
        <!-- Header & Action Toolbar -->
        <div
            class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-2.5 border-b border-slate-200/80 bg-white p-2.5 rounded-lg border border-slate-200">
            <div class="flex items-center gap-2.5">
                <div
                    class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white shrink-0 shadow-xs">
                    <ScaleIcon class="w-4 h-4" />
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-xs font-black text-slate-800 uppercase tracking-tight">Weighment Station</h3>
                        <span class="text-[10px] font-mono text-slate-500 font-bold">GRN: {{ data.inward_no
                        }}</span>
                        <span class="text-[10px] text-slate-500 font-bold">• Truck: {{ truckLabel }}</span>
                        <span v-if="Number(data.truck_loaded || 0) > 0 && Number(data.truck_empty || 0) > 0"
                            class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-emerald-100 text-emerald-700 border border-emerald-200">
                            Completed
                        </span>
                        <span v-else
                            class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-amber-100 text-amber-700 border border-amber-200 animate-pulse">
                            Pending Weigh-out
                        </span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2 shrink-0">
                <!-- <a :href="route('inwards.receipt', data.id)" target="_blank"
                    class="px-3 py-1.5 rounded-md font-bold text-[10px] uppercase flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-all text-decoration-none"
                    title="Print Receipt">
                    <PrinterIcon class="w-3.5 h-3.5" />
                    <span>Print</span>
                </a>

                <a :href="route('inwards.download-receipt', data.id)"
                    class="px-3 py-1.5 rounded-md font-bold text-[10px] uppercase flex items-center gap-1.5 bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 shadow-xs transition-all text-decoration-none"
                    title="Download PDF">
                    <ArrowDownTrayIcon class="w-3.5 h-3.5 text-slate-600" />
                    <span>PDF</span>
                </a> -->

                <button @click.stop="saveInwardDetails()" type="button" :disabled="isBilled"
                    class="px-3.5 py-1.5 rounded-md font-black text-sm uppercase flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition-all cursor-pointer border-0"
                    title="Save Changes">
                    <CheckCircleIcon class="w-3.5 h-3.5" />
                    <span>Save weights</span>
                </button>
            </div>
        </div>

        <div class="bg-white p-3 rounded-lg border border-slate-200 space-y-3">
            <h4 class="text-xs font-bold text-slate-700">Edit vehicle and units</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <InwardTruckSelect v-model="data.truck_id" :options="truckOptions" label="Vehicle / Truck"
                    placeholder="External vehicle" showClear :disabled="detailsSaving" :error="detailsErrors.truck_id"
                    @created="createdTrucks.push($event)" />
                <BaseSelect v-model="data.uom_id" :options="units" label="Received UOM" optionLabel="label"
                    optionValue="value" required :disabled="isBilled || detailsSaving" :error="detailsErrors.uom_id" />
                <BaseSelect v-model="data.conversion_uom_id" :options="units" label="Conversion UOM" optionLabel="label"
                    optionValue="value" showClear :disabled="isBilled || detailsSaving"
                    :error="detailsErrors.conversion_uom_id" />
                <div class="space-y-1">
                    <label :for="'inward-conversion-' + data.id" class="text-xs font-medium text-slate-600">Converted
                        quantity</label>
                    <input :id="'inward-conversion-' + data.id" v-model.number="data.conversion_quantity" type="number"
                        min="0" step="0.0001" :disabled="isBilled || detailsSaving"
                        class="w-full border border-slate-300 rounded-md text-sm" />
                    <p v-if="detailsErrors.conversion_quantity" class="text-xs text-red-600">{{
                        detailsErrors.conversion_quantity }}</p>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <p class="text-xs text-slate-500">{{ isBilled ? `Void the linked bills before changing receipt
                    quantities or units.` : `Received UOM changes move the receipt quantity to the selected stock unit.
                    Quantities are not converted automatically.` }}</p>
                <button type="button" @click="saveInwardDetails" :disabled="detailsSaving"
                    class="shrink-0 px-3 py-2 rounded-md bg-indigo-600 text-white text-xs font-bold disabled:opacity-50">{{
                        detailsSaving ? 'Saving…' : 'Save truck & units' }}</button>
            </div>
            <p v-if="detailsErrors.inward" class="text-xs text-red-600">{{ detailsErrors.inward }}</p>
        </div>

        <!-- Main Body Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">

            <!-- Panel 1: Gross & Tare Inputs (9 Cols) -->
            <div class="lg:col-span-9 flex flex-col md:flex-row gap-3">
                <!-- Gross (Loaded) Card -->
                <div class="w-full flex flex-col gap-2.5 bg-white p-3.5 rounded-lg border border-slate-200 shadow-2xs">
                    <div class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                        <label for="gross-input"
                            class="text-[10px] font-black uppercase tracking-wider text-slate-600">1. Gross
                            (Loaded)</label>
                    </div>

                    <div class="flex flex-row items-center justify-between gap-4 h-full w-full">
                        <!-- Input Wrapper -->
                        <div class="flex-1 flex items-center gap-2">
                            <input id="gross-input" step="any" type="number" v-model="data.truck_loaded" disabled
                                class="w-full bg-slate-50 border border-slate-300 rounded-md px-2.5 py-1.5 text-sm font-black text-slate-800 font-mono focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                placeholder="0.00" @keyup.enter="saveGrossWeight(data, data.truck_loaded)" />
                        </div>

                        <!-- Photo Thumbnail -->
                        <div v-if="getGrossPhotoUrl(data)"
                            class="flex items-center gap-2 shrink-0 border-l border-slate-200 pl-4 py-1">
                            <img :src="getGrossPhotoUrl(data)"
                                @click="openImageModal(getGrossPhotoUrl(data), 'Gross Weight Snap — ' + data.inward_no)"
                                class="w-24 h-12 object-cover rounded-md border border-slate-300 shadow-sm cursor-pointer hover:opacity-90 transition-opacity shrink-0"
                                alt="Gross Snap" />
                            <span
                                class="text-[9px] text-slate-500 font-bold uppercase tracking-wider leading-tight w-8">Gross
                                Snap</span>
                        </div>
                    </div>
                </div>

                <!-- Tare (Empty) Card -->
                <div class="w-full flex flex-col gap-2.5 bg-white p-3.5 rounded-lg border border-amber-200 shadow-2xs">
                    <div class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        <label for="tare-input"
                            class="text-[10px] font-black uppercase tracking-wider text-amber-700">2. Tare
                            (Empty)</label>
                    </div>

                    <div class="flex flex-row items-center justify-between gap-4 h-full w-full">
                        <!-- Input Wrapper -->
                        <div class="flex-1 flex items-center gap-2">
                            <input id="tare-input" step="any" type="number" v-model="data.truck_empty"
                                :disabled="isBilled || page.props.custom_settings?.batching?.manual_weight == 0"
                                class="w-full bg-slate-50 border border-amber-300 rounded-md px-2.5 py-1.5 text-sm font-black text-amber-900 font-mono focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                placeholder="0.00" @keyup.enter="saveEmptyWeight(data, data.truck_empty)" />

                            <button v-if="page.props.custom_settings?.batching?.manual_weight == 0"
                                @click.stop="captureTareWeight(data)" type="button" :disabled="isBilled" :class="[
                                    'relative px-3 py-1.5 rounded-md font-bold text-[10px] uppercase tracking-wider flex items-center gap-1.5 transition-all shadow-xs border cursor-pointer shrink-0 h-[38px]',
                                    isScaleConnected
                                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600 ring-2 ring-emerald-400/30'
                                        : 'bg-amber-500 hover:bg-amber-600 text-white border-amber-500'
                                ]"
                                :title="isScaleConnected ? 'Capture Tare Weight from Scale' : 'Connect Weighbridge'">
                                <!-- Pulsing Indicator Dot -->
                                <span v-if="!data.truck_empty || Number(data.truck_empty) === 0"
                                    class="absolute -top-1 -right-1 flex h-2.5 w-2.5">
                                    <span
                                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-300 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-400"></span>
                                </span>
                                <ArrowDownTrayIcon class="w-4 h-4 text-white animate-bounce" />
                                <span>Get Tare</span>
                            </button>
                        </div>

                        <!-- Photo Thumbnail -->
                        <div v-if="getTarePhotoUrl(data)"
                            class="flex items-center gap-2 shrink-0 border-l border-amber-200 pl-4 py-1">
                            <img :src="getTarePhotoUrl(data)"
                                @click="openImageModal(getTarePhotoUrl(data), 'Tare Weight Snap — ' + data.inward_no)"
                                class="w-24 h-12 object-cover rounded-md border border-amber-300 shadow-sm cursor-pointer hover:opacity-90 transition-opacity shrink-0"
                                alt="Tare Snap" />
                            <span
                                class="text-[9px] text-amber-700 font-bold uppercase tracking-wider leading-tight w-8">Tare
                                Snap</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel 2: Quantities Summary Stats (3 Cols) -->
            <div class="lg:col-span-3 flex flex-col gap-3 justify-start">
                <!-- Calculated Net -->
                <div
                    class="bg-emerald-50/80 p-3.5 rounded-lg border border-emerald-200/90 flex items-center justify-between shadow-2xs">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Net Weight</span>
                        <span class="text-[9px] text-emerald-600/70 font-bold uppercase mt-0.5">Gross - Tare</span>
                    </div>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-2xl font-black text-emerald-700 font-mono">
                            {{ Math.max(0, Number(data.truck_loaded || 0) - Number(data.truck_empty ||
                                0)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                        </span>
                        <span class="text-[10px] font-extrabold text-emerald-600 uppercase">{{ data.uom?.unit_code
                            }}</span>
                    </div>
                </div>

                <!-- Converted Qty -->
                <div v-show="Number(data.conversion_quantity || 0) > 0"
                    class="bg-indigo-50/80 p-3.5 rounded-lg border border-indigo-200/90 flex items-center justify-between shadow-2xs">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-wider text-indigo-800">Converted
                            Qty</span>
                        <span class="text-[9px] text-indigo-600/70 font-bold uppercase mt-0.5">In Stock Unit</span>
                    </div>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-2xl font-black text-indigo-800 font-mono">
                            {{ Number(data.conversion_quantity || 0).toLocaleString(undefined, {
                                minimumFractionDigits:
                                    2, maximumFractionDigits: 4
                            }) }}
                        </span>
                        <span class="text-[10px] font-extrabold text-indigo-600 uppercase">{{
                            data.conversion_uom?.unit_code || data.conversionUom?.unit_code || data.uom?.unit_code
                            }}</span>
                    </div>
                </div>
            </div>

        </div>

        <Dialog v-model:visible="imageModalVisible" modal :header="imageModalTitle"
            :style="{ width: '750px', maxWidth: '95vw' }"
            class="p-fluid rounded-2xl overflow-hidden shadow-2xl border-0">
            <div class="p-4 flex flex-col items-center justify-center bg-slate-950 rounded-xl">
                <img :src="imageModalSrc" class="w-full max-h-[75vh] object-contain rounded shadow-lg"
                    alt="Weight Snapshot" />
            </div>
        </Dialog>
    </div>
</template>
