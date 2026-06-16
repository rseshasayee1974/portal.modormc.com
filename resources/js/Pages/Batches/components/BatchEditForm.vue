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
    workOrders?: any[];
    trucks?: any[];
    transporters?: any[];
    personnel?: any[];
    products?: any[];
    uoms?: any[];
    statuses?: { label: string; value: number }[];
}>(), {
    batch: () => ({}),
    workOrders: () => [],
    trucks: () => [],
    transporters: () => [],
    personnel: () => [],
    products: () => [],
    uoms: () => [],
    statuses: () => [],
});

const emit = defineEmits<{
    (e: 'saved'): void;
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
    work_order_id: props.batch?.work_order_id ?? null,
    batch_no: props.batch?.batch_no ?? null,
    batch_size: Number(props.batch?.batch_size ?? 1),
    truck_id: props.batch?.dispatches?.[0]?.truck_id ?? null,
    transport_id: props.batch?.dispatches?.[0]?.transport_id ?? null,
    driver_id: props.batch?.dispatches?.[0]?.driver_id ?? null,
    sales_executive_id: props.batch?.dispatches?.[0]?.sales_executive_id ?? null,
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
        if ((!initialMaterials || initialMaterials.length === 0) && props.batch?.work_order?.mix_design?.items) {
            initialMaterials = props.batch.work_order.mix_design.items.map((item: any) => ({
                id: null,
                product_id: item.product_id,
                material_name: item.product?.title || 'Material',
                target_qty: Number(item.cross_quantity || item.actual_quantity || item.quantity || 0) * Number(props.batch?.batch_size ?? 1),
                actual_qty: 0,
                deviation_quantity: 0,
                uom_id: item.uom_id || item.product?.unit_id,
            }));
        } else if ((!initialMaterials || initialMaterials.length === 0) && props.workOrders) {
            const wo = props.workOrders.find(w => w.id === props.batch?.work_order_id);
            if (wo?.mix_design?.items) {
                initialMaterials = wo.mix_design.items.map((item: any) => ({
                    id: null,
                    product_id: item.product_id,
                    material_name: item.product?.title || 'Material',
                    target_qty: Number(item.cross_quantity || item.actual_quantity || item.quantity || 0) * Number(props.batch?.batch_size ?? 1),
                    actual_qty: 0,
                    deviation_quantity: 0,
                    uom_id: item.uom_id || item.product?.unit_id,
                }));
            }
        }
        return ((initialMaterials?.length ?? 0) > 0 ? initialMaterials : [blankMaterial()]).map((item: any) => ({
            id: item.id,
            product_id: item.product_id,
            material_name: item.material_name || item.product?.title || '',
            target_qty: Number(item.target_qty || 0),
            actual_qty: Number(item.actual_qty || 0),
            deviation_quantity: Number(item.deviation_quantity || (Number(item.actual_qty || 0) - Number(item.target_qty || 0))),
            uom_id: item.uom_id,
        }));
    })(),
});

const page = usePage();
const customSettings = page.props.custom_settings as any;

const isMetricTon = computed(() => {
    return customSettings?.batching?.InvoiceInMetricTon == 1;
});

const isLocked = computed(() => {
    return form.status === 3;
});

const selectedWorkOrder = computed(() => {
    if (props.batch?.work_order && props.batch.work_order.id === form.work_order_id) {
        return props.batch.work_order;
    }
    return props.workOrders.find(wo => wo.id === form.work_order_id);
});

const workOrderDetails = computed(() => {
    if (!selectedWorkOrder.value) return [];
    const wo = selectedWorkOrder.value;
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
    if (form.work_order_id && selectedWorkOrder.value?.mix_design?.items) {
        form.materials.forEach((mat) => {
            const originalItem = selectedWorkOrder.value.mix_design.items.find((item: any) => item.product_id === mat.product_id);
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

watch(() => props.batch, (newBatch) => {
    console.log('BatchEditForm watcher triggered. Batch ID:', newBatch?.id, 'has materials:', !!newBatch?.materials, 'count:', newBatch?.materials?.length);
    if (newBatch) {
        form.work_order_id = newBatch.work_order_id ?? null;
        form.batch_no = newBatch.batch_no ?? null;
        form.batch_size = Number(newBatch.batch_size ?? 1);
        
        const dispatch = newBatch.dispatches?.[0];
        form.truck_id = dispatch?.truck_id ?? null;
        form.transport_id = dispatch?.transport_id ?? null;
        form.driver_id = dispatch?.driver_id ?? null;
        form.sales_executive_id = dispatch?.sales_executive_id ?? null;
        form.empty_weight_truck = Number(dispatch?.empty_weight_truck ?? 0);
        form.loaded_weight_truck = Number(dispatch?.loaded_weight_truck ?? 0);
        form.net_weight = Number(dispatch?.net_weight ?? 0);
        form.empty_time = dispatch?.empty_time ? new Date(dispatch.empty_time) : new Date();
        form.load_time = dispatch?.load_time ? new Date(dispatch.load_time) : new Date();
        
        form.uom_id = newBatch.uom_id ?? props.uoms?.find((u: any) => String(u.unit_code).toUpperCase() === 'CBM')?.id ?? null;
        form.status = Number(newBatch.status ?? 1);
        form.start_time = newBatch.start_time ? new Date(newBatch.start_time) : new Date();
        form.end_time = newBatch.end_time ? new Date(newBatch.end_time) : new Date();
        
        let initialMaterials = newBatch.materials;
        console.log('BatchEditForm initialMaterials from newBatch:', initialMaterials);
        if ((!initialMaterials || initialMaterials.length === 0) && newBatch.work_order?.mix_design?.items) {
            console.log('Loading materials from mix_design items');
            initialMaterials = newBatch.work_order.mix_design.items.map((item: any) => ({
                id: null,
                product_id: item.product_id,
                material_name: item.product?.title || 'Material',
                target_qty: Number(item.cross_quantity || item.actual_quantity || item.quantity || 0) * Number(newBatch.batch_size ?? 1),
                actual_qty: 0,
                deviation_quantity: 0,
                uom_id: item.uom_id || item.product?.unit_id,
            }));
        } else if ((!initialMaterials || initialMaterials.length === 0) && props.workOrders) {
            console.log('Loading materials from props.workOrders');
            const wo = props.workOrders.find(w => w.id === newBatch.work_order_id);
            if (wo?.mix_design?.items) {
                initialMaterials = wo.mix_design.items.map((item: any) => ({
                    id: null,
                    product_id: item.product_id,
                    material_name: item.product?.title || 'Material',
                    target_qty: Number(item.cross_quantity || item.actual_quantity || item.quantity || 0) * Number(newBatch.batch_size ?? 1),
                    actual_qty: 0,
                    deviation_quantity: 0,
                    uom_id: item.uom_id || item.product?.unit_id,
                }));
            }
        }

        form.materials = ((initialMaterials?.length ?? 0) > 0 ? initialMaterials : [blankMaterial()]).map((item: any) => ({
            id: item.id,
            product_id: item.product_id,
            material_name: item.material_name || item.label || item.product?.title || '',
            target_qty: Number(item.target_qty || 0),
            actual_qty: Number(item.actual_qty || 0),
            deviation_quantity: Number(item.deviation_quantity || (Number(item.actual_qty || 0) - Number(item.target_qty || 0))),
            uom_id: item.uom_id,
        }));
        console.log('BatchEditForm set form.materials to:', JSON.parse(JSON.stringify(form.materials)));
    }
}, { deep: true, immediate: true });

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
const uploadProgress = ref(0); // 0-100
const sheetUrl = ref<string | null>(props.batch?.sheet_url ?? null);
const ocrFileInput = ref<HTMLInputElement | null>(null);
const isDragOver = ref(false);
const showUploadZone = ref(false);
const selectedFileName = ref<string | null>(null);

const openUploadZone = () => { showUploadZone.value = true; };
const closeUploadZone = () => { showUploadZone.value = false; isDragOver.value = false; selectedFileName.value = null; };

const onDragOver = (e: DragEvent) => { e.preventDefault(); isDragOver.value = true; };
const onDragLeave = () => { isDragOver.value = false; };
const onDrop = (e: DragEvent) => {
    e.preventDefault();
    isDragOver.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) processUploadFile(file);
};
const onFileSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) processUploadFile(file);
    if (target) target.value = '';
};

const processUploadFile = async (file: File) => {
    selectedFileName.value = file.name;
    const formData = new FormData();
    formData.append('file', file);
    formData.append('batch_id', String(props.batch?.id ?? ''));

    isScanning.value = true;
    uploadProgress.value = 0;
    try {
        const response = await axios.post(route('batches.ocr'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (progressEvent) => {
                const pct = progressEvent.total
                    ? Math.round((progressEvent.loaded / progressEvent.total) * 80)
                    : 40;
                uploadProgress.value = pct;
            },
        });
        uploadProgress.value = 85;
        await new Promise(r => setTimeout(r, 300));
        uploadProgress.value = 100;

        const data = response.data;
        if (data?.status) {
            if (data.data.materials?.length) applyMaterialData(data.data.materials);
            if (data.data?.url) sheetUrl.value = data.data.url;
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message || 'Sheet uploaded & analysed successfully', showConfirmButton: false, timer: 1800 });
            setTimeout(() => closeUploadZone(), 1200);
        } else {
            Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: data?.message || 'No material data found in file', showConfirmButton: false, timer: 2000 });
        }
    } catch (error: any) {
        const msg = error?.response?.data?.message || 'Failed to parse uploaded file';
        Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: msg, showConfirmButton: false, timer: 2000 });
        console.error('OCR error:', error);
    } finally {
        isScanning.value = false;
        setTimeout(() => { uploadProgress.value = 0; }, 1500);
    }
};
onUnmounted(() => {
    showUploadZone.value = false;
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
    const url = sheetUrl.value ?? props.batch?.sheet_url;
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

// Option 2: Upload PDF/image → now handled by the drag-drop zone
const triggerOcrScan = () => openUploadZone();

// Legacy handleOcrUpload kept for compat (now unused directly)
const handleOcrUpload = async (event: Event) => {
    onFileSelected(event);
};

const submit = () => {
    form.clearErrors();
    let hasErrors = false;
    if (isMetricTon.value) {
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
    console.log('Submitted data', form);
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
            emit('saved');
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

console.log('sdfcsdfsc', form);

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
                                v-model="form.work_order_id" 
                                optionLabel="full_number" 
                                :options="workOrders"  
                                optionValue="id" 
                                filter 
                                :disabled="true"
                                label="Work Order" 
                                :error="form.errors.work_order_id" 
                            />
                        </div>
                        <!-- Work Order Details Hint -->
                        <div v-if="workOrderDetails.length" class="rounded-xl border border-cyan-100 bg-white p-4 shadow-sm relative overflow-hidden">
                            <div class="absolute -top-2 -right-2 opacity-5">
                                <InformationCircleIcon class="w-16 h-16 text-cyan-600" />
                            </div>
                            <h4 class="mb-2 text-[10px] font-bold uppercase tracking-widest text-cyan-500 border-b border-cyan-50 italic">Work Order Context</h4>
                            <div class="space-y-2">
                                <div v-for="detail in workOrderDetails" :key="detail.label" class="flex flex-col">
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
                            <BaseSelect v-model="form.truck_id" :options="trucks" optionLabel="registration" optionValue="id" filter label="Truck Assignment" :error="form.errors.truck_id" :disabled="isLocked" />
                        </div>
                        
                         <div class="col-span-12 md:col-span-3">
                            <BaseSelect v-model="form.transport_id" :options="transporters" optionLabel="legal_name" optionValue="id" filter label="Transporter" showClear :error="form.errors.transport_id" :disabled="isLocked" />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseSelect v-model="form.driver_id" :options="personnel" optionLabel="label" optionValue="id" filter label="Driver" showClear :error="form.errors.driver_id" :disabled="isLocked" />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseSelect v-model="form.sales_executive_id" :options="personnel" optionLabel="label" optionValue="id" filter label="Sales Executive" showClear :error="form.errors.sales_executive_id" :disabled="isLocked" />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseInputNumber v-model="form.batch_size" label="Batch Quantity (m³)" :minFractionDigits="2" :disabled="true"  :error="form.errors.batch_size" />
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
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <BaseInputNumber v-model="form.empty_weight_truck" :disabled="isLocked || !customSettings?.batching?.manual_weight" label="Empty Weight" :required="isMetricTon" :error="form.errors.empty_weight_truck" />
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
                                    <BaseInputNumber v-model="form.loaded_weight_truck" :disabled="isLocked || !customSettings?.batching?.manual_weight" label="Full Weight" :required="isMetricTon" :error="form.errors.loaded_weight_truck" />
                                </div>
                                <button v-if="!isLocked && customSettings?.batching?.manual_weight && form.net_weight<=0" @click="handleWeightCapture('loaded')" type="button" 
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
                            <BaseInputNumber v-model="form.net_weight" :disabled="isLocked || isMetricTon" label="Net Weight (kg)" :required="!isMetricTon" :error="form.errors.net_weight" />
                        </div>
                        
                        <div class="col-span-12 md:col-span-3">
                            <BaseDatePicker label="Empty Time" v-model="form.empty_time" showTime hourFormat="24" fluid :required="isMetricTon" :error="form.errors.empty_time" :disabled="isLocked" />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseDatePicker label="Load Time" v-model="form.load_time" showTime hourFormat="24" fluid :required="isMetricTon" :error="form.errors.load_time" :disabled="isLocked" />
                        </div>
                    </div>

                    <!-- Target Recipe Visualization -->
                    <!-- <div v-if="selectedWorkOrder?.mix_design?.items?.length" class="rounded-xl border border-cyan-100 bg-cyan-50/30 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <BeakerIcon class="w-4 h-4 text-cyan-500" />
                                <h3 class="text-[10px] font-bold uppercase   text-cyan-500 tracking-[0.1em]">Calculated Targets</h3>
                            </div>
                            <span class="text-[9px] text-cyan-400 font-bold uppercase tracking-tighter">Yield: {{ form.batch_size }} m³</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <div v-for="item in selectedWorkOrder.mix_design.items" :key="item.id" 
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

                    <!-- Detailed Materials Table -->
                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-5 py-3">
                            <div class="flex items-center gap-2">
                                <ListBulletIcon class="w-4 h-4 text-slate-400" />
                                <h3 class="text-xs font-bold uppercase tracking-wide text-slate-600">Input Reconciliation</h3>
                            </div>
                             <div class="flex items-center gap-2">
                                <!-- Hidden file input -->
                                <input
                                    type="file"
                                    ref="ocrFileInput"
                                    class="hidden"
                                    :accept="isUploadFetchEnabled ? 'image/*,application/pdf' : 'image/*'"
                                    @change="handleOcrUpload"
                                />

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

                                <!-- Upload Drag-Drop Modal Overlay (In-component fixed position to avoid Teleport unmount issues) -->
                                <Transition name="ocr-fade">
                                    <div v-if="showUploadZone"
                                        class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                                        style="background:rgba(15,23,42,0.55);backdrop-filter:blur(4px)"
                                        @click.self="closeUploadZone"
                                    >
                                        <div class="relative w-full max-w-md mx-4 rounded-2xl bg-white shadow-2xl overflow-hidden">
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
                                                <button @click="closeUploadZone" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors" :disabled="isScanning">
                                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                </button>
                                            </div>

                                            <!-- Drop Zone -->
                                            <div class="p-5">
                                                <div
                                                    @dragover="onDragOver"
                                                    @dragleave="onDragLeave"
                                                    @drop="onDrop"
                                                    :class="[
                                                        'relative flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed transition-all duration-200 cursor-pointer p-8',
                                                        isDragOver ? 'border-cyan-400 bg-cyan-50 scale-[1.01]' : 'border-slate-200 bg-slate-50 hover:border-cyan-300 hover:bg-cyan-50/40',
                                                        isScanning ? 'pointer-events-none opacity-60' : ''
                                                    ]"
                                                    @click="ocrFileInput?.click()"
                                                >
                                                    <!-- Idle / Drag state -->
                                                    <template v-if="!isScanning">
                                                        <div :class="['w-14 h-14 rounded-2xl flex items-center justify-center transition-colors', isDragOver ? 'bg-cyan-100' : 'bg-white border border-slate-200 shadow-sm']">
                                                            <svg :class="['w-7 h-7 transition-colors', isDragOver ? 'text-cyan-500' : 'text-slate-400']" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                                                        </div>
                                                        <div class="text-center">
                                                            <p class="text-sm font-semibold text-slate-700">{{ isDragOver ? 'Drop it here!' : 'Drag & drop or click to browse' }}</p>
                                                            <p class="text-[11px] text-slate-400 mt-0.5">Supports JPG, PNG, PDF · Max 20 MB</p>
                                                        </div>
                                                        <div v-if="selectedFileName" class="flex items-center gap-2 rounded-lg bg-white border border-cyan-200 px-3 py-1.5 shadow-sm">
                                                            <svg class="w-3.5 h-3.5 text-cyan-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                            <span class="text-[11px] font-medium text-cyan-700 truncate max-w-[220px]">{{ selectedFileName }}</span>
                                                        </div>
                                                    </template>

                                                    <!-- Scanning / Progress state -->
                                                    <template v-else>
                                                        <div class="w-14 h-14 rounded-2xl bg-cyan-50 flex items-center justify-center">
                                                            <svg class="w-7 h-7 text-cyan-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                        </div>
                                                        <div class="text-center">
                                                            <p class="text-sm font-bold text-cyan-700">
                                                                {{ uploadProgress < 80 ? 'Uploading file…' : uploadProgress < 100 ? 'AI analysing sheet…' : '✓ Complete!' }}
                                                            </p>
                                                            <p class="text-[11px] text-slate-400 mt-0.5">{{ selectedFileName }}</p>
                                                        </div>
                                                        <div class="w-full">
                                                            <div class="flex justify-between mb-1">
                                                                <span class="text-[10px] text-slate-400">
                                                                    {{ uploadProgress < 80 ? 'Uploading…' : uploadProgress < 100 ? 'AI Analysing…' : 'Done!' }}
                                                                </span>
                                                                <span class="text-[10px] font-bold text-cyan-600">{{ uploadProgress }}%</span>
                                                            </div>
                                                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                                                <div
                                                                    class="h-2 rounded-full transition-all duration-300 ease-out"
                                                                    :class="uploadProgress === 100 ? 'bg-emerald-500' : 'bg-gradient-to-r from-cyan-400 to-blue-500'"
                                                                    :style="{ width: uploadProgress + '%' }"
                                                                ></div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>

                                                <!-- Supported formats tags -->
                                                <div class="flex items-center gap-2 mt-3 flex-wrap">
                                                    <span class="text-[10px] text-slate-400 font-medium">Supported:</span>
                                                    <span v-for="fmt in ['JPG','PNG','WEBP','PDF']" :key="fmt" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">{{ fmt }}</span>
                                                    <span class="ml-auto text-[10px] text-amber-500 font-semibold flex items-center gap-1">
                                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                                                        AI Powered
                                                    </span>
                                                </div>
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
                                <Button v-if="form.status !== 3" label="Add" icon="pi pi-plus" size="small" text rounded class="!text-xs !text-cyan-600" @click="addMaterial" />
                            </div>
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
                        <div class="px-2 pb-2">
                            <!-- Empty State -->
                            <div v-if="form.materials.length === 0" class="rounded-xl border-2 border-dashed border-slate-200 py-10 text-center">
                                <svg class="mx-auto mb-2 h-8 w-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">No materials added</p>
                                <p class="text-[10px] text-slate-300 mt-1">Click "Add" to add a material</p>
                            </div>

                            <!-- Cards Grid: one card per material, horizontal scroll -->
                            <div v-else class="overflow-x-auto p-2">
                                <div class="flex gap-3 min-w-max">
                                    <div
                                        v-for="(item, index) in form.materials"
                                        :key="index"
                                        class="relative flex-shrink-0 w-[200px] rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden hover:shadow-md transition-shadow"
                                    >
                                        <!-- Card Header: Product Name -->
                                        <div class="bg-gradient-to-br from-cyan-50 to-indigo-50 px-3 pt-3 pb-2 border-b border-slate-100">
                                            <!-- Material # badge + delete -->
                                            <div class="flex items-center justify-between mb-1.5">
                                                <span class="text-[9px] font-black uppercase tracking-widest text-indigo-400 bg-indigo-50 border border-indigo-100 px-2 py-0.5 rounded-full">
                                                    #{{ index + 1 }}
                                                </span>
                                                <Button
                                                    v-if="!isLocked && form.status !== 3 && !item.id"
                                                    icon="pi pi-trash"
                                                    text rounded severity="danger"
                                                    class="!h-5 !w-5 !text-[10px]"
                                                    @click="removeMaterial(index)"
                                                />
                                            </div>
                                            <!-- Product selector or product name -->
                                            <div class="space-y-1">
                                                <BaseSelect
                                                    v-model="item.product_id"
                                                    :options="products"
                                                    optionLabel="title"
                                                    optionValue="id"
                                                    filter
                                                    size="small"
                                                    :fluid="true"
                                                    :disabled="!!item.id || isLocked"
                                                    :error="form.errors[`materials.${index}.product_id`]"
                                                    placeholder="Select Product"
                                                />
                                                <BaseInput
                                                    v-model="item.material_name"
                                                    :disabled="!!item.id || isLocked"
                                                    size="small"
                                                    :fluid="true"
                                                    placeholder="Label / Custom Name"
                                                />
                                            </div>
                                        </div>

                                        <!-- Card Body: Data Rows -->
                                        <div class="divide-y divide-slate-50">
                                            <!-- Target Qty -->
                                            <div class="px-3 py-2 bg-slate-50/30">
                                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Target Qty (kg)</p>
                                                <BaseInputNumber
                                                    v-model="item.target_qty"
                                                    :disabled="!!item.id || isLocked"
                                                    :minFractionDigits="3"
                                                    size="small"
                                                    :fluid="true"
                                                    :error="form.errors[`materials.${index}.target_qty`]"
                                                />
                                            </div>

                                            <!-- Actual Qty -->
                                            <div class="px-3 py-2">
                                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Actual Qty (kg)</p>
                                                <BaseInputNumber
                                                    v-model="item.actual_qty"
                                                    :minFractionDigits="3"
                                                    size="small"
                                                    :fluid="true"
                                                    :disabled="isLocked"
                                                    :error="form.errors[`materials.${index}.actual_qty`]"
                                                />
                                            </div>

                                            <!-- Deviation Badge -->
                                            <div class="px-3 py-2 bg-slate-50/20 flex items-center justify-between">
                                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Deviation</p>
                                                <span
                                                    class="text-xs font-black px-2 py-0.5 rounded-full"
                                                    :class="item.deviation_quantity > 0
                                                        ? 'bg-rose-50 text-rose-600 border border-rose-100'
                                                        : item.deviation_quantity < 0
                                                        ? 'bg-emerald-50 text-emerald-600 border border-emerald-100'
                                                        : 'bg-slate-50 text-slate-400 border border-slate-100'"
                                                >
                                                    {{ item.deviation_quantity > 0 ? '+' : '' }}{{ item.deviation_quantity?.toFixed(3) }}
                                                </span>
                                            </div>

                                            <!-- UOM -->
                                            <div class="px-3 py-2">
                                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Unit</p>
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
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Summary Row: totals -->
                            <div v-if="form.materials.length > 0" class="mt-4 grid grid-cols-3 gap-3">
                                <div class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-3 text-center">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Total Target</p>
                                    <p class="text-sm font-black text-slate-700 mt-0.5">
                                        {{ form.materials.reduce((sum, m) => sum + Number(m.target_qty || 0), 0).toFixed(3) }} <span class="text-[10px] text-slate-400">kg</span>
                                    </p>
                                </div>
                                <div class="rounded-xl bg-indigo-50 border border-indigo-100 px-4 py-3 text-center">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-indigo-400">Total Actual</p>
                                    <p class="text-sm font-black text-indigo-700 mt-0.5">
                                        {{ form.materials.reduce((sum, m) => sum + Number(m.actual_qty || 0), 0).toFixed(3) }} <span class="text-[10px] text-indigo-400">kg</span>
                                    </p>
                                </div>
                                <div class="rounded-xl border px-4 py-3 text-center"
                                    :class="form.materials.reduce((sum, m) => sum + Number(m.deviation_quantity || 0), 0) > 0
                                        ? 'bg-rose-50 border-rose-100'
                                        : form.materials.reduce((sum, m) => sum + Number(m.deviation_quantity || 0), 0) < 0
                                        ? 'bg-emerald-50 border-emerald-100'
                                        : 'bg-slate-50 border-slate-100'"
                                >
                                    <p class="text-[9px] font-black uppercase tracking-widest"
                                        :class="form.materials.reduce((sum, m) => sum + Number(m.deviation_quantity || 0), 0) !== 0 ? 'text-rose-400' : 'text-slate-400'"
                                    >Net Deviation</p>
                                    <p class="text-sm font-black mt-0.5"
                                        :class="form.materials.reduce((sum, m) => sum + Number(m.deviation_quantity || 0), 0) > 0
                                            ? 'text-rose-600'
                                            : form.materials.reduce((sum, m) => sum + Number(m.deviation_quantity || 0), 0) < 0
                                            ? 'text-emerald-600'
                                            : 'text-slate-400'"
                                    >
                                        {{ form.materials.reduce((sum, m) => sum + Number(m.deviation_quantity || 0), 0) > 0 ? '+' : '' }}{{ form.materials.reduce((sum, m) => sum + Number(m.deviation_quantity || 0), 0).toFixed(3) }} <span class="text-[10px]">kg</span>
                                    </p>
                                </div>
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
