<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import Swal from 'sweetalert2';
import { PlusCircleIcon, InformationCircleIcon, BeakerIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';

const props = withDefaults(defineProps<{
    customers?: any[];
    sites?: any[];
    mixDesigns?: any[];
    statuses?: { label: string; value: number }[];
    activePlantId?: number;
    nextReference?: string;
}>(), {
    customers: () => [],
    sites: () => [],
    mixDesigns: () => [],
    statuses: () => [],
    activePlantId: 0,
    nextReference: '',
});

const showMixDesignModal = ref(false);
console.log(props.mixDesigns);
const safeCustomers = computed(() => props.customers ?? []);
const safeSites = computed(() => props.sites ?? []);
const safeMixDesigns = computed(() => props.mixDesigns ?? []);
const safeStatuses = computed(() => props.statuses ?? []);

const selectedMixDesign = computed(() => {
    const selectedId = form.mix_design_id !== null ? Number(form.mix_design_id) : null;
    return safeMixDesigns.value.find((md) => Number(md?.id) === selectedId);
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
const form = useForm({
    prefix: 'WO',
    order_no: '',
    plant_id: props.activePlantId,
    customer_id: null as number | null,
    site_id: null as number | null,
    mix_design_id: null as number | null,
    total_qty: 0,
    produced_qty: 0,
    status: 1,
    scheduled_start: null as Date | null,
    scheduled_end: null as Date | null,
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
                timer: 2200,
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
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'New Design available in dropdown',
        timer: 3000,
        showConfirmButton: false,
    });
};
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/50 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="rounded-lg bg-indigo-100 p-1.5 text-indigo-700 ring-1 ring-indigo-200">
                    <PlusCircleIcon class="h-4 w-4" />
                </div>
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">New Work Order</h2>
            </div>

            <div v-if="nextReference" class="flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700">
                <InformationCircleIcon class="h-3.5 w-3.5" />
                <span class="text-[10px] font-bold uppercase tracking-tight">Next Ref: {{ nextReference }}</span>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-5 p-5">
            <!-- Row 1: Identification & Primary -->
            <!-- <div class="col-span-12 md:col-span-3">
                <BaseInput v-model="form.prefix" label="Prefix" :error="form.errors.prefix" />
            </div>
            <div class="col-span-12 md:col-span-3">
                <BaseInput v-model="form.order_no" label="Order Number (Optional)" :error="form.errors.order_no" />
            </div> -->
            <div class="col-span-12 md:col-span-3">
                <BaseSelect v-model="form.customer_id" :options="safeCustomers" optionLabel="legal_name" optionValue="id" filter label="Customer" placeholder="Select Customer" :error="form.errors.customer_id" />
            </div>
            <div class="col-span-12 md:col-span-3">
                <BaseSelect v-model="form.site_id" :options="safeSites" optionLabel="name" optionValue="id" filter label="Site" placeholder="Select Site" :error="form.errors.site_id" />
            </div>

            <!-- Row 2: Technical & Status -->
            <div class="col-span-12 md:col-span-3">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-400">Mix Design</label>
                    <button type="button" @click="showMixDesignModal = true" class="flex items-center gap-1 text-[10px] font-bold text-indigo-600 hover:text-indigo-700 transition-colors">
                        <BeakerIcon class="h-3 w-3" />
                        <span>CREATE NEW</span>
                    </button>
                </div>
                <BaseSelect v-model="form.mix_design_id" :options="safeMixDesigns" optionLabel="design_name" optionValue="id" filter placeholder="Select Design" :error="form.errors.mix_design_id" />
                
                <!-- Mix Design Ingredients Hint -->
                <!-- <div v-if="selectedMixIngredients.length" class="mt-2.5 space-y-1.5 animate-in fade-in slide-in-from-top-1 duration-300">
                    <div class="flex items-center justify-between">
                        <label class="text-[9px] font-bold uppercase tracking-[0.1em] text-slate-400">Recipe Ingredients</label>
                        <span v-if="selectedMixDesign?.grade" class="rounded bg-indigo-50 px-1 py-0.5 text-[8px] font-bold text-indigo-700 ring-1 ring-inset ring-indigo-100">GRADE: {{ selectedMixDesign.grade }}</span>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <div v-for="item in selectedMixIngredients" :key="item.id" 
                             class="group flex items-center gap-2 rounded-md bg-slate-50 px-2 py-1 ring-1 ring-inset ring-slate-200 transition-all hover:bg-white hover:shadow-sm hover:ring-indigo-200">
                            <span class="text-[10px] font-medium text-slate-600">{{ item.name || 'Unknown' }}</span>
                            <div class="h-3 w-px bg-slate-200"></div>
                            <span class="text-[10px] font-bold text-indigo-600">{{ item.qty }} <span class="text-[9px] font-medium text-slate-400">{{ item.uom }}</span></span>
                        </div>
                    </div>
                </div> -->
            </div>
            <div class="col-span-12 md:col-span-3">
                <BaseSelect v-model="form.status" :options="safeStatuses" optionLabel="label" optionValue="value" label="Status" :error="form.errors.status" />
            </div>

            <div v-if="selectedMixDesign" class="col-span-12 rounded-lg border border-indigo-100 bg-indigo-50/40 p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[10px] font-bold uppercase tracking-[0.12em] text-indigo-700">Selected Mix Design</span>
                    <span class="text-xs font-semibold text-slate-700">{{ selectedMixDesign.design_name }}</span>
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    <div v-for="badge in mixDetailBadges" :key="badge.label" class="rounded-md border border-indigo-100 bg-white px-2 py-1">
                        <span class="text-[9px] font-bold uppercase tracking-[0.08em] text-slate-400">{{ badge.label }}</span>
                        <span class="ml-1 text-[11px] font-semibold text-slate-700">{{ badge.value }}</span>
                    </div>
                </div>

                <div v-if="selectedMixIngredients.length" class="mt-3">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Mix Ingredients</p>
                    <div class="mt-1.5 grid grid-cols-1 gap-1.5 md:grid-cols-2 xl:grid-cols-3">
                        <div
                            v-for="item in selectedMixIngredients"
                            :key="item.id"
                            class="flex items-center justify-between rounded-md border border-slate-200 bg-white px-2 py-1.5"
                        >
                            <span class="text-[11px] text-slate-700">{{ item.name || 'Unknown' }}</span>
                            <span class="text-[11px] font-bold text-indigo-600">
                                {{ Number(item.qty || 0).toFixed(3) }} {{ item.uom || '' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-12 md:col-span-3">
                <BaseInputNumber v-model="form.total_qty" label="Total Quantity (m³)" :error="form.errors.total_qty" :minFractionDigits="3" />
            </div>
            <!-- <div class="col-span-12 md:col-span-3">
                <BaseInputNumber v-model="form.produced_qty" label="Produced Quantity (m³)" :error="form.errors.produced_qty" :minFractionDigits="3" />
            </div> -->
            <div class="col-span-12 md:col-span-3">
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-widest text-gray-400">Scheduled Start</label>
                <BaseDatePicker v-model="form.scheduled_start" showTime hourFormat="24" fluid class="w-full" />
                <small v-if="form.errors.scheduled_start" class="text-red-500 text-[11px]">{{ form.errors.scheduled_start }}</small>
            </div>
            <div class="col-span-12 md:col-span-3">
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-widest text-gray-400">Scheduled End</label>
                <BaseDatePicker v-model="form.scheduled_end" showTime hourFormat="24" fluid class="w-full" />
                <small v-if="form.errors.scheduled_end" class="text-red-500 text-[11px]">{{ form.errors.scheduled_end }}</small>
            </div>


        </div>

        <div class="flex justify-end border-t border-slate-100 px-4 py-3">
            <Button label="Create Work Order" icon="pi pi-check" :loading="form.processing" @click="submit" />
        </div>
    </div>
</template>
