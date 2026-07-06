<script setup lang="ts">
import { useForm,usePage } from '@inertiajs/vue3';
import { computed, watch, ref, onMounted, onUnmounted } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import Button from 'primevue/button';
import axios from 'axios';
import Swal from 'sweetalert2';
import { PlusCircleIcon, InformationCircleIcon, BeakerIcon, ListBulletIcon, ClockIcon, ArrowDownTrayIcon, ScaleIcon, TruckIcon } from '@heroicons/vue/24/outline';
import Dialog from 'primevue/dialog';

const page = usePage();
interface BatchMaterial {
    id?: number;
    product_id: number | null;
    material_name: string;
    target_qty: number;
    actual_qty: number;
    uom_id: number | null;
}

const props = withDefaults(defineProps<{
    salesOrders?: any[];
    trucks?: any[];
    transporters?: any[];
    sales_executives?: any[];
    drivers?: any[];
    loading_sites?: any[];
    unloading_sites?: any[];
    products?: any[];
    uoms?: any[];
    taxes?: any[];
    statuses?: { label: string; value: number }[];
    nextBatchNo?: number;
    concretePumpOptions?: any[];
}>(), {
    salesOrders: () => [],
    trucks: () => [],
    transporters: () => [],
    sales_executives: () => [],
    drivers: () => [],
    products: () => [],
    uoms: () => [],
    taxes: () => [],
    statuses: () => [],
    nextBatchNo: 1,
    loading_sites: () => [],
    unloading_sites: () => [],
    concretePumpOptions: () => [],
});

const emit = defineEmits(['offline-batch-added', 'cancel','created']);

const blankMaterial = (): BatchMaterial => ({
    product_id: null,
    material_name: '',
    target_qty: 0,
    actual_qty: 0,
    uom_id: null,
});


const form = useForm({
    sales_order_id: null as number | null,
    batch_no: null as number | null,
    batch_size: 1,
    truck_id: null as number | null,
    transport_id: null as number | null,
    driver_id: null as number | null,
    sales_executive_id: null as number | null,
    concrete_pump: 'pump' as string | null,
    empty_weight_truck: 0,
    uom_id: null as number | null,
    site_id: null as number | null,
    status: 1,
    start_time: new Date() as Date | null,
    end_time: null as Date | null,
    empty_time: new Date() as Date | null,
    load_time: new Date() as Date | null,
    materials: [blankMaterial()] as BatchMaterial[],
    empty_weight_photo: null as string | null,
});

const addMaterial = () => form.materials.push(blankMaterial());
const removeMaterial = (index: number) => {
    if (form.materials.length > 1) form.materials.splice(index, 1);
};

const customSettings = page.props.custom_settings as any;

const isTimeManuallySet = ref(false);
const updatingProgrammatically = ref(false);
let liveTimerInterval: any = null;

const startLiveTimer = () => {
    liveTimerInterval = setInterval(() => {
        if (!isTimeManuallySet.value) {
            updatingProgrammatically.value = true;
            form.empty_time = new Date();
            updatingProgrammatically.value = false;
        }
    }, 1000);
};


onMounted(() => {
    startLiveTimer();
});

onUnmounted(() => {
    if (liveTimerInterval) {
        clearInterval(liveTimerInterval);
    }
});

watch(() => form.empty_time, (newVal) => {
    if (!updatingProgrammatically.value) {
        isTimeManuallySet.value = true;
        if (liveTimerInterval) {
            clearInterval(liveTimerInterval);
            liveTimerInterval = null;
        }
    }
});

const isMetricTon = computed(() => {
    return customSettings?.batching?.InvoiceInMetricTon == 1;
});

const selectedSalesOrder = computed(() => {
    if (!form.sales_order_id) return null;
    return props.salesOrders.find(wo => Number(wo.id) === Number(form.sales_order_id));
});

const nextBatchNoDisplay = computed(() => {
    return props.nextBatchNo;
});

const salesOrderDetails = computed(() => {
    if (!selectedSalesOrder.value) return [];
    const wo = selectedSalesOrder.value;
    return [
        { label: 'Order #', value: wo.full_number },
        { label: 'Customer', value: wo.customer?.legal_name || 'N/A' },
        { label: 'Site', value: wo.site?.name || 'N/A' },
        { label: 'Design', value: wo.mix_design?.design_name || 'N/A' },
        // { label: 'Grade/Ratio', value: `${wo.mix_design?.concrete_grade?.name || wo.mix_design?.grade || 'N/A'} (${wo.mix_design?.concrete_grade?.concrete_ratio || 'N/A'})` },
        { label: 'Total Qty', value: `${wo.produced_qty} / ${wo.total_qty} m³` },
        // { label: 'Produced', value: `${wo.produced_qty} m³` },
    ];
});

watch(() => form.sales_order_id, (newVal) => {
    if (newVal && selectedSalesOrder.value) {
        // Calculate remaining qty and assign to batch_size (capped at 6)
        const remaining = Number((Number(selectedSalesOrder.value.total_qty) - Number(selectedSalesOrder.value.produced_qty)).toFixed(3));
        form.batch_size = remaining > 6 ? 6 : (remaining > 0 ? remaining : 1);
        
        // Assign sales executive
        form.sales_executive_id = selectedSalesOrder.value.sales_executive_id || null;

        if (selectedSalesOrder.value.mix_design?.items) {
            form.materials = selectedSalesOrder.value.mix_design.items.map((item: any) => ({
                product_id: item.product_id,
                material_name: item.product?.title || 'Material',
                target_qty: Number(item.cross_quantity || item.quantity || 0) * form.batch_size,
                actual_qty: 0,
                uom_id: item.uom_id || item.product?.unit_id,
            }));
        } else {
            form.materials = [blankMaterial()];
        }
        form.batch_no = props.nextBatchNo;
        form.concrete_pump = selectedSalesOrder.value.concrete_pump;
    } else {
        form.materials = [blankMaterial()];
        form.batch_no = props.nextBatchNo;
        form.concrete_pump = null;
        form.sales_executive_id = null;
        form.batch_size = 1;
    }
});

// Default uom_id to MTR when uoms become available
watch(() => props.uoms, (uoms) => {
    if (!form.uom_id && uoms?.length) {
        const mtr = uoms.find((u: any) => u.unit_code?.toUpperCase() === 'MTR');
        if (mtr) form.uom_id = mtr.id;
    }
}, { immediate: true });

// Default site_id to first loading site when loading_sites become available
watch(() => props.loading_sites, (sites) => {
    if (!form.site_id && sites?.length) {
        form.site_id = sites[0].id;
    }
}, { immediate: true });

watch(() => form.batch_size, (newVal) => {
    if (newVal !== null && newVal !== undefined) {
        if (newVal > 9.9) {
            form.batch_size = 9.9;
        }
    }
    
    if (form.sales_order_id && selectedSalesOrder.value?.mix_design?.items) {
        form.materials.forEach((mat, index) => {
            const originalItem = selectedSalesOrder.value.mix_design.items[index];
            if (originalItem) {
                mat.target_qty = Number(originalItem.cross_quantity || originalItem.quantity || 0) * newVal;
            }
        });
    }
});

const showEmptyWeightModal = ref(false);
const tareForm = ref({
    truck_id: null as number | null,
    empty_weight: null as number | null,
});
const tareFormErrors = ref({
    truck_id: '',
    empty_weight: '',
});
const tareSubmitting = ref(false);

const openTareModal = () => {
    tareForm.value.truck_id = form.truck_id;
    tareForm.value.empty_weight = null;
    tareFormErrors.value.truck_id = '';
    tareFormErrors.value.empty_weight = '';
    showEmptyWeightModal.value = true;
};

const saveTareWeight = async () => {
    tareFormErrors.value.truck_id = '';
    tareFormErrors.value.empty_weight = '';
    
    if (!tareForm.value.truck_id) {
        tareFormErrors.value.truck_id = 'Truck is required';
        return;
    }
    if (!tareForm.value.empty_weight || tareForm.value.empty_weight <= 0) {
        tareFormErrors.value.empty_weight = 'Empty weight must be greater than 0';
        return;
    }

    tareSubmitting.value = true;
    try {
        const response = await axios.post(route('batches.store-truck-empty-weight'), {
            truck_id: tareForm.value.truck_id,
            empty_weight: tareForm.value.empty_weight,
        });

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Empty weight registered successfully',
            timer: 1500,
            showConfirmButton: false,
        });

        if (form.truck_id === tareForm.value.truck_id) {
            form.empty_weight_truck = response.data.empty_weight;
        }

        showEmptyWeightModal.value = false;
    } catch (err: any) {
        console.error('Failed to save tare weight:', err);
        if (err.response?.data?.errors) {
            tareFormErrors.value.truck_id = err.response.data.errors.truck_id?.[0] || '';
            tareFormErrors.value.empty_weight = err.response.data.errors.empty_weight?.[0] || '';
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: err.response?.data?.message || 'Failed to register tare weight.',
            });
        }
    } finally {
        tareSubmitting.value = false;
    }
};

watch(() => form.truck_id, async (newVal) => {
    form.empty_weight_truck = 0;
    if (newVal) {
        try {
            const response = await axios.get(route('batches.truck-empty-weight'), {
                params: { truck_id: newVal }
            });
            form.empty_weight_truck = response.data.empty_weight || 0;
        } catch (err) {
            console.error('Failed to fetch truck empty weight:', err);
        }
    }
});

import { useWeighbridge } from '@/Composables/useWeighbridge';

const { isScaleConnected, captureWeight, captureCameraSnap } = useWeighbridge();

const handleWeightCapture = () => {
    captureWeight(async (w) => {
        form.empty_weight_truck = w;
        form.empty_time = new Date();
        
        if (customSettings?.batching?.camera == 1 && (customSettings?.batching?.camera_url || customSettings?.batching?.camera_url_1)) {
            const cameraUrl = customSettings.batching.camera_url_1 || customSettings.batching.camera_url;
            try {
                const snap = await captureCameraSnap(cameraUrl);
                    form.empty_weight_photo = snap;
            } catch (err) {
                console.error('Camera capture failed:', err);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'warning',
                    title: 'Weight captured, but camera failed',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }
    });
};

const handleWeightCaptureDialog = () => {
    captureWeight((w) => {
        tareForm.value.empty_weight = w;
    });
};


// console.log('opekrpe',props);
// console.log('customSettings?.batching?.manual_weight',customSettings?.batching?.manual_weight);

const submit = () => {
    form.clearErrors();
    
    const maxAllowed = selectedSalesOrder.value 
        ? Math.max(0, Number(selectedSalesOrder.value.total_qty) - Number(selectedSalesOrder.value.produced_qty))
        : 9.9;
        const remainingQty = selectedSalesOrder.value
    ? Number(
        (
            Number(selectedSalesOrder.value.total_qty) -
            Number(selectedSalesOrder.value.produced_qty)
        ).toFixed(3)
      )
    : 9.9;

    const validations = [
        { condition: !form.sales_order_id, field: 'sales_order_id', message: 'Sales Order is required' },
        { condition: !form.truck_id, field: 'truck_id', message: 'Truck is required' },
        // { condition: !form.transport_id, field: 'transport_id', message: 'Transporter is required' },
        // { condition: !form.driver_id, field: 'driver_id', message: 'Driver is required' },
        // { condition: !form.sales_executive_id, field: 'sales_executive_id', message: 'Sales Executive is required' },
        { condition: !form.batch_size || form.batch_size < 0.1 || form.batch_size > 9.9, field: 'batch_size', message: 'Batch Quantity must be between 0.1 and 9.9 m³' },
        {
            condition:
                form.sales_order_id &&
                Number(form.batch_size.toFixed(3)) > remainingQty,
            field: 'batch_size',
            message: `Batch Quantity cannot exceed remaining order quantity (${remainingQty.toFixed(3)} m³)`
        },
        { condition: (form.empty_weight_truck === null || form.empty_weight_truck === undefined || form.empty_weight_truck <= 0.00), field: 'empty_weight_truck', message: 'Empty Weight is required' },
        { condition: !form.empty_time, field: 'empty_time', message: 'Empty Time is required' },
        // { condition: form.sales_order_id && form.batch_size > maxAllowed, field: 'batch_size', message: `Batch Quantity cannot exceed remaining order quantity (${maxAllowed.toFixed(3)} m³)` }
    ];

    let hasErrors = false;
    validations.forEach(v => {
        if (v.condition) {
            form.setError(v.field, v.message);
            hasErrors = true;
        }
    });

    if (hasErrors) return;

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

    // Check if browser is offline
    if (!navigator.onLine) {
        const formattedBatch = {
            id: -Date.now(), // Temporary negative ID
            batch_no: form.batch_no || props.nextBatchNo,
            sales_order_id: form.sales_order_id,
            sales_order: selectedSalesOrder.value,
            batch_size: form.batch_size,
            truck_id: form.truck_id,
            truck_registration: props.trucks.find(t => t.id === form.truck_id)?.registration || 'N/A',
            transport_id: form.transport_id,
            driver_id: form.driver_id,
            sales_executive_id: form.sales_executive_id,
            empty_weight_truck: form.empty_weight_truck,
            concrete_pump: form.concrete_pump,
            uom_id: form.uom_id,
            site_id: form.site_id,
            status: 1,
            start_time: formatDateTime(form.start_time) || formatDateTime(new Date()),
            end_time: formatDateTime(form.end_time),
            empty_time: formatDateTime(form.empty_time),
            load_time: formatDateTime(form.load_time),
            materials: form.materials.map((item: BatchMaterial) => ({
                ...item,
                material_name: item.material_name || props.products.find((p: any) => p.id === item.product_id)?.title || 'Material',
            })),
            empty_weight_photo: form.empty_weight_photo,
            is_offline_pending: true,
            created_at: new Date().toISOString(),
        };

        const queue = JSON.parse(localStorage.getItem('offline_batches') || '[]');
        queue.push(formattedBatch);
        localStorage.setItem('offline_batches', JSON.stringify(queue));

        emit('offline-batch-added', formattedBatch);

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'warning',
            title: 'No network. Queued locally for synchronization.',
            showConfirmButton: false,
            timer: 3000
        });

        form.reset();
        isTimeManuallySet.value = false;
        if (!liveTimerInterval) {
            startLiveTimer();
        }
        form.start_time = new Date();
        form.clearErrors();
        form.status = 1;
        form.concrete_pump = null;
        form.batch_size = 1;
        form.materials = [blankMaterial()];
        return;
    }

    form.transform((data) => ({
        ...data,
        start_time: formatDateTime(data.start_time),
        end_time: formatDateTime(data.end_time),
        empty_time: formatDateTime(data.empty_time),
        load_time: formatDateTime(data.load_time),
        materials: data.materials.map((item: BatchMaterial) => ({
            ...item,
            material_name: item.material_name || props.products.find((p: any) => p.id === item.product_id)?.title || 'Material',
        })),
    })).post(route('batches.store'), {
        onSuccess: (page: any) => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Batch created successfully',
                timer: 1500,
                showConfirmButton: false,
            });
            form.reset();
            isTimeManuallySet.value = false;
            if (!liveTimerInterval) {
                startLiveTimer();
            }
            form.start_time = new Date();
            form.clearErrors();
            form.status = 1;
            form.concrete_pump = null;
            form.batch_size = 1;
            form.materials = [blankMaterial()];
             // Force reload props from server to get latest batches/nextBatchNo
            emit('created') // trigger parent to refresh
        },
        onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join('\n');
            Swal.fire({
                icon: 'error',
                title: 'Creation Failed',
                text: errorMessages || 'Please check the input fields.',
                confirmButtonColor: '#4f46e5', // indigo color to match create form theme
            });
        }
    });
};
</script>

<template>
    <div class="no-print rounded-2xl border border-slate-100 bg-white shadow-xl shadow-slate-100/50 overflow-hidden">
        <!-- Premium Header Banner -->
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 px-6 py-5 text-white">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-white/10 p-2.5 backdrop-blur-md ring-1 ring-white/20">
                        <PlusCircleIcon class="h-6 w-6 text-indigo-300 animate-pulse" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold uppercase tracking-wider text-white">Plan & Create Batch</h2>
                        <p class="mt-0.5 text-xs text-slate-300">Set logistics, weights, and live target batch quantities.</p>
                    </div>
                </div>

                <div v-if="nextBatchNoDisplay" class="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-2 backdrop-blur-md border border-white/10 self-start sm:self-center">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-slate-300">Batch Number</span>
                    <span class="text-base font-black text-indigo-300">#{{ nextBatchNoDisplay }}</span>
                </div>
            </div>
        </div>        
        
        <div class="p-6 space-y-6">
            <!-- Section 1: Sales Order Link & Reference Info -->
            <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-5 shadow-sm">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <div class="flex flex-col justify-center">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-3 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-indigo-600"></span>
                            Sales Order Link
                        </h3>
                        <BaseSelect 
                            v-model="form.sales_order_id" 
                            :options="salesOrders" 
                            optionLabel="full_number" 
                            optionValue="id" 
                            filter 
                            label="Select Sales Order" 
                            required
                            :error="form.errors.sales_order_id" 
                        />
                    </div>
                    
                    <div v-if="salesOrderDetails.length" class="lg:col-span-3 border-t lg:border-t-0 lg:border-l border-slate-200/60 lg:pl-6 pt-4 lg:pt-0">
                        <h3 class="mb-3 text-[10px] font-bold uppercase tracking-widest text-indigo-600 flex items-center justify-between">
                            <span>Reference Details</span>
                            <span class="rounded bg-indigo-100 px-2 py-0.5 text-[9px] font-bold text-indigo-700">Live</span>
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div v-for="detail in salesOrderDetails" :key="detail.label" class="flex flex-col">
                                <span class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">{{ detail.label }}</span>
                                <span class="text-xs font-bold text-slate-800 mt-0.5 leading-tight">{{ detail.value }}</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Loading Site</span>
                                <span class="text-xs font-bold text-slate-800 mt-0.5 leading-tight">
                                    {{ loading_sites.find(s => Number(s.id) === Number(form.site_id))?.name || 'Loading Site Not Configured' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Logistics, Weights & Execution Parameters -->
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                <div class="flex items-center gap-2 border-b border-slate-50 bg-slate-50/50 px-5 py-3">
                    <div class="rounded-lg bg-indigo-50 p-1.5 text-indigo-600 ring-1 ring-indigo-100">
                        <ClockIcon class="h-4 w-4" />
                    </div>

                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Batch Parameters</h3>
                    <button @click="openTareModal" type="button" 
                                class="p-2.5 rounded-xl transition-all duration-200 border bg-indigo-50 border-indigo-200 text-indigo-600 hover:bg-indigo-100 flex items-center justify-center" 
                                title="Register Tare Weight">
                                <div class="flex flex-col items-center gap-0.5">
                                    <ScaleIcon class="w-4 h-4 text-indigo-500" />   
                                    <span class="text-[7px] font-black uppercase tracking-widest text-indigo-600">Register</span>
                                </div>
                            </button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 p-4">
                    <div>
                        <BaseSelect v-model="form.truck_id" :options="trucks" optionLabel="registration" optionValue="id" filter label="Assign Truck" required :error="form.errors.truck_id" />
                    </div>
                    <div>
                        <BaseSelect v-model="form.transport_id" :options="transporters" optionLabel="legal_name" optionValue="id" filter label="Transporter" showClear />
                    </div>
                    <div>
                        <BaseSelect v-model="form.driver_id" :options="drivers" optionLabel="label" optionValue="id" filter label="Driver" showClear />
                    </div>
                    <div>
                        <BaseSelect v-model="form.sales_executive_id" :options="sales_executives" optionLabel="label" optionValue="id" filter label="Sales Executive" showClear />
                    </div>
                    <div>
                        <BaseSelect v-model="form.concrete_pump" :options="concretePumpOptions" optionLabel="label" optionValue="value" label="Concrete Type" placeholder="Select Concrete Type" :error="form.errors.concrete_pump" />
                    </div>
                    <div>
                        <BaseInputNumber v-model="form.batch_size" label="Batch Quantity (m³)" :min="0.1" :minFractionDigits="1" :maxFractionDigits="1" :max="9.9" required :error="form.errors.batch_size" />
                    </div>
                    <div>
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <BaseInputNumber v-model="form.empty_weight_truck" :disabled="customSettings?.batching?.manual_weight === 0" label="Empty Weight (KGS)" :required="customSettings?.batching?.manual_weight === 1" :error="form.errors.empty_weight_truck" />
                            </div>
                            
                            <button @click="handleWeightCapture" type="button" v-if="customSettings?.batching?.manual_weight === 0" 
                                :class="['p-2.5 rounded-xl transition-all duration-200 border shadow-sm flex items-center justify-center', isScaleConnected ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border-emerald-200' : 'bg-amber-50 text-amber-600 hover:bg-amber-100 border-amber-200']" 
                                :title="isScaleConnected ? 'Capture Current Weight' : 'Connect & Capture'">
                                <div class="flex flex-col items-center gap-0.5">
                                    <ArrowDownTrayIcon class="w-4 h-4 animate-bounce" />
                                    <span v-if="customSettings?.batching?.camera == 1" class="text-[7px] font-black uppercase tracking-widest">Snap</span>
                                </div>
                            </button>
                        </div>
                        <div v-if="form.empty_weight_photo" class="mt-2 relative group rounded-xl overflow-hidden shadow-inner border border-slate-100">
                            <img :src="form.empty_weight_photo" class="w-full h-24 object-cover" />
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <button @click="form.empty_weight_photo = null" type="button" class="text-white text-xs font-bold bg-red-600 hover:bg-red-700 px-3 py-1.5 rounded-lg transition-all">Remove Snap</button>
                            </div>
                        </div>
                    </div>
                    <div>
                        <BaseDatePicker v-model="form.empty_time" label="Empty Time" showTime hourFormat="24" fluid :required="isMetricTon" :error="form.errors.empty_time" />
                    </div>
                    
                </div>
            </div>

            <!-- Section 3: Target Recipe Live Yield Visualization -->
            <div v-if="selectedSalesOrder?.mix_design?.items?.length" class="rounded-2xl border border-indigo-100 bg-indigo-50/10 p-5 shadow-sm transition-all duration-300 hover:shadow-md">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <BeakerIcon class="w-5 h-5 text-indigo-600" />
                        <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-900">Target Recipe Yield ({{ selectedSalesOrder.mix_design?.design_name }})</h3>
                    </div>
                    <span class="rounded-lg bg-indigo-100 px-3 py-1 text-xs font-bold text-indigo-700">
                        Batch Factor: {{ form.batch_size }} m³
                    </span>
                </div>
                
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <div v-for="item in selectedSalesOrder.mix_design.items" :key="item.id" 
                        class="flex items-center justify-between rounded-xl bg-white border border-indigo-100/50 p-3 shadow-sm hover:border-indigo-200 transition-all duration-200">
                        <div class="flex flex-col">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Material</span>
                            <span class="text-xs font-bold text-slate-700 mt-0.5">{{ item.product?.title || 'Material' }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-[9px] font-bold text-indigo-400 uppercase tracking-wider">Target Qty</span>
                            <div class="text-xs font-black text-indigo-700 mt-0.5">
                                {{ (Number(item.cross_quantity || item.quantity || 0) * form.batch_size).toFixed(3) }}
                                <span class="text-[9px] font-normal text-slate-400 ml-0.5">{{ item.uom?.unit_code || 'KGS' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="form.errors.materials" class="mx-6 mb-6 p-4 rounded-xl bg-rose-50 border border-rose-100 text-rose-800 text-xs flex flex-col gap-1.5 shadow-sm">
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

        <!-- Sticky Form Actions Footer -->
        <div class="border-t border-slate-100 bg-slate-50/50 px-6 py-4 flex justify-end gap-3">
            <Button 
                label="Add Batch" 
                icon="pi pi-check" 
                class="!bg-indigo-600 hover:!bg-indigo-700 !border-indigo-600 !px-8 !py-2.5 !rounded-xl text-xs font-bold uppercase tracking-wider shadow-lg shadow-indigo-100" 
                :loading="form.processing"
                @click="submit" 
            />
        </div>

        <Dialog v-model:visible="showEmptyWeightModal" modal :style="{ width: '480px' }" class="p-fluid rounded-3xl overflow-hidden shadow-2xl border-0">
            <template #header>
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-indigo-50 text-indigo-600 rounded-2xl ring-4 ring-indigo-50/50">
                        <ScaleIcon class="w-6 h-6 animate-pulse" />
                    </div>
                    <div>
                        <h3 class="text-base font-black tracking-tight text-slate-800 uppercase">Register Tare Weight</h3>
                        <p class="text-[10px] text-slate-400 font-bold uppercase mt-0.5 tracking-wider">Save Truck Empty Weight</p>
                    </div>
                </div>
            </template>

            <div class="flex flex-col gap-5 py-2 text-xs">
               

                <div class="flex flex-col gap-2">
                    <label class="font-bold text-slate-500 uppercase tracking-wider text-[10px] flex items-center gap-1.5">
                        <TruckIcon class="w-4 h-4 text-slate-400" />
                        Select Truck
                    </label>
                    <BaseSelect 
                        v-model="tareForm.truck_id" 
                        :options="trucks" 
                        optionLabel="registration" 
                        optionValue="id" 
                        filter 
                        placeholder="Choose Truck Registration" 
                        class="!rounded-2xl border-slate-200/80 shadow-sm focus:border-indigo-500 focus:shadow-indigo-500/10 text-sm font-bold"
                        :error="tareFormErrors.truck_id" 
                    />
                    <small v-if="tareFormErrors.truck_id" class="text-red-500 font-bold uppercase tracking-wider mt-1">{{ tareFormErrors.truck_id }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="font-bold text-slate-500 uppercase tracking-wider text-[10px] flex items-center gap-1.5">
                        <ScaleIcon class="w-4 h-4 text-slate-400" />
                        Empty Weight (KGS)
                    </label>
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <BaseInputNumber 
                                v-model="tareForm.empty_weight" 
                                placeholder="e.g. 5400" 
                                class="!rounded-2xl border-slate-200/80 shadow-sm focus:border-indigo-500 text-sm font-bold"
                                :error="tareFormErrors.empty_weight" 
                            />
                        </div>
                        <button @click="handleWeightCaptureDialog" type="button" v-if="customSettings?.batching?.manual_weight === 0" 
                            :class="['p-2.5 rounded-xl transition-all duration-200 border shadow-sm flex items-center justify-center h-10', isScaleConnected ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border-emerald-200' : 'bg-amber-50 text-amber-600 hover:bg-amber-100 border-amber-200']" 
                            :title="isScaleConnected ? 'Capture Current Weight' : 'Connect & Capture'">
                            <div class="flex flex-col items-center gap-0.5">
                                <ArrowDownTrayIcon class="w-4 h-4 animate-bounce" />
                                <span v-if="customSettings?.batching?.camera == 1" class="text-[7px] font-black uppercase tracking-widest">Snap</span>
                            </div>
                        </button>
                    </div>
                    <small v-if="tareFormErrors.empty_weight" class="text-red-500 font-bold uppercase tracking-wider mt-1">{{ tareFormErrors.empty_weight }}</small>
                </div>
            </div>

            <template #footer>
                <div class="flex gap-3 justify-end pt-4 border-t border-slate-100/80 mt-4">
                    <Button 
                        label="Cancel" 
                        text 
                        severity="secondary" 
                        @click="showEmptyWeightModal = false" 
                        class="!text-[11px] !font-black !uppercase !tracking-widest !rounded-2xl !py-3 !px-6 hover:!bg-slate-50 transition-all duration-200" 
                    />
                    <Button 
                        label="Save Weight" 
                        :loading="tareSubmitting" 
                        @click="saveTareWeight" 
                        class="!bg-gradient-to-r !from-indigo-600 !to-violet-600 hover:!from-indigo-700 hover:!to-violet-700 !border-0 !text-white !text-[11px] !font-black !uppercase !tracking-widest !rounded-2xl !py-3 !px-7 shadow-lg shadow-indigo-600/10 hover:shadow-indigo-600/25 transition-all duration-300 transform hover:-translate-y-0.5" 
                    />
                </div>
            </template>
        </Dialog>
    </div>
</template>







