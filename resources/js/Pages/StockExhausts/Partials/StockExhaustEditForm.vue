<script setup lang="ts">
import StockExhaustPricing from './StockExhaustPricing.vue';
import { APP_LOCALE } from '@/Utils/locale';
import { entityToday } from '@/Utils/entityDateTime';
import { computed, watch, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { validateStockExhaust } from '../validateStockExhaust';
import Swal from 'sweetalert2';
import {
    PencilSquareIcon, PlusIcon, TrashIcon, CheckIcon, XMarkIcon
} from '@heroicons/vue/24/outline';
import DatePicker from 'primevue/datepicker';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';

const props = defineProps<{
    machines: any[];
    vendors: any[];
    products: any[];
    units: any[];
    voucher: any;
    ledgers: any[];
    taxes: any[];
}>();

const emit = defineEmits<{
    (e: 'success'): void;
    (e: 'cancel'): void;
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
    lines: [] as Line[]
});

// Watch for voucher prop changes to populate form
watch(() => props.voucher, (val) => {
    if (val) {
        form.partner_id = val.partner_id;
        form.name = val.name;
        form.bill_number = val.bill_number || '';
        form.billed_date = val.billed_date ? new Date(val.billed_date) : null;
        form.invoice_status = val.invoice_status;
        form.is_tax_inclusive = Boolean(val.is_tax_inclusive);
        form.status = val.status;
        form.issued_date = val.issued_date ? new Date(val.issued_date) : null;
        form.ledger_id = val.ledger_id;
        form.lines = val.lines.map((l: any) => {
            const selectedProd = props.products.find(p => p.value === l.product_id);
            return {
                ...l,
                unit_price: Number(l.unit_price || 0), tax_id: l.tax_id ? Number(l.tax_id) : null,
                discount_type: l.discount_type || 'amount', discount: Number(l.discount || 0),
                product_id: l.product_id ? Number(l.product_id) : null,
                issue_date: l.issue_date ? new Date(l.issue_date) : null,
                quantity_issued: l.quantity_issued ? Number(l.quantity_issued) : null,
                no_items_issued: Number(l.no_items_issued),
                changed_km: Number(l.changed_km),
                stock_qty: Number(selectedProd?.stock_qty || 0) + Number(l.quantity_issued || 0),
                unit_code: selectedProd ? selectedProd.unit_code : null
            };
        });
    }
}, { immediate: true });

// ── Frontend validation errors ─────────────────────────────────────────────
const localErrors = reactive<{
    partner_id: string;
    issued_date: string;
    lines: string;
    lineErrors: { product_id: string; quantity_issued: string; }[];
}>({ partner_id: '', issued_date: '', lines: '', lineErrors: [] });

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
        if (line.product_id === null) return errs;
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
// ──────────────────────────────────────────────────────────────────────────

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
        line.stock_qty = Number(selectedProd.stock_qty || 0) + (props.voucher?.lines || []).filter((old: any) => Number(old.product_id) === Number(line.product_id)).reduce((sum: number, old: any) => sum + Number(old.quantity_issued || 0), 0);
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

    form.transform((data) => ({
        ...data,
        lines: data.lines.filter(l => l.product_id !== null)
    })).put(route('stock-exhausts.update', props.voucher.id), {
        onSuccess: () => {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Voucher modified', showConfirmButton: false, timer: 1500 });
            emit('success');
        }
    });
};
</script>

<template>
    <div class="min-w-0 bg-white dark:bg-slate-900 rounded-lg border border-indigo-200 dark:border-slate-700 overflow-hidden">
        <div class="p-4 sm:p-6">
            <div class="flex items-center gap-4 mb-8">
                <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600">
                    <PencilSquareIcon class="w-6 h-6" />
                </div>
                <div>
                    <h2 class="text-sm font-black text-slate-800 dark:text-slate-100 uppercase tracking-widest">
                        Modify Stock Exhaust Entry
                    </h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">
                        Add voucher header details, dynamic items issued, and link to vehicles
                    </p>
                </div>
            </div>

            <form @submit.prevent="submitForm" class="flex flex-col gap-6">
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
                        <BaseInput :modelValue="voucher.reference_number || '—'" label="Reference No" disabled class="!bg-slate-50 font-mono" />
                    </div>
                    <div class="col-span-12 md:col-span-3 field-group">
                        <BaseInput v-model="form.name" label="Voucher Label *" placeholder="E.g. Exhaust Tyre replacements, General Spare parts" :error="form.errors.name" />
                    </div>
                    <div class="col-span-12 md:col-span-3 field-group">
                        <label class="field-label">Partner / Transporter *</label>
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
                        <BaseInput v-model="form.bill_number" label="Bill Number" placeholder="Bill reference" />
                    </div>

                    <div class="col-span-6 md:col-span-3 field-group">
                        <label class="field-label">Billed Date *</label>
                        <DatePicker v-model="form.billed_date" dateFormat="yy-mm-dd" class="!w-full h-10" />
                    </div>
                    <div class="col-span-6 md:col-span-3 field-group">
                        <label class="field-label">Issued Date *</label>
                        <DatePicker
                            v-model="form.issued_date"
                            dateFormat="yy-mm-dd"
                            :class="['!w-full h-10', localErrors.issued_date ? 'p-invalid' : '']"
                            @date-select="localErrors.issued_date = ''"
                        />
                        <p v-if="localErrors.issued_date" class="mt-1 text-[10px] font-bold text-red-500">{{ localErrors.issued_date }}</p>
                    </div>
                    <div class="col-span-6 md:col-span-3 field-group">
                        <label class="field-label">Voucher Status</label>
                        <BaseSelect v-model="form.status" :options="voucherStatuses" optionLabel="label" optionValue="value" />
                    </div>
                    <div class="col-span-6 md:col-span-3 field-group">
                        <label class="field-label">Invoice Billing Status</label>
                        <BaseSelect v-model="form.invoice_status" :options="invoiceStatuses" optionLabel="label" optionValue="value" />
                    </div>
                    <div class="col-span-12 md:col-span-6 field-group">
                        <label class="field-label">Ledger Account (Expenses/Purchases)</label>
                        <BaseSelect v-model="form.ledger_id" :options="ledgers" optionLabel="label" optionValue="value" filter placeholder="Select Ledger Account" :error="form.errors.ledger_id" />
                    </div>
                </div>

                <!-- Lines Form Section -->
                <div class="mt-8 border-t border-slate-100 dark:border-slate-800 pt-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-widest">Issued Items (Lines)</h3>
                            <p v-if="localErrors.lines" class="mt-1 text-[10px] font-bold text-red-500">{{ localErrors.lines }}</p>
                        </div>
                        <button type="button" @click="addLine" class="flex items-center gap-2 px-4 py-2 bg-indigo-50 dark:bg-indigo-900/20 hover:bg-indigo-100 text-indigo-600 rounded-lg text-[9px] font-black uppercase tracking-widest transition-colors">
                            <PlusIcon class="w-3.5 h-3.5" /> Add Row
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
                        Update Voucher
                    </button>
                    
                    <button
                        @click="emit('cancel')"
                        type="button"
                        class="flex items-center justify-center w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-slate-200 transition-all active:scale-95"
                    >
                        <XMarkIcon class="w-6 h-6" />
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
:deep(.p-datepicker-input) {
    @apply h-10 text-sm font-bold border-slate-200 rounded-md !bg-white;
}
</style>
