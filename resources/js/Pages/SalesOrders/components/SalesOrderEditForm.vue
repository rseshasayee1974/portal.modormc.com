<script setup lang="ts">
import { useForm, router, usePage } from '@inertiajs/vue3';
import { computed, watch, ref, onMounted } from 'vue';
import { calculateLineItemTotals } from '@/composables/useLineItemCalculation';
import axios from 'axios';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Swal from 'sweetalert2';
import { 
    PencilSquareIcon, 
    DocumentCheckIcon,
    LockClosedIcon,
    SparklesIcon
} from '@heroicons/vue/24/outline';
import RecipePopover from '@/Components/Base/RecipePopover.vue';
import { usePermissions } from '@/Composables/usePermissions';
import MixDesignCreateForm from '@/Pages/MixDesigns/Partials/MixDesignCreateForm.vue';

const props = withDefaults(defineProps<{
    salesOrder?: any;
    customers?: any[];
    sites?: any[];
    mixDesigns?: any[];
    customerPOs?: any[];
    statuses?: { label: string; value: number }[];
    concretePumpOptions?: any[];
    salesExecutives?: any[];
    taxes?: any[];
    products?: any[];
    units?: any[];
    designTypes?: any[];
    pumpRates?: any[];
}>(), {
    salesOrder: () => ({}),
    customers: () => [],
    sites: () => [],
    mixDesigns: () => [],
    customerPOs: () => [],
    statuses: () => [],
    concretePumpOptions: () => [],
    salesExecutives: () => [],
    taxes: () => [],
    products: () => [],
    units: () => [],
    designTypes: () => [],
    pumpRates: () => [],
});

const emit = defineEmits<{
    (e: 'saved'): void;
    (e: 'cancel'): void;
}>();

const { can, isAdmin, isSuperAdmin } = usePermissions();

const showMixDesignModal = ref(false);
const isLoading = ref(true);
const isInitializing = ref(true);

const safeCustomers = computed(() => props.customers ?? []);
const safeMixDesigns = computed(() => props.mixDesigns ?? []);

const filteredStatuses = computed(() => {
    const backupStatuses = [
        { label: 'Scheduled', value: 1 },
        { label: 'In Progress', value: 2 },
        { label: 'Completed', value: 3 },
        { label: 'Cancelled', value: 4 }
    ];

    if (!props.statuses || props.statuses.length === 0) {
        return backupStatuses;
    }

    return props.statuses.filter(status => [1, 2, 4].includes(Number(status.value)));
});

const hasActiveData = computed(() => {
    return Number(props.salesOrder?.batches_count || 0) > 0 || 
           Number(props.salesOrder?.dispatches_count || 0) > 0 || 
           Number(props.salesOrder?.status) === 3;
});

const isCriticalLocked = computed(() => hasActiveData.value);

const isLocked = computed(() => {
    if (!can('SALES_ORDER.UPDATE')) return true;
    if (can('SALES_ORDER.APPROVE')) return false;

    const status = Number(props.salesOrder?.status);
    return hasActiveData.value || (status !== 1 && status !== 4);
});

const isRestrictedFieldLocked = computed(() => {
    if (!isAdmin.value && !isSuperAdmin.value) return true;
    return isLocked.value;
});

const isMixDesignLocked = computed(() => {
    if (!isAdmin.value && !isSuperAdmin.value) return true;
    return isCriticalLocked.value || !!form.customer_po_id;
});

const isRateTaxLocked = computed(() => {
    if (!form.customer_po_id) return isLocked.value;
    if (isAdmin.value || isSuperAdmin.value) return false;
    return true;
});

const defaultStart = new Date();

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
    rate: Number(props.salesOrder?.rate ?? 0),
    tax_id: props.salesOrder?.tax_id ? Number(props.salesOrder.tax_id) : null,
    is_tax_inclusive: props.salesOrder?.is_tax_inclusive ? true : false,
    produced_qty: Number(props.salesOrder?.produced_qty ?? 0),
    status: Number(props.salesOrder?.status ?? 1),
    concrete_pump: props.salesOrder?.concrete_pump ?? null,
    pump_rate: Number(props.salesOrder?.pump_rate ?? 0),
    scheduled_start: props.salesOrder?.scheduled_start ? new Date(props.salesOrder.scheduled_start) : defaultStart,
    scheduled_end: props.salesOrder?.scheduled_end ? new Date(props.salesOrder.scheduled_end) : null,
});

const safeSites = computed(() => {
    return (props.sites || []).filter((s: any) => {
        if (!s) return false;
        if (Number(s.id) === Number(form.site_id)) return true;
        
        let patronIds = s.patron_id;
        if (patronIds === null || patronIds === undefined || patronIds === '') return true;
        if (!form.customer_id) return true;
        
        if (typeof patronIds === 'string') {
            try {
                const parsed = JSON.parse(patronIds);
                if (Array.isArray(parsed)) patronIds = parsed;
            } catch (e) {}
        }
        
        const custId = Number(form.customer_id);
        if (Array.isArray(patronIds)) {
            if (patronIds.length === 0) return true;
            return patronIds.map(Number).includes(custId);
        }
        return Number(patronIds) === custId;
    });
});

const selectedMixDesign = computed(() => {
    const selectedId = form.mix_design_id !== null ? Number(form.mix_design_id) : null;
    return safeMixDesigns.value.find((md) => Number(md?.id) === selectedId);
});

const selectedMixIngredients = computed(() => {
    const mix = selectedMixDesign.value;
    if (!mix) return [];
    return Array.isArray(mix.ingredients) ? mix.ingredients : [];
});

const salesExecutiveOptions = computed(() => 
    (props.salesExecutives || []).map(se => ({ 
        label: se.label || `${se.first_name} ${se.last_name}`, 
        value: se.id 
    }))
);

const taxOptions = computed(() => {
    return (props.taxes ?? []).map((t: any) => ({
        label: `${t.tax_name} (${t.tax_rate}%)`,
        value: t.id,
    }));
});

const customerPOOptions = computed(() => {
    return [
        { label: 'None (Direct Sales Order)', value: null },
        ...(props.customerPOs || []).map((so: any) => {
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

const page = usePage();
const customSettings = page.props.custom_settings as any;

const selectedTaxRate = computed(() => {
    if (!form.tax_id || !props.taxes?.length) return 0;
    const tax = (props.taxes ?? []).find((t: any) => t.id === form.tax_id);
    return tax ? Number(tax.tax_rate || 0) : 0;
});

const lineCalc = computed(() => {
    return calculateLineItemTotals({
        quantity: Number(form.total_qty || 0),
        rate: Number(form.rate || 0),
        pump_rate: Number(form.pump_rate || 0),
        taxRate: selectedTaxRate.value,
        isTaxInclusive: Boolean(form.is_tax_inclusive),
    });
});

const subtotal = computed(() => lineCalc.value.untaxedAmount);
const taxAmount = computed(() => lineCalc.value.taxAmount);
const estimatedTotal = computed(() => lineCalc.value.amountTotal);

onMounted(async () => {
    try {
        const id = props.salesOrder?.id ?? props.salesOrder?.work_order_id;
        if (id) {
            const response = await axios.get(route('salesorders.show', id));
            const fullData = response.data;
            
            form.prefix = fullData.prefix ?? 'SO';
            form.order_no = fullData.order_no ?? '';
            form.plant_id = fullData.plant_id ? Number(fullData.plant_id) : null;
            form.sales_executive_id = fullData.sales_executive_id ? Number(fullData.sales_executive_id) : null;
            form.customer_id = fullData.customer_id ? Number(fullData.customer_id) : null;
            form.site_id = fullData.site_id ? Number(fullData.site_id) : null;
            form.mix_design_id = fullData.mix_design_id ? Number(fullData.mix_design_id) : null;
            form.customer_po_id = fullData.customer_po_id ? Number(fullData.customer_po_id) : null;
            form.total_qty = Number(fullData.total_qty ?? 0);
            form.rate = Number(fullData.rate ?? 0);
            form.tax_id = fullData.tax_id ? Number(fullData.tax_id) : null;
            form.is_tax_inclusive = fullData.is_tax_inclusive ? true : false;
            form.produced_qty = Number(fullData.produced_qty ?? 0);
            form.status = Number(fullData.status ?? 1);
            form.concrete_pump = fullData.concrete_pump ?? null;
            form.pump_rate = Number(fullData.pump_rate ?? 0);
            form.scheduled_start = fullData.scheduled_start ? new Date(fullData.scheduled_start) : defaultStart;
            form.scheduled_end = fullData.scheduled_end ? new Date(fullData.scheduled_end) : null;

            form.defaults(form.data());
        }
    } catch (e) {
        console.error('Failed to load full sales order data', e);
    } finally {
        setTimeout(() => {
            isLoading.value = false;
            isInitializing.value = false;
        }, 50);
    }
});

watch(() => form.customer_po_id, (newVal) => {
    if (isInitializing.value) return;

    if (newVal) {
        const salesOrder = props.customerPOs.find((so) => Number(so.id) === Number(newVal));
        if (salesOrder) {
            form.customer_id = salesOrder.patron_id;
            form.site_id = salesOrder.site_id;
            form.concrete_pump = salesOrder.concrete_pump;
            form.sales_executive_id = salesOrder.sales_executive_id;
            form.is_tax_inclusive = salesOrder.is_tax_inclusive ? true : false;
            
            const firstItem = salesOrder.items?.[0] || salesOrder.quotation?.items?.[0];
            if (firstItem) {
                form.mix_design_id = firstItem.mix_design_id;
                form.total_qty = Number(firstItem.quantity || 0);
                form.rate = Number(firstItem.rate || 0);
                form.tax_id = firstItem.tax_id ? Number(firstItem.tax_id) : null;
                form.concrete_pump = firstItem.concrete_pump ?? salesOrder.concrete_pump ?? null;
                form.pump_rate = Number(firstItem.pump_rate ?? salesOrder.pump_rate ?? 0);
            }
        }
    } else {
        form.customer_id = null;
        form.site_id = null;
        form.concrete_pump = null;
        form.pump_rate = 0;
        form.mix_design_id = null;
        form.total_qty = 0;
        form.rate = 0;
        form.tax_id = null;
        form.is_tax_inclusive = false;
        form.sales_executive_id = null;
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
                title: 'Sales Order updated successfully',
                timer: 1500,
                showConfirmButton: false,
            });
            emit('saved');
        },
    });
};

const loadDependencies = () => {
    if (!props.products?.length || !props.units?.length || !props.designTypes?.length) {
        router.reload({ only: ['products', 'units', 'designTypes'] });
    }
};

const handleMixCreated = () => {
    router.reload({
        only: ['mixDesigns'],
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'New Design available in dropdown',
                timer: 1500,
                showConfirmButton: false,
            });
        }
    });
};
</script>

<template>
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm overflow-hidden relative">
        
        <!-- Loading Overlay -->
        <div v-if="isLoading" class="absolute inset-0 z-20 flex items-center justify-center bg-white/60 dark:bg-slate-900/60 backdrop-blur-sm">
            <i class="pi pi-spinner pi-spin text-3xl text-indigo-500"></i>
        </div>

        <!-- Header -->
        <div class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 px-5 py-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-indigo-100 dark:bg-indigo-950/50 p-2 text-indigo-700 dark:text-indigo-400 ring-1 ring-indigo-200 dark:ring-indigo-900/30">
                    <PencilSquareIcon class="h-5 w-5" />
                </div>
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">Edit Sales Order</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Update order details & scheduled production</p>
                </div>
            </div>

            <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200/60 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300">
                <InformationCircleIcon class="h-4 w-4 text-indigo-500" />
                <span class="font-mono text-xs font-bold tracking-wide">REF #: {{ form.prefix }}{{ form.order_no || salesOrder.order_no }}</span>
            </div>
        </div>

        <!-- Locked Order Banner -->
        <div v-if="isLocked" class="m-5 p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-800 dark:text-amber-200 text-xs flex items-start gap-2.5">
            <LockClosedIcon class="h-4 w-4 mt-0.5 text-amber-600 dark:text-amber-400 shrink-0" />
            <div>
                <span class="font-bold block">Order Locked</span>
                <span v-if="hasActiveData">This sales order has active batches, dispatches, or is completed.</span>
                <span v-else>This sales order is no longer in a modifiable status.</span>
                Only authorized users can modify locked records.
            </div>
        </div>

        <!-- Form Body -->
        <div class="p-5 space-y-6">
            
            <!-- Document & Party Info Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-4 gap-5">
                
                <!-- Customer PO (Optional Linkage) -->
                <!-- <div>
                    <BaseSelect
                        v-model="form.customer_po_id"
                        :options="customerPOOptions"
                        optionLabel="label"
                        optionValue="value"
                        filter
                        label="Customer PO (Optional)"
                        placeholder="Direct Sales Order (None)"
                        :error="form.errors.customer_po_id"
                        :disabled="isCriticalLocked || !!form.customer_po_id"
                    />
                </div> -->

                <!-- Customer -->
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
                        :disabled="isCriticalLocked || !!form.customer_po_id"
                    />
                    <p v-if="form.customer_po_id" class="mt-1 text-xs text-indigo-600 dark:text-indigo-400 font-medium">
                        Locked to Customer PO
                    </p>
                </div>

                <!-- Site Location -->
                <div>
                    <BaseSelect
                        v-model="form.site_id"
                        :options="safeSites"
                        optionLabel="name"
                        optionValue="id"
                        filter
                        label="Site Location"
                        placeholder="Select Site"
                        :error="form.errors.site_id"
                        :disabled="isCriticalLocked || !!form.customer_po_id"
                    />
                    <p v-if="form.customer_po_id" class="mt-1 text-xs text-indigo-600 dark:text-indigo-400 font-medium">
                        Locked to Customer PO
                    </p>
                </div>

                <!-- Sales Executive -->
                <div>
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

                <!-- Scheduled Start -->
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-300">Scheduled Start</label>
                    <BaseDatePicker
                        v-model="form.scheduled_start"
                        showTime
                        hourFormat="24"
                        fluid
                        class="w-full"
                        :disabled="isLocked"
                    />
                    <small v-if="form.errors.scheduled_start" class="text-red-500 text-xs mt-1 block">
                        {{ form.errors.scheduled_start }}
                    </small>
                </div>

            </div>

            <!-- Order Specifications Section -->
            <div class="border-t border-slate-100 dark:border-slate-800 pt-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        Order Specifications
                    </h3>
                    <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-800/80 border border-slate-200/50 dark:border-slate-700/60 rounded-xl px-3 py-1 shadow-sm font-normal">
                        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Tax Inclusive Rates</span>
                        <input 
                            type="checkbox" 
                            v-model="form.is_tax_inclusive" 
                            id="is_tax_inclusive_so_edit" 
                            :disabled="isRateTaxLocked" 
                            class="peer hidden" 
                        />
                        <label 
                            for="is_tax_inclusive_so_edit" 
                            class="relative w-9 h-5 bg-slate-200 dark:bg-slate-700 peer-checked:bg-indigo-600 rounded-full cursor-pointer transition-colors duration-200 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-[16px] disabled:opacity-50 disabled:cursor-not-allowed"
                        ></label>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                    
                    <!-- Mix Design with Inline Action -->
                    <div class="xl:col-span-2">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
                                Mix Design
                            </label>
                            <button
                                v-if="!isMixDesignLocked"
                                type="button"
                                @click="showMixDesignModal = true"
                                class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 transition-colors"
                            >
                                <SparklesIcon class="h-3.5 w-3.5" />
                                <span>Create New</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-2 overflow-visible relative popover-container">
                            <div class="flex-1 min-w-0">
                                <BaseSelect
                                    v-model="form.mix_design_id"
                                    :options="safeMixDesigns"
                                    optionLabel="design_name"
                                    optionValue="id"
                                    filter
                                    placeholder="Select Mix Design"
                                    :error="form.errors.mix_design_id"
                                    :disabled="isMixDesignLocked"
                                    class="w-full"
                                />
                            </div>
                            <!-- Info Button Popover -->
                            <RecipePopover :mixDesignId="form.mix_design_id" :mixDesigns="props.mixDesigns" />
                        </div>
                    </div>

                    <!-- Pump Type -->
                    <div>
                        <BaseSelect
                            v-model="form.concrete_pump"
                            :options="concretePumpOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Pump Type"
                            placeholder="Select Type"
                            :error="form.errors.concrete_pump"
                            :disabled="isRestrictedFieldLocked"
                        />
                    </div>

                    <!-- Pump Rate -->
                    <div>
                        <BaseInputNumber
                            v-model="form.pump_rate"
                            label="Pump Rate"
                            :error="form.errors.pump_rate"
                            :minFractionDigits="2"
                            :disabled="isRateTaxLocked"
                        />
                    </div>

                    <!-- Total Quantity -->
                    <div v-if="can('SALES_ORDER.UPDATE')">
                        <BaseInputNumber
                            v-model="form.total_qty"
                            label="Total Quantity (m³)"
                            :error="form.errors.total_qty"
                            :minFractionDigits="3"
                            :disabled="isRestrictedFieldLocked"
                        />
                    </div>
                    <div v-else class="flex flex-col gap-1 mt-1">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Quantity (m³)</span>
                        <span class="font-semibold text-sm text-slate-800 dark:text-slate-200">{{ form.total_qty }} m³</span>
                    </div>

                    <!-- Produced Quantity -->
                    <div>
                        <BaseInputNumber
                            v-model="form.produced_qty"
                            label="Produced Quantity (m³)"
                            :error="form.errors.produced_qty"
                            readonly
                            disabled
                            :minFractionDigits="3"
                        />
                    </div>

                    <!-- Rate per m³ -->
                    <div>
                        <BaseInputNumber
                            v-model="form.rate"
                            label="Rate per m³"
                            :error="form.errors.rate"
                            :minFractionDigits="2"
                            :disabled="isRateTaxLocked"
                        />
                    </div>

                    <!-- Tax -->
                    <div>
                        <BaseSelect
                            v-model="form.tax_id"
                            :options="taxOptions"
                            optionLabel="label"
                            optionValue="value"
                            label="Tax"
                            placeholder="Select Tax"
                            :error="form.errors.tax_id"
                            :disabled="isRateTaxLocked"
                        />
                    </div>

                    <!-- Status -->
                    <div>
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

                </div>
            </div>

            <!-- Mix Design Specifications Breakdown -->
            <!-- <div v-if="selectedMixDesign" class="rounded-xl border border-indigo-100 dark:border-indigo-900/50 bg-indigo-50/40 dark:bg-indigo-950/20 p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-indigo-100 dark:border-indigo-900/50 pb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">Mix Specifications</span>
                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ selectedMixDesign.design_name }}</span>
                </div>

                <div v-if="selectedMixIngredients.length" class="pt-1">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Ingredients Breakdown</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-4 gap-2 overflow-y-auto">
                        <div 
                            v-for="item in selectedMixIngredients" 
                            :key="item.id" 
                            class="flex justify-between items-center bg-white dark:bg-slate-800 p-2.5 rounded-lg border border-slate-100 dark:border-slate-700/60 text-xs shadow-sm"
                        >
                            <span class="text-slate-600 dark:text-slate-300 text-[11px] truncate mr-1">{{ item.name || 'Unknown' }}</span>
                            <span class="font-bold text-indigo-600 dark:text-indigo-400 text-[11px] shrink-0">
                                {{ Number(item.qty || 0).toFixed(3) }} {{ item.uom || '' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div> -->

            <!-- Totals & Actions Footer -->
            <div class="flex flex-col md:flex-row justify-end items-end gap-6 border-t border-slate-100 dark:border-slate-800 pt-5">
                <!-- <div class="text-xs text-slate-500 dark:text-slate-400 max-w-md">
                    <p class="font-semibold text-slate-700 dark:text-slate-300 mb-1">Production Notice:</p>
                    <p>Updating this Sales Order will modify scheduling for plant #{{ form.plant_id }}. Ensure all pricing and quantity adjustments are verified.</p>
                </div> -->

                <div class="w-full md:w-96 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-3 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                        <div class="flex items-center gap-2">
                            <DocumentCheckIcon class="h-4 w-4 text-indigo-500" />
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">Order Summary</h3>
                        </div>
                        <span v-if="form.is_tax_inclusive" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium">
                            Tax-inclusive
                        </span>
                    </div>

                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between text-slate-500 dark:text-slate-400">
                            <span>Subtotal</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-200">₹ {{ subtotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                        </div>

                        <div class="flex justify-between text-slate-500 dark:text-slate-400">
                            <span>Tax Amount {{ selectedTaxRate ? `(${selectedTaxRate}%)` : '' }}</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-200">₹ {{ taxAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                        </div>

                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex justify-between items-baseline">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200">Estimated Total</span>
                            <span class="text-xl font-bold text-indigo-600 dark:text-indigo-400">₹ {{ estimatedTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                        </div>
                    </div>

                    <div v-if="!isLocked" class="pt-2 flex flex-col gap-2">
                        <Button 
                            label="Update Sales Order" 
                            icon="pi pi-check" 
                            :loading="form.processing" 
                            @click="submit" 
                            class="w-full p-button-indigo shadow-md py-2.5" 
                        />
                        <!-- <button
                            type="button"
                            @click="emit('cancel')"
                            class="w-full py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors"
                        >
                            Cancel
                        </button> -->
                    </div>
                </div>
            </div>

        </div>

        <!-- Create Mix Design Dialog Modal -->
        <Dialog v-model:visible="showMixDesignModal" modal header="Create Mix Design" :style="{ width: '80vw' }" @show="loadDependencies">
            <MixDesignCreateForm
                v-if="showMixDesignModal"
                :products="products"
                :units="units"
                :partners="customers"
                :designTypes="designTypes"
                @created="showMixDesignModal = false; handleMixCreated()"
            />
        </Dialog>
    </div>
</template>