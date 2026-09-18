<script setup lang="ts">
import { computed, watch, type PropType } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseField from '@/Components/Base/BaseField.vue';
import Textarea from 'primevue/textarea';
import Tag from 'primevue/tag';
import { ReceiptPercentIcon } from '@heroicons/vue/24/outline';
import type { Discount, DiscountOptions } from '../types';

const props = defineProps({
    journals: { type: Array as PropType<DiscountOptions['journals']>, required: true },
    accounts: { type: Array as PropType<DiscountOptions['accounts']>, required: true },
    partners: { type: Array as PropType<DiscountOptions['partners']>, required: true },
    discount: Object as PropType<Discount>,
    readonly: Boolean,
});

const emit = defineEmits<{ saved: []; cancel: [] }>();

const today = () => {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};

const values = () => ({
    primary_type: props.discount?.primary_type ?? 'Sales',
    value_type: props.discount?.value_type ?? 'amount',
    value: props.discount ? Number(props.discount.value) : (null as number | null),
    amount: props.discount ? Number(props.discount.amount) : (null as number | null),
    journal_id: props.discount?.journal_id ?? (null as number | null),
    account_id: props.discount?.account_id ?? (null as number | null),
    partner_id: props.discount?.partner_id ?? (null as number | null),
    invoice_id: props.discount?.invoice_id ?? (null as number | null),
    billing_id: props.discount?.billing_id ?? (null as number | null),
    payment_id: props.discount?.payment_id ?? (null as number | null),
    reference_number: props.discount?.reference_number ?? '',
    move_id: props.discount?.move_id ?? (null as number | null),
    date: props.discount?.date?.slice(0, 10) ?? today(),
    note: props.discount?.note ?? '',
    status: props.discount?.status ?? 1,
});

const form = useForm(values());

watch(
    () => props.discount,
    () => {
        form.defaults(values());
        form.reset();
        form.clearErrors();
    }
);

const isCustomer = (patronType?: string | null) => {
    if (!patronType) return false;
    const s = String(patronType).toLowerCase();
    return s.includes('customer');
};

const isVendorOrSupplier = (patronType?: string | null) => {
    if (!patronType) return false;
    const s = String(patronType).toLowerCase();
    return s.includes('vendor') || s.includes('supplier');
};

const getVoucherAmount = (j?: { total_debit?: string | number | null; total_credit?: string | number | null } | null) => {
    if (!j) return 0;
    const debit = Number(j.total_debit) || 0;
    const credit = Number(j.total_credit) || 0;
    return Math.max(debit, credit);
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(val);
};

// Filter partners to related patron only: Customer for Sales, Vendor/Supplier for Purchase
const partnerOptions = computed(() => {
    const isSales = form.primary_type === 'Sales';
    return props.partners.filter(p => {
        // Keep currently selected partner if editing
        if (props.discount && p.id === props.discount.partner_id) {
            return true;
        }
        const hasCustomer = isCustomer(p.patron_type);
        const hasVendor = isVendorOrSupplier(p.patron_type);

        if (isSales) {
            return hasCustomer || (!hasVendor && !p.patron_type);
        } else {
            return hasVendor || (!hasCustomer && !p.patron_type);
        }
    });
});

const selectedPartner = computed(() => {
    return props.partners.find(p => p.id === form.partner_id);
});

const journalOptions = computed(() => {
    const isSales = form.primary_type === 'Sales';

    // Sales Discount: only show invoice numbers ('SALES' / ref_module 'invoice') and receipts ('RECEIPT')
    // Purchase Discount: only show billing ('PURCHASE' / ref_module 'bill') and payments ('PAYMENT')
    const filtered = props.journals.filter(j => {
        if (props.discount && j.id === props.discount.journal_id) {
            return true;
        }

        // Once applied for any invoice, bill, payment or receipt, do not show!
        if (j.applied_discount_id && (!props.discount || j.applied_discount_id !== props.discount.id)) {
            return false;
        }

        const vType = (j.voucher_type || '').toUpperCase();
        const refMod = (j.ref_module || '').toLowerCase();

        const matchesType = isSales
            ? (vType === 'SALES' || vType === 'RECEIPT' || refMod === 'invoice' || refMod === 'sales')
            : (vType === 'PURCHASE' || vType === 'PAYMENT' || refMod === 'bill' || refMod === 'purchase');

        if (!matchesType) return false;

        // When a particular partner is selected, STRICTLY show that partner's bills or invoices only!
        if (form.partner_id) {
            return Number(j.partner_id) === Number(form.partner_id);
        }

        return true;
    });

    return filtered.map(j => {
        const vType = (j.voucher_type || '').toUpperCase();
        const refMod = (j.ref_module || '').toLowerCase();

        let typeBadge = j.voucher_type;
        if (vType === 'SALES' || refMod === 'invoice') typeBadge = 'Invoice';
        else if (vType === 'RECEIPT') typeBadge = 'Receipt';
        else if (vType === 'PURCHASE' || refMod === 'bill') typeBadge = 'Bill';
        else if (vType === 'PAYMENT') typeBadge = 'Payment';

        const amount = getVoucherAmount(j);
        const amountStr = amount > 0 ? ` (₹ ${formatCurrency(amount)})` : '';
        const refStr = j.invoice_number ? ` [Ref: #${j.invoice_number}]` : '';
        const partnerStr = !form.partner_id && j.partner_name ? ` · ${j.partner_name}` : '';

        return {
            value: j.id,
            label: `${j.voucher_number}${refStr} · ${typeBadge}${amountStr}${partnerStr}`,
            type: typeBadge,
            amount,
            partner_id: j.partner_id,
            partner_name: j.partner_name,
            invoice_number: j.invoice_number,
        };
    });
});

const selectedJournal = computed(() => {
    return props.journals.find(j => j.id === form.journal_id);
});

const selectedVoucherAmount = computed(() => {
    return getVoucherAmount(selectedJournal.value);
});

// Auto-fill partner and sync invoice_id / billing_id / payment_id when a voucher is picked
watch(
    () => form.journal_id,
    (newJournalId) => {
        if (newJournalId) {
            const found = props.journals.find(j => j.id === newJournalId);
            if (found?.partner_id && form.partner_id !== found.partner_id) {
                form.partner_id = found.partner_id;
            }

            const docId = found?.invoice_doc_id || found?.ref_id || null;
            const vType = (found?.voucher_type || '').toUpperCase();
            const refMod = (found?.ref_module || '').toLowerCase();

            form.move_id = found?.id || newJournalId;

            if (form.primary_type === 'Sales' || vType === 'SALES' || refMod === 'invoice') {
                form.invoice_id = docId;
                form.billing_id = null;
                form.payment_id = null;
            } else if (form.primary_type === 'Purchase' || vType === 'PURCHASE' || refMod === 'bill') {
                form.billing_id = docId;
                form.invoice_id = null;
                form.payment_id = null;
            } else if (vType === 'PAYMENT' || vType === 'RECEIPT' || refMod === 'payment') {
                form.payment_id = docId;
                form.invoice_id = null;
                form.billing_id = null;
            }
        } else {
            form.move_id = null;
            form.invoice_id = null;
            form.billing_id = null;
            form.payment_id = null;
        }
    }
);

// Reset voucher if selected partner changes and voucher does not belong to new partner
watch(
    () => form.partner_id,
    (newPartnerId) => {
        if (newPartnerId && form.journal_id) {
            const found = props.journals.find(j => j.id === form.journal_id);
            if (found?.partner_id && found.partner_id !== newPartnerId) {
                form.journal_id = null;
            }
        }
    }
);

// When primary_type changes, recheck partner & journal validity
watch(
    () => form.primary_type,
    (newType, oldType) => {
        if (oldType && newType !== oldType) {
            const partnerStillValid = partnerOptions.value.some(opt => opt.id === form.partner_id);
            if (!partnerStillValid) {
                form.partner_id = null;
            }
            const journalStillValid = journalOptions.value.some(opt => opt.value === form.journal_id);
            if (!journalStillValid) {
                form.journal_id = null;
            }
        }
    }
);

// Auto-calculate discount amount for fixed or percentage
watch([() => form.value_type, () => form.value, () => form.journal_id], () => {
    if (form.value_type === 'amount') {
        form.amount = form.value;
    } else if (form.value_type === 'percent') {
        if (selectedVoucherAmount.value > 0 && form.value !== null && form.value !== undefined) {
            form.amount = Math.round((selectedVoucherAmount.value * (Number(form.value) / 100)) * 100) / 100;
        }
    }
});

const typeOptions = [
    { label: 'Sales Discount', value: 'Sales' },
    { label: 'Purchase Discount', value: 'Purchase' },
];

const valueTypeOptions = [
    { label: 'Fixed Amount (₹)', value: 'amount' },
    { label: 'Percentage (%)', value: 'percent' },
];

const statusOptions = [
    { label: 'Active', value: 1 },
    { label: 'Inactive', value: 0 },
];

const reset = () => {
    form.reset();
    form.clearErrors();
};

const validateForm = (): boolean => {
    form.clearErrors();
    let isValid = true;
    const errorMessages: string[] = [];

    // 1. Transaction Type
    if (!form.primary_type) {
        form.setError('primary_type', 'Please select a transaction type (Sales or Purchase).');
        errorMessages.push('Transaction Type is required.');
        isValid = false;
    }

    // 2. Discount Date
    if (!form.date || !form.date.trim()) {
        form.setError('date', 'Please enter or select a discount date.');
        errorMessages.push('Discount Date is required.');
        isValid = false;
    }

    // 3. Partner (Customer / Supplier)
    if (!form.partner_id) {
        const pLabel = form.primary_type === 'Sales' ? 'Customer' : 'Supplier / Vendor';
        form.setError('partner_id', `Please select a ${pLabel} partner.`);
        errorMessages.push(`Partner (${pLabel}) is required.`);
        isValid = false;
    }

    // 4. Journal / Voucher
    if (!form.journal_id) {
        const vLabel = form.primary_type === 'Sales' ? 'sales invoice or receipt' : 'purchase bill or payment';
        form.setError('journal_id', `Please select a ${vLabel} voucher.`);
        errorMessages.push('Voucher selection is required.');
        isValid = false;
    } else if (form.partner_id && selectedJournal.value?.partner_id) {
        if (Number(selectedJournal.value.partner_id) !== Number(form.partner_id)) {
            form.setError('journal_id', 'Selected voucher does not belong to the selected partner.');
            errorMessages.push('Selected voucher does not match the chosen partner.');
            isValid = false;
        }
    }

    // 5. Discount Type
    if (!form.value_type) {
        form.setError('value_type', 'Please select a discount type (Fixed Amount or Percentage).');
        errorMessages.push('Discount Type is required.');
        isValid = false;
    }

    // 6. Discount Value / Rate
    if (form.value === null || form.value === undefined || form.value === ('' as any)) {
        form.setError('value', 'Discount value or percentage rate is required.');
        errorMessages.push('Discount Value / Rate is required.');
        isValid = false;
    } else if (Number(form.value) <= 0) {
        form.setError('value', 'Discount value must be greater than zero.');
        errorMessages.push('Discount Value must be greater than 0.');
        isValid = false;
    } else if (form.value_type === 'percent' && Number(form.value) > 100) {
        form.setError('value', 'Discount percentage rate cannot exceed 100%.');
        errorMessages.push('Percentage rate cannot exceed 100%.');
        isValid = false;
    }

    // 7. Net Discount Amount
    const netAmount = form.value_type === 'amount' ? Number(form.value) : Number(form.amount);
    if (!netAmount || netAmount <= 0) {
        form.setError('amount', 'Net discount amount must be greater than zero.');
        errorMessages.push('Net discount amount must be greater than 0.');
        isValid = false;
    } else if (selectedVoucherAmount.value > 0 && netAmount > selectedVoucherAmount.value) {
        const formattedVoucher = formatCurrency(selectedVoucherAmount.value);
        form.setError('amount', `Discount amount (₹ ${formatCurrency(netAmount)}) cannot exceed total voucher value (₹ ${formattedVoucher}).`);
        errorMessages.push(`Discount amount cannot exceed total voucher value (₹ ${formattedVoucher}).`);
        isValid = false;
    }

    // 8. Status
    if (form.status === null || form.status === undefined) {
        form.setError('status', 'Please select a status (Active or Inactive).');
        errorMessages.push('Status is required.');
        isValid = false;
    }

    // if (!isValid) {
    //     Swal.fire({
    //         icon: 'warning',
    //         title: 'Form Validation Incomplete',
    //         html: `<p class="text-xs text-slate-600 mb-2">Please correct the following highlighted fields:</p><ul class="text-left text-xs text-red-600 space-y-1 list-disc pl-5">${errorMessages.map(m => `<li>${m}</li>`).join('')}</ul>`,
    //         confirmButtonColor: '#4f46e5',
    //         confirmButtonText: 'Review Form',
    //     });
    // }

    return isValid;
};

// Clear field errors dynamically when the user modifies inputs
watch(() => form.primary_type, () => form.clearErrors('primary_type'));
watch(() => form.date, () => form.clearErrors('date'));
watch(() => form.partner_id, () => form.clearErrors('partner_id'));
watch(() => form.journal_id, () => form.clearErrors('journal_id'));
watch(() => form.value_type, () => form.clearErrors('value_type'));
watch(() => form.value, () => {
    form.clearErrors('value');
    form.clearErrors('amount');
});
watch(() => form.amount, () => form.clearErrors('amount'));
watch(() => form.status, () => form.clearErrors('status'));

const submit = () => {
    if (props.readonly || form.processing) return;
    if (!validateForm()) return;

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (!props.discount) reset();
            emit('saved');
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: props.discount ? 'Discount updated successfully' : 'Discount created successfully',
                timer: 1600,
                showConfirmButton: false,
            });
        },
        onError: (errors: any) => {
            Swal.fire({
                icon: 'error',
                title: 'Submission Failed',
                text: Object.values(errors).flat()[0] as string || 'Please check the highlighted fields.',
            });
        },
    };
    form.transform(data => ({
        ...data,
        amount: data.value_type === 'amount' ? data.value : data.amount,
    }));
    if (props.discount) {
        form.put(route('discounts.update', props.discount.id), options);
    } else {
        form.post(route('discounts.store'), options);
    }
};
</script>

<template>
    <form @submit.prevent="submit"
        class="rounded-xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-sm space-y-6">
        <!-- Form Header -->
        <div class="flex items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                    <ReceiptPercentIcon class="w-5 h-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900 tracking-tight">
                        {{ discount ? `Edit Discount #${discount.id}` : 'Create Discount Voucher' }}
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{
                            discount ? `Modify recorded parameters for voucher #${discount.id}` :
                                'Record a new sales or purchase discount for this active plant.' }}
                    </p>
                </div>
            </div>
            <div v-if="discount" class="flex items-center gap-2">
                <Tag :value="discount.status ? 'Active' : 'Inactive'"
                    :severity="discount.status ? 'success' : 'secondary'" />
                <span class="text-xs font-mono text-slate-400">{{ discount.date?.slice(0, 10) }}</span>
            </div>
        </div>

        <!-- Form Fields Grid -->
        <fieldset :disabled="readonly || !!discount || form.processing"
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
            <!-- Transaction Type -->
            <BaseSelect v-model="form.primary_type" label="Transaction Type" :options="typeOptions" optionLabel="label"
                optionValue="value" :error="form.errors.primary_type" :disabled="readonly || form.processing"
                required />

            <!-- Date -->
            <BaseDatePicker v-model="form.date" label="Discount Date" placeholder="YYYY-MM-DD" dateFormat="yy-mm-dd"
                :error="form.errors.date" :disabled="readonly || form.processing" required />

            <!-- Partner (Customer / Supplier) - Filtered to related patron only -->
            <BaseSelect v-model="form.partner_id"
                :label="form.primary_type === 'Sales' ? 'Partner (Customer)' : 'Partner (Supplier / Vendor)'"
                :options="partnerOptions" optionLabel="legal_name" optionValue="id" filter showClear
                :placeholder="form.primary_type === 'Sales' ? 'Select related customer' : 'Select related supplier'"
                :error="form.errors.partner_id" :disabled="readonly || form.processing" required />
            <!-- :hint="form.primary_type === 'Sales' ? 'Showing customer patrons only' : 'Showing supplier / vendor patrons only'" -->
            <!-- Journal Voucher: Filtered by Sales vs Purchase and Related Patron -->
            <BaseSelect v-model="form.journal_id"
                :label="form.primary_type === 'Sales' ? 'Invoice / Receipt Voucher' : 'Bill / Payment Voucher'"
                :options="journalOptions" optionLabel="label" optionValue="value" filter showClear
                :placeholder="form.partner_id ? (form.primary_type === 'Sales' ? 'Select invoice for this customer' : 'Select bill for this supplier') : (form.primary_type === 'Sales' ? 'Select customer invoice' : 'Select supplier bill')"
                :error="form.errors.journal_id" :disabled="readonly || form.processing" required />
            <!-- :hint="form.partner_id ? (journalOptions.length ? `Showing ${journalOptions.length} voucher(s) for ${selectedPartner?.legal_name || 'selected partner'}` : `No bills or invoices found for ${selectedPartner?.legal_name || 'selected partner'}`) : 'Select a partner to view their specific bills or invoices'" -->


            <!-- Account Ledger -->
            <BaseSelect v-model="form.account_id" label="Discount Account (Ledger)" :options="accounts"
                optionLabel="title" optionValue="id" filter showClear placeholder="Select ledger (optional)"
                :error="form.errors.account_id" :disabled="readonly || form.processing" />
            <!-- hint="Linked ledger line" -->
            <!-- Discount Type -->
            <BaseSelect v-model="form.value_type" label="Discount Type" :options="valueTypeOptions" optionLabel="label"
                optionValue="value" :error="form.errors.value_type" :disabled="readonly || form.processing" required />

            <!-- Discount Value / Rate -->
            <BaseInputNumber v-model="form.value"
                :label="form.value_type === 'percent' ? 'Discount Rate (%)' : 'Discount Value (₹)'"
                :prefix="form.value_type === 'percent' ? undefined : '₹ '"
                :suffix="form.value_type === 'percent' ? '%' : undefined" :min="0.01"
                :max="form.value_type === 'percent' ? 100 : undefined"
                :minFractionDigits="form.value_type === 'percent' ? 0 : 2" :maxFractionDigits="2"
                :error="form.errors.value" :disabled="readonly || form.processing" required />

            <!-- Calculated / Final Discount Amount -->
            <BaseInputNumber v-model="form.amount" label="Net Discount Amount (₹)" prefix="₹ " :min="0.01"
                :minFractionDigits="2" :maxFractionDigits="2" :readonly="form.value_type === 'amount'"
                :disabled="readonly || form.processing" :error="form.errors.amount" required />
            <!-- :hint="form.value_type === 'percent' ? 'Enter the monetary discount amount corresponding to percentage.' : 'Automatically matched with fixed value.'" -->
            <!-- Discount Reference Number -->
            <!-- <BaseInput
                v-model="form.reference_number"
                label="Reference Number"
                placeholder="Auto-generated if empty"
              
                :error="form.errors.reference_number"
                :disabled="readonly || form.processing"
            /> -->
            <!-- hint="e.g. SDISC/2627/00001 or custom ref" -->
            <!-- Move Reference -->




            <!-- Note Textarea wrapped in BaseField -->
            <BaseField label="Notes / Reason" :error="form.errors.note" class="sm:col-span-2">
                <template #default="{ invalid, inputId }">
                    <Textarea :id="inputId" v-model="form.note" rows="2" maxlength="10000"
                        :disabled="readonly || form.processing" class="w-full text-xs rounded-lg border-slate-300"
                        :class="{ 'p-invalid': invalid }"
                        placeholder="Reason for discount, approval remarks, or supporting details..." fluid />
                </template>
            </BaseField>

            <!-- Selected Voucher / Bill or Invoice Value Banner -->
            <div v-if="selectedJournal && selectedVoucherAmount > 0"
                class="col-span-1 sm:col-span-2 lg:col-span-2 rounded-xl border border-indigo-100 bg-gradient-to-r from-indigo-50/90 via-sky-50/40 to-white p-3.5 shadow-xs flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div
                        class="w-9 h-9 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                        ₹
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                {{ form.primary_type === 'Sales' ? 'Invoice Value' : 'Bill Value' }}:
                            </span>
                            <span class="text-base font-bold text-indigo-900 font-mono">
                                ₹ {{ formatCurrency(selectedVoucherAmount) }}
                            </span>
                            <Tag :value="selectedJournal.voucher_type || 'Voucher'" severity="info"
                                class="text-[10px] px-1.5 py-0.5 uppercase" />
                        </div>
                        <div class="text-[11px] text-slate-500 mt-0.5 flex flex-wrap items-center gap-2">
                            <span>Voucher: <strong class="text-slate-800 font-mono">{{ selectedJournal.voucher_number
                                    }}</strong></span>
                            <span v-if="selectedJournal.invoice_number">
                                • Ref: <strong class="text-slate-800 font-mono">#{{ selectedJournal.invoice_number
                                    }}</strong>
                            </span>
                            <template v-if="selectedJournal.partner_name">
                                <span>•</span>
                                <span>Related Patron: <strong class="text-slate-800">{{ selectedJournal.partner_name
                                        }}</strong></span>
                            </template>
                        </div>
                    </div>
                </div>

                <div v-if="form.value_type === 'percent' && form.value"
                    class="bg-white/90 border border-indigo-200 rounded-lg px-3 py-1.5 text-xs text-slate-700 shadow-2xs flex items-center gap-2">
                    <span class="text-slate-500">Discount ({{ form.value }}%):</span>
                    <span class="font-bold text-emerald-700 font-mono text-sm">
                        ₹ {{ formatCurrency(form.amount || 0) }}
                    </span>
                </div>
            </div>
        </fieldset>

        <!-- Form Actions Footer -->
        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
            <BaseButton v-if="discount" label="Close" severity="secondary" variant="text" size="small"
                icon="pi pi-times" @click="emit('cancel')" />
            <BaseButton v-else label="Reset" severity="secondary" variant="text" size="small" icon="pi pi-refresh"
                @click="reset" :disabled="form.processing" />
            <BaseButton v-if="!readonly && !discount" type="submit" label="Save Discount" icon="pi pi-check"
                variant="filled" size="small" :loading="form.processing" />
        </div>
    </form>
</template>
