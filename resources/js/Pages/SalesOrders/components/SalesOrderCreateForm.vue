<script setup lang="ts">
import { useForm, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { calculateLineItemTotals } from '@/composables/useLineItemCalculation';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Swal from 'sweetalert2';
import { 
    PlusCircleIcon, 
    DocumentCheckIcon,
    SparklesIcon
} from '@heroicons/vue/24/outline';
import RecipePopover from '@/Components/Base/RecipePopover.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import MixDesignCreateForm from '@/Pages/MixDesigns/Partials/MixDesignCreateForm.vue';

const props = withDefaults(defineProps<{
    customers?: any[];
    sites?: any[];
    mixDesigns?: any[];
    customerPOs?: any[];
    statuses?: { label: string; value: number }[];
    activePlantId?: number;
    nextReference?: string;
    concretePumpOptions?: any[];
    products?: any[];
    units?: any[];
    designTypes?: any[];
    salesExecutives?: any[];
    taxes?: any[];
    pumpRates?: any[];
}>(), {
    customers: () => [],
    sites: () => [],
    mixDesigns: () => [],
    customerPOs: () => [],
    statuses: () => [],
    activePlantId: 0,
    nextReference: '',
    concretePumpOptions: () => [],
    products: () => [],
    units: () => [],
    designTypes: () => [],
    salesExecutives: () => [],
    taxes: () => [],
    pumpRates: () => [],
});

const showMixDesignModal = ref(false);
const safeCustomers = computed(() => props.customers ?? []);
const safeSites = computed(() => {
    return (props.sites ?? []).filter((s: any) => {
        if (!s) return false;
        return !form.customer_id || (Array.isArray(s.patron_id) ? s.patron_id.includes(form.customer_id) : s.patron_id === form.customer_id);
    });
});
const safeMixDesigns = computed(() => props.mixDesigns ?? []);

const selectedMixDesign = computed(() => {
    const selectedId = form.mix_design_id !== null ? Number(form.mix_design_id) : null;
    return safeMixDesigns.value.find((md) => Number(md?.id) === selectedId);
});

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

const selectedMixIngredients = computed(() => {
    const mix = selectedMixDesign.value;
    if (!mix) return [];
    return Array.isArray(mix.ingredients) ? mix.ingredients : [];
});

const mixDetailBadges = computed(() => {
    const mix = selectedMixDesign.value;
    if (!mix) return [];

    return [
        { label: 'Design Code', value: mix.design_code || 'N/A' },
        { label: 'Grade', value: mix.grade || 'N/A' },
        { label: 'Ratio', value: mix.ratio || 'N/A' },
        { label: 'Ingredients Count', value: mix.ingredients.length || 'N/A' },
    ];
});

const salesExecutiveOptions = computed(() => 
    (props.salesExecutives || []).map(se => ({ 
        label: se.label || `${se.first_name} ${se.last_name}`, 
        value: se.id 
    }))
);

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

const defaultStart = new Date();

const form = useForm({
    prefix: 'SO',
    order_no: '',
    plant_id: props.activePlantId,
    sales_executive_id: null as number | null,
    customer_id: null as number | null,
    site_id: null as number | null,
    mix_design_id: null as number | null,
    customer_po_id: null as number | null,
    total_qty: 0,
    rate: 0,
    tax_id: null as number | null,
    is_tax_inclusive: false,
    produced_qty: 0,
    status: 1,
    concrete_pump: null as number | null,
    pump_rate: 0,
    scheduled_start: defaultStart as Date | null,
    scheduled_end: null as Date | null,
});

const taxOptions = computed(() => {
    return (props.taxes ?? []).map((t: any) => ({
        label: `${t.tax_name} (${t.tax_rate}%)`,
        value: t.id,
    }));
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

watch(() => form.customer_po_id, (newVal) => {
    if (newVal) {
        const customerPO = props.customerPOs.find((so) => Number(so.id) === Number(newVal));
        if (customerPO) {
            form.customer_id = customerPO.patron_id;
            form.site_id = customerPO.site_id;
            form.sales_executive_id = customerPO.sales_executive_id;
            form.is_tax_inclusive = !!customerPO.is_tax_inclusive;
            const firstItem = customerPO.items?.[0] || customerPO.quotation?.items?.[0];
            if (firstItem) {
                form.mix_design_id = firstItem.mix_design_id;
                form.total_qty = Number(firstItem.quantity || 0);
                form.rate = Number(firstItem.rate || 0);
                form.tax_id = firstItem.tax_id ? Number(firstItem.tax_id) : null;
                
                // Set initial pump rate
                resolveSinglePumpRate();
            }
        }
    } else {
        form.customer_id = null;
        form.site_id = null;
        form.mix_design_id = null;
        form.total_qty = 0;
        form.rate = 0;
        form.pump_rate = 0;
        form.tax_id = null;
        form.is_tax_inclusive = false;
        form.sales_executive_id = null;
        form.concrete_pump = null;
    }
});

const resolvePumpRatesLocally = (customerId: number | null, siteId: number | null) => {
    const activeRates = props.pumpRates || [];
    const scoredRates = activeRates.map(rate => {
        let score = 0;
        if (rate.customer_id !== null && Number(rate.customer_id) === Number(customerId)) {
            if (siteId !== null && Number(rate.site_id) === Number(siteId)) {
                score = 3;
            } else if (rate.site_id === null || rate.site_id === undefined) {
                score = 2;
            }
        } else if (rate.customer_id === null || rate.customer_id === undefined) {
            score = 1;
        }
        return { ...rate, score };
    }).filter(rate => rate.score > 0);

    const resolved: Record<string, any> = {};
    scoredRates.forEach(rate => {
        const type = rate.concrete_pump;
        if (!resolved[type] || resolved[type].score < rate.score) {
            resolved[type] = rate;
        }
    });

    return Object.values(resolved).sort((a: any, b: any) => b.score - a.score);
};

const resolveSinglePumpRate = (isDropdownChange = false) => {
    const resolved = resolvePumpRatesLocally(form.customer_id, form.site_id);
    if (form.concrete_pump) {
        const matched = resolved.find((r: any) => String(r.concrete_pump) === String(form.concrete_pump));
        if (matched) {
            if (isDropdownChange) {
                form.pump_rate = Number(matched.rate || matched.pump_rate || 0);
            }
        } else {
            if (isDropdownChange) {
                form.pump_rate = 0;
            }
        }
    } else {
        if (resolved.length > 0) {
            const matched = resolved[0];
            form.concrete_pump = Number(matched.concrete_pump);
            form.pump_rate = Number(matched.rate || matched.pump_rate || 0);
        } else {
            form.concrete_pump = null;
            form.pump_rate = 0;
        }
    }
};

watch(() => form.concrete_pump, () => {
    resolveSinglePumpRate(true);
});
watch(() => form.customer_id, () => {
    if (form.customer_po_id) return;
    form.concrete_pump = null;
    resolveSinglePumpRate(true);
});
watch(() => form.site_id, () => {
    if (form.customer_po_id) return;
    form.concrete_pump = null;
    resolveSinglePumpRate(true);
});

const submit = () => {
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
        order_no: data.order_no || null,
    })).post(route('salesorders.store'), {
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Sales Order created successfully',
                timer: 1500,
                showConfirmButton: false,
            });
            form.reset();
            form.clearErrors();
            form.prefix = 'SO';
            form.plant_id = props.activePlantId;
            form.status = 1;
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
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm overflow-hidden">
        
        <!-- Header -->
        <div class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 px-5 py-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-indigo-100 dark:bg-indigo-950/50 p-2 text-indigo-700 dark:text-indigo-400 ring-1 ring-indigo-200 dark:ring-indigo-900/30">
                    <PlusCircleIcon class="h-5 w-5" />
                </div>
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">Create Sales Order</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Schedule production orders & configure job details</p>
                </div>
            </div>

            <div v-if="nextReference" class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200/60 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300">
                <InformationCircleIcon class="h-4 w-4 text-indigo-500" />
                <span class="text-xs font-semibold tracking-wide">Next Ref: {{ nextReference }}</span>
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
                        :disabled="!!form.customer_po_id"
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
                        :disabled="!!form.customer_po_id"
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
                            id="is_tax_inclusive_so" 
                            :disabled="!!form.customer_po_id" 
                            class="peer hidden" 
                        />
                        <label 
                            for="is_tax_inclusive_so" 
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
                                v-if="!form.customer_po_id"
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
                                                            @update:modelValue="resolveSinglePumpRate(true)"

                        />
                    </div>

                    <!-- Pump Rate -->
                    <div>
                        <BaseInputNumber
                            v-model="form.pump_rate"
                            label="Pump Rate"
                            :error="form.errors.pump_rate"
                            :minFractionDigits="2"
                            :disabled="!!form.customer_po_id"
                        />
                    </div>

                    <!-- Total Quantity -->
                    <div>
                        <BaseInputNumber
                            v-model="form.total_qty"
                            label="Total Quantity (m³)"
                            :error="form.errors.total_qty"
                            :minFractionDigits="3"
                            :disabled="!!form.customer_po_id"
                        />
                    </div>

                    <!-- Rate per m³ -->
                    <div>
                        <BaseInputNumber
                            v-model="form.rate"
                            label="Rate per m³"
                            :error="form.errors.rate"
                            :minFractionDigits="2"
                            :disabled="!!form.customer_po_id"
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
                            :disabled="!!form.customer_po_id"
                        />
                    </div>

                    <!-- Initial Status -->
                    <div>
                        <BaseSelect
                            v-model="form.status"
                            :options="filteredStatuses"
                            optionLabel="label"
                            optionValue="value"
                            label="Initial Status"
                            :error="form.errors.status"
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
                    <p>Creating this Sales Order will queue it for scheduling at plant #{{ form.plant_id || activePlantId }}. Please verify quantity, mix design, and site details before submitting.</p>
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

                    <div class="pt-2">
                        <Button 
                            label="Create Sales Order" 
                            icon="pi pi-check" 
                            :loading="form.processing" 
                            @click="submit" 
                            class="w-full p-button-indigo shadow-md py-2.5" 
                        />
                    </div>
                </div>
            </div>

        </div>

        <!-- Create Mix Design Dialog -->
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