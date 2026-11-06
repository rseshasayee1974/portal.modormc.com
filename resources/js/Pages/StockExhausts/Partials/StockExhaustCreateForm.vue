<script setup lang="ts">
import StockExhaustPricing from './StockExhaustPricing.vue';
import { APP_LOCALE } from '@/Utils/locale';
import { entityToday } from '@/Utils/entityDateTime';
import { computed, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { validateStockExhaust } from '../validateStockExhaust';
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
    ledgers: any[];
    taxes: any[];
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
    unit_price: number;
    tax_id: number | null;
    discount_type: string;
    discount: number;
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
    is_tax_inclusive: false,
    status: 1,
    issued_date: null as any,
    ledger_id: null as number | null,
    lines: Array.from({ length: 5 }, () => ({
        unit_price: 0, tax_id: null, discount_type: 'amount', discount: 0,
        product_id: null,
        issue_date: entityToday(),
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
        if (line.quantity_issued !== null && line.quantity_issued !== undefined && String(line.quantity_issued).trim() !== '') {
            if (Number(line.quantity_issued) <= 0) {
                errs.quantity_issued = 'Quantity must be greater than 0.';
                valid = false;
            }
        }
        return errs;
    });

    return valid;
};
// ───────────────────────────────────────────────────────────────────────────

const addLine = () => {
    form.lines.push({
        unit_price: 0, tax_id: null, discount_type: 'amount', discount: 0,
        product_id: null,
        issue_date: entityToday(),
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
    if (form.processing) return;
    form.clearErrors();
    const errors = validateStockExhaust(form);
    const existingChecksPass = runFrontendValidation();
    if (Object.keys(errors).length) form.setError(errors as any);
    if (!existingChecksPass || Object.keys(errors).length) return;
    // Run frontend validation first

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
        form.name = 'Stock Exhaust - ' + entityToday();
    }
    form.transform((data) => ({
        ...data,
        lines: data.lines.filter(l => l.product_id !== null)
    })).post(route('stock-exhausts.store'), {
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
    <div class="min-w-0 w-full max-w-full bg-white dark:bg-slate-900 my-6 rounded-lg shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="min-w-0 p-3 sm:p-4">
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

            <form @submit.prevent="submitForm" class="min-w-0 max-w-full flex flex-col gap-6">
                <div v-if="form.hasErrors" role="alert" aria-live="polite"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-xs text-red-700">
                    <p class="font-bold mb-2">Please correct the following fields:</p>
                    <ul class="list-disc pl-4 space-y-1">
                        <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
                    </ul>
                </div>
                <!-- Header Info -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
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

                    <div class="col-span-12 md:col-span-3 field-group">
                        <label class="field-label required">Issued Date</label>
                        <BaseDatePicker
                            v-model="form.issued_date"
                            dateFormat="yy-mm-dd"
                            :class="['!w-full h-10', localErrors.issued_date ? 'p-invalid' : '']"
                            @date-select="localErrors.issued_date = ''"
                        />
                        <p v-if="localErrors.issued_date" class="mt-1 text-[10px] font-bold text-red-500">{{ localErrors.issued_date }}</p>
                    </div>

                    <div class="col-span-12 md:col-span-3 field-group">
                        <BaseInput v-model="form.bill_number" label="Bill Number" placeholder="Bill reference" :error="form.errors.bill_number" />
                    </div>

                    <div class="col-span-12 md:col-span-3 field-group">
                        <label class="field-label">Billed Date</label>
                        <BaseDatePicker v-model="form.billed_date" dateFormat="yy-mm-dd" class="!w-full h-10" />
                    </div>

                    <div class="col-span-12 md:col-span-3 field-group">
                        <label class="field-label">Voucher Status</label>
                        <BaseSelect v-model="form.status" :options="voucherStatuses" optionLabel="label" optionValue="value" />
                    </div>

                    <div class="col-span-12 md:col-span-3 field-group">
                        <label class="field-label">Invoice Billing Status</label>
                        <BaseSelect v-model="form.invoice_status" :options="invoiceStatuses" optionLabel="label" optionValue="value" />
                    </div>

                    <div class="col-span-12 md:col-span-6 field-group">
                        <label class="field-label">Ledger Account (Expenses/Purchases)</label>
                        <BaseSelect v-model="form.ledger_id" :options="ledgers" optionLabel="label" optionValue="value" filter placeholder="Select Ledger Account" :error="form.errors.ledger_id" />
                    </div>
                </div>

                <!-- Lines Form Section -->
                <div class="min-w-0">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div><h3 class="text-sm font-semibold">Issued Items</h3><p v-if="localErrors.lines" class="text-xs text-red-500">{{ localErrors.lines }}</p></div>
                        <button type="button" @click="addLine" class="flex items-center gap-2 rounded-lg bg-indigo-50 px-4 py-2 text-xs font-semibold text-indigo-600 hover:bg-indigo-100">
                            <PlusIcon class="h-4 w-4" /> Add Row
                        </button>
                    </div>
                    <StockExhaustPricing :form="form" :taxes="taxes" :products="products" :units="units"
                        :localErrors="localErrors" :getFilteredProducts="getFilteredProducts"
                        @product-change="onProductChange" @quantity-blur="validateQuantity" @remove="removeLine" />
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
