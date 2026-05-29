<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import {
    WrenchScrewdriverIcon, PlusIcon, TrashIcon, CheckIcon
} from '@heroicons/vue/24/outline';
import DatePicker from 'primevue/datepicker';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';

const props = defineProps<{
    machines: any[];
    vendors: any[];
    products: any[];
    units: any[];
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const machineOptions = computed(() => props.machines.map(m => ({ label: m.registration, value: m.id })));
const vendorOptions = computed(() => props.vendors.map(v => ({ label: v.legal_name, value: v.id })));

const voucherStatuses = [
    { label: 'Draft', value: 0 },
    { label: 'Approved', value: 1 },
    { label: 'Cancelled', value: 2 }
];

const invoiceStatuses = [
    { label: 'Unbilled', value: 0 },
    { label: 'Partially Billed', value: 1 },
    { label: 'Fully Billed', value: 2 }
];

interface Line {
    id?: number;
    product_id: number | null;
    issue_date: any;
    quantity_issued: number | null;
    no_items_issued: number;
    units: string;
    issued_to: string | null;
    vehicle_no: number | null;
    changed_km: number;
    notes: string | null;
    stock_qty?: number | null;
    unit_code?: string | null;
}

const form = useForm({
    partner_id: null as number | null,
    name: '',
    bill_number: '',
    billed_date: null as any,
    invoice_status: 0,
    status: 1,
    issued_date: null as any,
    lines: Array.from({ length: 5 }, () => ({
        product_id: null,
        issue_date: new Date(),
        quantity_issued: null,
        no_items_issued: 1,
        units: 'pcs',
        issued_to: null,
        vehicle_no: null,
        changed_km: 0,
        notes: '',
        stock_qty: null,
        unit_code: null
    })) as Line[]
});

// ── Frontend validation errors ──────────────────────────────────────────────
const localErrors = reactive<{
    partner_id: string;
    issued_date: string;
    lines: string;
    lineErrors: { product_id: string; quantity_issued: string; }[];
}>({ partner_id: '', issued_date: '', lines: '', lineErrors: [] });

const clearLineErrors = (index: number) => {
    if (localErrors.lineErrors[index]) {
        localErrors.lineErrors[index].product_id = '';
        localErrors.lineErrors[index].quantity_issued = '';
    }
};

const runFrontendValidation = (): boolean => {
    let valid = true;

    // Header: Partner
    if (!form.partner_id) {
        localErrors.partner_id = 'Partner is required.';
        valid = false;
    } else {
        localErrors.partner_id = '';
    }

    // Header: Issued Date
    if (!form.issued_date) {
        localErrors.issued_date = 'Issued date is required.';
        valid = false;
    } else {
        localErrors.issued_date = '';
    }

    // Lines: at least one
    const activeLines = form.lines.filter(l => l.product_id !== null);
    if (activeLines.length === 0) {
        localErrors.lines = 'At least one issued item line with a product must be added.';
        valid = false;
    } else {
        localErrors.lines = '';
    }

    // Per-line validation
    localErrors.lineErrors = form.lines.map((line) => {
        const errs = { product_id: '', quantity_issued: '' };
        if (line.product_id === null) return errs; // skip blank lines silently at row level
        if (!line.product_id) {
            errs.product_id = 'Product is required.';
            valid = false;
        }
        if (line.quantity_issued === null || line.quantity_issued === undefined || String(line.quantity_issued).trim() === '') {
            errs.quantity_issued = 'Quantity is required.';
            valid = false;
        } else if (Number(line.quantity_issued) <= 0) {
            errs.quantity_issued = 'Quantity must be greater than 0.';
            valid = false;
        }
        return errs;
    });

    return valid;
};
// ───────────────────────────────────────────────────────────────────────────

const addLine = () => {
    form.lines.push({
        product_id: null,
        issue_date: new Date(),
        quantity_issued: null,
        no_items_issued: 1,
        units: 'pcs',
        issued_to: null,
        vehicle_no: null,
        changed_km: 0,
        notes: '',
        stock_qty: null,
        unit_code: null
    });
    localErrors.lineErrors.push({ product_id: '', quantity_issued: '' });
};

const removeLine = (index: number) => {
    form.lines.splice(index, 1);
    localErrors.lineErrors.splice(index, 1);
};

const onProductChange = (index: number) => {
    const line = form.lines[index];
    const selectedProd = props.products.find(p => p.value === line.product_id);
    if (selectedProd) {
        line.quantity_issued = 1;
        line.units = selectedProd.unit_id;
        line.stock_qty = selectedProd.stock_qty;
        line.unit_code = selectedProd.unit_code;
    } else {
        line.stock_qty = null;
        line.unit_code = null;
    }
    // Clear product error on change
    if (localErrors.lineErrors[index]) localErrors.lineErrors[index].product_id = '';
    localErrors.lines = '';
};

const getFilteredProducts = (currentIndex: number) => {
    const selectedIds = form.lines
        .map((line, idx) => idx !== currentIndex ? line.product_id : null)
        .filter(id => id !== null);
    return props.products.filter(p => !selectedIds.includes(p.value));
};

const validateQuantity = (index: number) => {
    const line = form.lines[index];
    if (line.product_id && line.quantity_issued !== null) {
        const qty = Number(line.quantity_issued);
        const stock = line.stock_qty !== null && line.stock_qty !== undefined ? Number(line.stock_qty) : 0;
        if (qty > stock) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'warning',
                title: `Quantity reset to 1 (max stock: ${stock})`,
                showConfirmButton: false,
                timer: 2000
            });
            line.quantity_issued = 1;
        }
    }
    // Clear quantity error on blur
    if (localErrors.lineErrors[index]) localErrors.lineErrors[index].quantity_issued = '';
};

const submitForm = () => {
    // Run frontend validation first
    if (!runFrontendValidation()) return;

    // Check if any active line has quantity_issued exceeding stock_qty
    for (let i = 0; i < form.lines.length; i++) {
        const line = form.lines[i];
        if (line.product_id && line.quantity_issued !== null) {
            const qty = Number(line.quantity_issued);
            const stock = line.stock_qty !== null && line.stock_qty !== undefined ? Number(line.stock_qty) : 0;
            if (qty > stock) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Quantity',
                    text: `Quantity issued for row ${i + 1} exceeds available stock of ${stock} units.`,
                    confirmButtonColor: '#4f46e5',
                    customClass: { popup: 'rounded-3xl' }
                });
                return;
            }
        }
    }

    if (!form.name) {
        form.name = 'Stock Exhaust - ' + new Date().toISOString().slice(0, 10);
    }
    form.post(route('stock-exhausts.store'), {
        onSuccess: () => {
            form.reset();
            localErrors.partner_id = '';
            localErrors.issued_date = '';
            localErrors.lines = '';
            localErrors.lineErrors = [];
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Voucher registered', showConfirmButton: false, timer: 1500 });
            emit('success');
        }
    });
};
</script>

<template>
    <div class="bg-white dark:bg-slate-900 my-6 rounded-lg shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="p-8">
            <div class="flex items-center gap-4 mb-8">
                <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600">
                    <WrenchScrewdriverIcon class="w-6 h-6" />
                </div>
                <div>
                    <h2 class="text-sm font-black text-slate-800 dark:text-slate-100 uppercase tracking-widest">
                        Log Stock Exhaust
                    </h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">
                        Add voucher header details, dynamic items issued, and link to vehicles
                    </p>
                </div>
            </div>

            <form @submit.prevent="submitForm" class="flex flex-col gap-6">
                <!-- Header Info -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                    <!-- <div class="col-span-12 md:col-span-4 field-group">
                        <BaseInput v-model="form.name" label="Voucher Label *" placeholder="E.g. Exhaust Tyre replacements, General Spare parts" :error="form.errors.name" />
                    </div> -->
                    <div class="col-span-12 md:col-span-3 field-group">
                        <label class="field-label required">Partner</label>
                        <BaseSelect
                            v-model="form.partner_id"
                            :options="vendorOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Partner"
                            :error="form.errors.partner_id || localErrors.partner_id"
                            @change="localErrors.partner_id = ''"
                        />
                        <p v-if="localErrors.partner_id" class="mt-1 text-[10px] font-bold text-red-500">{{ localErrors.partner_id }}</p>
                    </div>

                    <!-- <div class="col-span-6 md:col-span-3 field-group">
                        <label class="field-label required">Billed Date</label>
                        <BaseDatePicker v-model="form.billed_date" dateFormat="yy-mm-dd" class="!w-full h-10" />
                    </div> -->
                    <div class="col-span-6 md:col-span-3 field-group">
                        <label class="field-label required">Issued Date</label>
                        <BaseDatePicker
                            v-model="form.issued_date"
                            dateFormat="yy-mm-dd"
                            :class="['!w-full h-10', localErrors.issued_date ? 'p-invalid' : '']"
                            @date-select="localErrors.issued_date = ''"
                        />
                        <p v-if="localErrors.issued_date" class="mt-1 text-[10px] font-bold text-red-500">{{ localErrors.issued_date }}</p>
                    </div>
                     <!-- <div class="col-span-12 md:col-span-3 field-group">
                        <BaseInput v-model="form.bill_number" label="Bill Number" placeholder="Bill reference" />
                    </div> -->
                    <!-- <div class="col-span-6 md:col-span-3 field-group">
                        <label class="field-label">Voucher Status</label>
                        <BaseSelect v-model="form.status" :options="voucherStatuses" optionLabel="label" optionValue="value" />
                    </div>
                    <div class="col-span-6 md:col-span-3 field-group">
                        <label class="field-label">Invoice Billing Status</label>
                        <BaseSelect v-model="form.invoice_status" :options="invoiceStatuses" optionLabel="label" optionValue="value" />
                    </div> -->
                </div>

                <!-- Lines Form Section -->
                <div class="">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-widest">Issued Items (Lines)</h3>
                            <p v-if="localErrors.lines" class="mt-1 text-[10px] font-bold text-red-500">{{ localErrors.lines }}</p>
                        </div>
                        <button type="button" @click="addLine" class="flex items-center gap-2 px-4 py-2 bg-indigo-50 dark:bg-indigo-900/20 hover:bg-indigo-100 text-indigo-600 rounded-lg text-[9px] font-black uppercase tracking-widest transition-colors">
                            <PlusIcon class="w-3.5 h-3.5" /> Add Row
                        </button>
                    </div>

                    <div v-if="form.lines.length === 0" class="py-12 border-2 border-dashed border-slate-100 dark:border-slate-800 rounded-xl flex flex-col items-center justify-center bg-slate-50/20">
                        <WrenchScrewdriverIcon class="w-8 h-8 text-slate-200 dark:text-slate-700 mb-2" />
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">No issued lines added</p>
                    </div>

                    <div v-else class="overflow-x-auto border border-slate-100 dark:border-slate-800 rounded-xl">
                        <table class="w-full text-left border-collapse min-w-full">
                            <thead class="bg-slate-50 dark:bg-slate-900/50">
                                <tr class="text-[9px] font-black uppercase text-slate-400 tracking-wider border-b border-slate-100 dark:border-slate-800">
                                    <th class="py-3 px-3 w-[150px]">Product *</th>
                                    <th class="py-3 px-3 w-[100px]">Qty Issued</th>
                                    <th class="py-3 px-3 w-[70px]">Units *</th>
                                    <!-- <th class="py-3 px-3 w-[130px]">Changed KM *</th> -->
                                    <th class="py-3 px-3 w-[200px]">Notes</th>
                                    <th class="py-3 px-3 w-[60px] text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(line, index) in form.lines"
                                    :key="index"
                                    :class="[
                                        'border-b border-slate-50 dark:border-slate-800/50 hover:bg-slate-50/30',
                                        (localErrors.lineErrors[index]?.product_id || localErrors.lineErrors[index]?.quantity_issued)
                                            ? 'bg-red-50/30 dark:bg-red-900/10'
                                            : ''
                                    ]"
                                >
                                    <td class="py-2 px-3">
                                        <BaseSelect
                                            v-model="line.product_id"
                                            :options="getFilteredProducts(index)"
                                            optionLabel="label"
                                            optionValue="value"
                                            placeholder="Select"
                                            class="!h-9 text-xs"
                                            @change="onProductChange(index)"
                                        />
                                        <span v-if="line.stock_qty !== null && line.stock_qty !== undefined" class="text-[10px] text-emerald-600 font-bold block mt-1">
                                            Stock: {{ Number(line.stock_qty).toLocaleString() }} {{ line.unit_code || '' }}
                                        </span>
                                        <span v-if="localErrors.lineErrors[index]?.product_id" class="text-[9px] text-red-500 font-bold block mt-1">
                                            {{ localErrors.lineErrors[index].product_id }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-3">
                                        <BaseInput
                                            v-model="line.quantity_issued"
                                            placeholder="0.00"
                                            class="!h-9 text-xs"
                                            @blur="validateQuantity(index)"
                                            @input="localErrors.lineErrors[index] && (localErrors.lineErrors[index].quantity_issued = '')"
                                        />
                                        <span v-if="line.product_id && line.quantity_issued !== null && Number(line.quantity_issued) > (line.stock_qty || 0)" class="text-[9px] text-red-500 font-bold block mt-1">
                                            Exceeds stock
                                        </span>
                                        <span v-else-if="localErrors.lineErrors[index]?.quantity_issued" class="text-[9px] text-red-500 font-bold block mt-1">
                                            {{ localErrors.lineErrors[index].quantity_issued }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-3">
                                        <BaseSelect v-model="line.units" :options="units" optionLabel="unit_code" optionValue="id" placeholder="Select" class="!h-9 text-xs" />
                                    </td>
                                    <!-- <td class="py-2 px-3">
                                        <BaseInput v-model="line.changed_km" placeholder="0" class="!h-9 text-xs" />
                                    </td> -->
                                    <td class="py-2 px-3">
                                        <BaseInput v-model="line.notes" placeholder="Remarks" class="!h-9 text-xs" />
                                    </td>
                                    <td class="py-2 px-3 text-right">
                                        <button type="button" @click="removeLine(index)" class="p-2 text-red-600 hover:text-red-500 transition-colors">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 mt-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex items-center justify-center gap-3 h-12 px-8 rounded-2xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-black text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-indigo-100 dark:shadow-none transition-all duration-200 active:scale-95"
                    >
                        <CheckIcon v-if="!form.processing" class="w-4 h-4 stroke-[3px]" />
                        <span v-else class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                        Register Voucher
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
