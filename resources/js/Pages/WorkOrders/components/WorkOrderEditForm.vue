<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Swal from 'sweetalert2';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import StatusBadge from '@/Components/Mm/Badge.vue';

const props = withDefaults(defineProps<{
    workOrder?: any;
    customers?: any[];
    sites?: any[];
    mixDesigns?: any[];
    salesOrders?: any[];
    statuses?: { label: string; value: number }[];
}>(), {
    workOrder: () => ({}),
    customers: () => [],
    sites: () => [],
    mixDesigns: () => [],
    salesOrders: () => [],
    statuses: () => [],
});

// Intercept and strictly allow only Scheduled (1), In Progress (2), and Cancelled (4)
const filteredStatuses = computed(() => {
    const backupStatuses = [
        { label: 'Scheduled', value: 1 },
        { label: 'In Progress', value: 2 },
        { label: 'Cancelled', value: 4 }
    ];

    if (!props.statuses || props.statuses.length === 0) {
        return backupStatuses;
    }

    // Filter incoming array values matching the desired target status IDs
    return props.statuses.filter(status => [1, 2, 4].includes(Number(status.value)));
});

const selectedMixDesign = computed(() => {
    const selectedId = form.mix_design_id !== null ? Number(form.mix_design_id) : null;
    return props.mixDesigns.find((md) => Number(md?.id) === selectedId);
});

// console.log(selectedMixDesign);

const selectedMixIngredients = computed(() => {
    const mix = selectedMixDesign.value;
    if (!mix) return [];
    
    return Array.isArray(mix.ingredients) ? mix.ingredients : [];
});

const emit = defineEmits<{
    (e: 'saved'): void;
    (e: 'cancel'): void;
}>();

const form = useForm({
    prefix: props.workOrder?.prefix ?? 'WO',
    order_no: props.workOrder?.order_no ?? '',
    plant_id: props.workOrder?.plant_id ?? null,
    customer_id: props.workOrder?.customer_id ?? null,
    site_id: props.workOrder?.site_id ?? null,
    mix_design_id: props.workOrder?.mix_design_id ?? null,
    sales_order_id: props.workOrder?.sales_order_id ?? null,
    total_qty: Number(props.workOrder?.total_qty ?? 0),
    produced_qty: Number(props.workOrder?.produced_qty ?? 0),
    status: Number(props.workOrder?.status ?? 1),
    scheduled_start: props.workOrder?.scheduled_start ? new Date(props.workOrder.scheduled_start) : null,
    scheduled_end: props.workOrder?.scheduled_end ? new Date(props.workOrder.scheduled_end) : null,
});

const salesOrderOptions = computed(() => {
    return [
        { label: 'None (Direct Work Order)', value: null },
        ...props.salesOrders.map((so) => {
            const patronName = so.patron?.legal_name || 'Unknown';
            const mixName = so.quotation?.items?.[0]?.mix_design?.design_name || 'N/A';
            const qty = so.quotation?.items?.[0]?.quantity || 0;
            return {
                label: `SO #${so.id} - ${patronName} (${mixName}, ${qty} m³)`,
                value: so.id,
            };
        }),
    ];
});

// Watch sales order selection to auto-fill patron, site, mix design, and total quantity
watch(() => form.sales_order_id, (newVal) => {
    if (newVal) {
        const salesOrder = props.salesOrders.find((so) => Number(so.id) === Number(newVal));
        if (salesOrder) {
            form.customer_id = salesOrder.patron_id;
            form.site_id = salesOrder.site_id;
            
            const firstItem = salesOrder.quotation?.items?.[0];
            if (firstItem) {
                form.mix_design_id = firstItem.mix_design_id;
                form.total_qty = Number(firstItem.quantity || 0);
            }
        }
    } else {
        form.customer_id = null;
        form.site_id = null;
        form.mix_design_id = null;
        form.total_qty = 0;
    }
});

const submit = () => {
    const workOrderId = props.workOrder?.id ?? props.workOrder?.work_order_id ?? null;

    if (!workOrderId) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: 'Unable to update: missing work order id',
            timer: 1500,
            showConfirmButton: false,
        });
        return;
    }

    form.transform((data) => ({
        ...data,
        scheduled_start: data.scheduled_start ? data.scheduled_start.toISOString() : null,
        scheduled_end: data.scheduled_end ? data.scheduled_end.toISOString() : null,
    })).put(route('workorders.update', { workorder: workOrderId }), {
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Work order updated',
                timer: 1500,
                showConfirmButton: false,
            });
            emit('saved');
        },
    });
};
const isOverdue = computed(() => {
    if (!form.scheduled_end) return false

    return new Date(form.scheduled_end) < new Date()
})
</script>

<template>
    <div class="rounded-lg border border-indigo-100 bg-indigo-50/40 p-4">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wide text-indigo-800">Edit Work Order</h3>
            
            <div class="flex items-center gap-3"><StatusBadge

    v-if="form.scheduled_end"
    :value="new Date(form.scheduled_end) < new Date() ? 'Overdue' : 'Due'"
/> 
            <span class="font-mono text-xs font-bold text-amber-600">REF # : {{ workOrder.prefix }}{{ workOrder.order_no }}</span>
        </div>
        </div>

        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12 md:col-span-3">
                <BaseSelect
                    v-model="form.sales_order_id"
                    :options="salesOrderOptions"
                    optionLabel="label"
                    optionValue="value"
                    filter
                    label="Sales Order (Optional)"
                    :error="form.errors.sales_order_id"
                />
            </div>

            <div class="col-span-12 md:col-span-3">
                <BaseSelect v-model="form.customer_id" :options="customers" optionLabel="legal_name" optionValue="id" filter label="Customer" :error="form.errors.customer_id" :disabled="!!form.sales_order_id" />
            </div>

            <div class="col-span-12 md:col-span-3">
                <BaseSelect v-model="form.site_id" :options="sites" optionLabel="name" optionValue="id" filter label="Site" :error="form.errors.site_id" :disabled="!!form.sales_order_id" />
            </div>
            
            <div class="col-span-12 md:col-span-3">
                <BaseInputNumber v-model="form.total_qty" label="Total Quantity (m³)" :error="form.errors.total_qty" :minFractionDigits="3" />
            </div>
            <div class="col-span-12 md:col-span-3">
                <BaseInputNumber v-model="form.produced_qty" label="Produced Quantity (m³)" :error="form.errors.produced_qty" readonly :minFractionDigits="3" disabled/>
            </div>
            <div class="col-span-12 md:col-span-3">
                <BaseSelect v-model="form.status" :options="filteredStatuses" optionLabel="label" optionValue="value" label="Status" :error="form.errors.status" />
            </div>

            <div class="col-span-12 md:col-span-3">
                <label class="mb-1 block text-xs font-semibold text-slate-500">Scheduled Start</label>
                <BaseDatePicker v-model="form.scheduled_start" showTime hourFormat="24" fluid />
                <small class="text-red-500">{{ form.errors.scheduled_start }}</small>
            </div>
            <div class="col-span-12 md:col-span-3">
                <label class="mb-1 block text-xs font-semibold text-slate-500">Scheduled End</label>
                <BaseDatePicker v-model="form.scheduled_end" showTime hourFormat="24" fluid />
                <small class="text-red-500">{{ form.errors.scheduled_end }}</small>

</div>

            <div class="col-span-12 md:col-span-6">
                <div class="col-span-12 md:col-span-3">
                    <BaseSelect v-model="form.mix_design_id" :options="mixDesigns"  optionLabel="design_name" optionValue="id" filter label="Mix Design" :error="form.errors.mix_design_id" :disabled="!!form.sales_order_id" />
                </div>
                
                <div v-if="selectedMixIngredients.length" class="mt-2.5 space-y-1.5 animate-in fade-in slide-in-from-top-1 duration-300">
                    <div class="flex items-center justify-between">
                        <label class="text-[9px] font-bold uppercase tracking-[0.1em] text-indigo-400">Recipe Details</label>
                        <span v-if="selectedMixDesign?.grade" class="rounded bg-indigo-100 px-1 py-0.5 text-[8px] font-bold text-indigo-700">GRADE: {{ selectedMixDesign.design_name }}</span>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <div v-for="item in selectedMixIngredients" :key="item.id" 
                             class="group flex items-center gap-2 rounded-md bg-white/50 px-2 py-1 ring-1 ring-inset ring-indigo-100 transition-all hover:bg-white hover:shadow-sm hover:ring-indigo-200">
                            <span class="text-[10px] font-medium text-slate-600">{{ item.name || 'Unknown' }}</span>
                            <div class="h-3 w-px bg-indigo-100"></div>
                            <span class="text-[10px] font-bold text-indigo-600">{{ item.qty }} <span class="text-[9px] font-medium text-slate-400">{{ item.uom }}</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="workOrder?.status === 1 || workOrder?.status === 4" class="mt-4 border-t border-indigo-100 pt-3">
            <BaseFormActions 
                mode="update" 
                updateLabel="Update Work Order" 
                :loading="form.processing" 
                @submit="submit" 
                @cancel="emit('cancel')" 
            />
        </div>
    </div>
</template>