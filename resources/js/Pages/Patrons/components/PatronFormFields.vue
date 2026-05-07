<script setup lang="ts">
import Button from 'primevue/button';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import MultiSelect from 'primevue/multiselect';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseField from '@/Components/Base/BaseField.vue';

defineProps<{
    form: any;
    patronTypes: any[];
    operationalStatuses: any[];
    states: any[];
    addBank: () => void;
    removeBank: (index: number) => void;
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
                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        label="Internal Code" 
                        required
                        v-model="form.code" 
                        placeholder="e.g. PAT-001"
                        :error="form.errors.code" 
                    />
                </div>
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
            </div>
        </section>

        <!-- ── Section: Contact & Geography ── -->
        <section>
            <div class="flex items-center gap-2 mb-6">
                <div class="w-1.5 h-6 bg-emerald-500 rounded-full"></div>
                <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-700 dark:text-slate-200">Contact & Reach</h3>
            </div>

            <div class="grid grid-cols-12 gap-5">
               
                <div class="col-span-12 md:col-span-3">
                    <BaseInput label="Office Address line 1" v-model="form.address_line_1" placeholder="Plot No, Building Name" :error="form.errors.address_line_1" />
                </div>
                <div class="col-span-12 md:col-span-3">
                    <BaseInput label="Office Address line 2" v-model="form.address_line_2" placeholder="Street, Industrial Area" :error="form.errors.address_line_2" />
                </div>
                <div class="col-span-12 md:col-span-3">
                    <BaseInput label="City" v-model="form.address_city" placeholder="Mumbai" :error="form.errors.address_city" />
                </div>
                <div class="col-span-12 md:col-span-3">
                    <BaseSelect 
                        label="State" 
                        v-model="form.address_state_id" 
                        :options="states" 
                        optionLabel="label" 
                        optionValue="value" 
                        filter 
                        placeholder="Select State"
                        class="!rounded-xl !border-slate-200"
                        :invalid="!!form.errors.address_state_id" 
                    />
                    <small v-if="form.errors.address_state_id" class="p-error px-1">{{ form.errors.address_state_id }}</small>
                </div>
                <div class="col-span-12 md:col-span-3">
                    <BaseInput label="Zipcode" v-model="form.address_zipcode" placeholder="400001" :error="form.errors.address_zipcode" />
                </div>
                 <div class="col-span-12 md:col-span-3">
                    <BaseInput label="Primary Contact Person" v-model="form.contact_name" placeholder="Full Name" :error="form.errors.contact_name" />
                </div>
                <div class="col-span-12 md:col-span-3">
                    <BaseInput label="Mobile Number" v-model="form.contact_mobile" placeholder="+91 00000 00000" :error="form.errors.contact_mobile" />
                </div>
                <div class="col-span-12 md:col-span-3">
                    <BaseInput label="Official Email" v-model="form.contact_email" placeholder="contact@company.com" :error="form.errors.contact_email" />
                </div>
            </div>
        </section>

        <!-- ── Section: Banking Infrastructure ── -->
        <section>
            <div class="flex items-center gap-2 mb-6">
                <div class="w-1.5 h-6 bg-amber-500 rounded-full"></div>
                <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-700 dark:text-slate-200">Banking Details</h3>
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
                
                <div v-if="!form.bank_accounts?.length" class="text-center py-10 border-2 border-dashed border-slate-200 rounded-2xl opacity-50">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest italic">No bank accounts linked</p>
                </div>
            </div>
        </section>
    </div>
</template>
