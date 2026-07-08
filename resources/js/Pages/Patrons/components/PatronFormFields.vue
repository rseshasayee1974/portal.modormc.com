<script setup lang="ts">
import Button from 'primevue/button';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import MultiSelect from 'primevue/multiselect';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseField from '@/Components/Base/BaseField.vue';
import { ref, watch, onMounted, computed } from 'vue';
import axios from 'axios';

const props = defineProps<{
    form: any;
    patronTypes: any[];
    operationalStatuses: any[];
    states: any[];
    addBank: () => void;
    removeBank: (index: number) => void;
    allstates?: any[];
}>();

// --- Dynamic Address Lookup Logic ---
const districts = ref<string[]>([]);
const allLocations = ref<any[]>([]);
const uniqueZipcodes = ref<string[]>([]);
const areas = ref<string[]>([]);

const selectedDistrict = ref('');
const selectedZipcode = ref('');
const selectedArea = ref('');

const isLoadingDistricts = ref(false);
const isLoadingLocations = ref(false);

const districtsOptions = computed(() => districts.value.map(d => ({ label: d, value: d })));
const zipcodesOptions = computed(() => uniqueZipcodes.value.map(z => ({ label: z, value: z })));
const areasOptions = computed(() => areas.value.map(a => ({ label: a, value: a })));

const hasDistrictOptions = computed(() => districts.value.length > 0);

const loadDistricts = async (stateId: number, isInitial = false) => {
    if (!stateId) {
        districts.value = [];
        return;
    }
    isLoadingDistricts.value = true;
    try {
        const res = await axios.get(`/master/statecodes/${stateId}/districts`);
        districts.value = res.data;
        
        if (isInitial && props.form.address_city) {
            selectedDistrict.value = props.form.address_city;
            await loadLocations(stateId, selectedDistrict.value, true);
        }
    } catch (err) {
        console.error('Error fetching districts:', err);
    } finally {
        isLoadingDistricts.value = false;
    }
};

const loadLocations = async (stateId: number, district: string, isInitial = false) => {
    if (!stateId || !district) {
        allLocations.value = [];
        uniqueZipcodes.value = [];
        return;
    }
    isLoadingLocations.value = true;
    try {
        const res = await axios.get(`/master/statecodes/${stateId}/zipcodes`, {
            params: { district }
        });
        allLocations.value = res.data;
        
        const uniqueZips = Array.from(new Set(res.data.map((item: any) => item.zipcode))) as string[];
        uniqueZipcodes.value = uniqueZips;
        
        if (isInitial && props.form.address_zipcode) {
            selectedZipcode.value = props.form.address_zipcode;
            updateAreas(selectedZipcode.value);
            if (props.form.address_line_2) {
                selectedArea.value = props.form.address_line_2;
            }
        }
    } catch (err) {
        console.error('Error fetching locations:', err);
    } finally {
        isLoadingLocations.value = false;
    }
};

const updateAreas = (zip: string) => {
    if (!zip) {
        areas.value = [];
        return;
    }
    areas.value = allLocations.value
        .filter((item: any) => item.zipcode === zip)
        .map((item: any) => item.area);
};

watch(() => props.form.address_state_id, (newVal) => {
    if (newVal) {
        selectedDistrict.value = '';
        selectedZipcode.value = '';
        selectedArea.value = '';
        uniqueZipcodes.value = [];
        areas.value = [];
        loadDistricts(newVal);
    } else {
        districts.value = [];
        allLocations.value = [];
        uniqueZipcodes.value = [];
        areas.value = [];
        selectedDistrict.value = '';
        selectedZipcode.value = '';
        selectedArea.value = '';
        props.form.address_city = '';
        props.form.address_zipcode = '';
        props.form.address_line_2 = '';
    }
});

watch(selectedDistrict, (newVal) => {
    if (newVal) {
        selectedZipcode.value = '';
        selectedArea.value = '';
        areas.value = [];
        loadLocations(props.form.address_state_id, newVal);
        props.form.address_city = newVal;
    } else {
        props.form.address_city = '';
    }
});

watch(selectedZipcode, (newVal) => {
    if (newVal) {
        selectedArea.value = '';
        updateAreas(newVal);
        props.form.address_zipcode = newVal;
    } else {
        props.form.address_zipcode = '';
    }
});

watch(selectedArea, (newVal) => {
    if (newVal) {
        props.form.address_line_2 = newVal;
    } else {
        props.form.address_line_2 = '';
    }
});

onMounted(() => {
    if (props.form.address_state_id) {
        loadDistricts(props.form.address_state_id, true);
    }
});
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

        <!-- ── Section: Contact & Geography ── -->
        <section>
            <div class="flex items-center gap-2 mb-6">
                <div class="w-1.5 h-6 bg-emerald-500 rounded-full"></div>
                <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-700 dark:text-slate-200">Contact & Reach</h3>
            </div>

            <div class="grid grid-cols-12 gap-5">
               
                <!-- 1. State (First Select) -->
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

                <template v-if="hasDistrictOptions">
                    <!-- 2. District Select -->
                    <div class="col-span-12 md:col-span-3">
                        <BaseSelect 
                            label="District" 
                            v-model="selectedDistrict" 
                            :options="districtsOptions" 
                            optionLabel="label" 
                            optionValue="value" 
                            filter 
                            clearable
                            :disabled="!form.address_state_id || isLoadingDistricts"
                            :placeholder="isLoadingDistricts ? 'Loading...' : 'Select District'"
                            class="!rounded-xl !border-slate-200"
                        />
                    </div>

                    <!-- 3. Zipcode Select -->
                    <div class="col-span-12 md:col-span-3">
                        <BaseSelect 
                            label="Zipcode / Pincode" 
                            v-model="selectedZipcode" 
                            :options="zipcodesOptions" 
                            optionLabel="label" 
                            optionValue="value" 
                            filter 
                            clearable
                            :disabled="!selectedDistrict || isLoadingLocations"
                            :placeholder="isLoadingLocations ? 'Loading...' : 'Select Zipcode'"
                            class="!rounded-xl !border-slate-200"
                        />
                    </div>

                    <!-- 4. Area Select -->
                    <div class="col-span-12 md:col-span-3">
                        <BaseSelect 
                            label="Area / Locality" 
                            v-model="selectedArea" 
                            :options="areasOptions" 
                            optionLabel="label" 
                            optionValue="value" 
                            filter 
                            clearable
                            :disabled="!selectedZipcode"
                            placeholder="Select Area"
                            class="!rounded-xl !border-slate-200"
                        />
                    </div>
                </template>

                <template v-else>
                    <!-- 2. City / District Input (Manual) -->
                    <div class="col-span-12 md:col-span-3">
                        <BaseInput 
                            label="City / District" 
                            v-model="form.address_city" 
                            placeholder="e.g. Puducherry" 
                            :error="form.errors.address_city" 
                        />
                    </div>

                    <!-- 3. Zipcode Input (Manual) -->
                    <div class="col-span-12 md:col-span-3">
                        <BaseInput 
                            label="Zipcode" 
                            v-model="form.address_zipcode" 
                            placeholder="e.g. 605001" 
                            :error="form.errors.address_zipcode" 
                        />
                    </div>

                    <!-- 4. Office Address line 2 (Area, Manual) -->
                    <div class="col-span-12 md:col-span-3">
                        <BaseInput 
                            label="Office Address line 2 (Area)" 
                            v-model="form.address_line_2" 
                            placeholder="e.g. Heritage Town" 
                            :error="form.errors.address_line_2" 
                        />
                    </div>
                </template>

                <!-- 5. Address line 1 (Only Manual Input) -->
                <div class="col-span-12 md:col-span-3">
                    <BaseInput label="Address line 1" v-model="form.address_line_1" placeholder="e.g. No. 12, Gandhi Street" :error="form.errors.address_line_1" />
                </div>

                <!-- <template v-if="hasDistrictOptions">
                    <div class="col-span-12 mt-2">
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100 pb-1">Formatted Address Preview</h4>
                    </div>

                     
                    <div class="col-span-12 md:col-span-4">
                        <BaseInput 
                            label="Office Address line 2 (Area)" 
                            v-model="form.address_line_2" 
                            disabled
                            placeholder="Selected Area will appear here" 
                            inputClass="!w-full !rounded-xl !border-slate-100 bg-slate-50 text-slate-500 font-medium text-sm cursor-not-allowed"
                        />
                    </div>

                     
                    <div class="col-span-12 md:col-span-4">
                        <BaseInput 
                            label="City / District" 
                            v-model="form.address_city" 
                            disabled
                            placeholder="Selected District will appear here" 
                            inputClass="!w-full !rounded-xl !border-slate-100 bg-slate-50 text-slate-500 font-medium text-sm cursor-not-allowed"
                        />
                    </div>

                    
                    <div class="col-span-12 md:col-span-4">
                        <BaseInput 
                            label="Zipcode" 
                            v-model="form.address_zipcode" 
                            disabled
                            placeholder="Selected Zipcode will appear here" 
                            inputClass="!w-full !rounded-xl !border-slate-100 bg-slate-50 text-slate-500 font-medium text-sm cursor-not-allowed"
                        />
                    </div>
                </template> -->
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
