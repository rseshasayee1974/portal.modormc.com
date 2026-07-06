<script setup lang="ts">
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import { ScaleIcon, BanknotesIcon, TruckIcon, PrinterIcon, DocumentDuplicateIcon } from '@heroicons/vue/24/outline';
import { usePermissions } from '@/Composables/usePermissions';

const props = withDefaults(defineProps<{
    modelValue: any; // The whole form object
    uoms: any[];
    taxes: any[];
    drivers:any[];
    sales_executives:any[];
    loading_sites: any[];
    unloading_sites: any[];
    trucks?: any[];
    transporters?: any[];
    personnel?: any[];
    payment_methods?: any[];
    sales_ledgers?: any[];
    errors: any;
    isReadOnly?: boolean;
}>(), {
    uoms: () => [],
    taxes: () => [],
    loading_sites: () => [],
    unloading_sites: () => [],
    trucks: () => [],
    transporters: () => [],
    personnel: () => [],
    payment_methods: () => [],
    sales_ledgers: () => [],
    errors: () => ({}),
    isReadOnly: false
});
// console.log(props.sales_ledgers)
const emit = defineEmits(['update:modelValue', 'generateInvoice' , 'deleteInvoice']);

const { can, isAdmin, isSuperAdmin, permissions, userRole } = usePermissions();
console.log('Current User Role:', userRole.value);
console.log('Current User Permissions:', permissions.value);

import { watch, computed } from 'vue';
import Swal from 'sweetalert2';

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
</script>

<template>
    <div class="space-y-6">
        <!-- Validation Errors Alert -->
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

        <!-- 1. Logistics & Delivery Section -->
        <div class="bg-white">
            <div class="flex items-center gap-2 border-b border-slate-100">
                <TruckIcon class="h-5 w-5 text-indigo-500" />
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">1. Logistics & Delivery</h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <BaseSelect v-model="modelValue.truck_id" :options="trucks" optionLabel="registration" optionValue="id" label="Truck" filter showClear :disabled="true" :error="errors.truck_id" />
                <BaseSelect v-model="modelValue.transport_id" :options="transporters" optionLabel="legal_name" optionValue="id" label="Transporter" filter showClear :error="errors.transport_id" :disabled="isReadOnly" />
                <BaseSelect v-model="modelValue.driver_id" :options="drivers" optionLabel="label" optionValue="id" label="Driver" filter showClear :error="errors.driver_id" :disabled="isReadOnly" />
                <BaseSelect v-model="modelValue.sales_executive_id" :options="sales_executives" optionLabel="label" optionValue="id" label="Sales Executive" filter showClear :error="errors.sales_executive_id" :disabled="isReadOnly" />
                <BaseSelect v-model="modelValue.unload_site_id" :options="unloading_sites" optionLabel="name" optionValue="id" label="Delivery Site" filter showClear :error="errors.unload_site_id" :disabled="isReadOnly" />
                <BaseInput v-model="modelValue.status.receiver_name" label="Receiver Name" :error="errors['status.receiver_name']" :disabled="isReadOnly" />
                <BaseInput v-model="modelValue.status.receive_mobile" label="Receiver Mobile" :error="errors['status.receive_mobile']" :disabled="isReadOnly" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <BaseInput v-model="modelValue.status.invoice_number" label="Invoice #" :disabled="true" v-if="modelValue.status.dispatch_status"/>
                <BaseDatePicker v-model="modelValue.status.invoice_date" label="Invoice Date" fluid :disabled="true" v-if="modelValue.status.dispatch_status" :error="errors.invoice_date" />
            </div>
        </div>

        <!-- 2. Pricing & Quantities Section -->
        <div class="bg-white">
            <div class="flex items-center gap-2 border-b border-slate-100 pb-1">
                <ScaleIcon class="h-5 w-5 text-indigo-500" />
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">2. Pricing & Quantities</h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <BaseSelect v-model="modelValue.uom_id" :options="uoms" optionLabel="unit_code" optionValue="id" label="Unit of Measure" filter showClear :error="errors.uom_id" :disabled="isReadOnly" />
                <BaseInputNumber v-model="modelValue.financials.load_rate" label="Load Rate" :minFractionDigits="2" :error="errors['financials.load_rate']" :disabled="isReadOnly" />
                <BaseSelect v-model="modelValue.financials.load_tax_id" :options="taxes" optionLabel="tax_name" optionValue="id" label="Tax Group" filter showClear :error="errors['financials.load_tax_id']" :disabled="isReadOnly" />
                <BaseSelect v-model="modelValue.payment_mode" :options="[{label: 'Cash', value: 'cash'}, {label: 'Credit', value: 'credit'}]" optionLabel="label" optionValue="value" label="Payment Mode" :error="errors.payment_mode" :disabled="isReadOnly" />
                <BaseInput v-model="modelValue.dispatch_reference" label="Site Ref" :error="errors.dispatch_reference" :disabled="isReadOnly" class="col-span-1 sm:col-span-2 md:col-span-1" />
            </div>
        </div>

        <!-- 3. Financials & Invoice Section -->
        <div class="bg-white">
            <div class="flex items-center gap-2 border-b border-slate-100">
                <BanknotesIcon class="h-5 w-5 text-indigo-500" />
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">3. Financials & Invoice</h3>
            </div>

            <!-- Adjustments Grid -->
               
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <BaseInputNumber v-model="modelValue.financials.pass_amount" label="Pass Amount" :minFractionDigits="2" :error="errors['financials.pass_amount']" :disabled="isReadOnly" />
                <BaseInputNumber v-model="modelValue.financials.discount_amount" label="Discount" :minFractionDigits="2" :error="errors['financials.discount_amount']" :disabled="isReadOnly" />
                <BaseInputNumber v-model="modelValue.financials.transport_expenses" label="Transport Exp." :minFractionDigits="2" :error="errors['financials.transport_expenses']" :disabled="isReadOnly" />
                <BaseInputNumber v-model="modelValue.financials.adjustment_amount" label="Adjustment" :minFractionDigits="2" :error="errors['financials.adjustment_amount']" :disabled="isReadOnly" />
                <BaseInputNumber v-model="modelValue.financials.round_off" label="Round Off" :minFractionDigits="2" :min="0" :max="99" :error="errors['financials.round_off']" :disabled="isReadOnly"   />
            </div>
            

            <!-- Immediate Payment Box -->
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
                    <BaseInputNumber v-model="modelValue.payment.amount" label="Amount" :minFractionDigits="2" :error="errors['payment.amount']" :disabled="isReadOnly" />
                </div>
            </div>

            <!-- Invoice Section -->
            <div v-if="modelValue.status.invoice_status != 1" class="p-2">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-indigo-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    <div>
                        <h4 class="text-[11px] font-black uppercase tracking-widest text-indigo-700">Invoice Generation</h4>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <BaseSelect v-model="modelValue.ledger_id" :options="sales_ledgers" optionLabel="label" optionValue="value" label="Sales Ledger" filter placeholder="Select Sales Account" :error="errors.ledger_id" />
                    <BaseDatePicker v-model="modelValue.invoice_date" label="Invoice Date" :error="errors.invoice_date" />
                
                <div class="flex justify-end pt-2 col-span-2">
                    <button 
                        type="button"
                        @click="handleGenerateInvoice"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-sm text-white text-[11px] font-black uppercase tracking-widest rounded-xl"
                    >
                        Generate Invoice
                    </button>
                </div>
                </div>
            </div>

            <!-- Generated Invoice Information -->
            <div v-else class="py-2">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-emerald-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <h4 class="text-[11px] font-black uppercase tracking-widest text-emerald-700">Invoice Linked</h4>
                            <p class="text-[9px] font-bold text-emerald-800 uppercase">Billing Processed Successfully</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-xs font-semibold text-slate-700">
                        <div>
                            <span class="text-[9px] block text-slate-400 uppercase">Invoice #</span>
                            <span>{{ modelValue.status.invoice_number }}</span>
                        </div>
                        <div class="h-6 w-px bg-slate-200"></div>
                        <div>
                            <span class="text-[9px] block text-slate-400 uppercase">Date</span>
                            <span>{{ formatDate(modelValue.status.invoice_date) }} {{ formatTime(modelValue.status.invoice_date) }}</span>
                        </div>
                        <div class="h-6 w-px bg-slate-200"></div>
                        <div>
                            <span class="text-[9px] block text-slate-400 uppercase">Creator</span>
                            <span class="text-indigo-600 uppercase">{{ modelValue.status.invoice?.creator?.email || modelValue.status.invoice?.creator?.username || 'System' }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 py-2">
                    <template v-if="modelValue.status.invoice?.encrypted_id">
                        <template v-if="canExportInvoice">
                            <a 
                                :href="route('print.document', { module: 'invoices', id: modelValue.status.invoice.encrypted_id, action: 'download', force: 'original' })" 
                                target="_blank"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-[11px] font-black uppercase tracking-widest rounded-xl hover:bg-emerald-700 transition-colors shadow-sm"
                            >
                                <PrinterIcon class="h-4 w-4" />
                                Print Original
                            </a>
                            <a 
                                :href="route('print.document', { module: 'invoices', id: modelValue.status.invoice.encrypted_id, action: 'download', force: 'duplicate' })" 
                                target="_blank"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-[11px] font-black uppercase tracking-widest rounded-xl hover:bg-indigo-700 transition-colors shadow-sm"
                            >
                                <DocumentDuplicateIcon class="h-4 w-4" />
                                Print Duplicate
                            </a>
                        </template>
                        <a 
                            v-else
                            :href="modelValue.status.invoice?.is_duplicate 
                                ? route('print.document', { module: 'invoices', id: modelValue.status.invoice.encrypted_id, action: 'download', force: 'duplicate' })
                                : route('print.document', { module: 'invoices', id: modelValue.status.invoice.encrypted_id, action: 'view' })" 
                            target="_blank"
                            @click="modelValue.status.invoice.is_duplicate = 1"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-[11px] font-black uppercase tracking-widest rounded-xl hover:bg-emerald-700 transition-colors shadow-sm"
                        >
                            <DocumentDuplicateIcon v-if="modelValue.status.invoice?.is_duplicate" class="h-4 w-4" />
                            <PrinterIcon v-else class="h-4 w-4" />
                            Print Invoice
                        </a>
                    </template>
                    <button 
                        type="button"
                        @click="$emit('deleteInvoice')"
                        :disabled="isReadOnly"
                        :class="[isReadOnly ? 'opacity-50 cursor-not-allowed' : '']"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-rose-50 text-rose-600 border border-rose-100 text-[11px] font-black uppercase tracking-widest rounded-xl hover:bg-rose-100 transition-colors shadow-sm"
                    >
                        Delete Invoice
                    </button>
                </div>
            </div>
        </div>

        <!-- 6. Dispatch Audit Info -->
        <!-- <div class="px-5 py-3 bg-slate-50/50 border border-slate-200 rounded-2xl flex items-center justify-between">
            <div class="flex items-center gap-6">
                <div class="flex flex-col">
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Created</span>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="text-[10px] font-black text-slate-600">{{ modelValue.creator?.email || 'System' }}</span>
                        <span class="text-[10px] text-slate-400">@</span>
                        <span class="text-[10px] font-bold text-slate-500">{{ modelValue.created_at ? new Date(modelValue.created_at).toLocaleString('en-IN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }).replace(/\//g, '-') : '---' }}</span>
                    </div>
                </div>
                <div v-if="modelValue.updated_at && modelValue.updated_at !== modelValue.created_at" class="flex flex-col border-l border-slate-200 pl-6">
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Last Modified</span>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="text-[10px] font-black text-slate-600">{{ modelValue.modifier?.email || modelValue.creator?.email || 'System' }}</span>
                        <span class="text-[10px] text-slate-400">@</span>
                        <span class="text-[10px] font-bold text-slate-500">{{ new Date(modelValue.updated_at).toLocaleString('en-IN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }).replace(/\//g, '-') }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse" v-if="modelValue.dispatch_status === 'Delivered'"></div>
                <span class="text-[9px] font-black uppercase tracking-widest" :class="modelValue.dispatch_status === 'Delivered' ? 'text-emerald-600' : 'text-amber-600'">
                    {{ modelValue.dispatch_status }}
                </span>
            </div>
        </div> -->
    </div>
</template>
