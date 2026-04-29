<script setup lang="ts">
import Button from 'primevue/button';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import MultiSelect from 'primevue/multiselect';
import ToggleSwitch from 'primevue/toggleswitch';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseLabel from '@/Components/Base/BaseField.vue';

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
    <div class="flex flex-col gap-4">
        <div>
            <h3 class="text-[11px] font-bold uppercase tracking-wider text-indigo-700 mb-4 border-b border-gray-100 dark:border-gray-700 pt-2">General</h3>
            <div class="grid grid-cols-12 gap-4">
                <div class="flex flex-col gap-1 md:col-span-1">
                    <BaseInput label="Code" v-model="form.code" :error="form.errors.code" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-2">
                    <BaseInput label="Legal Name" v-model="form.legal_name" :error="form.errors.legal_name" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-2">
                    <label class="text-[10px] font-bold uppercase text-gray-800">Patron Type</label>
                    <MultiSelect
                        v-model="form.patron_type"
                        :options="patronTypes"
                        optionLabel="label"
                        optionValue="value"
                        display="chip"
                        placeholder="Select patron types"
                        class="w-full !h-9"
                        :invalid="!!form.errors.patron_type"
                    />
                    <!-- <small v-if="form.errors.patron_type" class="p-error text-xs text-red-500">{{ form.errors.patron_type }}</small> -->
                </div>
                <div class="flex flex-col gap-1 md:col-span-2">
                    <!-- <BaseInput v-model="form.operational_status" :error="form.errors.operational_status" /> -->
                    <BaseSelect v-model="form.operational_status" label="Operational status"  :options="operationalStatuses" optionLabel="label" optionValue="value" :invalid="!!form.errors.operational_status" />
                    <small v-if="form.errors.operational_status" class="p-error text-xs text-red-500">{{ form.errors.operational_status }}</small>
                </div>
                <div class="flex flex-col gap-1 md:col-span-2">
                    <BaseInput 
                        label="PAN No" 
                        v-model="form.pan_no" 
                        :error="form.errors.pan_no" 
                        @update:modelValue="form.pan_no = form.pan_no?.toUpperCase().replace(/[^A-Z0-9]/g, '')"
                    />
                </div>
                <div class="flex flex-col gap-1 md:col-span-2">
                    <BaseInput 
                        label="GSTIN" 
                        v-model="form.gstin" 
                        :error="form.errors.gstin" 
                        @update:modelValue="form.gstin = form.gstin?.toUpperCase().replace(/[^A-Z0-9]/g, '')"
                    />
                </div>
                <!-- <div class="flex items-center gap-2 md:col-span-2">
                    <ToggleSwitch v-model="form.status" />
                    <label class="text-xs font-bold text-gray-600 dark:text-gray-300">Active profile</label>
                </div> -->
            </div>
        </div>

        <div>
            <h3 class="text-[11px] font-bold uppercase tracking-wider text-indigo-700 mb-2 border-b border-gray-100 dark:border-gray-700 ">Contact &amp; address</h3>
            <div class="grid grid-cols-12 gap-4">
                <div class="flex flex-col gap-1 md:col-span-3">
                    <BaseInput label="Contact name" v-model="form.contact_name" :error="form.errors.contact_name" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-3">
                    <BaseInput label="Mobile" v-model="form.contact_mobile" :error="form.errors.contact_mobile" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-3">
                    <BaseInput label="Email" v-model="form.contact_email" :error="form.errors.contact_email" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-3">
                    <BaseInput label="Address line 1" v-model="form.address_line_1" :error="form.errors.address_line_1" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-3">
                    <BaseInput label="Address line 2" v-model="form.address_line_2" :error="form.errors.address_line_2" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-3">
                    <BaseInput label="City" v-model="form.address_city" :error="form.errors.address_city" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-3">
                    <BaseSelect label="State" v-model="form.address_state_id" :options="states" optionLabel="label" optionValue="value" filter :invalid="!!form.errors.address_state_id" />
                    <small v-if="form.errors.address_state_id" class="p-error text-xs text-red-500">{{ form.errors.address_state_id }}</small>
                </div>
                <div class="flex flex-col gap-1 md:col-span-3">
                    <BaseInput label="Zipcode" v-model="form.address_zipcode" :error="form.errors.address_zipcode" placeholder="400001" />
                </div>
            </div>
        </div>

        <div>
            <h3 class="text-[11px] font-bold uppercase tracking-wider text-indigo-700 mb-2 border-b border-gray-100 dark:border-gray-700 ">Bank accounts</h3>
            <div class="flex flex-col gap-4">
                <div
                    v-for="(bank, index) in form.bank_accounts"
                    :key="index"
                    class="     rounded-lg flex flex-col gap-2 relative"
                >
                    
                    <div class="grid grid-cols-12 gap-2">
                        <div class="flex flex-col gap-1 md:col-span-3">
                            <BaseInput label="Account holder" v-model="bank.account_holder_name" :error="form.errors[`bank_accounts.${index}.account_holder_name`]" />
                        </div>
                        <div class="flex flex-col gap-1 md:col-span-3">
                            <BaseInput label="Account number" v-model="bank.account_number" :error="form.errors[`bank_accounts.${index}.account_number`]" />
                        </div>
                        <div class="flex flex-col gap-1 md:col-span-3">
                            <BaseInput label="Bank name" v-model="bank.bank_name" :error="form.errors[`bank_accounts.${index}.bank_name`]" />
                        </div>
                        <div class="flex flex-col gap-1 md:col-span-2">
                            <BaseInput label="IFSC" v-model="bank.ifsc_code" :error="form.errors[`bank_accounts.${index}.ifsc_code`]" />
                        </div>
                        <Button icon="pi pi-times" severity="danger" text  class="absolute top-2 right-2" type="button" @click="removeBank(index)" />
                    </div>
                </div>
                <!-- <Button label="Add bank account" icon="pi pi-plus"  text type="button" @click="addBank" /> -->
            </div>
        </div>
    </div>
</template>
