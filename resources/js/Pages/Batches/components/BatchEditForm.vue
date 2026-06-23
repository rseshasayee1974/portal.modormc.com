<script setup lang="ts">
import { useForm, usePage, router } from '@inertiajs/vue3';
import { computed, watch, ref, onMounted, onUnmounted } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import Button from 'primevue/button';
import axios from 'axios';
import Swal from 'sweetalert2';
import { CubeIcon, InformationCircleIcon, BeakerIcon, ListBulletIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';
import { useWeighbridge } from '@/Composables/useWeighbridge';
import BatchSheetUploader from './BatchSheetUploader.vue';

interface BatchMaterial {
    id?: number;
    product_id: number | null;
    material_name: string;
    target_qty: number;
    actual_qty: number;
    deviation_quantity?: number;
    uom_id: number | null;
    product?: { title: string };
}

const props = withDefaults(defineProps<{
    batch?: any;
    salesOrders?: any[];
    trucks?: any[];
    transporters?: any[];
    sales_executives?: any[];
    drivers?: any[];
    products?: any[];
    uoms?: any[];
    statuses?: { label: string; value: number }[];
    concretePumpOptions?: any[];
    onSaved?: (payload?: { batchId: number, type: 'batching' | 'dispatch' }) => void;
}>(), {
    batch: () => ({}),
    salesOrders: () => [],
    trucks: () => [],
    transporters: () => [],
    sales_executives: () => [],
    drivers: () => [],
    products: () => [],
    uoms: () => [],
    statuses: () => [],
    concretePumpOptions: () => [],
}); 
const emit = defineEmits<{
    (e: 'saved', payload?: { batchId: number, type: 'batching' | 'dispatch' }): void;
    (e: 'cancel'): void;
}>();



const blankMaterial = (): BatchMaterial => ({
    product_id: null,
    material_name: '',
    target_qty: 0,
    actual_qty: 0,
    deviation_quantity: 0,
    uom_id: null,
});

const form = useForm({
    sales_order_id: props.batch?.sales_order_id ?? null,
    batch_no: props.batch?.batch_no ?? null,
    batch_size: Number(props.batch?.batch_size ?? 1),
    truck_id: props.batch?.dispatches?.[0]?.truck_id ?? null,
    transport_id: props.batch?.dispatches?.[0]?.transport_id ?? null,
    driver_id: props.batch?.dispatches?.[0]?.driver_id ?? null,
    sales_executive_id: props.batch?.dispatches?.[0]?.sales_executive_id ?? null,
    concrete_pump: props.batch?.dispatches?.[0]?.concrete_pump ?? null,
    empty_weight_truck: Number(props.batch?.dispatches?.[0]?.empty_weight_truck ?? 0),
    loaded_weight_truck: Number(props.batch?.dispatches?.[0]?.loaded_weight_truck ?? 0),
        
    loaded_weight_photo: null as string | null,
    net_weight: Number(props.batch?.dispatches?.[0]?.net_weight ?? 0),
    uom_id: props.batch?.uom_id ?? props.uoms?.find((u: any) => String(u.unit_code).toUpperCase() === 'CBM')?.id ?? null,
    status: Number(props.batch?.status ?? 1),
    start_time: props.batch?.start_time ? new Date(props.batch.start_time) : new Date(),
    end_time: props.batch?.end_time ? new Date(props.batch.end_time) : new Date(),
    empty_time: props.batch?.dispatches?.[0]?.empty_time ? new Date(props.batch.dispatches[0].empty_time) : new Date(),
    load_time: props.batch?.dispatches?.[0]?.load_time ? new Date(props.batch.dispatches[0].load_time) : new Date(),
    materials: (() => {
        let initialMaterials = props.batch?.materials; 
        return ((initialMaterials?.length ?? 0) > 0 ? initialMaterials : [blankMaterial()]).map((item: any) => ({
            id: item.id ?? null,
            product_id: item.product_id,
            material_name: item.material_name || item.product?.title || '',
            target_qty: Number(item.target_qty || 0),
            actual_qty: Number(item.actual_qty || 0),
            deviation_quantity: Number(item.deviation_quantity ?? (Number(item.actual_qty || 0) - Number(item.target_qty || 0))),
            uom_id: item.uom_id ?? null,
        }));
    })(),
});

console.log('batcjh==',props);

const page = usePage();
const customSettings = page.props.custom_settings as any;

const isMetricTon = computed(() => {
    return customSettings?.batching?.InvoiceInMetricTon == 1;
});

const isLocked = computed(() => {
    return form.status === 3;
});

const selectedSalesOrder = computed(() => {
    if (props.batch?.sales_order && props.batch.sales_order.id === form.sales_order_id) {
        return props.batch.sales_order;
    }
    return props.salesOrders.find(wo => wo.id === form.sales_order_id);
});

const salesOrderDetails = computed(() => {
    if (!selectedSalesOrder.value) return [];
    const wo = selectedSalesOrder.value;
    return [
        { label: 'Customer', value: wo.customer?.legal_name || 'N/A' },
        { label: 'Site', value: wo.site?.name || 'N/A' },
        { label: 'Design', value: wo.mix_design?.design_name || 'N/A' },
        { label: 'Grade/Ratio', value: wo.mix_design?.concrete_grade?.name 
            ? `${wo.mix_design.concrete_grade.name}${wo.mix_design.concrete_grade.concrete_ratio ? ` (${wo.mix_design.concrete_grade.concrete_ratio})` : ''}` 
            : (wo.mix_design?.grade || 'N/A') 
        },
        { label: 'Total Qty', value: `${wo.produced_qty} / ${wo.total_qty} m³` },
    ];
});

watch(() => form.batch_size, (newVal) => {
    if (form.sales_order_id && selectedSalesOrder.value?.mix_design?.items) {
        form.materials.forEach((mat) => {
            const originalItem = selectedSalesOrder.value.mix_design.items.find((item: any) => item.product_id === mat.product_id);
            if (originalItem) {
                mat.target_qty = Number(originalItem.cross_quantity || originalItem.actual_quantity || originalItem.quantity || 0) * newVal;
            }
        });
    }
});

// watch(() => form.truck_id, (newVal, oldVal) => {
//     // Only reset if it's an actual change by the user, not the initial load of the edit form
//     if (oldVal !== undefined && oldVal !== null) {
//         form.empty_weight_truck = 0;
//     }
// });

watch(() => [form.empty_weight_truck, form.loaded_weight_truck], ([emptyWt, loadedWt]) => {
    form.net_weight = (Number(loadedWt) || 0) - (Number(emptyWt) || 0);
});

const addMaterial = () => form.materials.push(blankMaterial());
const removeMaterial = (index: number) => {
    if (form.materials.length > 1) form.materials.splice(index, 1);
};

watch(() => form.materials, (newMaterials) => {
    newMaterials.forEach(m => {
        m.deviation_quantity = Number(m.actual_qty || 0) - Number(m.target_qty || 0);
    });
}, { deep: true });

// Auto-fill material_name when product_id is selected
watch(
    () => form.materials.map(m => m.product_id),
    (newIds) => {
        newIds.forEach((productId, index) => {
            if (productId && !form.materials[index].material_name) {
                const product = props.products.find((p: any) => p.id === productId);
                if (product) form.materials[index].material_name = product.title;
            }
        });
    }
);

const applyBatchToForm = (newBatch: any) => {
    if (!newBatch) return;

    form.sales_order_id = newBatch.sales_order_id ?? null;
    form.batch_no = newBatch.batch_no ?? null;
    form.batch_size = Number(newBatch.batch_size ?? 1);
    
    const dispatch = newBatch.dispatches?.[0];
    form.truck_id = dispatch?.truck_id ?? null;
    form.transport_id = dispatch?.transport_id ?? null;
    form.driver_id = dispatch?.driver_id ?? null;
    form.sales_executive_id = dispatch?.sales_executive_id ?? null;
    form.concrete_pump = dispatch?.concrete_pump ?? null;
    form.empty_weight_truck = Number(dispatch?.empty_weight_truck ?? 0);
    form.loaded_weight_truck = Number(dispatch?.loaded_weight_truck ?? 0);
    form.net_weight = Number(dispatch?.net_weight ?? 0);
    form.empty_time = dispatch?.empty_time ? new Date(dispatch.empty_time) : new Date();
    form.load_time = dispatch?.load_time ? new Date(dispatch.load_time) : new Date();
    
    form.uom_id = newBatch.uom_id ?? props.uoms?.find((u: any) => String(u.unit_code).toUpperCase() === 'CBM')?.id ?? null;
    form.status = Number(newBatch.status ?? 1);
    form.start_time = newBatch.start_time ? new Date(newBatch.start_time) : new Date();
    form.end_time = newBatch.end_time ? new Date(newBatch.end_time) : new Date();
    
    let initialMaterials = newBatch?.materials;
    // console.log('[BatchEditForm] applyBatchToForm — batch.id:', newBatch?.id, '| materials count:', initialMaterials?.length, '| raw materials:', JSON.stringify(initialMaterials));

    form.materials = ((initialMaterials?.length ?? 0) > 0 ? initialMaterials : [blankMaterial()]).map((item: any) => ({
        id: item.id ?? null,
        product_id: item.product_id,
        material_name: item.material_name || item.label || item.product?.title || '',
        target_qty: Number(item.target_qty ?? 0),
        actual_qty: Number(item.actual_qty ?? 0),
        deviation_quantity: Number(item.deviation_quantity ?? (Number(item.actual_qty ?? 0) - Number(item.target_qty ?? 0))),
        uom_id: item.uom_id ?? null,
    }));
    // console.log('[BatchEditForm] form.materials set to:', JSON.parse(JSON.stringify(form.materials)));
};

// Run on mount with whatever data is available at render time
applyBatchToForm(props.batch);

/**
 * Fire whenever the batch OBJECT REFERENCE changes.
 * This covers:
 *   - Switching from one expanded row to another (id changes)
 *   - The async upgrade: slotProps.data  →  detailedBatches[id]
 *     (same id, different object reference — the materials key appears)
 */ 
watch(
    () => props.batch,
    (newBatch) => applyBatchToForm(newBatch),
    { deep: false }  // shallow — only fires when the object reference itself changes
);

/**
 * Extra safety: if Vue decides to reuse the same object reference but
 * mutates the materials array in-place, catch that too.
 */
watch(
    () => props.batch?.materials,
    (newMaterials, oldMaterials) => {
        // Only re-apply when materials transitions from undefined/null → defined
        if (newMaterials !== undefined && oldMaterials === undefined) {
            applyBatchToForm(props.batch);
        }
    },
    { deep: false }
);

const { isScaleConnected, captureWeight, captureCameraSnap } = useWeighbridge();

const handleWeightCapture = (type: 'empty' | 'loaded') => {
    captureWeight(async (w) => {
        if (type === 'empty') {
            form.empty_weight_truck = w;
            form.empty_time = new Date();
        } else {
            form.loaded_weight_truck = w;
            form.load_time = new Date();
        }
        
        if (customSettings?.batching?.camera == 1 && (customSettings?.batching?.camera_url || customSettings?.batching?.camera_url_1 || customSettings?.batching?.camera_url_2)) {
            let cameraUrl = customSettings.batching.camera_url_1 || customSettings.batching.camera_url;
            if (type === 'loaded' && customSettings.batching.camera_url_2) {
                cameraUrl = customSettings.batching.camera_url_2;
            }

            try {
                const snap = await captureCameraSnap(cameraUrl);
                if (type === 'empty') {
                    // form.empty_weight_photo = snap;
                } else {
                    form.loaded_weight_photo = snap;
                }
            } catch (err) {
                console.error('Camera capture failed:', err);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'warning',
                    title: `${type} weight captured, but camera failed`,
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }
    });
};

const isFetchingConsumption = ref(false);
const isConsumptionSynced = ref(false);
const isSaved = ref(false);
const isScanning = ref(false);
const ocrWarning = ref<string | null>(null);
const sheetUrl = ref<string | null>(props.batch?.original_sheet_url ?? props.batch?.sheet_url ?? null);
const showUploadZone = ref(false);

const openUploadZone = () => { showUploadZone.value = true; };
const closeUploadZone = () => { showUploadZone.value = false; };

const handleUploaderCompleted = (result: any) => {
    const data = result.data || result; // Fallback in case just data was passed
    
    if (data?.materials?.length) applyMaterialData(data.materials);
    if (data?.original_url || data?.url) sheetUrl.value = data.original_url || data.url;

    if (result.status === false) {
        ocrWarning.value = result.message || 'Automatic parsing failed. Please enter the data manually.';
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'File uploaded, but parsing failed', showConfirmButton: false, timer: 3000 });
    } else {
        ocrWarning.value = null;
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Sheet uploaded & analysed successfully', showConfirmButton: false, timer: 1800 });
    }
    setTimeout(() => closeUploadZone(), 1200);
};

onUnmounted(() => {
    showUploadZone.value = false;
    // showReviewZone.value = false;
});

const hasConsumptionData = computed(() => {
    return form.materials.some(mat => Number(mat.actual_qty) > 0);
});

// Check if the upload-based fetch is enabled in custom settings
const isUploadFetchEnabled = computed(() => !!customSettings?.batching?.sheet_upload);

// Shared function – applies extracted material list to the form
const applyMaterialData = (materials: any[]) => {
    materials.forEach((apiMat: any) => {
        const key = (apiMat.item || apiMat.name || '').toString();
        if (!key) return;

        const matchedMat = form.materials.find(mat => {
            const productName = props.products.find((p: any) => p.id === mat.product_id)?.title || '';
            const materialName = mat.material_name || '';
            return (
                productName.toUpperCase().includes(key.toUpperCase()) ||
                materialName.toUpperCase().includes(key.toUpperCase()) ||
                key.toUpperCase().includes(productName.toUpperCase().trim())
            );
        });

        if (matchedMat) {
            matchedMat.actual_qty = Number(apiMat.actual ?? apiMat.act ?? 0);
        }
    });
};

const viewBatchSheet = () => {
    const url = sheetUrl.value ?? props.batch?.original_sheet_url ?? props.batch?.sheet_url;
    if (url) {
        window.open(url, '_blank');
    }
};

// Option 1: Fetch consumption via batches.sync API
const fetchConsumption = async () => {
    isFetchingConsumption.value = true;
    try {
        const response = await axios.post(route('batches.sync', props.batch?.id));
        const data = response.data;

        if (data?.mat?.length) {
            applyMaterialData(data.mat);
            if (data.end) form.end_time = new Date(data.end);
            if (data.start) form.start_time = new Date(data.start);
            isConsumptionSynced.value = true;
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Consumption synced', showConfirmButton: false, timer: 1500 });
        } else {
            Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: data?.message || 'No consumption data returned', showConfirmButton: false, timer: 2000 });
        }
    } catch (error: any) {
        const msg = error?.response?.data?.message || 'Failed to sync consumption';
        Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: msg, showConfirmButton: false, timer: 2000 });
        console.error('Sync error:', error);
    } finally {
        isFetchingConsumption.value = false;
    }
};



const copyTargetsToActuals = () => {
    form.materials.forEach((mat) => {
        mat.actual_qty = Number(mat.target_qty ?? 0);
        mat.deviation_quantity = 0;
    });
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Recipe targets copied to actual quantities',
        showConfirmButton: false,
        timer: 1500
    });
};

const normalizeNumber = (val: any) => {
    const n = Number(val);
    return isNaN(n) || val === null || val === '' ? 0 : n;
}

// Run it on key financial fields whenever they change
watch(() => [
    form.empty_weight_truck, 
    form.loaded_weight_truck, 
    form.net_weight,
    form.batch_size,
    ...form.materials.flatMap(m => [m.target_qty, m.actual_qty])
], () => {
    form.empty_weight_truck = normalizeNumber(form.empty_weight_truck);
    form.loaded_weight_truck = normalizeNumber(form.loaded_weight_truck);
    form.net_weight = normalizeNumber(form.net_weight);
    form.batch_size = normalizeNumber(form.batch_size) || 1; // batch_size min 1
    
    form.materials.forEach(m => {
        m.target_qty = normalizeNumber(m.target_qty);
        m.actual_qty = normalizeNumber(m.actual_qty);
        m.deviation_quantity = m.actual_qty - m.target_qty;
    });
}, { deep: true });
const submit = () => {
    form.clearErrors();
    let hasErrors = false;
        if (form.empty_weight_truck === null || form.empty_weight_truck === undefined || form.empty_weight_truck <= 0) {
            form.setError('empty_weight_truck', 'Empty Weight is required');
            hasErrors = true;
        }
        if (form.loaded_weight_truck === null || form.loaded_weight_truck === undefined || form.loaded_weight_truck <= 0) {
            form.setError('loaded_weight_truck', 'Full Weight is required');
            hasErrors = true;
        }
        if (!form.empty_time) {
            form.setError('empty_time', 'Empty Time is required');
            hasErrors = true;
        }
        if (!form.load_time) {
            form.setError('load_time', 'Load Time is required');
            hasErrors = true;
        }
    if (hasErrors) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Failed',
            text: 'Please check the required fields.',
            confirmButtonColor: '#0891b2',
        });
        return;
    }

    const formatDateTime = (date: Date | null | string) => {
        if (!date) return null;
        const d = date instanceof Date ? date : new Date(date);
        if (isNaN(d.getTime())) return null;
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const seconds = String(d.getSeconds()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }; 
    form.transform((data) => ({
        ...data,
        start_time: formatDateTime(data.start_time),
        end_time: formatDateTime(data.end_time),
        empty_time: formatDateTime(data.empty_time),
        load_time: formatDateTime(data.load_time),
        materials: data.materials.map((item: BatchMaterial) => ({
            ...item,
            material_name: item.material_name || props.products.find((p: any) => p.id === item.product_id)?.title || 'Material',
            deviation_quantity: Number(item.actual_qty || 0) - Number(item.target_qty || 0),
        })),
    })).put(route('batches.update', props.batch?.id), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Batch updated',
                timer: 1500,
                showConfirmButton: false,
            });
            isSaved.value = true;
            emit('saved', { batchId: props.batch.id, type: 'batching' });
        },
        onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join('\n');
            Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: errorMessages || 'Please check the input fields.',
                confirmButtonColor: '#0891b2',
            });
        }
    });
};


</script>

<template>
    <div class="rounded-xl border border-cyan-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-cyan-100 bg-cyan-50/30 px-2 py-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-cyan-100 p-2 text-cyan-600">
                        <CubeIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wide text-cyan-800 ">Edit Batch Details</h3>
                        <p class="mt-1 text-[10px] text-cyan-600/70 font-medium uppercase tracking-wider">Modify execution parameters</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 rounded-lg bg-white px-3 py-1.5 border border-cyan-100 shadow-sm">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-cyan-400">Batch ID</span>
                    <span class="text-sm font-black text-cyan-700">#{{ batch.batch_no }}</span>
                </div>
            </div>
        </div>

        <div class="p-1">
            <div class="grid grid-cols-12 gap-1">
                <!-- Left Column: Context -->
                <div class="col-span-12 md:col-span-3">
                    <div class="space-y-1">
                       
                        <div class="col-span-12 md:col-span-3 py-3">
                            <BaseSelect 
                                v-model="form.sales_order_id" 
                                optionLabel="full_number" 
                                :options="salesOrders"  
                                optionValue="id" 
                                filter 
                                :disabled="true"
                                label="Sales Order" 
                                :error="form.errors.sales_order_id" 
                            />
                        </div>
                        <!-- Sales Order Details Hint -->
                        <div v-if="salesOrderDetails.length" class="rounded-xl border border-cyan-100 bg-white p-4 shadow-sm relative overflow-hidden">
                            <div class="absolute -top-2 -right-2 opacity-5">
                                <InformationCircleIcon class="w-16 h-16 text-cyan-600" />
                            </div>
                            <h4 class="mb-2 text-[10px] font-bold uppercase tracking-widest text-cyan-500 border-b border-cyan-50 italic">Sales Order Context</h4>
                            <div class="space-y-2">
                                <div v-for="detail in salesOrderDetails" :key="detail.label" class="flex flex-col">
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400">{{ detail.label }}</span>
                                    <span class="text-xs font-semibold text-cyan-900 leading-tight">{{ detail.value }}</span>
                                </div>
                                <!-- <div class="">
                                    <BaseSelect v-model="form.status" :options="statuses" :disabled="form.status === 3" optionLabel="label" optionValue="value" label="Current Status" :error="form.errors.status" />
                                </div> -->
                                <!-- <div class="col-span-12 md:col-span-3">
                                    <BaseDatePicker label="Start Time" v-model="form.start_time" showTime hourFormat="24" fluid :disabled="isLocked" />
                                    <small class="text-red-500">{{ form.errors.start_time }}</small>
                                </div>
                                <div class="col-span-12 md:col-span-3">
                                    <BaseDatePicker label="End Time" v-model="form.end_time" showTime hourFormat="24" fluid :disabled="isLocked" />
                                    <small class="text-red-500">{{ form.errors.end_time }}</small>
                                </div> -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Fillable Details -->
                <div class="col-span-12 md:col-span-9 space-y-6">
                    <!-- Config Grid -->
                    <div class="grid grid-cols-12 gap-4 rounded-xl border border-slate-100 bg-white p-5 shadow-sm">
                         
                        <div class="col-span-12 md:col-span-3">
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <BaseInputNumber v-model="form.empty_weight_truck" :disabled="isLocked " label="Empty Weight" required :error="form.errors.empty_weight_truck" />
                                </div>
                                <!-- <button v-if="customSettings?.batching?.manual_weight" @click="handleWeightCapture('empty')" type="button" 
                                    :class="['p-2 rounded transition-colors border', isScaleConnected ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border-emerald-200' : 'bg-amber-50 text-amber-600 hover:bg-amber-100 border-amber-200']" 
                                    :title="isScaleConnected ? 'Capture Current Weight' : 'Connect & Capture'">
                                    <div class="flex flex-col items-center gap-0.5">
                                        <ArrowDownTrayIcon class="w-4 h-4" />
                                        <span v-if="customSettings?.batching?.camera == 1" class="text-[8px] font-bold"> + SNAP</span>
                                    </div>
                                </button> -->
                            </div>
                            <!-- <div v-if="form.empty_weight_photo" class="mt-2 relative group">
                                <img :src="form.empty_weight_photo" class="w-full h-16 object-cover rounded-lg border border-slate-200" />
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-lg">
                                    <button @click="form.empty_weight_photo = null" type="button" class="text-white text-[8px] font-bold bg-red-500 px-2 py-0.5 rounded">Remove</button>
                                </div>
                            </div> -->
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <BaseInputNumber v-model="form.loaded_weight_truck" :disabled="isLocked || customSettings?.batching?.manual_weight === 0" label="Full Weight" required :error="form.errors.loaded_weight_truck" />
                                </div>
                                <button v-if="!isLocked && !customSettings?.batching?.manual_weight && form.net_weight<=0" @click="handleWeightCapture('loaded')" type="button" 
                                    :class="['p-2 rounded transition-colors border', isScaleConnected ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border-emerald-200' : 'bg-amber-50 text-amber-600 hover:bg-amber-100 border-amber-200']" 
                                    :title="isScaleConnected ? 'Capture Current Weight' : 'Connect & Capture'">
                                    <div class="flex flex-col items-center gap-0.5">
                                        <ArrowDownTrayIcon class="w-4 h-4" />
                                        <span v-if="customSettings?.batching?.camera == 1" class="text-[8px] font-bold"> + SNAP</span>
                                    </div>
                                </button>
                            </div>
                            <div v-if="form.loaded_weight_photo" class="mt-2 relative group">
                                <img :src="form.loaded_weight_photo" class="w-full h-16 object-cover rounded-lg border border-slate-200" />
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-lg">
                                    <button @click="form.loaded_weight_photo = null" type="button" class="text-white text-[8px] font-bold bg-red-500 px-2 py-0.5 rounded">Remove</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-span-12 md:col-span-3">
                            <BaseInputNumber v-model="form.net_weight" :disabled="isLocked || isMetricTon || customSettings?.batching?.manual_weight === 1" label="Net Weight (kg)" :required="!isMetricTon" :error="form.errors.net_weight" />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                        <BaseSelect
                                v-model="form.uom_id"
                                :options="uoms"
                                label="UOM"
                                optionLabel="unit_code"
                                optionValue="id"
                                size="small"
                                :fluid="true"
                                :error="form.errors.uom_id"
                                :disabled="isLocked"
                            />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseSelect v-model="form.truck_id" :options="trucks" optionLabel="registration" optionValue="id" filter label="Truck Assignment" :error="form.errors.truck_id" :disabled="isLocked" />
                        </div>
                        
                         <div class="col-span-12 md:col-span-3">
                            <BaseSelect v-model="form.transport_id" :options="transporters" optionLabel="legal_name" optionValue="id" filter label="Transporter" showClear :error="form.errors.transport_id" :disabled="isLocked" />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseSelect v-model="form.driver_id" :options="drivers" optionLabel="label" optionValue="id" filter label="Driver" showClear :error="form.errors.driver_id" :disabled="isLocked" />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseSelect v-model="form.sales_executive_id" :options="sales_executives" optionLabel="label" optionValue="id" filter label="Sales Executive" showClear :error="form.errors.sales_executive_id" :disabled="isLocked" />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseInputNumber v-model="form.batch_size" label="Batch Quantity (m³)" :minFractionDigits="2" :disabled="true"  :error="form.errors.batch_size" />
                        </div>
                        
                        
                        
                        <div class="col-span-12 md:col-span-3">
                            <BaseDatePicker label="Empty Time" v-model="form.empty_time" showTime hourFormat="24" fluid :required="isMetricTon" :error="form.errors.empty_time" :disabled="isLocked" />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseDatePicker label="Load Time" v-model="form.load_time" showTime hourFormat="24" fluid :required="isMetricTon" :error="form.errors.load_time" :disabled="isLocked" />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseSelect
                                v-model="form.concrete_pump"
                                :options="concretePumpOptions"
                                label="Concrete Type"
                                placeholder="Select Concrete Type"
                                optionLabel="label"
                                optionValue="value"
                                :fluid="true"
                                :error="form.errors.concrete_pump"
                                :disabled="isLocked"
                            />
                        </div>
                    </div>

                    <!-- Target Recipe Visualization -->
                    <!-- <div v-if="selectedSalesOrder?.mix_design?.items?.length" class="rounded-xl border border-cyan-100 bg-cyan-50/30 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <BeakerIcon class="w-4 h-4 text-cyan-500" />
                                <h3 class="text-[10px] font-bold uppercase   text-cyan-500 tracking-[0.1em]">Calculated Targets</h3>
                            </div>
                            <span class="text-[9px] text-cyan-400 font-bold uppercase tracking-tighter">Yield: {{ form.batch_size }} m³</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <div v-for="item in selectedSalesOrder.mix_design.items" :key="item.id" 
                                class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 shadow-sm border border-cyan-100/50">
                                <span class="text-[10px] font-bold text-slate-500 uppercase">{{ item.product?.title || 'Material' }}</span>
                                <span class="h-4 w-[1px] bg-slate-100"></span>
                                <span class="text-xs font-black text-cyan-600">
                                    {{ (Number(item.cross_quantity || item.quantity || 0) * form.batch_size).toFixed(3) }}
                                    <span class="text-[9px] font-normal text-slate-400 ml-0.5">{{ item.uom?.unit_code || '' }}</span>
                                </span>
                            </div>
                        </div>
                    </div> -->
</div>
<div class="col-span-12 md:col-span-12 space-y-6">
                    <!-- Detailed Materials Table -->
                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-5 py-3">
                            <div class="flex items-center gap-2">
                                <ListBulletIcon class="w-4 h-4 text-slate-400" />
                                <h3 class="text-xs font-bold uppercase tracking-wide text-slate-600">Input Reconciliation</h3>
                            </div>
                             <div class="flex items-center gap-2">
                                <!-- Upload Batch Sheet button - always visible when batch is active -->
                                <Button
                                    v-if="form.status !== 3 && isUploadFetchEnabled"
                                    :label="sheetUrl || props.batch?.sheet_url ? 'Re-upload Sheet' : 'Upload Batch Sheet'"
                                    icon="pi pi-upload"
                                    size="small"
                                    severity="info"
                                    outlined
                                    class="!text-xs"
                                    :loading="isScanning"
                                    @click="openUploadZone"
                                />

                                <Transition name="ocr-fade">
                                    <div v-if="showUploadZone"
                                        class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                                        style="background:rgba(15,23,42,0.55);backdrop-filter:blur(4px)"
                                        @click.self="closeUploadZone"
                                    >
                                        <div class="relative w-full max-w-2xl mx-4 rounded-2xl bg-white shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto">
                                            <!-- Header -->
                                            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-cyan-50 to-blue-50">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-lg bg-cyan-100 flex items-center justify-center">
                                                        <svg class="w-4 h-4 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs font-bold text-slate-700">Upload Batch Sheet</p>
                                                        <p class="text-[10px] text-slate-400">AI will extract material weights automatically</p>
                                                    </div>
                                                </div>
                                                <button @click="closeUploadZone" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors">
                                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                </button>
                                            </div>

                                            <!-- Drop Zone -->
                                            <div class="p-5">
                                                <BatchSheetUploader
                                                    :batchId="props.batch?.id"
                                                    @completed="handleUploaderCompleted"
                                                    @close="closeUploadZone"
                                                />
                                            </div>
                                        </div>
                                </div>
                                </Transition>

                                <!-- View/Download Batch Sheet button: only if url exists -->
                                <Button
                                    v-if="(sheetUrl || props.batch?.sheet_url) && isUploadFetchEnabled"
                                    label="View Sheet"
                                    icon="pi pi-eye"
                                    size="small"
                                    severity="help"
                                    outlined
                                    class="!text-xs"
                                    @click="viewBatchSheet"
                                />

                                <!-- Sync Consumption button: always available -->
                                <Button
                                    v-if="form.status !== 3 && !isUploadFetchEnabled"
                                    label="Sync Consumption"
                                    icon="pi pi-sync"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    class="!text-xs"
                                    :loading="isFetchingConsumption"
                                    @click="fetchConsumption"
                                />
                                
                                <!-- One-Click Target to Actual button -->
                                <Button
                                    v-if="form.status !== 3 && customSettings?.batching?.target_to_actual == 1"
                                    label="Set Actuals = Targets"
                                    icon="pi pi-copy"
                                    size="small"
                                    severity="success"
                                    outlined
                                    class="!text-xs"
                                    @click="copyTargetsToActuals"
                                />

                                <Button v-if="form.status !== 3" label="Add" icon="pi pi-plus" size="small" text rounded class="!text-xs !text-cyan-600" @click="addMaterial" />
                            </div>
                        </div>

                        <!-- OCR Failure Warning -->
                        <div v-if="ocrWarning" class="mx-5 m-4 p-4 rounded-xl bg-orange-50 border border-orange-200 text-orange-800 text-xs shadow-sm flex items-start gap-3 relative overflow-hidden">
                            <div class="absolute inset-y-0 left-0 w-1 bg-orange-400"></div>
                            <div class="mt-0.5 bg-orange-100 rounded-full p-1.5 flex-shrink-0">
                                <svg class="w-4 h-4 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-orange-800 text-sm mb-1">Manual Entry Required</h4>
                                <p class="text-orange-700">{{ ocrWarning }}</p>
                                <!-- <p class="text-orange-600 mt-2 font-medium italic text-[11px]">You can click "View Sheet" to open the uploaded document side-by-side.</p> -->
                            </div>
                            <button @click="ocrWarning = null" class="text-orange-400 hover:text-orange-600 transition-colors p-1 rounded-full hover:bg-orange-100/50">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <div v-if="form.errors.materials" class="m-5 p-4 rounded-xl bg-rose-50 border border-rose-100 text-rose-800 text-xs flex flex-col gap-1.5 shadow-sm">
                            <div class="font-bold flex items-center gap-2 text-rose-700">
                                <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                Stock Validation Failed
                            </div>
                            <ul class="list-disc list-inside mt-1 space-y-1 font-semibold text-rose-600">
                                <li v-for="err in (Array.isArray(form.errors.materials) ? form.errors.materials : [form.errors.materials])" :key="err">
                                    {{ err }}
                                </li>
                            </ul>
                        </div>

                        <!-- Materials: Card-per-Material Layout (Batch Report Style) -->
                        <div class="px-5 pb-5">
                            <!-- Empty State -->
                            <div v-if="form.materials.length === 0" class="rounded-xl border-2 border-dashed border-slate-200 py-10 text-center">
                                <svg class="mx-auto mb-2 h-8 w-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">No materials added</p>
                                <p class="text-[10px] text-slate-300 mt-1">Click "Add" in the header to add a material</p>
                            </div>

                            <!-- Table Layout -->
                            <div v-else class="overflow-x-auto border border-slate-300 rounded-lg shadow-sm">
                                <table class="w-full text-left border-collapse whitespace-nowrap">
                                    <thead>
                                        <tr class="bg-black text-white">
                                            <th class="border-b border-slate-700 px-3 py-2 font-bold uppercase text-[10px]" :colspan="form.materials.length + 1">
                                                <div class="flex justify-between items-center">
                                                    <span>Materials Breakdown</span>
                                                </div>
                                            </th>
                                        </tr>
                                        <tr class="bg-slate-100 text-slate-800">
                                            <th class="border-r border-b border-slate-300 px-3 py-2 font-bold uppercase w-40 bg-slate-200 text-[10px]">Product</th>
                                            <th v-for="(item, index) in form.materials" :key="index" class="border-r border-b border-slate-300 px-2 py-1 min-w-[160px]">
                                                <div class="flex items-center gap-1">
                                                    <BaseSelect
                                                        v-model="form.materials[index].product_id"
                                                        :options="products"
                                                        optionLabel="title"
                                                        optionValue="id"
                                                        filter
                                                        size="small"
                                                        :fluid="true"
                                                        :disabled="!!item.id || isLocked"
                                                        :error="form.errors[`materials.${index}.product_id`]"
                                                        placeholder="Select Product"
                                                        class="!text-[10px] w-full"
                                                    />
                                                    <Button
                                                        v-if="!isLocked && form.status !== 3 && !item.id"
                                                        icon="pi pi-trash" 
                                                        text rounded severity="danger"
                                                        class="!h-6 !w-6 !p-0 flex-shrink-0"
                                                        @click="removeMaterial(index)"
                                                    />
                                                </div>
                                                <BaseInput
                                                    v-model="form.materials[index].material_name"
                                                    disabled
                                                    size="small" 
                                                    :fluid="true"
                                                    placeholder="Label"
                                                    class="mt-1"
                                                />
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Target Qty -->
                                        <tr>
                                            <td class="border-r border-b border-slate-300 px-3 py-2 font-bold bg-slate-50 text-slate-600 uppercase text-[10px]">Target Qty</td>
                                            <td v-for="(item, index) in form.materials" :key="index" class="border-r border-b border-slate-300 px-2 py-1">
                                                <BaseInputNumber
                                                    :modelValue="form.materials[index].target_qty"
                                                    @update:modelValue="form.materials[index].target_qty = Number($event ?? 0)"
                                                    :disabled="!!item.id || isLocked"
                                                    :minFractionDigits="3"
                                                    size="small"
                                                    :fluid="true"
                                                    :error="form.errors[`materials.${index}.target_qty`]"
                                                    class="!text-[11px] !font-bold text-center"
                                                />
                                            </td>
                                        </tr>
                                        <!-- Target Summary -->
                                        <tr class="bg-slate-100">
                                            <td :colspan="form.materials.length + 1" class="border-r border-b border-slate-300 px-3 py-2 font-bold text-right text-slate-600 uppercase text-[10px]">
                                                Mass of Recipe Targets in kg :
                                                <span class="ml-2 text-slate-800 font-black text-sm">{{ form.materials.reduce((sum, m) => sum + Number(m.target_qty || 0), 0).toFixed(3) }}</span>
                                            </td>
                                        </tr>
                                        <!-- Actual Qty -->
                                        <tr>
                                            <td class="border-r border-b border-slate-300 px-3 py-2 font-bold bg-slate-50 text-slate-600 uppercase text-[10px]">Actual Qty</td>
                                            <td v-for="(item, index) in form.materials" :key="index" class="border-r border-b border-slate-300 px-2 py-1">
                                                <BaseInputNumber
                                                    :modelValue="form.materials[index].actual_qty"
                                                    @update:modelValue="form.materials[index].actual_qty = Number($event ?? 0)"
                                                    :disabled="isLocked"
                                                    :minFractionDigits="3"
                                                    size="small"
                                                    :fluid="true"
                                                    :error="form.errors[`materials.${index}.actual_qty`]"
                                                    class="!text-[11px] !font-bold text-center"
                                                />
                                            </td>
                                        </tr>
                                        <!-- Deviation -->
                                        <tr>
                                            <td class="border-r border-b border-slate-300 px-3 py-2 font-bold bg-slate-50 text-slate-600 uppercase text-[10px]">Deviation</td>
                                            <td v-for="(item, index) in form.materials" :key="index" class="border-r border-b border-slate-300 px-3 py-2 font-bold text-center text-xs"
                                                :class="item.deviation_quantity > 0 ? 'text-rose-600 bg-rose-50/50' : item.deviation_quantity < 0 ? 'text-emerald-600 bg-emerald-50/50' : 'text-slate-600'">
                                                {{ item.deviation_quantity > 0 ? '+' : '' }}{{ item.deviation_quantity?.toFixed(3) }}
                                            </td>
                                        </tr>
                                        <!-- Actual Summary -->
                                        <tr class="bg-slate-100 border-t-2 border-slate-300">
                                            <td :colspan="form.materials.length + 1" class="border-r border-b border-slate-300 px-3 py-2 font-bold text-right text-slate-600 uppercase text-[10px]">
                                                Mass of Total Set Weight in kg :
                                                <span class="ml-2 text-slate-800 font-black text-sm">{{ form.materials.reduce((sum, m) => sum + Number(m.actual_qty || 0), 0).toFixed(3) }}</span>
                                            </td>
                                        </tr>
                                        <!-- Unit -->
                                        <!-- <tr>
                                            <td class="border-r border-slate-300 px-3 py-2 font-bold bg-slate-50 text-slate-600 uppercase text-[10px]">Unit</td>
                                            <td v-for="(item, index) in form.materials" :key="index" class="border-r border-slate-300 px-2 py-1">
                                                <BaseSelect
                                                    v-model="item.uom_id"
                                                    :options="uoms"
                                                    optionLabel="unit_code"
                                                    optionValue="id"
                                                    size="small"
                                                    :fluid="true"
                                                    :disabled="!!item.id || isLocked"
                                                    :error="form.errors[`materials.${index}.uom_id`]"
                                                />
                                            </td>
                                        </tr> -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-cyan-100 bg-cyan-50/30 p-5" v-if="form.status !== 3">
            <BaseFormActions 
                mode="update" 
                updateLabel="Save Changes" 
                :loading="form.processing" 
                @submit="submit" 
                @cancel="emit('cancel')" 
            />
        </div>
    </div>
</template>

<style scoped>
.ocr-fade-enter-active,
.ocr-fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.ocr-fade-enter-from,
.ocr-fade-leave-to {
    opacity: 0;
    transform: scale(0.97);
}
</style>

