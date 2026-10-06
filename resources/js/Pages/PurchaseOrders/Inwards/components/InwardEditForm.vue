<script setup lang="ts">
import { APP_LOCALE } from '@/Utils/locale';
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

// Components
import Dialog from 'primevue/dialog';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import { entityToday } from '@/Utils/entityDateTime';
import InwardTruckSelect from './InwardTruckSelect.vue';

import { CheckCircleIcon, ScaleIcon, PrinterIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';
import { useWeighbridge } from '@/Composables/useWeighbridge';

const props = withDefaults(defineProps<{ inward: any; vehicles?: any[]; units?: any[]; accounts?: any[]; canGenerateBill?: boolean }>(), {
    vehicles: () => [],
    units: () => [],
    accounts: () => [],
    canGenerateBill: false,
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
const generateBill = ref(false);
const billForm = ref({
    account_id: null,
    invoice_date: entityToday(),
    due_date: props.inward.order?.due_date?.slice(0, 10) || entityToday(),
    unit_price: Number(props.inward.item?.unit_price || 0),
});
const canBillInward = computed(() => props.canGenerateBill && !isBilled.value
    && Number(data.value.truck_loaded || 0) > 0
    && ['approved', 'billed', 'received'].includes(String(data.value.order?.state || '').toLowerCase()));
const capturingWeight = ref(false);
const capturingPhoto = ref(false);
const formBusy = computed(() => detailsSaving.value || capturingWeight.value || capturingPhoto.value);
watch(canBillInward, (eligible) => { if (!eligible) generateBill.value = false; });
const recalculateConversion = () => {
    if (Number(data.value.convert_volume) > 0) {
        data.value.conversion_quantity = Number((Number(data.value.received_qty || 0) / Number(data.value.convert_volume)).toFixed(2));
    }
};
const truckLabel = computed(() => truckOptions.value.find(vehicle => Number(vehicle.value) === Number(data.value.truck_id))?.label || (data.value.truck_id ? data.value.truck?.registration : 'External Vehicle'));
watch(() => props.inward, (inward) => {
    if (!detailsSaving.value) data.value = { ...inward };
});
const page = usePage();
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

const captureInwardWeight = async (inward: any, kind: 'loaded' | 'empty') => {
    if (isBilled.value || formBusy.value) return;
    capturingWeight.value = true;
    try {
        await captureWeight(async (weight: number) => {
            capturingPhoto.value = true;
            try {
                inward[kind === 'loaded' ? 'truck_loaded' : 'truck_empty'] = weight;
                const settings: any = page.props.custom_settings?.batching || {};
                const cameraUrl = kind === 'loaded'
                    ? settings.camera_url_2 || settings.camera_url || settings.camera_url_1
                    : settings.camera_url_1 || settings.camera_url || settings.camera_url_2;
                if (settings.camera == 1 && cameraUrl) {
                    try {
                        inward[kind === 'loaded' ? '_loaded_snap' : '_empty_snap'] = await captureCameraSnap(cameraUrl);
                    } catch (error) {
                        console.error('Weight camera capture failed:', error);
                        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Weight captured, but camera failed', showConfirmButton: false, timer: 1500 });
                    }
                }
            } finally {
                capturingPhoto.value = false;
            }
        });
    } finally {
        capturingWeight.value = false;
    }
};

const captureTareWeight = (inward: any) => captureInwardWeight(inward, 'empty');
const captureGrossWeight = (inward: any) => captureInwardWeight(inward, 'loaded');

const saveInwardDetails = () => {
    if (formBusy.value) return;
    const payload: Record<string, any> = {};
    for (const field of ['truck_id', 'received_qty', 'convert_volume', 'conversion_uom_id', 'conversion_quantity', 'truck_loaded', 'truck_empty']) {
        const value = data.value[field] ?? null;
        const original = props.inward[field] ?? null;
        if (value !== original && (value === null || original === null || Number(value) !== Number(original))) {
            payload[field] = value;
        }
    }
    if (data.value._loaded_snap) payload.loaded_weight_photo = data.value._loaded_snap;
    if (data.value._empty_snap) payload.empty_weight_photo = data.value._empty_snap;
    if (generateBill.value && canBillInward.value) {
        payload.generate_bill = true;
        payload.bill = { ...billForm.value };
    }
    if (!Object.keys(payload).length) return;
    detailsErrors.value = {};
    detailsSaving.value = true;
    router.post(route('inwards.update-weight', data.value.id), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            data.value = { ...props.inward };
            generateBill.value = false;
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: payload.generate_bill ? 'Inward saved and purchase bill generated' : 'Inward details saved', showConfirmButton: false, timer: 1500 });
        },
        onError: (errors) => { detailsErrors.value = errors; },
        onFinish: () => { detailsSaving.value = false; },
    });
};
</script>

<template>
    <form :id="`inward-edit-${data.id}`" @submit.prevent="saveInwardDetails" class="space-y-2">
        <!-- Header & Action Toolbar -->
        <!-- <div
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

            <div class="flex items-center gap-2 shrink-0"> -->
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

                <!-- <button type="submit" :disabled="formBusy"
                    class="px-3.5 py-1.5 rounded-md font-black text-sm uppercase flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition-all cursor-pointer border-0 disabled:opacity-50"
                    title="Save Changes">
                    <CheckCircleIcon class="w-3.5 h-3.5" />
                    <span>{{ detailsSaving ? 'Saving…' : generateBill ? 'Save & Generate Bill' : 'Save' }}</span>
                </button> -->
            <!-- </div>
        </div> -->

        <div class="bg-white p-3 rounded-lg border border-slate-200 space-y-3">
            <h4 class="text-xs font-bold text-slate-700">Edit vehicle and units</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <InwardTruckSelect v-model="data.truck_id" :options="truckOptions" label="Vehicle / Truck" class="text-[10px]" placeholder="External vehicle" showClear :disabled="detailsSaving" :error="detailsErrors.truck_id" @created="createdTrucks.push($event)" />
                <BaseSelect :model-value="data.uom_id" :options="units" label="Received UOM" optionLabel="label" optionValue="value" required disabled :error="detailsErrors.uom_id" />
                <BaseSelect v-model="data.conversion_uom_id" :options="units" label="Conversion UOM" optionLabel="label" optionValue="value" showClear :disabled="isBilled || detailsSaving" :error="detailsErrors.conversion_uom_id" />
                <div class="space-y-1">
                    <label :for="'inward-conversion-' + data.id" class="text-xs font-medium text-slate-600">Converted quantity</label>
                    <input :id="'inward-conversion-' + data.id" v-model.number="data.conversion_quantity" type="number" min="0" step="0.01" :disabled="isBilled || detailsSaving" class="w-full border border-slate-300 rounded-md text-sm" />
                    <p v-if="detailsErrors.conversion_quantity" class="text-xs text-red-600">{{ detailsErrors.conversion_quantity }}</p>
                </div>
            </div>
            <!-- <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <p class="text-xs text-slate-500">Received UOM is fixed when the inward is created and cannot be changed. <span v-if="isBilled">Void the linked bills before changing receipt quantities or conversion units.</span></p>
            </div> -->
        </div>

        <div v-if="canBillInward" class="bg-white p-3 rounded-lg border border-indigo-200 space-y-3">
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 cursor-pointer">
                <input v-model="generateBill" type="checkbox" :disabled="formBusy" class="rounded border-slate-300 text-indigo-600" />
                Generate purchase bill
            </label>
            <template v-if="generateBill">
                <!-- <p class="text-xs text-slate-500">Save will bill this inward's unbilled received quantity, including any weight changes made here.</p> -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <BaseSelect v-model="billForm.account_id" :options="accounts" label="Purchase account" optionLabel="label" optionValue="value" placeholder="Select account" required filter :disabled="formBusy" :error="detailsErrors['bill.account_id']" />
                    <BaseDatePicker v-model="billForm.invoice_date" label="Bill date" required :disabled="formBusy" :error="detailsErrors['bill.invoice_date']" />
                    <BaseDatePicker v-model="billForm.due_date" label="Due date" :disabled="formBusy" :error="detailsErrors['bill.due_date']" />
                    <BaseInputNumber v-model="billForm.unit_price" :label="`Rate per ${data.order?.items?.find((item: any) => item.id === data.order_item_id)?.uom?.unit_code || 'PO unit'}`" :min="0" :minFractionDigits="2" :maxFractionDigits="2" required :disabled="formBusy" :error="detailsErrors['bill.unit_price']" />
                </div>
                <!-- <p class="text-xs text-indigo-600">{{ data.order?.tax_inclusive ? 'The rate includes tax.' : 'Tax is added to the rate.' }} Purchase order discounts and charges apply.</p> -->
            </template>
        </div>
        <p v-else-if="isBilled" class="text-xs font-semibold text-emerald-700">This inward has already been billed.</p>
        <div v-if="Object.keys(detailsErrors).length" role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">
            <p v-for="(error, field) in detailsErrors" :key="field">{{ error }}</p>
        </div>

        <!-- Main Body Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">

            <!-- Panel 1: Gross & Tare Inputs (7 Cols) -->
            <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <!-- Gross (Loaded) Card -->
                <div class="flex flex-col gap-2 bg-white p-3 rounded-lg border border-slate-200 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[10px] font-black uppercase tracking-wider text-slate-600 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                            1. Gross (Loaded)
                        </span>
                        <!-- <span
                            class="text-[9px] font-mono text-slate-400 font-extrabold uppercase bg-slate-100 px-1.5 py-0.5 rounded">{{
                                data.uom?.unit_code }}</span> -->
                    </div>

                    <div class="flex items-center gap-2">
                        <input step="any" type="number" v-model="data.truck_loaded" disabled
                            class="w-full bg-slate-50 border border-slate-300 rounded-md px-2.5 py-1.5 text-sm font-black text-slate-800 font-mono focus:bg-white focus:ring-1 focus:ring-indigo-500"
                            placeholder="0.00" />

                        <!-- <button v-if="page.props.custom_settings?.batching?.manual_weight == 0"
                            @click.stop="captureGrossWeight(data)" type="button" :class="[
                                'relative px-2.5 py-1.5 rounded-md font-bold text-[9px] uppercase tracking-wider flex items-center gap-1 transition-all shadow-xs border cursor-pointer shrink-0 h-9',
                                isScaleConnected
                                    ? 'bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600 ring-2 ring-emerald-400/30'
                                    : 'bg-indigo-600 hover:bg-indigo-700 text-white border-indigo-600'
                            ]" :title="isScaleConnected ? 'Capture Gross Weight from Scale' : 'Connect Weighbridge'">
                        <span v-if="!data.truck_loaded || Number(data.truck_loaded) === 0"
                            class="absolute -top-1 -right-1 flex h-2.5 w-2.5">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-300 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-indigo-400"></span>
                        </span>
                        <ArrowDownTrayIcon class="w-3.5 h-3.5 text-white animate-bounce" />
                        <span>Get Gross</span>
                        </button> -->
                    </div>

                    <!-- Photo Thumbnail -->
                    <div v-if="getGrossPhotoUrl(data)" class="flex items-center gap-2 pt-1.5 border-t border-slate-100">
                        <img :src="getGrossPhotoUrl(data)"
                            @click="openImageModal(getGrossPhotoUrl(data), 'Gross Weight Snap — ' + data.inward_no)"
                            class="w-12 h-9 object-cover rounded-md border border-slate-300 cursor-pointer hover:opacity-90 transition-opacity shrink-0"
                            alt="Gross Snap" />
                        <span class="text-[9px] text-slate-500 font-bold uppercase tracking-wider">Gross Snap</span>
                    </div>
                </div>

                <!-- Tare (Empty) Card -->
                <div class="flex flex-col gap-2 bg-white p-3 rounded-lg border border-amber-200 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[10px] font-black uppercase tracking-wider text-amber-700 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            2. Tare (Empty)
                        </span>
                        <!-- <span
                            class="text-[9px] font-mono text-amber-600 font-extrabold uppercase bg-amber-50 px-1.5 py-0.5 rounded border border-amber-100">{{
                                data.uom?.unit_code }}</span> -->
                    </div>

                    <div class="flex items-center gap-2">
                        <input step="any" type="number" v-model="data.truck_empty"
                            :disabled="isBilled || formBusy || page.props.custom_settings?.batching?.manual_weight == 0"
                            class="w-full bg-slate-50 border border-amber-300 rounded-md px-2.5 py-1.5 text-sm font-black text-amber-900 font-mono focus:bg-white focus:ring-1 focus:ring-amber-500"
                            placeholder="0.00" />

                        <button v-if="page.props.custom_settings?.batching?.manual_weight == 0"
                            @click.stop="captureTareWeight(data)" type="button" :disabled="isBilled || formBusy" :class="[
                                'relative px-2.5 py-1.5 rounded-md font-bold text-[9px] uppercase tracking-wider flex items-center gap-1 transition-all shadow-xs border cursor-pointer shrink-0 h-9',
                                isScaleConnected
                                    ? 'bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600 ring-2 ring-emerald-400/30'
                                    : 'bg-amber-500 hover:bg-amber-600 text-white border-amber-500'
                            ]" :title="isScaleConnected ? 'Capture Tare Weight from Scale' : 'Connect Weighbridge'">
                            <!-- Pulsing Indicator Dot -->
                            <span v-if="!data.truck_empty || Number(data.truck_empty) === 0"
                                class="absolute -top-1 -right-1 flex h-2.5 w-2.5">
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-300 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-400"></span>
                            </span>
                            <ArrowDownTrayIcon class="w-3.5 h-3.5 text-white animate-bounce" />
                            <span>Get Tare</span>
                        </button>
                    </div>

                    <!-- Photo Thumbnail -->
                    <div v-if="getTarePhotoUrl(data)" class="flex items-center gap-2 pt-1.5 border-t border-amber-100">
                        <img :src="getTarePhotoUrl(data)"
                            @click="openImageModal(getTarePhotoUrl(data), 'Tare Weight Snap — ' + data.inward_no)"
                            class="w-12 h-9 object-cover rounded-md border border-amber-300 cursor-pointer hover:opacity-90 transition-opacity shrink-0"
                            alt="Tare Snap" />
                        <span class="text-[9px] text-amber-700 font-bold uppercase tracking-wider">Tare Snap</span>
                    </div>
                </div>
            </div>

            <!-- Panel 2: Quantities Summary Stats (5 Cols) -->
            <div class="lg:col-span-5 grid grid-cols-3 gap-2">
                <!-- Calculated Net -->
                <div
                    class="bg-emerald-50/80 p-2.5 rounded-lg border border-emerald-200/90 flex flex-col justify-between shadow-2xs">
                    <span class="text-[9px] font-black uppercase tracking-wider text-emerald-800">Net Weight</span>
                    <div class="my-1 flex items-baseline gap-1">
                        <span class="text-base font-black text-emerald-700 font-mono">
                            {{ Math.max(0, Number(data.truck_loaded || 0) - Number(data.truck_empty ||
                                0)).toLocaleString(APP_LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                        </span>
                        <span class="text-[9px] font-extrabold text-emerald-600 uppercase">{{ data.uom?.unit_code
                        }}</span>
                    </div>
                    <span class="text-[8px] text-emerald-700 font-extrabold uppercase">Gross - Tare</span>
                </div>

                <!-- Accepted Qty -->
                <!-- <div class="bg-white p-2.5 rounded-lg border border-slate-200 flex flex-col justify-between shadow-2xs">
                    <span class="text-[9px] font-black uppercase tracking-wider text-slate-600">Accepted Qty</span>
                    <div class="my-1 flex items-baseline gap-1">
                        <span class="text-base font-black text-slate-800 font-mono">
                            {{ Number(data.received_qty || 0).toLocaleString(APP_LOCALE, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }) }}
                        </span>

                    </div>
                    <span class="text-[8px] text-slate-400 font-extrabold uppercase">In Stock</span>
                </div> -->

                <!-- Converted Qty -->
                <div
                    class="bg-indigo-50/80 p-2.5 rounded-lg border border-indigo-200/90 flex flex-col justify-between shadow-2xs">
                    <span class="text-[9px] font-black uppercase tracking-wider text-indigo-800">Converted Qty</span>
                    <div class="my-1 flex items-baseline gap-1">
                        <span class="text-base font-black text-indigo-800 font-mono">
                            {{ Number(data.conversion_quantity || 0).toLocaleString(APP_LOCALE, {
                                minimumFractionDigits: 2, maximumFractionDigits: 4
                            }) }}
                        </span>
                        <span class="text-[9px] font-extrabold text-indigo-600 uppercase">{{
                            data.conversion_uom?.unit_code
                            || data.conversionUom?.unit_code || data.uom?.unit_code }}</span>
                    </div>
                    <span class="text-[8px] text-indigo-700 font-extrabold uppercase">Converted</span>
                </div>
            </div>

        </div>
    </form>
  <!-- Action Buttons -->
            <div class="flex justify-end p-3 gap-2 shrink-0">
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

                <button type="submit" :form="`inward-edit-${data.id}`" :disabled="formBusy"
                    class="px-3.5 py-1.5 rounded-md font-black text-sm uppercase flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition-all cursor-pointer border-0 disabled:opacity-50"
                    title="Save Changes">
                    <CheckCircleIcon class="w-3.5 h-3.5" />
                    <span>{{ detailsSaving ? 'Saving…' : generateBill ? 'Save & Generate Bill' : 'Save' }}</span>
                </button>
            </div>
    <Dialog v-model:visible="imageModalVisible" modal :header="imageModalTitle"
        :style="{ width: '750px', maxWidth: '95vw' }" class="p-fluid rounded-2xl overflow-hidden shadow-2xl border-0">
        <div class="p-4 flex flex-col items-center justify-center bg-slate-950 rounded-xl">
            <img :src="imageModalSrc" class="w-full max-h-[75vh] object-contain rounded shadow-lg"
                alt="Weight Snapshot" />
        </div>
    </Dialog>
</template>
