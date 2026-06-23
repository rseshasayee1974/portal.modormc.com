    <script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import Swal from 'sweetalert2';
import { PlusCircleIcon, InformationCircleIcon, BeakerIcon } from '@heroicons/vue/24/outline';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import Dialog from 'primevue/dialog';
import MixDesignCreateForm from '@/Pages/MixDesigns/Partials/MixDesignCreateForm.vue';

const props = withDefaults(defineProps<{
    customers?: any[];
    sites?: any[];
    mixDesigns?: any[];
    salesOrders?: any[];
    statuses?: { label: string; value: number }[];
    activePlantId?: number;
    nextReference?: string;
    concretePumpOptions?: any[];
    products?: any[];
    units?: any[];
    defaultUomId?: number | null;
    designTypes?: any[];
}>(), {
    customers: () => [],
    sites: () => [],
    mixDesigns: () => [],
    salesOrders: () => [],
    statuses: () => [],
    activePlantId: 0,
    nextReference: '',
    concretePumpOptions: () => [],
    products: () => [],
    units: () => [],
    defaultUomId: null,
    designTypes: () => [],
});

const showMixDesignModal = ref(false);
const safeCustomers = computed(() => props.customers ?? []);
const safeSites = computed(() => props.sites ?? []);
const safeMixDesigns = computed(() => props.mixDesigns ?? []);
const safeStatuses = computed(() => props.statuses ?? []);

const selectedMixDesign = computed(() => {
    const selectedId = form.mix_design_id !== null ? Number(form.mix_design_id) : null;
    return safeMixDesigns.value.find((md) => Number(md?.id) === selectedId);
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
const selectedMixIngredients = computed(() => {
    const mix = selectedMixDesign.value;
    if (!mix) return [];
    
    return Array.isArray(mix.ingredients) ? mix.ingredients : [];
});

const mixDetailBadges = computed(() => {
    const mix = selectedMixDesign.value;
    if (!mix) {
        return [];
    }

    return [
        { label: 'Design Code', value: mix.design_code || 'N/A' },
        { label: 'Grade', value: mix.grade || 'N/A' },
           { label: 'Ratio', value: mix.ratio || 'N/A' },
        { label: 'Ingredients', value: String(selectedMixIngredients.value.length) },
    ];
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

const form = useForm({
    prefix: 'WO',
    order_no: '',
    plant_id: props.activePlantId,
    customer_id: null as number | null,
    site_id: null as number | null,
    mix_design_id: null as number | null,
    sales_order_id: null as number | null,
    total_qty: 0,
    produced_qty: 0,
    status: 1,
    concrete_pump: 'pump' as string | null,
    scheduled_start: null as Date | null,
    scheduled_end: null as Date | null,
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
    form.transform((data) => ({
        ...data,
        scheduled_start: data.scheduled_start ? data.scheduled_start.toISOString() : null,
        scheduled_end: data.scheduled_end ? data.scheduled_end.toISOString() : null,
        order_no: data.order_no || null,
    })).post(route('workorders.store'), {
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Work order created',
                timer: 1500,
                showConfirmButton: false,
            });
            form.reset();
            form.clearErrors();
            form.prefix = 'WO';
            form.plant_id = props.activePlantId;
            form.status = 1;
        },
    });
};

const handleMixCreated = () => {
    showMixDesignModal.value = false;
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'New Design available in dropdown',
        timer: 1500,
        showConfirmButton: false,
    });
};
</script>

<template>
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="rounded-lg bg-indigo-100 p-1.5 text-indigo-700 ring-1 ring-indigo-200">
                    <PlusCircleIcon class="h-4 w-4" />
                </div>
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700" >New Work Order</h2>
            </div>

            <div v-if="nextReference" class="flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700">
                <InformationCircleIcon class="h-3.5 w-3.5" />
                <span class="text-[10px] font-bold uppercase tracking-tight">Next Ref: {{ nextReference }}</span>
            </div>
        </div>

       <div class="grid grid-cols-1 md:grid-cols-5 gap-5 p-5">
    <!-- Row 1 -->
    <!-- <div>
        <BaseSelect
            v-model="form.sales_order_id"
            :options="salesOrderOptions"
            optionLabel="label"
            optionValue="value"
            filter
            label="Sales Order (Optional)"
            placeholder="Select Sales Order"
            :error="form.errors.sales_order_id"
        />
    </div> -->

    <div>
        <BaseSelect
            v-model="form.customer_id"
            :options="safeCustomers"
            optionLabel="legal_name"
            optionValue="id"
            filter
            label="Customer"
            placeholder="Select Customer"
            :error="form.errors.customer_id"
            :disabled="!!form.sales_order_id"
        />
    </div>

    <div>
        <BaseSelect
            v-model="form.site_id"
            :options="safeSites"
            optionLabel="name"
            optionValue="id"
            filter
            label="Site"
            placeholder="Select Site"
            :error="form.errors.site_id"
            :disabled="!!form.sales_order_id"
        />
    </div>

    <div>
        <div class="flex items-center justify-between mb-1">
            <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-400">
                Mix Design
            </label>

            <button
                v-if="!form.sales_order_id"
                type="button"
                @click="showMixDesignModal = true"
                class="flex items-center gap-1 text-[10px] font-bold text-indigo-600 hover:text-indigo-700 transition-colors"
            >
                <BeakerIcon class="h-3 w-3" />
                <span>CREATE NEW</span>
            </button>
        </div>

        <BaseSelect
            v-model="form.mix_design_id"
            :options="safeMixDesigns"
            optionLabel="design_name"
            optionValue="id"
            filter
            placeholder="Select Design"
            :error="form.errors.mix_design_id"
            :disabled="!!form.sales_order_id"
        />
    </div>

    <div>
        <BaseSelect
            v-model="form.status"
            :options="filteredStatuses"
            optionLabel="label"
            optionValue="value"
            label="Status"
            :error="form.errors.status"
        />
    </div>

    <!-- Row 2 -->
    <div>
        <BaseSelect
            v-model="form.concrete_pump"
            :options="concretePumpOptions"
            optionLabel="label"
            optionValue="value"
            label="Concrete Type"
            placeholder="Select Concrete Type"
            :error="form.errors.concrete_pump"
        />
    </div>

    <div>
        <BaseInputNumber
            v-model="form.total_qty"
            label="Total Quantity (m³)"
            :error="form.errors.total_qty"
            :minFractionDigits="3"
            :disabled="!!form.sales_order_id"
        />
    </div>

    <div>
        <label class="mb-1 block text-[10px] font-bold uppercase tracking-widest text-gray-400">
            Scheduled Start
        </label>

        <BaseDatePicker
            v-model="form.scheduled_start"
            showTime
            hourFormat="24"
            fluid
            class="w-full"
        />

        <small
            v-if="form.errors.scheduled_start"
            class="text-red-500 text-[11px]"
        >
            {{ form.errors.scheduled_start }}
        </small>
    </div>

    <div>
        <label class="mb-1 block text-[10px] font-bold uppercase tracking-widest text-gray-400">
            Scheduled End
        </label>

        <BaseDatePicker
            v-model="form.scheduled_end"
            showTime
            hourFormat="24"
            fluid
            class="w-full"
        />

        <small
            v-if="form.errors.scheduled_end"
            class="text-red-500 text-[11px]"
        >
            {{ form.errors.scheduled_end }}
        </small>
    </div>

    <!-- Empty cell to complete 5 columns -->
    <div></div>

    <!-- Mix Design Details -->
    <div
        v-if="selectedMixDesign"
        class="md:col-span-5 rounded-lg border border-indigo-100 bg-indigo-50/40 p-3"
    >
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-[10px] font-bold uppercase tracking-[0.12em] text-indigo-700">
                Selected Mix Design
            </span>

            <span class="text-xs font-semibold text-slate-700">
                {{ selectedMixDesign.design_name }}
            </span>
        </div>

        <div class="mt-2 flex flex-wrap gap-2">
            <div
                v-for="badge in mixDetailBadges"
                :key="badge.label"
                class="rounded-md border border-indigo-100 bg-white px-2 py-1"
            >
                <span class="text-[9px] font-bold uppercase tracking-[0.08em] text-slate-400">
                    {{ badge.label }}
                </span>

                <span class="ml-1 text-[11px] font-semibold text-slate-700">
                    {{ badge.value }}
                </span>
            </div>
        </div>

        <div v-if="selectedMixIngredients.length" class="mt-3">
            <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">
                Mix Ingredients
            </p>

            <div class="mt-1.5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-1.5">
                <div
                    v-for="item in selectedMixIngredients"
                    :key="item.id"
                    class="flex items-center justify-between rounded-md border border-slate-200 bg-white px-2 py-1.5"
                >
                    <span class="text-[11px] text-slate-700">
                        {{ item.name || 'Unknown' }}
                    </span>

                    <span class="text-[11px] font-bold text-indigo-600">
                        {{ Number(item.qty || 0).toFixed(3) }}
                        {{ item.uom || '' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

        <div class="flex justify-end border-t border-slate-100 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-800/30 px-4 py-3">
            <Button label="Create Work Order" icon="pi pi-check" :loading="form.processing" @click="submit" class="p-button-indigo" />
        </div>

        <Dialog v-model:visible="showMixDesignModal" modal header="Create Mix Design" :style="{ width: '90vw', maxWidth: '1200px' }">
            <div class="p-1">
                <MixDesignCreateForm
                    :products="products"
                    :units="units"
                    :partners="customers"
                    :defaultUomId="defaultUomId"
                    :designTypes="designTypes"
                    @created="handleMixCreated"
                />
            </div>
        </Dialog>
    </div>
</template>
