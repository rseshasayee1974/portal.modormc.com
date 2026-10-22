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

import { CheckCircleIcon, ScaleIcon, PrinterIcon, ArrowDownTrayIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline';
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
const receivedUomLabel = computed(() => props.units.find(unit => Number(unit.value) === Number(data.value.uom_id))?.label || data.value.uom?.unit_code || '');
const billsConvertedQuantity = computed(() => Number(data.value.conversion_quantity || 0) > 0);
const billingUomLabel = computed(() => {
    const unitId = billsConvertedQuantity.value ? data.value.conversion_uom_id : data.value.uom_id;
    return props.units.find(unit => Number(unit.value) === Number(unitId))?.label
        || (billsConvertedQuantity.value ? 'converted unit' : data.value.uom?.unit_code || 'received unit');
});
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

const remainingOrderedQty = computed(() => {
    const item = props.inward?.item;
    if (!item || item.product_quantity === undefined || item.product_quantity === null) {
        return null;
    }
    const totalOrdered = Number(item.product_quantity || 0);
    const totalReceived = Number(item.received_quantity || 0);
    const currentInwardQty = Number(props.inward.received_qty || 0);
    const otherReceived = Math.max(0, totalReceived - currentInwardQty);
    return Math.max(0, totalOrdered - otherReceived);
});

const computedNetWeight = computed(() => {
    const loaded = Number(data.value.truck_loaded || 0);
    const empty = Number(data.value.truck_empty || 0);
    if (empty > 0 && loaded > 0) {
        return Math.max(0, loaded - empty);
    }
    if (loaded > 0) {
        return loaded;
    }
    return Number(data.value.received_qty || 0);
});

const tareValidationError = computed(() => {
    const rawTare = data.value.truck_empty;
    const hasTareValue = rawTare !== null && rawTare !== undefined && rawTare !== '';
    const tare = hasTareValue ? Number(rawTare) : null;
    const loaded = Number(data.value.truck_loaded || 0);

    if (hasTareValue && tare !== null) {
        if (isNaN(tare)) {
            return 'Tare weight must be a valid number.';
        }
        if (tare < 0) {
            return 'Tare weight cannot be negative.';
        }
        if (loaded <= 0 && tare > 0) {
            return 'Gross (loaded) weight must be recorded before tare weight.';
        }
        if (loaded > 0 && tare >= loaded) {
            return `Tare weight must be less than gross weight (${loaded.toLocaleString(APP_LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}).`;
        }
    }

    // Check remaining ordered quantity constraint
    if (remainingOrderedQty.value !== null && loaded > 0) {
        const net = computedNetWeight.value;
        const remaining = remainingOrderedQty.value;
        if (net > remaining + 0.0001) {
            if (!hasTareValue || tare === 0) {
                return `Gross weight (${loaded.toLocaleString(APP_LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}) exceeds remaining order (${remaining.toLocaleString(APP_LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${receivedUomLabel.value}). Record empty truck weight.`;
            }
            return `Net received quantity (${net.toLocaleString(APP_LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${receivedUomLabel.value}) exceeds remaining ordered quantity (${remaining.toLocaleString(APP_LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${receivedUomLabel.value}).`;
        }
    }

    return null;
});

const tareError = computed(() => {
    return tareValidationError.value
        || detailsErrors.value.truck_empty
        || detailsErrors.value.received_qty
        || '';
});

const unhandledErrors = computed(() => {
    const errors: Record<string, string> = {};
    for (const [key, val] of Object.entries(detailsErrors.value)) {
        if (key !== 'truck_empty' && key !== 'received_qty') {
            errors[key] = val;
        }
    }
    return errors;
});

watch(() => data.value.truck_empty, () => {
    if (!tareValidationError.value) {
        if (detailsErrors.value.truck_empty) delete detailsErrors.value.truck_empty;
        if (detailsErrors.value.received_qty) delete detailsErrors.value.received_qty;
    }
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
                if (kind === 'empty') {
                    const loaded = Number(inward.truck_loaded || 0);
                    if (loaded > 0 && weight >= loaded) {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'error',
                            title: `Captured tare (${weight.toFixed(2)}) must be less than gross weight (${loaded.toFixed(2)})`,
                            showConfirmButton: false,
                            timer: 3000,
                        });
                    }
                }
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
    if (tareValidationError.value) {
        detailsErrors.value = {
            ...detailsErrors.value,
            truck_empty: tareValidationError.value,
        };
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: tareValidationError.value,
            showConfirmButton: false,
            timer: 2500,
        });
        return;
    }
    const payload: Record<string, any> = {};
    for (const field of ['truck_id', 'uom_id', 'received_qty', 'convert_volume', 'conversion_uom_id', 'conversion_quantity', 'truck_loaded', 'truck_empty']) {
        const value = data.value[field] ?? null;
        const original = props.inward[field] ?? null;
        if (value !== original && (value === null || original === null || Number(value) !== Number(original))) {
            payload[field] = value;
        }
    }
    if (data.value._loaded_snap) payload.loaded_weight_photo = data.value._loaded_snap;
    if (data.value._empty_snap) payload.empty_weight_photo = data.value._empty_snap;
    if (generateBill.value && canBillInward.value) {
        detailsErrors.value = {};
        if (!billForm.value.unit_price || Number(billForm.value.unit_price) <= 0) {
            detailsErrors.value = {
                'bill.unit_price': 'The rate must be greater than 0.'
            };
            return;
        }
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
        <!-- Header & Action Toolbar -->

        <div class="bg-white p-3 rounded-lg border border-slate-200 space-y-3">
            <h4 class="text-xs font-bold text-slate-700">Edit vehicle and units</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <InwardTruckSelect v-model="data.truck_id" :options="truckOptions" label="Vehicle / Truck" class="text-[10px]" placeholder="External vehicle" showClear :disabled="detailsSaving" :error="detailsErrors.truck_id" @created="createdTrucks.push($event)" />
                <BaseSelect v-model="data.uom_id" :disabled="isBilled || formBusy" :options="units" label="Actual UOM" optionLabel="label" optionValue="value" required :error="detailsErrors.uom_id" />
                <BaseSelect v-model="data.conversion_uom_id" :options="units" label="Conversion UOM" optionLabel="label" optionValue="value" showClear :disabled="isBilled || detailsSaving" :error="detailsErrors.conversion_uom_id" />
                <BaseInputNumber :model-value="Number(data.conversion_quantity || 0)" @update:model-value="data.conversion_quantity = $event" label="Converted quantity" :min="0" :minFractionDigits="2" :maxFractionDigits="2" :disabled="isBilled || detailsSaving" :error="detailsErrors.conversion_quantity" />
            </div>
            <!-- <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <p class="text-xs text-slate-500">Received UOM changes also update the stock unit. <span v-if="isBilled">Void the linked bills before changing receipt quantities or conversion units.</span></p>
            </div> -->
        </div>

        <div v-if="canBillInward" class="bg-white p-3 rounded-lg border border-indigo-200 space-y-3">
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 cursor-pointer">
                <input v-model="generateBill" type="checkbox" :disabled="formBusy"
                    class="rounded border-slate-300 text-indigo-600" />
                Generate purchase bill
            </label>
            <template v-if="generateBill">
                <!-- <p class="text-xs text-slate-500">Save will bill this inward's unbilled received quantity, including any weight changes made here.</p> -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <BaseSelect v-model="billForm.account_id" :options="accounts" label="Purchase account"
                        optionLabel="label" optionValue="value" placeholder="Select account" required filter
                        :disabled="formBusy" :error="detailsErrors['bill.account_id']" />
                    <BaseDatePicker v-model="billForm.invoice_date" label="Bill date" required :disabled="formBusy"
                        :error="detailsErrors['bill.invoice_date']" />
                    <BaseDatePicker v-model="billForm.due_date" label="Due date" :disabled="formBusy"
                        :error="detailsErrors['bill.due_date']" />
                    <BaseInputNumber v-model="billForm.unit_price" :label="`Rate per ${billingUomLabel}`"
                        :minFractionDigits="2" :maxFractionDigits="2" required :disabled="formBusy"
                        :error="detailsErrors['bill.unit_price']" />
                </div>
                <p class="text-xs text-indigo-600">Bill quantity: {{ Number(billsConvertedQuantity ? data.conversion_quantity : data.received_qty).toLocaleString(APP_LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} {{ billingUomLabel }} ({{ billsConvertedQuantity ? 'converted quantity' : 'received quantity' }}).</p>
                <!-- <p class="text-xs text-indigo-600">{{ data.order?.tax_inclusive ? 'The rate includes tax.' : 'Tax is added to the rate.' }} Purchase order discounts and charges apply.</p> -->
            </template>
        </div>
        <p v-else-if="isBilled" class="text-xs font-semibold text-emerald-700">This inward has already been billed.</p>
        <div v-if="Object.keys(unhandledErrors).length" role="alert"
            class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">
            <p v-for="(error, field) in unhandledErrors" :key="field">{{ error }}</p>
        </div>

        <!-- Main Body Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-stretch">

            <!-- Panel 1: Gross & Tare Inputs (9 Cols) -->
            <div class="lg:col-span-9 flex flex-col md:flex-row gap-3">
                <!-- Gross (Loaded) Card -->
                <div class="w-full flex-1 flex flex-col justify-between bg-white p-3.5 rounded-lg border border-slate-200 shadow-2xs min-h-[115px]">
                    <div class="flex items-center justify-between h-5">
                        <div class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                            <label for="gross-input"
                                class="text-[10px] font-black uppercase tracking-wider text-slate-600">1. Gross (Loaded)</label>
                        </div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Locked</span>
                    </div>

                    <div class="flex flex-col gap-1 w-full mt-auto pt-2">
                        <div class="flex flex-row items-center justify-between gap-3 h-10 w-full">
                            <div class="flex-1 flex items-center">
                                <input id="gross-input" step="any" type="number" v-model="data.truck_loaded" disabled
                                    class="w-full h-10 bg-slate-50 border border-slate-300 rounded-md px-3 text-sm font-black text-slate-700 font-mono"
                                    placeholder="0.00" />
                            </div>

                            <!-- Photo Thumbnail -->
                            <div v-if="getGrossPhotoUrl(data)"
                                class="flex items-center gap-2 shrink-0 border-l border-slate-200 pl-3">
                                <img :src="getGrossPhotoUrl(data)"
                                    @click="openImageModal(getGrossPhotoUrl(data), 'Gross Weight Snap — ' + data.inward_no)"
                                    class="w-20 h-10 object-cover rounded-md border border-slate-300 shadow-xs cursor-pointer hover:opacity-90 transition-opacity shrink-0"
                                    alt="Gross Snap" />
                                <span
                                    class="text-[9px] text-slate-500 font-bold uppercase tracking-wider leading-tight w-8">Gross Snap</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tare (Empty) Card -->
                <div :class="[
                    'w-full flex-1 flex flex-col justify-between bg-white p-3.5 rounded-lg border shadow-2xs transition-colors min-h-[115px]',
                    tareError ? 'border-red-300 ring-1 ring-red-200 bg-red-50/10' : 'border-slate-200 hover:border-amber-300'
                ]">
                    <div class="flex items-center justify-between h-5">
                        <div class="flex items-center gap-1.5">
                            <span :class="['w-1.5 h-1.5 rounded-full', tareError ? 'bg-red-500' : 'bg-amber-500']"></span>
                            <label for="tare-input"
                                :class="['text-[10px] font-black uppercase tracking-wider', tareError ? 'text-red-700' : 'text-amber-700']">
                                2. Tare (Empty)
                            </label>
                        </div>
                        <span v-if="Number(data.truck_loaded || 0) > 0"
                            :class="[
                                'text-[9px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wider border',
                                tareError ? 'bg-red-50 text-red-600 border-red-200' : 'bg-amber-50 text-amber-700 border-amber-200/70'
                            ]">
                            Must be &lt; {{ Number(data.truck_loaded).toLocaleString(APP_LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-1 w-full mt-auto pt-2">
                        <div class="flex flex-row items-center justify-between gap-3 h-10 w-full">
                            <div class="flex-1 flex items-center gap-2">
                                <input id="tare-input" step="any" type="number" v-model="data.truck_empty"
                                    :max="Number(data.truck_loaded || 0) > 0 ? Number(data.truck_loaded) - 0.001 : undefined"
                                    :disabled="isBilled || formBusy || page.props.custom_settings?.batching?.manual_weight == 0"
                                    :class="[
                                        'w-full h-10 rounded-md px-3 text-sm font-black font-mono transition-all',
                                        tareError
                                            ? 'bg-red-50 border border-red-400 text-red-900 focus:bg-white focus:ring-1 focus:ring-red-500 focus:border-red-500'
                                            : 'bg-white border border-slate-300 hover:border-amber-400 focus:border-amber-500 text-slate-900 focus:ring-1 focus:ring-amber-500'
                                    ]"
                                    placeholder="0.00" />

                                <button v-if="page.props.custom_settings?.batching?.manual_weight == 0"
                                    @click.stop="captureTareWeight(data)" type="button" :disabled="isBilled || formBusy"
                                    :class="[
                                        'relative px-3 rounded-md font-bold text-[9px] uppercase tracking-wider flex items-center gap-1.5 transition-all shadow-xs border cursor-pointer shrink-0 h-10',
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
                                    <ArrowDownTrayIcon class="w-3.5 h-3.5 text-white animate-bounce" />
                                    <span>Get Tare</span>
                                </button>
                            </div>

                            <!-- Photo Thumbnail -->
                            <div v-if="getTarePhotoUrl(data)"
                                class="flex items-center gap-2 shrink-0 border-l border-slate-200 pl-3">
                                <img :src="getTarePhotoUrl(data)"
                                    @click="openImageModal(getTarePhotoUrl(data), 'Tare Weight Snap — ' + data.inward_no)"
                                    class="w-20 h-10 object-cover rounded-md border border-slate-300 shadow-xs cursor-pointer hover:opacity-90 transition-opacity shrink-0"
                                    alt="Tare Snap" />
                                <span
                                    class="text-[9px] text-amber-700 font-bold uppercase tracking-wider leading-tight w-8">Tare Snap</span>
                            </div>
                        </div>

                        <!-- Tare Validation Error Message -->
                        <p v-if="tareError" class="text-[11px] font-bold text-red-600 flex items-center gap-1 mt-1 leading-tight">
                            <ExclamationCircleIcon class="w-3.5 h-3.5 shrink-0" />
                            <span>{{ tareError }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Panel 2: Quantities Summary Stats (3 Cols) -->
            <div class="lg:col-span-3 flex flex-col gap-3 justify-between">
                <!-- Calculated Net -->
                <div
                    class="flex-1 bg-emerald-50/90 p-3 rounded-lg border border-emerald-200/90 flex items-center justify-between shadow-2xs min-h-[52px]">
                    <div class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full" :class="tareError ? 'bg-red-500' : 'bg-emerald-500'"></span>
                        <span class="text-[10px] font-black uppercase tracking-wider" :class="tareError ? 'text-red-800' : 'text-emerald-800'">Net Weight</span>
                    </div>
                    <div class="flex items-baseline gap-1">
                        <span :class="['text-xl font-black font-mono', tareError ? 'text-red-600' : 'text-emerald-700']">
                            {{ tareValidationError ? '0.00' : Math.max(0, Number(data.truck_loaded || 0) - Number(data.truck_empty || 0)).toLocaleString(APP_LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                        </span>
                        <span class="text-[9px] font-extrabold text-emerald-600 uppercase">{{ receivedUomLabel }}</span>
                    </div>
                </div>

                <!-- Converted Qty -->
                <div
                    class="flex-1 bg-indigo-50/90 p-3 rounded-lg border border-indigo-200/90 flex items-center justify-between shadow-2xs min-h-[52px]">
                    <div class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                        <span class="text-[10px] font-black uppercase tracking-wider text-indigo-800">Converted Qty</span>
                    </div>
                    <div class="flex items-baseline gap-1">
                        <span class="text-xl font-black text-indigo-800 font-mono">
                            {{ Number(data.conversion_quantity || 0).toLocaleString(APP_LOCALE, {
                                minimumFractionDigits: 2, maximumFractionDigits: 2
                            }) }}
                        </span>
                        <span class="text-[9px] font-extrabold text-indigo-600 uppercase">{{ billingUomLabel }}</span>
                    </div>
                </div>
            </div>

        </div>
    </form>
    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 gap-3 bg-white rounded-lg border border-slate-200 shadow-2xs">
        <div class="flex-1 min-w-0">
            <p v-if="tareError" class="text-xs font-bold text-red-600 flex items-center gap-1.5">
                <ExclamationCircleIcon class="w-4 h-4 shrink-0 text-red-500" />
                <span>{{ tareError }}</span>
            </p>
            <p v-else-if="remainingOrderedQty !== null && Number(data.truck_loaded || 0) > 0" class="text-xs text-slate-500 flex items-center gap-1.5">
                <span class="text-slate-400 font-medium">Remaining ordered quantity:</span>
                <span class="font-bold text-slate-700 font-mono">{{ remainingOrderedQty.toLocaleString(APP_LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} {{ receivedUomLabel }}</span>
            </p>
        </div>

        <button type="submit" :form="`inward-edit-${data.id}`" :disabled="formBusy || Boolean(tareValidationError)"
            class="px-5 py-2 rounded-md font-black text-xs uppercase tracking-wider flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition-all cursor-pointer border-0 disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
            :title="tareValidationError ? tareValidationError : 'Save Changes'">
            <CheckCircleIcon class="w-4 h-4" />
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
