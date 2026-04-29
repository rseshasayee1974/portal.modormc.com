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
import { PlusCircleIcon, InformationCircleIcon, BeakerIcon, ListBulletIcon, ClockIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

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
    workOrders?: any[];
    trucks?: any[];
    transporters?: any[];
    personnel?: any[];
    loading_sites?: any[];
    unloading_sites?: any[];
    products?: any[];
    uoms?: any[];
    taxes?: any[];
    statuses?: { label: string; value: number }[];
    nextBatchNo?: number;
}>(), {
    workOrders: () => [],
    trucks: () => [],
    transporters: () => [],
    personnel: () => [],
    products: () => [],
    uoms: () => [],
    taxes: () => [],
    statuses: () => [],
    nextBatchNo: 1,
    loading_sites: () => [],
    unloading_sites: () => [],
});

const blankMaterial = (): BatchMaterial => ({
    product_id: null,
    material_name: '',
    target_qty: 0,
    actual_qty: 0,
    uom_id: null,
});

const form = useForm({
    work_order_id: null as number | null,
    batch_no: null as number | null,
    batch_size: 1,
    truck_id: null as number | null,
    transport_id: null as number | null,
    driver_id: null as number | null,
    empty_weight_truck: 0,
    uom_id: null as number | null,
    site_id: null as number | null,
    status: 1,
    start_time: new Date() as Date | null,
    end_time: null as Date | null,
    materials: [blankMaterial()] as BatchMaterial[],
});

const addMaterial = () => form.materials.push(blankMaterial());
const removeMaterial = (index: number) => {
    if (form.materials.length > 1) form.materials.splice(index, 1);
};

const customSettings = page.props.custom_settings as any;

const selectedWorkOrder = computed(() => {
    if (!form.work_order_id) return null;
    return props.workOrders.find(wo => Number(wo.id) === Number(form.work_order_id));
});

const nextBatchNoDisplay = computed(() => {
    return props.nextBatchNo;
});

const workOrderDetails = computed(() => {
    if (!selectedWorkOrder.value) return [];
    const wo = selectedWorkOrder.value;
    return [
        { label: 'Customer', value: wo.customer?.legal_name || 'N/A' },
        { label: 'Site', value: wo.site?.name || 'N/A' },
        { label: 'Design', value: wo.mix_design?.design_name || 'N/A' },
        // { label: 'Grade/Ratio', value: `${wo.mix_design?.concrete_grade?.name || wo.mix_design?.grade || 'N/A'} (${wo.mix_design?.concrete_grade?.concrete_ratio || 'N/A'})` },
        { label: 'Total Qty', value: `${wo.produced_qty} / ${wo.total_qty} m³` },
        // { label: 'Produced', value: `${wo.produced_qty} m³` },
    ];
});

watch(() => form.work_order_id, (newVal) => {
    if (newVal && selectedWorkOrder.value?.mix_design?.items) {
        form.materials = selectedWorkOrder.value.mix_design.items.map((item: any) => ({
            product_id: item.product_id,
            material_name: item.product?.title || 'Material',
            target_qty: Number(item.cross_quantity || item.quantity || 0) * form.batch_size,
            actual_qty: 0,
            uom_id: item.uom_id || item.product?.unit_id,
        }));
        form.batch_no = props.nextBatchNo;
    } else {
        form.materials = [blankMaterial()];
        form.batch_no = props.nextBatchNo;
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
    
    if (form.work_order_id && selectedWorkOrder.value?.mix_design?.items) {
        form.materials.forEach((mat, index) => {
            const originalItem = selectedWorkOrder.value.mix_design.items[index];
            if (originalItem) {
                mat.target_qty = Number(originalItem.cross_quantity || originalItem.quantity || 0) * newVal;
            }
        });
    }
});

watch(() => form.truck_id, () => {
    form.empty_weight_truck = 0;
});

import { useWeighbridge } from '@/Composables/useWeighbridge';

const { isScaleConnected, captureWeight } = useWeighbridge();

const submit = () => {
    form.clearErrors();
    
    // Frontend Validation
    const validations = [
        { condition: !form.work_order_id, field: 'work_order_id', message: 'Work Order is required' },
        { condition: !form.truck_id, field: 'truck_id', message: 'Truck is required' },
        // { condition: !form.transport_id, field: 'transport_id', message: 'Transporter is required' },
        // { condition: !form.driver_id, field: 'driver_id', message: 'Driver is required' },
        { condition: !form.batch_size || form.batch_size < 0.2 || form.batch_size > 9.9, field: 'batch_size', message: 'Batch Quantity must be between 0.2 and 9.9 m³' }
    ];

    let hasErrors = false;
    validations.forEach(v => {
        if (v.condition) {
            form.setError(v.field, v.message);
            hasErrors = true;
        }
    });

    if (hasErrors) return;

    form.transform((data) => ({
        ...data,
        start_time: data.start_time instanceof Date ? data.start_time.toISOString() : data.start_time,
        end_time: data.end_time instanceof Date ? data.end_time.toISOString() : data.end_time,
        materials: data.materials.map((item: BatchMaterial) => ({
            ...item,
            material_name: item.material_name || props.products.find((p: any) => p.id === item.product_id)?.title || 'Material',
        })),
    })).post(route('batches.store'), {
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Batch created successfully',
                timer: 2200,
                showConfirmButton: false,
            });
            form.reset();
            form.start_time = new Date();
            form.clearErrors();
            form.status = 1;
            form.batch_size = 1;
            form.materials = [blankMaterial()];
        },
        onError: (errors) => {
            console.error('Batch creation failed:', errors);
        }
    });
};
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
            <div class="flex items-start justify-between gap-2.5">
                <div class="flex items-start gap-2.5">
                    <div class="rounded-lg bg-cyan-50 p-1.5 text-cyan-600">
                        <PlusCircleIcon class="h-4 w-4" />
                    </div>
                    <div class="flex flex-col">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700">Create Batch</h2>
                        <p class="mt-1 text-xs text-slate-400">Plan batch and define target vs actual material usage.</p>
                    </div>
                </div>

                <div v-if="nextBatchNoDisplay" class="flex items-center gap-2 rounded-lg bg-indigo-50 px-3 py-1.5 border border-indigo-100">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-indigo-400">Batch Number</span>
                    <span class="text-sm font-black text-indigo-700">#{{ nextBatchNoDisplay }}</span>
                </div>
                
            </div>
        </div>        
        <div class="p-5">
            <div class="grid grid-cols-12 gap-8">
                <!-- Left Column: Selection & Reference Details -->
                <div class="col-span-12 md:col-span-3">
                    <div class="sticky top-4 space-y-6">
                        <div class="rounded-xl bg-slate-50 p-4 border border-slate-200/60 shadow-sm">
                            <BaseSelect 
                                v-model="form.work_order_id" 
                                :options="workOrders" 
                                optionLabel="order_no" 
                                optionValue="id" 
                                filter 
                                label="Select Work Order" 
                                required
                                :error="form.errors.work_order_id" 
                            />
                        </div>

                        <!-- Work Order Details Card -->
                        <div v-if="workOrderDetails.length" class="rounded-xl border border-indigo-100 bg-white p-4 shadow-sm overflow-hidden relative">
                            <div class="absolute top-0 right-0 p-2 opacity-10">
                                <InformationCircleIcon class="w-12 h-12 text-indigo-500" />
                            </div>
                            <h3 class="mb-4 text-[10px] font-bold uppercase tracking-widest text-indigo-500 border-b border-indigo-50 pb-2">Reference Details</h3>
                            <div class="space-y-4">
                                <div v-for="detail in workOrderDetails" :key="detail.label" class="flex flex-col">
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400">{{ detail.label }}</span>
                                    <span class="text-xs font-semibold text-slate-700 leading-tight">{{ detail.value }}</span>
                                </div>
                            </div>
                            <div class="col-span-12 md:col-span-3 mt-4">
                                <BaseSelect v-model="form.site_id" :options="loading_sites" :disabled="true" optionLabel="name" optionValue="id" filter label="Loading Site" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Batch Configuration & Materials -->
                <div class="col-span-12 md:col-span-9 space-y-6">
                    <!-- Batch Core Config Row -->
                    <div class="rounded-xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                        <div class="flex items-center gap-2 border-b border-slate-50 bg-slate-50/50 px-4 py-2.5">
                            <div class="rounded-md bg-indigo-100 p-1 text-indigo-600">
                                <ClockIcon class="h-4 w-4" />
                            </div>
                            <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Execution Parameters</h3>
                        </div>
                        <div class="grid grid-cols-12 gap-5 p-5">
                            <div class="col-span-12 md:col-span-3">
                                <BaseSelect v-model="form.truck_id" :options="trucks" optionLabel="registration" optionValue="id" filter label="Assign Truck" required :error="form.errors.truck_id" />
                            </div>
                            <div class="col-span-12 md:col-span-3">
                                <BaseSelect v-model="form.transport_id" :options="transporters" optionLabel="legal_name" optionValue="id" filter label="Transporter"  />
                            </div>
                            <div class="col-span-12 md:col-span-3">
                                <BaseSelect v-model="form.driver_id" :options="personnel" optionLabel="first_name" optionValue="id" filter label="Driver"  />
                            </div>
                            <div class="col-span-12 md:col-span-3">
                                <BaseInputNumber v-model="form.batch_size" label="Batch Quantity (m³)" :min="0.2" :minFractionDigits="1" :maxFractionDigits="1" :max="9.9" required :error="form.errors.batch_size" />
                            </div>
                            <div class="col-span-12 md:col-span-3">
                                <div class="flex items-end">
                                    <div class="flex-1">
                                        <BaseInputNumber v-model="form.empty_weight_truck" :disabled="customSettings.batching.manual_weight" label="Empty Weight" :error="form.errors.empty_weight_truck" />
                                    </div>
                                    <button @click="captureWeight((w) => form.empty_weight_truck = w)" type="button" v-if="customSettings.batching.manual_weight" 
                                        :class="['p-2 rounded transition-colors border', isScaleConnected ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border-emerald-200' : 'bg-amber-50 text-amber-600 hover:bg-amber-100 border-amber-200']" 
                                        :title="isScaleConnected ? 'Capture Current Weight' : 'Connect & Capture'">
                                        <ArrowDownTrayIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                            <!-- <div class="col-span-12 md:col-span-3">
                                <BaseSelect v-model="form.uom_id" :options="uoms" optionLabel="unit_code" optionValue="id" label="Unit of Measure" filter :error="form.errors.uom_id" />
                            </div> -->
                            
                            
                            <div class="col-span-12 md:col-span-3">
                                <BaseDatePicker v-model="form.start_time" label="Scheduled Start" showTime hourFormat="24" fluid :error="form.errors.start_time" />
                            </div>
                            
                        </div>
                    </div>

                    <!-- Target Recipe Visualization -->
                    <!-- <div v-if="selectedWorkOrder?.mix_design?.items?.length" class="rounded-xl border border-indigo-100 bg-indigo-50/30 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <BeakerIcon class="w-4 h-4 text-indigo-500" />
                                <h3 class="text-[10px] font-bold uppercase tracking-widest text-indigo-500">Target Recipe ({{ selectedWorkOrder.mix_design?.design_name }})</h3>
                            </div>
                            <span class="text-[9px] text-indigo-400 font-medium">Batch Factor: {{ form.batch_size }} m³</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <div v-for="item in selectedWorkOrder.mix_design.items" :key="item.id" 
                                class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 shadow-sm ring-1 ring-indigo-100/50">
                                <span class="text-[10px] font-bold text-slate-500 uppercase">{{ item.product?.title || 'Material' }}</span>
                                <span class="h-4 w-[1px] bg-slate-100"></span>
                                <span class="text-xs font-black text-indigo-600">
                                    {{ (Number(item.cross_quantity || item.quantity || 0) * form.batch_size).toFixed(3) }}
                                    <span class="text-[9px] font-normal text-slate-400 ml-0.5">{{ item.uom?.unit_code || '' }}</span>
                                </span>
                            </div>
                        </div>
                    </div> -->

                    <!-- Detailed Materials Table -->
                    <!-- <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <ListBulletIcon class="w-4 h-4 text-slate-400" />
                                <h3 class="text-xs font-bold uppercase tracking-wide text-slate-600">Actual Batch Inputs</h3>
                            </div>
                            <Button label="Add" icon="pi pi-plus" size="small" text rounded class="!text-xs" @click="addMaterial" />
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50/30">
                                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 w-[30%]">Product</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 w-[25%]">Material Label</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 w-[20%]">Target Qty</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 w-[15%]">UOM</th>
                                        <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500 w-[10%]"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <tr v-for="(item, index) in form.materials" :key="index" class="hover:bg-indigo-50/20 transition-colors">
                                        <td class="px-2 py-3">
                                            <BaseSelect
                                                v-model="item.product_id"
                                                :options="products"
                                                optionLabel="title"
                                                optionValue="id"
                                                filter
                                                size="small"
                                                :fluid="true"
                                                :error="form.errors[`materials.${index}.product_id`]"
                                            />
                                        </td>
                                        <td class="px-2 py-3">
                                            <BaseInput v-model="item.material_name" size="small" :fluid="true" />
                                        </td>
                                        <td class="px-2 py-3">
                                            <BaseInputNumber v-model="item.target_qty" :minFractionDigits="3" size="small" :fluid="true" :error="form.errors[`materials.${index}.target_qty`]" />
                                        </td>
                                        <td class="px-2 py-3">
                                            <BaseSelect
                                                v-model="item.uom_id"
                                                :options="uoms"
                                                optionLabel="unit_code"
                                                optionValue="id"
                                                size="small"
                                                :fluid="true"
                                                :error="form.errors[`materials.${index}.uom_id`]"
                                            />
                                        </td>
                                        <td class="px-2 py-3 text-right">
                                            <Button icon="pi pi-trash" text rounded severity="danger" class="!h-8 !w-8" @click="removeMaterial(index)" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div> -->
                </div>
            </div>
        </div>

        <div class="border-t border-slate-100 bg-slate-50/30 p-4">
            <div class="flex justify-end">
                <Button 
                    label="Create Batch Entry" 
                    icon="pi pi-check" 
                    class="!px-8 !py-3 !rounded-xl shadow-lg shadow-indigo-200" 
                    :loading="form.processing"
                    @click="submit" 
                />
            </div>
        </div>
    </div>
</template>
