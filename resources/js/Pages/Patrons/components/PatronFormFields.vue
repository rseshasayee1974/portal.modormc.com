<script setup lang="ts">
import Button from 'primevue/button';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import MultiSelect from 'primevue/multiselect';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseField from '@/Components/Base/BaseField.vue';
import { computed } from 'vue';
import AddressDetails from '@/Components/AddressDetails.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const salesExecutiveOptions = computed(() => [
    { label: 'Unassigned', value: null },
    ...((page.props.salesExecutives as any[]) || []),
]);
const ledgerOptions = (ledgers: any[]) => (ledgers || []).map(ledger => ({
    label: `${ledger.name} - ${ledger.title}`,
    value: ledger.id,
}));
const debitLedgerOptions = computed(() => ledgerOptions(page.props.debitLedgers as any[]));
const creditLedgerOptions = computed(() => ledgerOptions(page.props.creditLedgers as any[]));

defineProps<{
    form: any;
    patronTypes: any[];
    operationalStatuses: any[];
    states: any[];
    addBank: () => void;
    removeBank: (index: number) => void;
    allstates?: any[];
}>();

</script>

<template>
    <div class="flex flex-col gap-8">
        <!-- ── Section: General Identity ── -->
        <section>
            <div class="flex items-center gap-2 mb-6">
                <div class="w-1.5 h-6 bg-indigo-500 rounded-full"></div>
                <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-700 dark:text-slate-200">Identity & Status</h3>
            </div>
            
            <div class="grid grid-cols-12 gap-5">
                <!-- <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        label="Internal Code" 
                        required
                        v-model="form.code" 
                        placeholder="e.g. PAT-001"
                        :error="form.errors.code" 
                    />
                </div> -->
                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        label="Legal Entity Name" 
                        required
                        v-model="form.legal_name" 
                        placeholder="Full Registered Name"
                        :error="form.errors.legal_name" 
                    />
                </div>
                <div class="col-span-12 md:col-span-3">
                    <BaseSelect
                        class="w-full"
                        v-model="form.sales_executive_id"
                        label="Sales Executive"
                        :options="salesExecutiveOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select a sales executive"
                        :error="form.errors.sales_executive_id"
                    />
                </div>
                <div class="col-span-12 md:col-span-3">
                    <BaseField label="Patron Category" required :error="form.errors.patron_type">
                        <MultiSelect
                            v-model="form.patron_type"
                            :options="patronTypes"
                            optionLabel="label"
                            optionValue="value"
                            display="chip"
                            placeholder="Select categories"
                            class="!w-full !h-11 !rounded-xl !border-slate-200 focus:!ring-indigo-100 transition-all text-sm"
                            :invalid="!!form.errors.patron_type"
                        />
                    </BaseField>
                </div>
                <div class="col-span-12 md:col-span-3">
                    <BaseSelect 
                        v-model="form.operational_status" 
                        label="Operational Status" 
                        required
                        :options="operationalStatuses" 
                        optionLabel="label" 
                        optionValue="value" 
                        class="!rounded-xl !border-slate-200"
                        :invalid="!!form.errors.operational_status" 
                    />
                    <small v-if="form.errors.operational_status" class="p-error px-1">{{ form.errors.operational_status }}</small>
                </div>
                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        label="PAN Identification" 
                        v-model="form.pan_no" 
                        placeholder="ABCDE1234F"
                        :error="form.errors.pan_no" 
                        @update:modelValue="form.pan_no = form.pan_no?.toUpperCase().replace(/[^A-Z0-9]/g, '')"
                        inputClass="uppercase tracking-widest font-mono"
                    />
                </div>
                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        label="GSTIN Number" 
                        v-model="form.gstin" 
                        placeholder="22AAAAA0000A1Z5"
                        :error="form.errors.gstin" 
                        @update:modelValue="form.gstin = form.gstin?.toUpperCase().replace(/[^A-Z0-9]/g, '')"
                        inputClass="uppercase tracking-widest font-mono"
                    />
                </div>
                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        label="Aadhar Number" 
                        v-model="form.aadhar_number" 
                        placeholder="12-digit number"
                        :error="form.errors.aadhar_number" 
                        @update:modelValue="form.aadhar_number = form.aadhar_number?.replace(/[^0-9]/g, '').substring(0, 12)"
                        inputClass="tracking-widest font-mono"
                    />
                </div>
            </div>
        </section>

        <section>
            <h3 class="text-xs font-bold uppercase text-slate-700 dark:text-slate-200 mb-4">Patron Ledgers</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-5">
                <BaseSelect label="Debit Ledger" v-model="form.debit_ledger_id" :options="debitLedgerOptions"
                    optionLabel="label" optionValue="value" filter clearable
                    placeholder="Use account defaults" :error="form.errors.debit_ledger_id" />
                <BaseSelect label="Credit Ledger" v-model="form.credit_ledger_id" :options="creditLedgerOptions"
                    optionLabel="label" optionValue="value" filter clearable
                    placeholder="Use account defaults" :error="form.errors.credit_ledger_id" />
            </div>
        </section>

        <!-- ── Section: Contact & Geography ── -->
        <section>
            <h3 class="mb-4 text-xs font-bold uppercase text-slate-700 dark:text-slate-200">Contact Details</h3>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <BaseInput label="Mobile Number" v-model="form.contact_mobile" type="tel"
                    :error="form.errors.contact_mobile" />
                <BaseInput label="Phone (Landline)" v-model="form.contact_landline" type="tel"
                    :maxlength="45" :error="form.errors.contact_landline" />
                <BaseInput label="Email" v-model="form.contact_email" type="email"
                    :error="form.errors.contact_email" />
            </div>
        </section>

        <section>
            <div class="flex items-center gap-2 mb-6">
                <div class="w-1.5 h-6 bg-emerald-500 rounded-full"></div>
                <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-700 dark:text-slate-200">Address Details</h3>
            </div>

            <AddressDetails :states="states" :errors="form.errors" v-model:state="form.address_state_id"
                v-model:city="form.address_city" v-model:zipcode="form.address_zipcode"
                v-model:line1="form.address_line_1" v-model:line2="form.address_line_2" />
        </section>

        <!-- ── Section: Banking Infrastructure ── -->
        <!-- <section>
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-2">
                    <div class="w-1.5 h-6 bg-amber-500 rounded-full"></div>
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-700 dark:text-slate-200">Banking Details</h3>
                </div>
                <Button 
                    label="Add Bank Account" 
                    icon="pi pi-plus" 
                    outlined 
                    size="small" 
                    severity="warning" 
                    class="!rounded-xl"
                    type="button"
                    @click="addBank"
                />
            </div>

            <div class="flex flex-col gap-6">
                <div
                    v-for="(bank, index) in form.bank_accounts"
                    :key="index"
                    class="p-6 bg-slate-50 dark:bg-slate-900/40 rounded-2xl border border-slate-100 dark:border-slate-800 relative group transition-all hover:shadow-md"
                >
                    <div class="grid grid-cols-12 gap-5">
                        <div class="col-span-12 md:col-span-3">
                            <BaseInput label="Account Holder" v-model="bank.account_holder_name" :error="form.errors[`bank_accounts.${index}.account_holder_name`]" />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseInput label="Account Number" v-model="bank.account_number" :error="form.errors[`bank_accounts.${index}.account_number`]" />
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <BaseInput label="Bank Name" v-model="bank.bank_name" :error="form.errors[`bank_accounts.${index}.bank_name`]" />
                        </div>
                        <div class="col-span-12 md:col-span-2">
                            <BaseInput label="IFSC Code" v-model="bank.ifsc_code" :error="form.errors[`bank_accounts.${index}.ifsc_code`]" />
                        </div>
                        <Button 
                            icon="pi pi-trash" 
                            severity="danger" 
                            text  
                            rounded
                            class="absolute -top-3 -right-3 !bg-white dark:!bg-slate-800 shadow-sm border border-slate-100 opacity-0 group-hover:opacity-100 transition-opacity" 
                            type="button" 
                            @click="removeBank(index)" 
                        />
                    </div>
                </div>
                
                <div v-if="!form.bank_accounts?.length" class="text-center py-10 border-2 border-dashed border-slate-200 rounded-2xl flex flex-col items-center justify-center gap-3">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest italic">No bank accounts linked</p>
                    <Button 
                        label="Link Bank Account" 
                        icon="pi pi-plus" 
                        outlined 
                        size="small" 
                        severity="warning" 
                        class="!rounded-xl"
                        type="button"
                        @click="addBank"
                    />
                </div>
            </div>
        </section> -->
    </div>
</template>
