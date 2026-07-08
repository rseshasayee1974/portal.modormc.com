<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch, onMounted, ref } from 'vue';
import axios from 'axios';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Swal from 'sweetalert2';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import StatusBadge from '@/Components/Mm/Badge.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = withDefaults(defineProps<{
    salesOrder?: any;
    customers?: any[];
    sites?: any[];
    mixDesigns?: any[];
    customerPOs?: any[];
    statuses?: { label: string; value: number }[];
    concretePumpOptions?: any[];
    salesExecutives?: any[];
}>(), {
    salesOrder: () => ({}),
    customers: () => [],
    sites: () => [],
    mixDesigns: () => [],
    customerPOs: () => [],
    statuses: () => [],
    concretePumpOptions: () => [],
    salesExecutives: () => [],
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

const { can } = usePermissions();

const hasActiveData = computed(() => {
    return Number(props.salesOrder?.batches_count || 0) > 0 || 
           Number(props.salesOrder?.dispatches_count || 0) > 0 || 
           Number(props.salesOrder?.status) === 3;
});

const isCriticalLocked = computed(() => {
    return hasActiveData.value; // Nobody can edit these if active data exists to prevent data corruption
});

const isLocked = computed(() => {
    // If the user doesn't have the basic UPDATE permission, they are completely locked out
    if (!can('SALES_ORDER.UPDATE')) {
        return true;
    }

    // If they have APPROVE permission, they can bypass the status and active data locks
    if (can('SALES_ORDER.APPROVE')) {
        return false;
    }

    // Normal users (with UPDATE but no APPROVE) are locked if there is active data or if the status is not Scheduled (1) or Cancelled (4)
    const status = Number(props.salesOrder?.status);
    return hasActiveData.value || (status !== 1 && status !== 4);
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

const safeSites = computed(() => {
    return (props.sites || []).filter((s: any) => {
        if (!s) return false;
        if (!form.customer_id) return true;
        
        let patronIds = s.patron_id;
        if (typeof patronIds === 'string') {
            try {
                const parsed = JSON.parse(patronIds);
                if (Array.isArray(parsed)) patronIds = parsed;
            } catch (e) {}
        }
        
        const custId = Number(form.customer_id);
        if (Array.isArray(patronIds)) {
            return patronIds.map(Number).includes(custId);
        }
        return Number(patronIds) === custId;
    });
});

const emit = defineEmits<{
    (e: 'saved'): void;
    (e: 'cancel'): void;
}>();

const salesExecutiveOptions = computed(() => (props.salesExecutives || []).map(se => ({ label: se.label || `${se.first_name} ${se.last_name}`, value: se.id })));

const defaultStart = new Date();
const defaultEnd = new Date(defaultStart);
defaultEnd.setHours(defaultEnd.getHours() + 1);

const form = useForm({
    prefix: props.salesOrder?.prefix ?? 'SO',
    order_no: props.salesOrder?.order_no ?? '',
    plant_id: props.salesOrder?.plant_id ? Number(props.salesOrder.plant_id) : null,
    sales_executive_id: props.salesOrder?.sales_executive_id ? Number(props.salesOrder.sales_executive_id) : null,
    customer_id: props.salesOrder?.customer_id ? Number(props.salesOrder.customer_id) : null,
    site_id: props.salesOrder?.site_id ? Number(props.salesOrder.site_id) : null,
    mix_design_id: props.salesOrder?.mix_design_id ? Number(props.salesOrder.mix_design_id) : null,
    customer_po_id: props.salesOrder?.customer_po_id ? Number(props.salesOrder.customer_po_id) : null,
    total_qty: Number(props.salesOrder?.total_qty ?? 0),
    produced_qty: Number(props.salesOrder?.produced_qty ?? 0),
    status: Number(props.salesOrder?.status ?? 1),
    concrete_pump: props.salesOrder?.concrete_pump ? Number(props.salesOrder.concrete_pump) : null,
    scheduled_start: props.salesOrder?.scheduled_start ? new Date(props.salesOrder.scheduled_start) : defaultStart,
    scheduled_end: props.salesOrder?.scheduled_end ? new Date(props.salesOrder.scheduled_end) : defaultEnd,
});

const isLoading = ref(true);

onMounted(async () => {
    try {
        const id = props.salesOrder?.id ?? props.salesOrder?.work_order_id;
        if (id) {
            const response = await axios.get(route('salesorders.show', id));
            const fullData = response.data;
            
            // Update form with complete data
            Object.assign(form, {
                prefix: fullData.prefix ?? 'SO',
                order_no: fullData.order_no ?? '',
                plant_id: fullData.plant_id ? Number(fullData.plant_id) : null,
                sales_executive_id: fullData.sales_executive_id ? Number(fullData.sales_executive_id) : null,
                customer_id: fullData.customer_id ? Number(fullData.customer_id) : null,
                site_id: fullData.site_id ? Number(fullData.site_id) : null,
                mix_design_id: fullData.mix_design_id ? Number(fullData.mix_design_id) : null,
                customer_po_id: fullData.customer_po_id ? Number(fullData.customer_po_id) : null,
                total_qty: Number(fullData.total_qty ?? 0),
                produced_qty: Number(fullData.produced_qty ?? 0),
                status: Number(fullData.status ?? 1),
                concrete_pump: fullData.concrete_pump ? Number(fullData.concrete_pump) : null,
                scheduled_start: fullData.scheduled_start ? new Date(fullData.scheduled_start) : defaultStart,
                scheduled_end: fullData.scheduled_end ? new Date(fullData.scheduled_end) : defaultEnd,
            });
            form.defaults(form.data());
        }
    } catch (e) {
        console.error('Failed to load full sales order data', e);
    } finally {
        isLoading.value = false;
    }
});

const customerPOOptions = computed(() => {
    return [
        { label: 'None (Direct Sales Order)', value: null },
        ...props.customerPOs.map((so) => {
            const patronName = so.patron?.legal_name || 'Unknown';
            const mixName = so.quotation?.items?.[0]?.mix_design?.design_name || 'N/A';
            const qty = so.quotation?.items?.[0]?.quantity || 0;
            return {
                label: `PO #${so.id} - ${patronName} (${mixName}, ${qty} m³)`,
                value: so.id,
            };
        }),
    ];
});

// Watch sales order selection to auto-fill patron, site, mix design, and total quantity
watch(() => form.customer_po_id, (newVal) => {
    if (newVal) {
        const salesOrder = props.customerPOs.find((so) => Number(so.id) === Number(newVal));
        if (salesOrder) {
            form.customer_id = salesOrder.patron_id;
            form.site_id = salesOrder.site_id;
            form.concrete_pump = salesOrder.concrete_pump;
            form.sales_executive_id = salesOrder.sales_executive_id;
            
            const firstItem = salesOrder.quotation?.items?.[0];
            if (firstItem) {
                form.mix_design_id = firstItem.mix_design_id;
                form.total_qty = Number(firstItem.quantity || 0);
            }
        }
    } else {
        form.customer_id = null;
        form.site_id = null;
        form.concrete_pump = null;
        form.mix_design_id = null;
        form.total_qty = 0;
        form.sales_executive_id = null;
    }
});

watch(() => form.scheduled_start, (newStart) => {
    if (newStart) {
        const start = new Date(newStart);
        if (!form.scheduled_end || new Date(form.scheduled_end) <= start) {
            const endDate = new Date(start);
            endDate.setHours(endDate.getHours() + 1);
            form.scheduled_end = endDate;
        }
    }
});

const submit = () => {
    const salesOrderId = props.salesOrder?.id ?? props.salesOrder?.work_order_id ?? null;

    if (!salesOrderId) {
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

    form.clearErrors('scheduled_end');
    if (form.scheduled_start && form.scheduled_end) {
        const startDate = new Date(form.scheduled_start);
        startDate.setSeconds(0, 0);
        const start = startDate.getTime();

        const endDate = new Date(form.scheduled_end);
        endDate.setSeconds(0, 0);
        const end = endDate.getTime();
        
        if (start === end) {
            form.setError('scheduled_end', 'Start and end time cannot be exactly the same.');
            return;
        }
        if (start > end) {
            form.setError('scheduled_end', 'End time cannot be before the start time.');
            return;
        }
    }

    const formatLocalTime = (date: Date | any) => {
        if (!date) return null;
        const d = new Date(date);
        const pad = (n: number) => n.toString().padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    };

    form.transform((data) => ({
        ...data,
        scheduled_start: formatLocalTime(data.scheduled_start),
        scheduled_end: formatLocalTime(data.scheduled_end),
    })).put(route('salesorders.update', { salesorder: salesOrderId }), {
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Sales Order updated',
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
    <div class="rounded-lg border border-indigo-100 bg-indigo-50/40 p-4 relative">
        <div v-if="isLoading" class="absolute inset-0 z-10 flex items-center justify-center bg-white/60 backdrop-blur-sm rounded-lg">
            <i class="pi pi-spinner pi-spin text-3xl text-indigo-500"></i>
        </div>
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wide text-indigo-800">Edit Sales Order</h3>
            
            <div class="flex items-center gap-3"><StatusBadge

    v-if="form.scheduled_end"
    :value="new Date(form.scheduled_end) < new Date() ? 'Overdue' : 'Due'"
/> 
            <span class="font-mono text-xs font-bold text-amber-600">REF # : {{ salesOrder.prefix }}{{ salesOrder.order_no }}</span>
        </div>
        </div>

        <div v-if="isLocked" class="mb-4 rounded-md bg-amber-50 p-3 text-amber-800 text-xs flex items-start gap-2 border border-amber-200">
            <i class="pi pi-lock mt-0.5 text-amber-600"></i>
            <div>
                <span class="font-bold text-amber-900 block">Order Locked</span>
                <span v-if="hasActiveData">This sales order has active batches, dispatches, or is completed.</span>
                <span v-else>This sales order is no longer in a modifiable status.</span>
                Only users with update permissions can modify it.
            </div>
        </div>

      <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
    <!-- Row 1 -->
    <!-- <div class="col-span-1">
        <BaseSelect
            v-model="form.customer_po_id"
            :options="customerPOOptions"
            optionLabel="label"
            optionValue="value"
            filter
            label="Sales Order (Optional)"
            :error="form.errors.customer_po_id"
        />
    </div> -->

    <div class="col-span-1">
        <BaseSelect
            v-model="form.customer_id"
            :options="customers"
            optionLabel="legal_name"
            optionValue="id"
            filter
            label="Customer"
            :error="form.errors.customer_id"
            :disabled="isCriticalLocked || !!form.customer_po_id"
        />
    </div>

    <div class="col-span-1">
        <BaseSelect
            v-model="form.site_id"
            :options="safeSites"
            optionLabel="name"
            optionValue="id"
            filter
            label="Site"
            :error="form.errors.site_id"
            :disabled="isCriticalLocked || !!form.customer_po_id"
        />
    </div>

    <div class="col-span-1">
        <BaseInputNumber
            v-if="can('SALES_ORDER.UPDATE')"
            v-model="form.total_qty"
            label="Total Quantity (m³)"
            :error="form.errors.total_qty"
            :disabled="isLocked"
            :minFractionDigits="3"
        />
        <div v-else class="flex flex-col gap-1 mt-1">
            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Total Quantity (m³)</span>
            <span class="font-semibold text-sm">{{ form.total_qty }} m³</span>
        </div>
    </div>

    <div class="col-span-1">
        <BaseInputNumber
            v-model="form.produced_qty"
            label="Produced Quantity (m³)"
            :error="form.errors.produced_qty"
            readonly
            disabled
            :minFractionDigits="3"
        />
    </div>

    <div class="col-span-1">
        <BaseSelect
            v-model="form.sales_executive_id"
            :options="salesExecutiveOptions"
            optionLabel="label"
            optionValue="value"
            filter
            label="Sales Executive"
            placeholder="Select Sales Executive"
            :error="form.errors.sales_executive_id"
            :disabled="isLocked"
        />
    </div>

    <!-- Row 2 -->
    <div class="col-span-1">
        <BaseSelect
            v-model="form.status"
            :options="filteredStatuses"
            optionLabel="label"
            optionValue="value"
            label="Status"
            :error="form.errors.status"
            :disabled="isLocked"
        />
    </div>

    <div class="col-span-1">
        <BaseSelect
            v-model="form.concrete_pump"
            :options="concretePumpOptions"
            optionLabel="label"
            optionValue="value"
            label="Concrete Type"
            placeholder="Select Concrete Type"
            :error="form.errors.concrete_pump"
            :disabled="isLocked"
        />
    </div>

    <div class="col-span-1">
        <label class="mb-1 block text-xs font-semibold text-slate-500">
            Scheduled Start
        </label>

        <BaseDatePicker
            v-model="form.scheduled_start"
            showTime
            hourFormat="24"
            fluid
            :disabled="isLocked"
        />

        <small class="text-red-500">
            {{ form.errors.scheduled_start }}
        </small>
    </div>

    <div class="col-span-1">
        <label class="mb-1 block text-xs font-semibold text-slate-500">
            Scheduled End
        </label>

        <BaseDatePicker
            v-model="form.scheduled_end"
            showTime
            hourFormat="24"
            fluid
            :disabled="isLocked"
        />

        <small class="text-red-500">
            {{ form.errors.scheduled_end }}
        </small>
    </div>

    <div class="col-span-1">
        <BaseSelect
            v-model="form.mix_design_id"
            :options="mixDesigns"
            optionLabel="design_name"
            optionValue="id"
            filter
            label="Mix Design"
            :error="form.errors.mix_design_id"
            :disabled="isCriticalLocked || !!form.customer_po_id"
        />
    </div>

    <!-- Recipe Details -->
    <div
        v-if="selectedMixIngredients.length"
        class="md:col-span-5 rounded-lg border border-indigo-100 bg-indigo-50/40 p-3"
    >
        <div class="flex items-center justify-between">
            <label class="text-[10px] font-bold uppercase tracking-[0.1em] text-indigo-500">
                Recipe Details
            </label>

            <span
                v-if="selectedMixDesign?.grade"
                class="rounded bg-indigo-100 px-2 py-1 text-[10px] font-bold text-indigo-700"
            >
                {{ selectedMixDesign.design_name }}
            </span>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <div
                v-for="item in selectedMixIngredients"
                :key="item.id"
                class="flex items-center gap-2 rounded-md border border-indigo-100 bg-white px-3 py-2"
            >
                <span class="text-xs text-slate-700">
                    {{ item.name || 'Unknown' }}
                </span>

                <span class="font-semibold text-indigo-600">
                    {{ item.qty }}
                    <span class="text-slate-400">
                        {{ item.uom }}
                    </span>
                </span>
            </div>
        </div>
    </div>
</div>
        <div v-if="!isLocked" class="mt-4 border-t border-indigo-100 pt-3">
            <BaseFormActions 
                mode="update" 
                updateLabel="Update Sales Order" 
                :loading="form.processing" 
                @submit="submit" 
                @cancel="emit('cancel')" 
            />
        </div>
    </div>
</template>