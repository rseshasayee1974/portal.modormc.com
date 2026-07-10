    <script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Swal from 'sweetalert2';
import { PlusCircleIcon, InformationCircleIcon, BeakerIcon } from '@heroicons/vue/24/outline';
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
         { label: 'Completed', value: 3 },
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

const salesExecutiveOptions = computed(() => (props.salesExecutives || []).map(se => ({ label: se.label || `${se.first_name} ${se.last_name}`, value: se.id })));

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
    produced_qty: 0,
    status: 1,
    concrete_pump: null as number | null,
    scheduled_start: defaultStart as Date | null,
    scheduled_end: null as Date | null,
});

// Watch sales order selection to auto-fill patron, site, mix design, and total quantity
watch(() => form.customer_po_id, (newVal) => {
    if (newVal) {
        const customerPO = props.customerPOs.find((so) => Number(so.id) === Number(newVal));
        if (customerPO) {
            form.customer_id = customerPO.patron_id;
            form.site_id = customerPO.site_id;
            form.sales_executive_id = customerPO.sales_executive_id;
            const firstItem = customerPO.quotation?.items?.[0];
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
        form.sales_executive_id = null;
    }
});

watch(() => form.scheduled_start, (newStart) => {
    if (newStart) {
        const start = new Date(newStart);
        if (form.scheduled_end && new Date(form.scheduled_end) <= start) {
            const endDate = new Date(start);
            endDate.setHours(endDate.getHours() + 1);
            form.scheduled_end = endDate;
        }
    }
});

const submit = () => {
     form.clearErrors('scheduled_end');
    if (form.scheduled_start && form.scheduled_end) {
        const startDate = new Date(form.scheduled_start);
        startDate.setSeconds(0, 0);
        const start = startDate.getTime();

        const endDate = new Date(form.scheduled_end);
        endDate.setSeconds(0, 0);
        const end = endDate.getTime();
        
        // if (start === end) {
        //     form.setError('scheduled_end', 'Start and end time cannot be exactly the same.');
        //     return;
        // }
        if (start > end) {
            form.setError('scheduled_end', 'End time cannot be before the start time.');
            return;
        }
    }
    if (!form.scheduled_end && form.scheduled_start) {
        const endDate = new Date(form.scheduled_start);
        endDate.setHours(endDate.getHours() + 1);
        form.scheduled_end = endDate;
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
        order_no: data.order_no || null,
    })).post(route('salesorders.store'), {
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Sales Order created',
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
        <div class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="rounded-lg bg-indigo-100 p-1.5 text-indigo-700 ring-1 ring-indigo-200">
                    <PlusCircleIcon class="h-4 w-4" />
                </div>
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">New Sales Order</h2>
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
            v-model="form.customer_po_id"
            :options="customerPOOptions"
            optionLabel="label"
            optionValue="value"
            filter
            label="Sales Order (Optional)"
            placeholder="Select Sales Order"
            :error="form.errors.customer_po_id"
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
            :disabled="!!form.customer_po_id"
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
            :disabled="!!form.customer_po_id"
        />
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
    <div>
        <div class="flex items-center justify-between mb-1">
            <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-400">
                Mix Design
            </label>

            <button
                v-if="!form.customer_po_id"
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
            :disabled="!!form.customer_po_id"
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
            :disabled="!!form.customer_po_id"
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
            <Button label="Create Sales Order" icon="pi pi-check" :loading="form.processing" @click="submit" class="p-button-indigo" />
        </div>

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
