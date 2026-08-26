<script setup lang="ts">
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import { 
    ScaleIcon, 
    BanknotesIcon, 
    TruckIcon, 
    PrinterIcon, 
    DocumentDuplicateIcon, 
    TrashIcon,
    DocumentTextIcon,
    SparklesIcon,
    CheckCircleIcon,
    LockClosedIcon,
    CalendarIcon,
    UserIcon,
    QrCodeIcon
} from '@heroicons/vue/24/outline';
import { usePermissions } from '@/Composables/usePermissions';
import { watch, computed } from 'vue';
import Swal from 'sweetalert2';

const props = withDefaults(defineProps<{
    modelValue: any; // The whole form object
    uoms: any[];
    taxes: any[];
    drivers: any[];
    operators?: any[];
    sales_executives: any[];
    loading_sites: any[];
    unloading_sites: any[];
    trucks?: any[];
    transporters?: any[];
    personnel?: any[];
    payment_methods?: any[];
    sales_ledgers?: any[];
    errors: any;
    isReadOnly?: boolean;
    showInvoiceSection?: boolean;
    addPumpToTotal?: boolean;
}>(), {
    uoms: () => [],
    taxes: () => [],
    drivers: () => [],
    operators: () => [],
    loading_sites: () => [],
    unloading_sites: () => [],
    trucks: () => [],
    transporters: () => [],
    personnel: () => [],
    payment_methods: () => [],
    sales_ledgers: () => [],
    errors: () => ({}),
    isReadOnly: false,
    showInvoiceSection: false,
    addPumpToTotal: false
});

const emit = defineEmits(['update:modelValue', 'generateInvoice', 'generateEInvoice', 'deleteInvoice']);

const { can, isAdmin, isSuperAdmin, permissions, userRole } = usePermissions();

const canExportInvoice = computed(() => isAdmin.value );

const handleGenerateInvoice = () => {
    if (!props.modelValue.ledger_id) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Ledger',
            text: 'Please select a Sales Ledger before generating the invoice.',
            confirmButtonColor: '#4f46e5',
        });
        return;
    }
    emit('generateInvoice');
};

watch(() => props.modelValue.payment_mode, (newMode) => {
    if (newMode === 'cash') {
        if (!props.modelValue.payment.payment_method_id) {
            const cashMethod = props.payment_methods.find(m => 
                m.name.toLowerCase().includes('cash')
            );
            if (cashMethod) {
                props.modelValue.payment.payment_method_id = cashMethod.id;
            }
        }
    } else {
        props.modelValue.payment.amount = 0;
        props.modelValue.payment.payment_method_id = null;
    }
});

const isValidDate = (dateVal: any) => {
    if (!dateVal) return false;
    const d = new Date(dateVal);
    return d instanceof Date && !isNaN(d.getTime());
};

const formatDate = (dateVal: any) => {
    if (!isValidDate(dateVal)) return '---';
    return new Date(dateVal).toLocaleDateString('en-IN', { day: '2-digit', month: '2-digit', year: 'numeric' }).replace(/\//g, '-');
};

const formatTime = (dateVal: any) => {
    if (!isValidDate(dateVal)) return '';
    return new Date(dateVal).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
};

// console.log('jkghkjgk', props.modelValue);

</script>

<template>
    <div class="space-y-6">
        <div v-if="Object.keys(errors).length > 0" class="mx-5 my-2 p-4 rounded-xl bg-rose-50 border border-rose-100 text-rose-800 text-xs flex flex-col gap-1.5 shadow-sm">
            <div class="font-bold flex items-center gap-2 text-rose-700">
                <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Validation Failed
            </div>
            <ul class="list-disc list-inside mt-1 space-y-1 font-semibold text-rose-600">
                <li v-for="(error, field) in errors" :key="field">
                    {{ error }}
                </li>
            </ul>
        </div>

        <div class="bg-white px-2">
            <div class="flex items-center gap-2 border-b border-slate-100 mb-4">
                <TruckIcon class="h-5 w-5 text-indigo-500" />
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">1. Logistics & Delivery</h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <BaseSelect v-model="modelValue.truck_id" :options="trucks" optionLabel="registration" optionValue="id" label="Truck" filter showClear :disabled="true" :error="errors.truck_id" />
                <BaseSelect v-model="modelValue.transport_id" :options="transporters" optionLabel="legal_name" optionValue="id" label="Transporter" filter showClear :error="errors.transport_id" :disabled="isReadOnly" />
                <BaseSelect v-model="modelValue.driver_id" :options="drivers" optionLabel="label" optionValue="id" label="Driver" filter showClear :error="errors.driver_id" :disabled="isReadOnly" />
                <BaseSelect v-model="modelValue.operator_id" :options="operators" optionLabel="label" optionValue="id" label="Operator" filter showClear :error="errors.operator_id" :disabled="isReadOnly" />
                <BaseSelect v-model="modelValue.sales_executive_id" :options="sales_executives" optionLabel="label" optionValue="id" label="Sales Executive" filter showClear :error="errors.sales_executive_id" :disabled="isReadOnly" />
                <BaseSelect v-model="modelValue.unload_site_id" :options="unloading_sites" optionLabel="name" optionValue="id" label="Delivery Site" filter showClear :error="errors.unload_site_id" :disabled="isReadOnly" />
                <BaseInput v-model="modelValue.status.receiver_name" label="Receiver Name" :error="errors['status.receiver_name']" :disabled="isReadOnly" />
                <BaseInput v-model="modelValue.status.receive_mobile" label="Receiver Mobile" :error="errors['status.receive_mobile']" :disabled="isReadOnly" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <BaseInput v-model="modelValue.status.invoice_number" label="Invoice #" :disabled="true" v-if="modelValue.status.dispatch_status"/>
                <BaseDatePicker v-model="modelValue.status.invoice_date" label="Invoice Date" fluid :disabled="true" v-if="modelValue.status.dispatch_status" :error="errors.invoice_date" />
            </div>
        </div>

        <div class="bg-white px-2">
            <div class="flex items-center gap-2 border-b border-slate-100 pb-1 mb-4">
                <ScaleIcon class="h-5 w-5 text-indigo-500" />
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">2. Pricing & Quantities</h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <BaseSelect v-model="modelValue.uom_id" :options="uoms" optionLabel="unit_code" optionValue="id" label="Unit of Measure" filter showClear :error="errors.uom_id" :disabled="isReadOnly" />
                
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Load Rate</label>
                        <div class="flex items-center gap-1.5 cursor-pointer">
                            <span class="text-[9px] font-bold text-slate-500 uppercase tracking-tight">Tax Incl.</span>
                            <input type="checkbox" v-model="modelValue.status.is_tax_inclusive" id="is_tax_inclusive_dispatch" :disabled="isReadOnly" class="peer hidden" />
                            <label for="is_tax_inclusive_dispatch" :class="isReadOnly ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'" class="relative w-7 h-4 bg-slate-200 peer-checked:bg-indigo-600 rounded-full transition-colors duration-200 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-[12px]"></label>
                        </div>
                    </div>
                    <BaseInputNumber v-model="modelValue.financials.load_rate" :minFractionDigits="2" :error="errors['financials.load_rate']" :disabled="isReadOnly" />
                </div>

                <BaseSelect v-model="modelValue.financials.load_tax_id" :options="taxes" optionLabel="tax_name" optionValue="id" label="Tax Group" filter showClear :error="errors['financials.load_tax_id']" :disabled="isReadOnly" />
                <BaseSelect v-model="modelValue.payment_mode" :options="[{label: 'Cash', value: 'cash'}, {label: 'Credit', value: 'credit'}]" optionLabel="label" optionValue="value" label="Payment Mode" :error="errors.payment_mode" :disabled="isReadOnly" />
                <BaseInput v-model="modelValue.dispatch_reference" label="Site Ref" :error="errors.dispatch_reference" :disabled="isReadOnly" class="col-span-1 sm:col-span-2 md:col-span-1" />
            </div>
        </div>

        <div class="bg-white px-2">
            <div class="flex items-center gap-2 border-b border-slate-100 pb-1">
                <BanknotesIcon class="h-5 w-5 text-indigo-500" />
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">3. Financials & Invoice</h3>
            </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <BaseInputNumber v-model="modelValue.financials.pump_charges" label="Pump Charges" :minFractionDigits="2" :error="errors['financials.pump_charges']" :disabled="isReadOnly" />
            <BaseInputNumber v-model="modelValue.financials.pass_amount" label="Pass Amount" :minFractionDigits="2" :error="errors['financials.pass_amount']" :disabled="isReadOnly" />
            <BaseInputNumber v-model="modelValue.financials.discount_amount" label="Discount" :minFractionDigits="2" :error="errors['financials.discount_amount']" :disabled="isReadOnly" />
            <BaseInputNumber v-model="modelValue.financials.transport_expenses" label="Transport Exp." :minFractionDigits="2" :error="errors['financials.transport_expenses']" :disabled="isReadOnly" />
            <BaseInputNumber v-model="modelValue.financials.adjustment_amount" label="Adjustment" :minFractionDigits="2" :error="errors['financials.adjustment_amount']" :disabled="isReadOnly" />
            <BaseInputNumber v-model="modelValue.financials.round_off" label="Round Off" :minFractionDigits="2" :min="0" :max="99" :error="errors['financials.round_off']" :disabled="isReadOnly" />
        </div>

        <div v-show="modelValue.payment_mode === 'cash'" class="p-2 space-y-2">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-emerald-500">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                </svg>
                <div>
                    <h4 class="text-[11px] font-black uppercase tracking-widest text-emerald-600">Immediate Payment Collection</h4>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <BaseSelect 
                    v-model="modelValue.payment.payment_method_id" 
                    label="Payment Method" 
                    :options="payment_methods" 
                    optionLabel="name" 
                    optionValue="id" 
                    placeholder="Select Method"
                    filter
                    :error="errors['payment.payment_method_id']"
                    :disabled="isReadOnly"
                />
            </div>
        </div>

        <!-- Invoice Management Section -->
        <div v-if="(modelValue.id || modelValue.dispatch_id) && showInvoiceSection" class="mt-4 mb-2">
            <div v-if="modelValue.generate_invoice === true">
                <!-- Case A: Invoice Not Yet Generated -->
                <div 
                    v-if="modelValue.status.invoice_status !== 1" 
                    class="p-4 bg-gradient-to-r from-slate-50 to-indigo-50/30 border border-indigo-100/80 rounded-2xl shadow-xs space-y-4"
                >
                    <div class="flex items-center justify-between gap-2 border-b border-indigo-100/60 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="p-1.5 rounded-lg bg-indigo-100 text-indigo-600">
                                <DocumentTextIcon class="h-4 w-4" />
                            </div>
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-900 leading-tight">Invoice Generation</h4>
                                <p class="text-[11px] text-slate-500 font-medium">Select sales ledger and date to issue invoice</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200/60">
                            Pending Generation
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                        <div class="md:col-span-6 lg:col-span-5">
                            <BaseSelect 
                                v-model="modelValue.ledger_id" 
                                :options="sales_ledgers" 
                                optionLabel="label" 
                                optionValue="value" 
                                label="Sales Ledger" 
                                filter 
                                placeholder="Select ..." 
                                :error="errors.ledger_id" 
                                :disabled="isReadOnly"
                            />
                        </div>
                        <div class="md:col-span-6 lg:col-span-4">
                            <BaseDatePicker 
                                v-model="modelValue.invoice_date" 
                                label="Invoice Date" 
                                :error="errors.invoice_date" 
                                :disabled="isReadOnly"
                            />
                        </div>
                        <div class="md:col-span-12 lg:col-span-3 flex justify-start lg:justify-end">
                            <button 
                                type="button"
                                @click="handleGenerateInvoice"
                                :disabled="isReadOnly"
                                class="w-full lg:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:scale-[0.98] transition-all text-white text-xs font-bold uppercase tracking-wider rounded-xl shadow-sm hover:shadow disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                            >
                                <SparklesIcon class="h-4 w-4" />
                                <span>Generate Invoice</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Case B: Invoice Generated & Linked -->
                <div 
                    v-if="modelValue.status.invoice_status == 1" 
                    class="p-4 bg-white border border-slate-200/80 hover:border-indigo-200 transition-colors rounded-2xl shadow-xs space-y-3"
                >
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <!-- Left: Status & Details -->
                        <div class="flex flex-wrap items-center gap-4 sm:gap-6">
                            <!-- Status Badge -->
                            <div class="flex items-center gap-2.5 bg-emerald-50/80 border border-emerald-100 px-3 py-1.5 rounded-xl">
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500 text-white shadow-xs">
                                    <CheckCircleIcon class="h-4 w-4 stroke-[2.5]" />
                                </div>
                                <div>
                                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800 leading-none block">Invoice Linked</span>
                                    <span class="text-[9px] text-emerald-600 font-semibold uppercase tracking-wider">Billing Completed</span>
                                </div>
                            </div>

                            <div class="h-8 w-px bg-slate-200 hidden sm:block"></div>

                            <!-- Metadata Cards -->
                            <div class="flex flex-wrap items-center gap-4 sm:gap-6 text-xs">
                                <div>
                                    <span class="text-[10px] block text-slate-400 font-semibold uppercase tracking-wider">Invoice No</span>
                                    <span class="font-mono font-bold text-slate-800 text-sm tracking-tight">{{ modelValue.status.invoice?.full_number || '---' }}</span>
                                </div>
                                <div class="h-6 w-px bg-slate-100 hidden sm:block"></div>
                                <div>
                                    <span class="text-[10px] block text-slate-400 font-semibold uppercase tracking-wider">Invoice Date</span>
                                    <div class="flex items-center gap-1 text-slate-700 font-medium">
                                        <CalendarIcon class="h-3.5 w-3.5 text-slate-400" />
                                        <span>{{ formatDate(modelValue.status.invoice_date) }}</span>
                                        <!-- <span v-if="formatTime(modelValue.status.invoice_date)" class="text-slate-400 text-[11px]">({{ formatTime(modelValue.status.invoice_date) }})</span> -->
                                    </div>
                                </div>
                                <div class="h-6 w-px bg-slate-100 hidden sm:block"></div>
                                <div>
                                    <span class="text-[10px] block text-slate-400 font-semibold uppercase tracking-wider">Created By</span>
                                    <div class="flex items-center gap-1">
                                        <UserIcon class="h-3.5 w-3.5 text-indigo-400" />
                                        <span class="text-indigo-600 font-semibold text-[11px] truncate max-w-[140px]" :title="modelValue.status.invoice?.creator?.email || modelValue.status.invoice?.creator?.username">
                                            {{ modelValue.status.invoice?.creator?.email || modelValue.status.invoice?.creator?.username || 'System' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="h-6 w-px bg-slate-100 hidden sm:block" v-if="modelValue.status.invoice?.einvoice_status === 'generated' || modelValue.status.invoice?.einvoice_irn"></div>
                                <div v-if="modelValue.status.invoice?.einvoice_status === 'generated' || modelValue.status.invoice?.einvoice_irn">
                                    <span class="text-[10px] block text-purple-500 font-semibold uppercase tracking-wider">E-Invoice</span>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                            <QrCodeIcon class="h-3 w-3 text-purple-600" />
                                            <span>IRN Linked</span>
                                        </span>
                                        <span v-if="modelValue.status.invoice?.einvoice_ack_no" class="text-[11px] text-slate-500 font-mono" :title="modelValue.status.invoice?.einvoice_irn">
                                            #{{ modelValue.status.invoice.einvoice_ack_no }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Action Buttons -->
                        <div class="flex items-center gap-2 w-full lg:w-auto justify-end border-t lg:border-t-0 pt-3 lg:pt-0 border-slate-100 flex-wrap">
                            <!-- Generate E-Invoice Button (Visible when Invoice is generated & linked, but E-Invoice is not yet generated) -->
                            <button 
                                v-if="modelValue.status.invoice?.id && modelValue.status.invoice?.einvoice_status !== 'generated' && !modelValue.status.invoice?.einvoice_irn"
                                type="button"
                                @click="$emit('generateEInvoice', modelValue.status.invoice.id)"
                                :disabled="isReadOnly"
                                :class="[isReadOnly ? 'opacity-50 cursor-not-allowed' : 'hover:bg-purple-700 active:scale-[0.98] cursor-pointer']"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-purple-600 text-white text-[11px] font-bold uppercase tracking-wider rounded-xl transition-all shadow-xs"
                            >
                                <SparklesIcon class="h-4 w-4 text-purple-200" />
                                <span>Generate E-Invoice</span>
                            </button>

                            <!-- Print E-Invoice Button (When E-Invoice IRN is generated) -->
                            <a 
                                v-if="modelValue.status.invoice?.encrypted_id && (modelValue.status.invoice?.einvoice_status === 'generated' || modelValue.status.invoice?.einvoice_irn)"
                                :href="route('print.document', { module: 'invoices', id: modelValue.status.invoice.encrypted_id, action: 'view' })" 
                                target="_blank"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-purple-50 hover:bg-purple-100 active:scale-[0.98] text-purple-700 border border-purple-200/80 text-[11px] font-bold uppercase tracking-wider rounded-xl transition-all shadow-xs"
                            >
                                <QrCodeIcon class="h-4 w-4 text-purple-600" />
                                <span>E-Invoice Print</span>
                            </a>

                            <template v-if="modelValue.status.invoice?.encrypted_id">
                                <a 
                                    v-if="canExportInvoice"
                                    :href="route('print.document', { module: 'invoices', id: modelValue.status.invoice.encrypted_id, action: 'download', force: 'original' })" 
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 active:scale-[0.98] text-emerald-700 border border-emerald-200/80 text-[11px] font-bold uppercase tracking-wider rounded-xl transition-all shadow-xs"
                                >
                                    <PrinterIcon class="h-4 w-4 text-emerald-600" />
                                    <span>Print</span>
                                </a>
                                <a 
                                    v-else
                                    :href="modelValue.status.invoice?.is_duplicate 
                                        ? route('print.document', { module: 'invoices', id: modelValue.status.invoice.encrypted_id, action: 'download', force: 'duplicate' })
                                        : route('print.document', { module: 'invoices', id: modelValue.status.invoice.encrypted_id, action: 'view' })" 
                                    target="_blank"
                                    @click="modelValue.status.invoice.is_duplicate = 1"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 active:scale-[0.98] text-emerald-700 border border-emerald-200/80 text-[11px] font-bold uppercase tracking-wider rounded-xl transition-all shadow-xs"
                                >
                                    <DocumentDuplicateIcon v-if="modelValue.status.invoice?.is_duplicate" class="h-4 w-4 text-emerald-600" />
                                    <PrinterIcon v-else class="h-4 w-4 text-emerald-600" />
                                    <span>Print</span>
                                </a>
                            </template>
                            <button 
                                type="button"
                                @click="$emit('deleteInvoice')"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border text-[11px] font-bold uppercase tracking-wider rounded-xl transition-all shadow-xs"
                            >
                                <TrashIcon class="h-4 w-4" />
                                <span>Delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="mt-4 flex items-center justify-center gap-2.5 p-4 bg-slate-50 border border-dashed border-slate-200 rounded-2xl text-center text-xs text-slate-500 font-medium">
            <LockClosedIcon class="w-4 h-4 text-slate-400 flex-shrink-0" />
            <span>Save dispatch to enable invoice processing</span>
        </div>
        </div>
    </div>
</template>