<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import axios from 'axios';

const props = defineProps<{
    initialData?: any;
    plants: any[];
    grades: any[];
    patrons?: any[];
    activePlantId?: number | null;
    isEdit?: boolean;
}>();

const emit = defineEmits<{
    (e: 'submit', data: any): void;
    (e: 'cancel'): void;
}>();

// Default 3 specimen rows
const defaultSpecimens = [
    { id: null, ident_mark: 'EB', weight_kg: '', load_kn: '', compressive_strength: '' },
    { id: null, ident_mark: 'EB', weight_kg: '', load_kn: '', compressive_strength: '' },
    { id: null, ident_mark: 'EB', weight_kg: '', load_kn: '', compressive_strength: '' },
];

const today = new Date().toISOString().substring(0, 10);

const form = useForm({
    id: props.initialData?.id ?? null,
    plant_id: props.initialData?.plant_id ?? (props.activePlantId || (props.plants?.[0]?.id ?? null)),
    account_name: props.initialData?.account_name ?? '',
    patron_id: props.initialData?.patron_id ?? null,
    invoice_id: props.initialData?.invoice_id ?? null,
    invoice_no: props.initialData?.invoice_no ?? '',
    grade: props.initialData?.grade ?? (props.grades?.[0]?.name ?? 'M25 (Gst)'),
    concrete_date: props.initialData?.concrete_date ? props.initialData.concrete_date.substring(0, 10) : today,
    age_of_test_days: props.initialData?.age_of_test_days ?? 7,
    date_of_testing: props.initialData?.date_of_testing ? props.initialData.date_of_testing.substring(0, 10) : '',
    project: props.initialData?.project ?? '',
    billing_address: props.initialData?.billing_address ?? '',
    shipping_address: props.initialData?.shipping_address ?? '',
    description: props.initialData?.description ?? props.initialData?.remarks ?? '',
    test_number: props.initialData?.test_number ?? props.initialData?.test_code ?? '',
    dimension_length: props.initialData?.dimension_length ?? 15,
    dimension_width: props.initialData?.dimension_width ?? 15,
    dimension_height: props.initialData?.dimension_height ?? 15,
    fresh_unit_weight: props.initialData?.fresh_unit_weight ?? 2511,
    slump_value: props.initialData?.slump_value ?? 120,
    air_content: props.initialData?.air_content ?? 1.2,
    fresh_temperature: props.initialData?.fresh_temperature ?? 32,
    lab_technician: props.initialData?.lab_technician ?? '',
    field_technician: props.initialData?.field_technician ?? '',
    ident_mark: props.initialData?.ident_mark ?? 'EB',
    status: props.initialData?.status ?? 'passed',
    specimens: (props.initialData?.specimens && props.initialData.specimens.length > 0)
        ? props.initialData.specimens.map((s: any) => ({
            id: s.id ?? null,
            ident_mark: s.ident_mark ?? props.initialData?.ident_mark ?? 'EB',
            weight_kg: s.weight_kg ?? '',
            load_kn: s.load_kn ?? '',
            compressive_strength: s.compressive_strength ?? '',
        }))
        : JSON.parse(JSON.stringify(defaultSpecimens)),
});

// Auto-calculate Date of Testing based on Concrete Date + Age of Test
const calculateTestingDate = () => {
    if (!form.concrete_date || !form.age_of_test_days) return;
    try {
        const parts = form.concrete_date.split('-');
        if (parts.length === 3) {
            const date = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            date.setDate(date.getDate() + parseInt(form.age_of_test_days as any));
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            form.date_of_testing = `${y}-${m}-${d}`;
        }
    } catch (e) {
        console.error(e);
    }
};

watch(() => [form.concrete_date, form.age_of_test_days], () => {
    calculateTestingDate();
}, { immediate: true });

// Auto-calculate specimen compressive strengths and average
const calculateStrengths = () => {
    const dimL = parseFloat(form.dimension_length as any) || 15;
    const dimW = parseFloat(form.dimension_width as any) || 15;
    // Area in mm2 = (dimL cm * 10) * (dimW cm * 10)
    const areaMm2 = (dimL * 10) * (dimW * 10);
    if (areaMm2 <= 0) return;

    form.specimens.forEach((spec: any) => {
        const load = parseFloat(spec.load_kn);
        if (!isNaN(load) && load > 0) {
            // N/mm2 = (kN * 1000) / mm2
            spec.compressive_strength = ((load * 1000) / areaMm2).toFixed(2);
        } else {
            spec.compressive_strength = '';
        }
    });
};

watch(() => [form.dimension_length, form.dimension_width, form.specimens], () => {
    calculateStrengths();
}, { deep: true });

// Compute average compressive strength
const avgCompressiveStrength = computed(() => {
    const validStrengths = form.specimens
        .map((s: any) => parseFloat(s.compressive_strength))
        .filter((val: number) => !isNaN(val) && val > 0);

    if (validStrengths.length === 0) return '';
    const sum = validStrengths.reduce((a: number, b: number) => a + b, 0);
    return (sum / validStrengths.length).toFixed(2);
});

// Specimen row operations
const addSpecimenRow = () => {
    form.specimens.push({
        id: null,
        ident_mark: form.ident_mark || 'EB',
        weight_kg: '',
        load_kn: '',
        compressive_strength: '',
    });
};

const removeSpecimenRow = (index: number) => {
    if (form.specimens.length > 1) {
        form.specimens.splice(index, 1);
        calculateStrengths();
    }
};

// Invoice lookup modal
const showInvoiceDialog = ref(false);
const invoiceSearchQuery = ref('');
const invoiceSearchResults = ref<any[]>([]);
const isSearchingInvoices = ref(false);

const searchInvoices = async () => {
    isSearchingInvoices.value = true;
    try {
        const response = await axios.get(route('concrete-quality-tests.lookup.invoices'), {
            params: {
                search: invoiceSearchQuery.value,
                plant_id: form.plant_id,
            }
        });
        invoiceSearchResults.value = response.data || [];
    } catch (e) {
        console.error('Invoice lookup failed:', e);
    } finally {
        isSearchingInvoices.value = false;
    }
};

const openInvoiceLookup = () => {
    invoiceSearchQuery.value = form.invoice_no || '';
    showInvoiceDialog.value = true;
    searchInvoices();
};

const selectInvoice = (inv: any) => {
    form.invoice_id = inv.id;
    form.invoice_no = inv.full_number || inv.invoice_number;
    if (inv.account_name) form.account_name = inv.account_name;
    if (inv.patron_id) form.patron_id = inv.patron_id;
    if (inv.billing_address) form.billing_address = inv.billing_address;
    if (inv.shipping_address) form.shipping_address = inv.shipping_address;
    if (inv.grade) form.grade = inv.grade;
    if (inv.concrete_date) {
        form.concrete_date = inv.concrete_date;
        calculateTestingDate();
    }
    showInvoiceDialog.value = false;
};

// Patron selection helper
const onPatronChange = (e: any) => {
    const patronId = parseInt(e.target.value);
    if (!patronId) return;
    const p = props.patrons?.find(x => x.id === patronId);
    if (p) {
        form.account_name = p.name;
    }
};

// Submit handler
const submitForm = () => {
    calculateStrengths();
    if (props.isEdit && form.id) {
        form.put(route('concrete-quality-tests.update', form.id), {
            preserveScroll: true,
            onError: (err) => console.error(err),
        });
    } else {
        form.post(route('concrete-quality-tests.store'), {
            preserveScroll: true,
            onError: (err) => console.error(err),
        });
    }
};

const cancelForm = () => {
    if (props.initialData?.id) {
        router.get(route('concrete-quality-tests.show', props.initialData.id));
    } else {
        router.get(route('concrete-quality-tests.index'));
    }
};
</script>

<template>
    <div class="max-w-7xl mx-auto pb-12">
        <!-- ── Top Tab Bar (Exact match to screenshot) ── -->
        <div class="flex items-center border-b border-gray-200">
            <div class="bg-[#00a2ed] text-white px-8 py-2.5 font-semibold text-sm rounded-t-sm shadow-sm flex items-center gap-2 cursor-pointer">
                <span>Basic Information</span>
            </div>
        </div>

        <form @submit.prevent="submitForm" class="bg-white border border-gray-200 shadow-sm p-6 sm:p-8 space-y-8 rounded-b-sm">

            <!-- ── SECTION 1: Concrete Test Information ── -->
            <div class="space-y-4">
                <div class="border-b border-sky-100 pb-2">
                    <h2 class="text-sm font-bold text-[#0088cc] uppercase tracking-wider">
                        Concrete Test Information
                    </h2>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-3.5 text-xs text-gray-700">

                    <!-- Left Column -->
                    <div class="space-y-3">
                        <!-- Account Name -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Account Name <span class="text-red-500">*</span>
                            </label>
                            <div class="col-span-8 relative flex items-center">
                                <input
                                    v-model="form.account_name"
                                    type="text"
                                    required
                                    placeholder="Enter Account / Customer Name"
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500 pr-8"
                                />
                                <div class="absolute right-2 text-gray-400 pointer-events-none">
                                    <i class="pi pi-user text-xs"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Factory -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Factory <span class="text-red-500">*</span>
                            </label>
                            <div class="col-span-8">
                                <select
                                    v-model="form.plant_id"
                                    required
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500 bg-white"
                                >
                                    <option v-for="plant in plants" :key="plant.id" :value="plant.id">
                                        {{ plant.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Invoice -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Invoice
                            </label>
                            <div class="col-span-8 flex items-center gap-1.5">
                                <input
                                    v-model="form.invoice_no"
                                    type="text"
                                    placeholder="e.g. 2026-27/5529"
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                />
                                <button
                                    type="button"
                                    @click="openInvoiceLookup"
                                    title="Lookup Invoice Details"
                                    class="bg-red-500 hover:bg-red-600 active:bg-red-700 text-white px-2.5 py-1.5 rounded text-xs flex items-center gap-1 shadow-sm transition-colors flex-shrink-0"
                                >
                                    <span>...</span>
                                    <i class="pi pi-search text-[10px]"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Grade -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Grade <span class="text-red-500">*</span>
                            </label>
                            <div class="col-span-8">
                                <select
                                    v-model="form.grade"
                                    required
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500 bg-white"
                                >
                                    <option v-for="g in grades" :key="g.id || g.name" :value="g.name">
                                        {{ g.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Concrete Date -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Concrete Date <span class="text-red-500">*</span>
                            </label>
                            <div class="col-span-8">
                                <input
                                    v-model="form.concrete_date"
                                    type="date"
                                    required
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                />
                            </div>
                        </div>

                        <!-- Age Of Test (days) -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Age Of Test (days) <span class="text-red-500">*</span>
                            </label>
                            <div class="col-span-8">
                                <select
                                    v-model="form.age_of_test_days"
                                    required
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500 bg-white"
                                >
                                    <option :value="3">3</option>
                                    <option :value="7">7</option>
                                    <option :value="14">14</option>
                                    <option :value="28">28</option>
                                    <option :value="56">56</option>
                                    <option :value="90">90</option>
                                </select>
                            </div>
                        </div>

                        <!-- Date Of Testing -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Date Of Testing <span class="text-red-500">*</span>
                            </label>
                            <div class="col-span-8">
                                <input
                                    v-model="form.date_of_testing"
                                    type="date"
                                    required
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                />
                            </div>
                        </div>

                        <!-- Project -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Project
                            </label>
                            <div class="col-span-8">
                                <input
                                    v-model="form.project"
                                    type="text"
                                    placeholder="Enter Project Name"
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                />
                            </div>
                        </div>

                        <!-- Billing Address -->
                        <div class="grid grid-cols-12 items-start gap-2">
                            <label class="col-span-4 font-medium text-gray-700 pt-1">
                                Billing Address
                            </label>
                            <div class="col-span-8">
                                <textarea
                                    v-model="form.billing_address"
                                    rows="2"
                                    placeholder="Billing address details"
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500 resize-y"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-3">
                        <!-- Test Number -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Test Number
                            </label>
                            <div class="col-span-8">
                                <input
                                    :value="form.test_number || 'AUTO GEN ON SAVE'"
                                    type="text"
                                    readonly
                                    class="w-full px-3 py-1.5 text-xs font-semibold rounded bg-gray-50 cursor-not-allowed border"
                                    :class="form.test_number ? 'border-gray-300 text-gray-800' : 'border-red-200 text-red-500'"
                                />
                            </div>
                        </div>

                        <!-- Dimension (cm) * -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Dimension (cm) <span class="text-red-500">*</span>
                            </label>
                            <div class="col-span-8 flex items-center gap-2">
                                <input
                                    v-model="form.dimension_length"
                                    type="number"
                                    step="any"
                                    required
                                    class="w-20 px-2 py-1.5 text-center text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                />
                                <span class="text-gray-500 font-bold text-xs">X</span>
                                <input
                                    v-model="form.dimension_width"
                                    type="number"
                                    step="any"
                                    required
                                    class="w-20 px-2 py-1.5 text-center text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                />
                                <span class="text-gray-500 font-bold text-xs">X</span>
                                <input
                                    v-model="form.dimension_height"
                                    type="number"
                                    step="any"
                                    required
                                    class="w-20 px-2 py-1.5 text-center text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                />
                            </div>
                        </div>

                        <!-- Fresh Unit Weight (kg) -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Fresh Unit Weight <span class="text-gray-400 font-normal">(kg)</span>
                            </label>
                            <div class="col-span-8">
                                <input
                                    v-model="form.fresh_unit_weight"
                                    type="number"
                                    step="any"
                                    placeholder="e.g. 2511"
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                />
                            </div>
                        </div>

                        <!-- Slump (mm) -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Slump <span class="text-gray-400 font-normal">(mm)</span> <span class="text-red-500">*</span>
                            </label>
                            <div class="col-span-8">
                                <input
                                    v-model="form.slump_value"
                                    type="number"
                                    step="any"
                                    required
                                    placeholder="e.g. 120"
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                />
                            </div>
                        </div>

                        <!-- Air Content (%) -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Air Content <span class="text-gray-400 font-normal">(%)</span>
                            </label>
                            <div class="col-span-8">
                                <input
                                    v-model="form.air_content"
                                    type="number"
                                    step="any"
                                    placeholder="e.g. 1.2"
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                />
                            </div>
                        </div>

                        <!-- Concrete Temperature (°C) -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Concrete Temperature <span class="text-gray-400 font-normal">(°C)</span>
                            </label>
                            <div class="col-span-8">
                                <input
                                    v-model="form.fresh_temperature"
                                    type="number"
                                    step="any"
                                    placeholder="e.g. 32"
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                />
                            </div>
                        </div>

                        <!-- Lab Technician -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Lab Technician
                            </label>
                            <div class="col-span-8 relative flex items-center">
                                <input
                                    v-model="form.lab_technician"
                                    type="text"
                                    placeholder="e.g. PRADEEP RAJ"
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500 pr-8"
                                />
                                <div class="absolute right-2 text-gray-400 pointer-events-none">
                                    <i class="pi pi-user text-xs"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Field Technician -->
                        <div class="grid grid-cols-12 items-center gap-2">
                            <label class="col-span-4 font-medium text-gray-700">
                                Field Technician
                            </label>
                            <div class="col-span-8 relative flex items-center">
                                <input
                                    v-model="form.field_technician"
                                    type="text"
                                    placeholder="e.g. PAVITHRAN"
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500 pr-8"
                                />
                                <div class="absolute right-2 text-gray-400 pointer-events-none">
                                    <i class="pi pi-user text-xs"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Shipping Address -->
                        <div class="grid grid-cols-12 items-start gap-2">
                            <label class="col-span-4 font-medium text-gray-700 pt-1">
                                Shipping Address
                            </label>
                            <div class="col-span-8">
                                <textarea
                                    v-model="form.shipping_address"
                                    rows="2"
                                    placeholder="Site / shipping address"
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500 resize-y"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Description (Full Width spanning both columns) -->
                    <div class="lg:col-span-2 grid grid-cols-12 items-start gap-2 pt-1 border-t border-gray-100">
                        <label class="col-span-2 font-medium text-gray-700 pt-1">
                            Description
                        </label>
                        <div class="col-span-10">
                            <textarea
                                v-model="form.description"
                                rows="2"
                                placeholder="Additional test details, customer remarks, or casting notes..."
                                class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500 resize-y"
                            ></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── SECTION 2: Laboratory Test Information (Cube Specimens) ── -->
            <div class="space-y-4 pt-4 border-t border-gray-200">
                <div class="border-b border-sky-100 pb-2">
                    <h2 class="text-sm font-bold text-[#0088cc] uppercase tracking-wider">
                        Laboratory Test Information
                    </h2>
                </div>

                <!-- Cube Specimens Table (Exact match to screenshot) -->
                <div class="overflow-x-auto border border-gray-200 rounded-sm">
                    <table class="w-full text-xs text-center border-collapse">
                        <thead>
                            <tr class="bg-[#fcf9ee] border-b border-gray-200 text-gray-800 font-bold">
                                <th class="py-2.5 px-4 text-center font-bold tracking-wide w-48">Ident Mark</th>
                                <th class="py-2.5 px-4 text-center font-bold tracking-wide">
                                    Weight <span class="text-[#0088cc]">(kg)</span>
                                </th>
                                <th class="py-2.5 px-4 text-center font-bold tracking-wide">
                                    Load <span class="text-[#0088cc]">(kN)</span>
                                </th>
                                <th class="py-2.5 px-4 text-center font-bold tracking-wide">
                                    Compressive Strength <span class="text-[#0088cc]">(N/mm²)</span>
                                </th>
                                <th class="py-2.5 px-4 text-center font-bold tracking-wide">
                                    Avg.Compressive Strength <span class="text-[#0088cc]">(N/mm²)</span>
                                </th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="(spec, index) in form.specimens" :key="index" class="hover:bg-gray-50/50">
                                <!-- Ident Mark (shown on first row with rowspan or editable) -->
                                <td v-if="index === 0" :rowspan="form.specimens.length" class="p-3 border-r border-gray-200 align-middle">
                                    <input
                                        v-model="form.ident_mark"
                                        type="text"
                                        placeholder="e.g. EB"
                                        class="w-32 mx-auto text-center px-2 py-1.5 text-xs font-semibold border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500 bg-white"
                                    />
                                </td>

                                <!-- Weight (kg) -->
                                <td class="p-2 border-r border-gray-200">
                                    <input
                                        v-model="spec.weight_kg"
                                        type="number"
                                        step="any"
                                        placeholder="e.g. 8.65"
                                        class="w-36 mx-auto text-center px-2 py-1.5 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                    />
                                </td>

                                <!-- Load (kN) -->
                                <td class="p-2 border-r border-gray-200">
                                    <input
                                        v-model="spec.load_kn"
                                        type="number"
                                        step="any"
                                        placeholder="e.g. 635.60"
                                        class="w-36 mx-auto text-center px-2 py-1.5 text-xs font-semibold border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                    />
                                </td>

                                <!-- Compressive Strength (N/mm2) -->
                                <td class="p-2 border-r border-gray-200">
                                    <input
                                        :value="spec.compressive_strength"
                                        type="text"
                                        readonly
                                        placeholder="Calculated"
                                        class="w-36 mx-auto text-center px-2 py-1.5 text-xs font-bold text-gray-800 bg-gray-50 border border-gray-200 rounded cursor-not-allowed"
                                    />
                                </td>

                                <!-- Avg. Compressive Strength (merged across rows) -->
                                <td v-if="index === 0" :rowspan="form.specimens.length" class="p-3 border-r border-gray-200 align-middle">
                                    <div class="w-36 mx-auto py-2 px-3 bg-sky-50/60 border border-sky-200 rounded text-center">
                                        <span class="text-sm font-black text-sky-800">
                                            {{ avgCompressiveStrength || '—' }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Action (delete row) -->
                                <td class="p-2 text-center align-middle">
                                    <button
                                        v-if="form.specimens.length > 1"
                                        type="button"
                                        @click="removeSpecimenRow(index)"
                                        class="text-gray-400 hover:text-red-600 p-1 transition-colors"
                                        title="Remove specimen row"
                                    >
                                        <i class="pi pi-times text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Add Row Button at bottom right (plus icon) -->
                    <div class="bg-gray-50/60 border-t border-gray-200 p-2 flex justify-end">
                        <button
                            type="button"
                            @click="addSpecimenRow"
                            class="bg-white hover:bg-gray-100 text-gray-700 font-bold border border-gray-300 px-3 py-1 rounded text-xs flex items-center gap-1 shadow-sm transition-colors"
                            title="Add Specimen Cube"
                        >
                            <i class="pi pi-plus text-xs font-bold"></i>
                            <span>Add Cube</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Form Action Buttons (Save, Cancel) ── -->
            <div class="flex items-center justify-center gap-3 pt-6 border-t border-gray-200">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="bg-white hover:bg-gray-50 text-gray-800 font-semibold px-6 py-1.5 text-xs rounded border border-gray-300 shadow-sm transition-all flex items-center gap-1.5 min-w-[80px] justify-center"
                >
                    <i v-if="form.processing" class="pi pi-spin pi-spinner text-xs"></i>
                    <span>Save</span>
                </button>
                <button
                    type="button"
                    @click="cancelForm"
                    class="bg-white hover:bg-gray-50 text-gray-800 font-semibold px-6 py-1.5 text-xs rounded border border-gray-300 shadow-sm transition-all min-w-[80px] justify-center"
                >
                    Cancel
                </button>
            </div>
        </form>

        <!-- ── Invoice Lookup Dialog ── -->
        <Dialog
            v-model:visible="showInvoiceDialog"
            header="Select Invoice to Auto-Populate Test"
            :modal="true"
            class="w-full max-w-2xl"
        >
            <div class="p-4 space-y-4">
                <div class="flex gap-2">
                    <input
                        v-model="invoiceSearchQuery"
                        type="text"
                        placeholder="Search invoice number or customer name..."
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                        @keyup.enter="searchInvoices"
                    />
                    <button
                        type="button"
                        @click="searchInvoices"
                        class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 text-xs font-semibold rounded flex items-center gap-1.5"
                    >
                        <i v-if="isSearchingInvoices" class="pi pi-spin pi-spinner text-xs"></i>
                        <i v-else class="pi pi-search text-xs"></i>
                        <span>Search</span>
                    </button>
                </div>

                <div class="max-h-80 overflow-y-auto border border-gray-200 rounded divide-y divide-gray-100">
                    <div
                        v-for="inv in invoiceSearchResults"
                        :key="inv.id"
                        @click="selectInvoice(inv)"
                        class="p-3 hover:bg-sky-50 cursor-pointer transition-colors text-xs flex items-center justify-between"
                    >
                        <div>
                            <div class="font-bold text-sky-700">
                                {{ inv.full_number || inv.invoice_number }}
                            </div>
                            <div class="text-gray-800 font-medium mt-0.5">
                                {{ inv.account_name || 'No Customer Specified' }}
                            </div>
                            <div class="text-gray-400 text-[11px] mt-0.5">
                                Date: {{ inv.invoice_date }} | Grade: {{ inv.grade || 'Standard' }}
                            </div>
                        </div>
                        <div class="text-sky-600 font-semibold text-xs flex items-center gap-1">
                            <span>Select</span>
                            <i class="pi pi-chevron-right text-[10px]"></i>
                        </div>
                    </div>
                    <div v-if="invoiceSearchResults.length === 0" class="p-8 text-center text-gray-400 text-xs">
                        {{ isSearchingInvoices ? 'Searching invoices...' : 'No invoices found matching criteria.' }}
                    </div>
                </div>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
/* High quality form styling matching the uploaded screen */
input, select, textarea {
    outline: none;
}
</style>
