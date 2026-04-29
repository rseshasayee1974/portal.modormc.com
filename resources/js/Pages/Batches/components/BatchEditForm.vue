<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
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
    truck_id: props.batch?.truck_id ?? null,
    transport_id: props.batch?.transport_id ?? null,
    driver_id: props.batch?.driver_id ?? null,
    empty_weight_truck: Number(props.batch?.empty_weight_truck ?? 0),
    loaded_weight_truck: Number(props.batch?.loaded_weight_truck ?? 0),
    net_weight: Number(props.batch?.net_weight ?? 0),
    uom_id: props.batch?.uom_id ?? props.uoms?.find((u: any) => String(u.unit_code).toUpperCase() === 'CBM')?.id ?? null,
    status: Number(props.batch?.status ?? 1),
    start_time: props.batch?.start_time ? new Date(props.batch.start_time) : new Date(),
    end_time: props.batch?.end_time ? new Date(props.batch.end_time) : new Date(),
    materials: ((props.batch?.materials?.length ?? 0) > 0 ? props.batch.materials : [blankMaterial()]).map((item: any) => ({
        id: item.id,
        product_id: item.product_id,
        material_name: item.material_name || item.product?.title || '',
        target_qty: Number(item.target_qty || 0),
        actual_qty: Number(item.actual_qty || 0),
        deviation_quantity: Number(item.deviation_quantity || (Number(item.actual_qty || 0) - Number(item.target_qty || 0))),
        uom_id: item.uom_id,
    })),
});

const page = usePage();
const customSettings = page.props.custom_settings as any;

const selectedWorkOrder = computed(() => 
    props.workOrders.find(wo => wo.id === form.work_order_id)
);

const workOrderDetails = computed(() => {
    if (!selectedWorkOrder.value) return [];
    const wo = selectedWorkOrder.value;
    return [
        { label: 'Customer', value: wo.customer?.legal_name || 'N/A' },
        { label: 'Site', value: wo.site?.name || 'N/A' },
        { label: 'Design', value: wo.mix_design?.design_name || 'N/A' },
        // { label: 'Grade/Ratio', value: `${wo.mix_design?.concrete_grade?.name || wo.mix_design?.grade || 'N/A'} (${wo.mix_design?.concrete_grade?.concrete_ratio || 'N/A'})` },
        { label: 'Total Qty', value: `${wo.produced_qty} / ${wo.total_qty} m³` },
    ];
});

watch(() => form.batch_size, (newVal) => {
    if (form.work_order_id && selectedWorkOrder.value?.mix_design?.items) {
        form.materials.forEach((mat, index) => {
            const originalItem = selectedWorkOrder.value.mix_design.items[index];
            if (originalItem) {
                mat.target_qty = Number(originalItem.actual_quantity || originalItem.quantity || 0) * newVal;
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
    let hasActual = false;
    newMaterials.forEach(m => {
        m.deviation_quantity = Number(m.actual_qty || 0) - Number(m.target_qty || 0);
        if (Number(m.actual_qty) > 0) {
            hasActual = true;
        }
    });

    if (hasActual && form.status === 1) { // 1 is Planned, 3 is Dispatched
        form.status = 3;
    }
}, { deep: true });

import { useWeighbridge } from '@/Composables/useWeighbridge';

const { isScaleConnected, captureWeight } = useWeighbridge();

const isFetchingConsumption = ref(false);

const fetchConsumption = async () => {
    isFetchingConsumption.value = true;
    try {
        const response = await axios.get('/orders/production/batch', {
            params: {
                batch_no: props.batch?.batch_no,
                cust_id: selectedWorkOrder.value?.customer?.code,
                rec_id: selectedWorkOrder.value?.mix_design?.design_name
            }
        });
        const data = response.data;
        
        if (data && data.mat) {
            data.mat.forEach((apiMat: any) => {
                let matchedMat = form.materials.find(mat => {
                    const productName = props.products.find((p: any) => p.id === mat.product_id)?.title || '';
                    return apiMat.item.toLowerCase() === mat.material_name.toLowerCase() ||
                           productName.toLowerCase() === apiMat.item.toLowerCase() ||
                           productName.toLowerCase().includes(apiMat.item.toLowerCase());
                });

                if (matchedMat) {
                    matchedMat.actual_qty = apiMat.act;
                } else {
                    const newMat = blankMaterial();
                    newMat.material_name = apiMat.item;
                    newMat.actual_qty = apiMat.act;
                    
                    // Auto-detect product_id based on name
                    const matchedProduct = props.products.find((p: any) => 
                        p.title.toLowerCase() === apiMat.item.toLowerCase() ||
                        p.title.toLowerCase().includes(apiMat.item.toLowerCase()) ||
                        apiMat.item.toLowerCase().includes(p.title.toLowerCase())
                    );
                    
                    if (matchedProduct) {
                        newMat.product_id = matchedProduct.id;
                        if (matchedProduct.unit?.id) {
                            newMat.uom_id = matchedProduct.unit.id;
                        } else if (matchedProduct.unit_id) {
                            newMat.uom_id = matchedProduct.unit_id;
                        }
                    }
                    
                    form.materials.push(newMat);
                }
            });
            
            if (data.end) form.end_time = new Date(data.end);
            if (data.start) form.start_time = new Date(data.start);
            
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Consumption data synced', showConfirmButton: false, timer: 2200 });
        }
    } catch (error) {
        console.error('API fetch failed:', error);
        Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Failed to get consumption', showConfirmButton: false, timer: 2200 });
    } finally {
        isFetchingConsumption.value = false;
    }
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        start_time: data.start_time ? data.start_time.toISOString() : null,
        end_time: data.end_time ? data.end_time.toISOString() : null,
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
                timer: 2200,
                showConfirmButton: false,
            });
            emit('saved');
        },
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
                                v-model="form.work_order_id" 
                                :options="workOrders" 
                                optionLabel="order_no" 
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
                            <h4 class="mb-4 text-[10px] font-bold uppercase tracking-widest text-cyan-500 border-b border-cyan-50 pb-2 italic">Work Order Context</h4>
                            <div class="space-y-4">
                                <div v-for="detail in workOrderDetails" :key="detail.label" class="flex flex-col">
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400">{{ detail.label }}</span>
                                    <span class="text-xs font-semibold text-cyan-900 leading-tight">{{ detail.value }}</span>
                                </div>
                                <div class="">
                                    <BaseSelect v-model="form.status" :options="statuses" :disabled="true" optionLabel="label" optionValue="value" label="Current Status" :error="form.errors.status" />
                                </div>
                                 <div class="col-span-12 md:col-span-3">
                            <BaseDatePicker label="Start Time" v-model="form.start_time" showTime hourFormat="24" fluid  />
                            <small class="text-red-500">{{ form.errors.start_time }}</small>
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseDatePicker label="End Time" v-model="form.end_time" showTime hourFormat="24" fluid  />
                            <small class="text-red-500">{{ form.errors.end_time }}</small>
                        </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Fillable Details -->
                <div class="col-span-12 md:col-span-9 space-y-6">
                    <!-- Config Grid -->
                    <div class="grid grid-cols-12 gap-4 rounded-xl border border-slate-100 bg-white p-5 shadow-sm">
                         <div class="col-span-12 md:col-span-3">
                            <BaseSelect v-model="form.truck_id" :options="trucks" optionLabel="registration" optionValue="id" filter label="Truck Assignment" :error="form.errors.truck_id" />
                        </div>
                        
                         <div class="col-span-12 md:col-span-3">
                            <BaseSelect v-model="form.transport_id" :options="transporters" optionLabel="legal_name" optionValue="id" filter label="Transporter" :error="form.errors.transport_id" />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseSelect v-model="form.driver_id" :options="personnel" optionLabel="first_name" optionValue="id" filter label="Driver" :error="form.errors.driver_id" />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseInputNumber v-model="form.batch_size" label="Batch Quantity (m³)" :minFractionDigits="2" :error="form.errors.batch_size" />
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
                            />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <BaseInputNumber v-model="form.empty_weight_truck" :disabled="true" label="Empty Truck (kg)" :error="form.errors.empty_weight_truck" />
                                </div>
                                <!-- <button v-if="customSettings?.batching?.manual_weight" @click="captureWeight((w) => form.empty_weight_truck = w)" type="button" 
                                    :class="['p-2 rounded transition-colors border', isScaleConnected ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border-emerald-200' : 'bg-amber-50 text-amber-600 hover:bg-amber-100 border-amber-200']" 
                                    :title="isScaleConnected ? 'Capture Current Weight' : 'Connect & Capture'">
                                    <ArrowDownTrayIcon class="w-4 h-4" />
                                </button> -->
                            </div>
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <div class="flex items-end">
                                <div class="flex-1">
                                    <BaseInputNumber v-model="form.loaded_weight_truck" :disabled="customSettings?.batching?.manual_weight" label="Loaded Truck (kg)" :error="form.errors.loaded_weight_truck" />
                                </div>
                                <button v-if="customSettings?.batching?.manual_weight" @click="captureWeight((w) => form.loaded_weight_truck = w)" type="button" 
                                    :class="['p-2 rounded transition-colors border', isScaleConnected ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border-emerald-200' : 'bg-amber-50 text-amber-600 hover:bg-amber-100 border-amber-200']" 
                                    :title="isScaleConnected ? 'Capture Current Weight' : 'Connect & Capture'">
                                    <ArrowDownTrayIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-span-12 md:col-span-3">
                            <BaseInputNumber v-model="form.net_weight" :disabled="true" label="Net Weight (kg)" :error="form.errors.net_weight" />
                        </div>
                        
                        <!-- <div class="col-span-12 md:col-span-3">
                            <BaseDatePicker label="Scheduled Start" v-model="form.start_time" showTime hourFormat="24" fluid disabled />
                            <small class="text-red-500">{{ form.errors.start_time }}</small>
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseDatePicker label="Scheduled End" v-model="form.end_time" showTime hourFormat="24" fluid disabled />
                            <small class="text-red-500">{{ form.errors.end_time }}</small>
                        </div> -->
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
                                <Button 
                                    label="Get Consumption" 
                                    icon="pi pi-cloud-download" 
                                    size="small" 
                                    severity="secondary" 
                                    outlined 
                                    class="!text-xs" 
                                    v-if="form.status !== 3"
                                    :loading="isFetchingConsumption"
                                    @click="fetchConsumption" 
                                />
                                <Button v-if="form.status !== 3" label="Add" icon="pi pi-plus" size="small" text rounded class="!text-xs !text-cyan-600" @click="addMaterial" />
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50/30">
                                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[20%]">Product</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[15%]">Label</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[15%]">Target</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[15%]">Actual</th>
                                        <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[10%]">Dev.</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[15%]">UOM</th>
                                        <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[10%]"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <tr v-for="(item, index) in form.materials" :key="index" class="hover:bg-cyan-50/20 transition-colors">
                                        <td class="px-2 py-3">
                                            <BaseSelect
                                                v-model="item.product_id"
                                                :options="products"
                                                optionLabel="title"
                                                optionValue="id"
                                                filter
                                                size="small"
                                                :fluid="true"
                                                :disabled="!!item.id"
                                                :error="form.errors[`materials.${index}.product_id`]"
                                            />
                                        </td>
                                        <td class="px-2 py-3">
                                            <BaseInput v-model="item.material_name" :disabled="!!item.id" size="small" :fluid="true" />
                                        </td>
                                        <td class="px-2 py-3">
                                            <BaseInputNumber v-model="item.target_qty" :disabled="!!item.id" :minFractionDigits="3" size="small" :fluid="true" :error="form.errors[`materials.${index}.target_qty`]" />
                                        </td>
                                        <td class="px-2 py-3">
                                            <BaseInputNumber v-model="item.actual_qty" :minFractionDigits="3" size="small" :fluid="true" :error="form.errors[`materials.${index}.actual_qty`]" />
                                        </td>
                                        <td class="px-2 py-3 text-center">
                                            <span class="text-[11px] font-bold" :class="item.deviation_quantity > 0 ? 'text-rose-500' : (item.deviation_quantity < 0 ? 'text-emerald-500' : 'text-slate-400')">
                                                {{ item.deviation_quantity > 0 ? '+' : '' }}{{ item.deviation_quantity?.toFixed(3) }}
                                            </span>
                                        </td>
                                        <td class="px-2 py-3" >
                                            <BaseSelect
                                                v-model="item.uom_id"
                                                :options="uoms"
                                                optionLabel="unit_code"
                                                optionValue="id"
                                                size="small"
                                                :fluid="true"
                                                :disabled="!!item.id"
                                                :error="form.errors[`materials.${index}.uom_id`]"
                                            />
                                        </td>
                                        <td class="px-2 py-3 text-right" >
                                            <Button icon="pi pi-trash" text rounded severity="danger" class="!h-8 !w-8" :disabled="!!item.id" @click="removeMaterial(index)" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
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
