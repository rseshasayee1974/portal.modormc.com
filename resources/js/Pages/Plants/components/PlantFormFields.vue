<script setup lang="ts"> 
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import ToggleSwitch from 'primevue/toggleswitch';
import { BuildingOffice2Icon, MapPinIcon, PhoneIcon, LinkIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps<{
    form: any;
    entities: any[];
    addressTypes: any[];
    contactTypes: any[];
    states: any[];
    errors?: any;
    plantId?: number;
    canEditIdentityOnUpdate?: boolean;
}>();

const activeTab = ref<'basic' | 'location' | 'contact' | 'integration'>('basic');
const isEditMode = computed(() => Boolean(props.plantId));

const logoPreview = computed(() => {
    if (props.form.logo && props.form.logo instanceof File) {
        try {
            return URL.createObjectURL(props.form.logo);
        } catch (e) {
            return null;
        }
    }
    if (props.form.logo_path) {
        return `/storage/${props.form.logo_path}`;
    }
    return null;
});

const sealPreview = computed(() => {
    if (props.form.seal_sign && props.form.seal_sign instanceof File) {
        try {
            return URL.createObjectURL(props.form.seal_sign);
        } catch (e) {
            return null;
        }
    }
    if (props.form.seal_sign_path) {
        return `/storage/${props.form.seal_sign_path}`;
    }
    return null;
});

const upiQrPreview = computed(() => {
    if (props.form.upi_qr && props.form.upi_qr instanceof File) {
        try {
            return URL.createObjectURL(props.form.upi_qr);
        } catch (e) {
            return null;
        }
    }
    if (props.form.upi_qr_path) {
        return `/storage/${props.form.upi_qr_path}`;
    }
    return null;
});

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

// Determine if dropdown data is available (fallback to manual input if empty)
const hasDistrictData = computed(() => districts.value.length > 0);
const hasZipcodeData = computed(() => uniqueZipcodes.value.length > 0);
const hasAreaData = computed(() => areas.value.length > 0);

const districtsOptions = computed(() => districts.value.map(d => ({ label: d, value: d })));
const zipcodesOptions = computed(() => uniqueZipcodes.value.map(z => ({ label: z, value: z })));
const areasOptions = computed(() => areas.value.map(a => ({ label: a, value: a })));

const loadDistricts = async (stateId: number, isInitial = false) => {
    if (!stateId) {
        districts.value = [];
        return;
    }
    isLoadingDistricts.value = true;
    try {
        const res = await axios.get(`/master/statecodes/${stateId}/districts`);
        districts.value = res.data;
        
        if (isInitial && props.form.address.city && districts.value.length > 0) {
            selectedDistrict.value = props.form.address.city;
            await loadLocations(stateId, selectedDistrict.value, true);
        }
    } catch (err) {
        console.error('Error fetching districts:', err);
        districts.value = [];
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
        
        // Extract unique zipcodes
        const uniqueZips = Array.from(new Set(res.data.map((item: any) => item.zipcode))) as string[];
        uniqueZipcodes.value = uniqueZips;
        
        if (isInitial && props.form.address.zipcode && uniqueZips.length > 0) {
            selectedZipcode.value = props.form.address.zipcode;
            updateAreas(selectedZipcode.value);
            if (props.form.address.line_2) {
                selectedArea.value = props.form.address.line_2;
            }
        }
    } catch (err) {
        console.error('Error fetching locations:', err);
        allLocations.value = [];
        uniqueZipcodes.value = [];
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

// Watch for State ID change
watch(() => props.form.address.state_id, (newVal) => {
    if (newVal) {
        // Reset dropdown selections (but keep manual form values intact)
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
    }
});

// Watch for District selection (only when using dropdowns)
watch(selectedDistrict, (newVal) => {
    if (newVal && hasDistrictData.value) {
        selectedZipcode.value = '';
        selectedArea.value = '';
        areas.value = [];
        
        loadLocations(props.form.address.state_id, newVal);
        props.form.address.city = newVal;
    }
});

// Watch for Zipcode selection (only when using dropdowns)
watch(selectedZipcode, (newVal) => {
    if (newVal && hasZipcodeData.value) {
        selectedArea.value = '';
        updateAreas(newVal);
        props.form.address.zipcode = newVal;
    }
});

// Watch for Area selection (only when using dropdowns)
watch(selectedArea, (newVal) => {
    if (newVal && hasAreaData.value) {
        props.form.address.line_2 = newVal;
    }
});

// Initial load for Edit mode
onMounted(() => {
    if (props.form.address.state_id) {
        loadDistricts(props.form.address.state_id, true);
    }
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Tab Pills -->
        <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-900 rounded-xl p-1 w-fit">
            <button
                v-for="tab in [
                    { key: 'basic', label: 'General Info', icon: BuildingOffice2Icon },
                    { key: 'location', label: 'Location Details', icon: MapPinIcon },
                    { key: 'contact', label: 'Contact Person', icon: PhoneIcon },
                    { key: 'integration', label: 'API Integrations', icon: LinkIcon },
                ]"
                :key="tab.key"
                type="button"
                @click="activeTab = tab.key as any"
                :class="[
                    'flex items-center gap-2 px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-lg transition-all duration-200',
                    activeTab === tab.key
                        ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm'
                        : 'text-slate-500 hover:text-slate-700 hover:bg-slate-200/50'
                ]"
            >
                <component :is="tab.icon" class="w-4 h-4" />
                {{ tab.label }}
            </button>
            
        </div>

        <!-- Form Sections -->
        <div class="form-content">
            <!-- TAB: General -->
            <div v-show="activeTab === 'basic'" class="grid grid-cols-1 md:grid-cols-12 gap-5 animate-in fade-in slide-in-from-top-1 duration-300">
                <div class="col-span-12 md:col-span-2 flex flex-col gap-1.5">
                    <BaseSelect
                        v-model="form.entity_id"
                        label="Legal Entity"
                        required
                        :options="entities"
                        optionLabel="legal_name"
                        optionValue="id"
                        placeholder="Select entity"
                        filter
                        class="!w-full !rounded-md !border-slate-200 focus:!ring-indigo-100 transition-all font-medium text-sm"
                        :class="{'p-invalid': errors?.entity_id}"
                    />
                    <small v-if="errors?.entity_id" class="p-error px-1">{{ errors.entity_id[0] }}</small>
                </div>

                <div class="col-span-12 md:col-span-2">
                    <BaseInput 
                        v-model="form.code" 
                        label="Plant Code"
                        required
                        placeholder="XYZ-01" 
                        :error="errors?.code"
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-2">
                    <BaseInput 
                        v-model="form.name" 
                        label="Plant Name"
                        required
                        placeholder="Enter facility name" 
                        :error="errors?.name"
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-2">
                    <BaseInput 
                        v-model="form.email_address" 
                        label="Admin Email"
                        required
                        type="email"
                        placeholder="admin@plant.com" 
                        :error="errors?.email_address"
                        hint="Used for automated user creation and login credentials."
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-2">
                    <BaseInput 
                        v-model="form.mobile_number" 
                        label="Admin Mobile"
                        placeholder="9876543210" 
                        :error="errors?.mobile_number"
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-2">
                    <BaseInput 
                        v-model="form.gstin" 
                        label="GSTIN"
                        placeholder="22AAAAA0000A1Z5" 
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm uppercase"
                    />
                </div>

                <div class="col-span-12 md:col-span-2">
                    <BaseInput 
                        v-model="form.plant_type" 
                        label="Plant Type"
                        placeholder="e.g. RMC, Crusher" 
                        :error="errors?.plant_type"
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-2">
                    <BaseInputNumber 
                        v-model="form.mixer_capacity" 
                        label="Mixer Capacity (m³)"
                        placeholder="e.g. 1.25" 
                        :minFractionDigits="2"
                        :error="errors?.mixer_capacity"
                    />
                </div>

                <div class="col-span-12 md:col-span-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Plant Logo</label>
                    <div class="mt-1 flex items-center gap-3">
                        <div v-if="logoPreview" class="relative w-12 h-12 rounded-lg border border-slate-200 overflow-hidden bg-slate-50 flex items-center justify-center">
                            <img 
                                :src="logoPreview" 
                                class="w-full h-full object-contain"
                            />
                        </div>
                        <input 
                            type="file" 
                            accept="image/*" 
                            @change="(e: any) => form.logo = e.target.files[0]"
                            class="text-[10px] text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:uppercase file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
                        />
                    </div>
                    <small v-if="errors?.logo" class="p-error text-[10px] block mt-1">{{ Array.isArray(errors.logo) ? errors.logo[0] : errors.logo }}</small>
                </div>

                <div class="col-span-12 md:col-span-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Seal & Signature</label>
                    <div class="mt-1 flex items-center gap-3">
                        <div v-if="sealPreview" class="relative w-12 h-12 rounded-lg border border-slate-200 overflow-hidden bg-slate-50 flex items-center justify-center">
                            <img 
                                :src="sealPreview" 
                                class="w-full h-full object-contain"
                            />
                        </div>
                        <input 
                            type="file" 
                            accept="image/*" 
                            @change="(e: any) => form.seal_sign = e.target.files[0]"
                            class="text-[10px] text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:uppercase file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
                        />
                    </div>
                    <small v-if="errors?.seal_sign" class="p-error text-[10px] block mt-1">{{ Array.isArray(errors.seal_sign) ? errors.seal_sign[0] : errors.seal_sign }}</small>
                </div>

                <div class="col-span-12 md:col-span-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">UPI QR Code</label>
                    <div class="mt-1 flex items-center gap-3">
                        <div v-if="upiQrPreview" class="relative w-12 h-12 rounded-lg border border-slate-200 overflow-hidden bg-slate-50 flex items-center justify-center">
                            <img 
                                :src="upiQrPreview" 
                                class="w-full h-full object-contain"
                            />
                        </div>
                        <input 
                            type="file" 
                            accept="image/*" 
                            @change="(e: any) => form.upi_qr = e.target.files[0]"
                            class="text-[10px] text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:uppercase file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
                        />
                    </div>
                    <small v-if="errors?.upi_qr" class="p-error text-[10px] block mt-1">{{ Array.isArray(errors.upi_qr) ? errors.upi_qr[0] : errors.upi_qr }}</small>
                </div>

                <div class="md:col-span-2 flex flex-col gap-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Main Unit</label>
                    <div class="flex items-center gap-3 h-11 px-3 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-100 dark:border-slate-800">
                        <ToggleSwitch v-model="form.is_main" />
                        <span class="text-[11px] font-bold text-slate-500 uppercase">Headquarters</span>
                    </div>
                </div>

                <div class="md:col-span-2 flex flex-col gap-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Active</label>
                    <div class="flex items-center gap-3 h-11 px-3 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-100 dark:border-slate-800">
                        <ToggleSwitch v-model="form.is_active" />
                        <span class="text-[11px] font-bold text-slate-500 uppercase">Operational</span>
                    </div>
                </div>
            </div>

            <!-- TAB: Location -->
            <div v-show="activeTab === 'location'" class="grid grid-cols-1 md:grid-cols-12 gap-5 animate-in fade-in slide-in-from-top-1 duration-300">
                <!-- Address Type -->
                <div class="md:col-span-2 flex flex-col gap-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Address Type</label>
                    <BaseSelect
                        v-model="form.address.address_type_id"
                        :options="addressTypes"
                        optionLabel="type"
                        optionValue="id"
                        placeholder="Address Type"
                        :class="{'p-invalid': errors?.['address.address_type_id']}"
                        class="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                    <small v-if="errors?.['address.address_type_id']" class="p-error px-1">{{ errors['address.address_type_id'][0] }}</small>
                </div>

                <!-- 1. State (First Select) -->
                <div class="md:col-span-2 flex flex-col gap-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">State</label>
                    <BaseSelect
                        v-model="form.address.state_id"
                        :options="states"
                        optionLabel="state_name"
                        optionValue="id"
                        filter
                        clearable
                        placeholder="Select State"
                        class="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <!-- 2. District / City -->
                <div class="md:col-span-2 flex flex-col gap-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">City / District</label>
                    <!-- Dropdown mode: when API returned district data -->
                    <BaseSelect
                        v-if="hasDistrictData"
                        v-model="selectedDistrict"
                        :options="districtsOptions"
                        optionLabel="label"
                        optionValue="value"
                        filter
                        clearable
                        :disabled="!form.address.state_id || isLoadingDistricts"
                        :placeholder="isLoadingDistricts ? 'Loading...' : 'Select District'"
                        class="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                    <!-- Manual input mode: when no district data exists -->
                    <BaseInput
                        v-else
                        v-model="form.address.city"
                        :placeholder="isLoadingDistricts ? 'Loading...' : 'e.g. Chennai'"
                        :disabled="!form.address.state_id || isLoadingDistricts"
                        :error="errors?.['address.city']"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <!-- 3. Zipcode / Pincode -->
                <div class="md:col-span-2 flex flex-col gap-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Zipcode / Pincode</label>
                    <!-- Dropdown mode -->
                    <BaseSelect
                        v-if="hasZipcodeData"
                        v-model="selectedZipcode"
                        :options="zipcodesOptions"
                        optionLabel="label"
                        optionValue="value"
                        filter
                        clearable
                        :disabled="!selectedDistrict || isLoadingLocations"
                        :placeholder="isLoadingLocations ? 'Loading...' : 'Select Zipcode'"
                        class="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                    <!-- Manual input mode -->
                    <BaseInput
                        v-else
                        v-model="form.address.zipcode"
                        placeholder="e.g. 600001"
                        :disabled="!form.address.state_id"
                        :error="errors?.['address.zipcode']"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <!-- 4. Area / Locality -->
                <div class="md:col-span-2 flex flex-col gap-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Area / Locality</label>
                    <!-- Dropdown mode -->
                    <!-- <BaseSelect
                        v-if="hasAreaData"
                        v-model="selectedArea"
                        :options="areasOptions"
                        optionLabel="label"
                        optionValue="value"
                        filter
                        clearable
                        :disabled="!selectedZipcode"
                        placeholder="Select Area"
                        class="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    /> -->
                    <!-- Manual input mode -->
                    <BaseInput 
                        v-model="form.address.line_2"
                        placeholder="e.g. T. Nagar"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <!-- 5. Address Line 1 -->
                <div class="md:col-span-4">
                    <BaseInput 
                        v-model="form.address.line_1" 
                        label="Address Line 1 (Street, Building, Door No.)"
                        placeholder="e.g. No. 12, Gandhi Street" 
                        :error="errors?.['address.line_1']"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <!-- Latitude & Longitude -->
                <div class="md:col-span-4">
                    <BaseInput 
                        v-model="form.latitude" 
                        label="Latitude"
                        placeholder="e.g. 13.0827" 
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-4">
                    <BaseInput 
                        v-model="form.longitude" 
                        label="Longitude"
                        placeholder="e.g. 80.2707" 
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>
            </div>

            <!-- TAB: Contact -->
            <div v-show="activeTab === 'contact'" class="grid grid-cols-1 md:grid-cols-12 gap-5 animate-in fade-in slide-in-from-top-1 duration-300">
                <div class="md:col-span-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Contact Type</label>
                    <BaseSelect
                        v-model="form.contact.contact_type_id"
                        :options="contactTypes"
                        optionLabel="type"
                        optionValue="id"
                        placeholder="Contact Type"
                        class="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>
                <div class="md:col-span-2">
                    <BaseInput 
                        v-model="form.contact.name" 
                        label="Contact Person Name"
                        placeholder="Full Name" 
                        :error="errors?.['contact.name']"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-2">
                    <BaseInput 
                        v-model="form.contact.email" 
                        label="Email ID"
                        type="email"
                        placeholder="example@company.com" 
                        :error="errors?.['contact.email']"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-2">
                    <BaseInput 
                        v-model="form.contact.mobile" 
                        label="Mobile Number"
                        placeholder="+91 00000 00000" 
                        :error="errors?.['contact.mobile']"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-2">
                    <BaseInput 
                        v-model="form.contact.alt_mobile" 
                        label="Alt. Mobile"
                        placeholder="Additional Number" 
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-2">
                    <BaseInput 
                        v-model="form.contact.landline" 
                        label="Landline"
                        placeholder="022-XXXXXXX" 
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>
            </div>

            <!-- TAB: Integration -->
            <div v-show="activeTab === 'integration'" class="grid grid-cols-1 md:grid-cols-12 gap-5 animate-in fade-in slide-in-from-top-1 duration-300">
                <div class="col-span-12">
                    <h3 class="text-sm font-bold text-slate-800 border-b border-slate-200 pb-2">Scheduler API Configuration</h3>
                    <p class="text-xs text-slate-500 mt-1">Configure credentials to automatically push batch data to a third-party scheduler.</p>
                </div>

                <div class="col-span-12 md:col-span-6">
                    <BaseInput 
                        v-model="form.scheduler_api_url" 
                        label="Scheduler API URL"
                        placeholder="https://example.com/api/production__Order__data" 
                        :error="errors?.scheduler_api_url"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-6">
                    <BaseInput 
                        v-model="form.scheduler_api_token" 
                        label="Static API Token (Optional)"
                        placeholder="Long static bearer token" 
                        :error="errors?.scheduler_api_token"
                        hint="If provided, dynamic OAuth will be skipped."
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12">
                    <h3 class="text-sm font-bold text-slate-800 border-b border-slate-200 pb-2 mt-4">Dynamic OAuth Credentials</h3>
                </div>

                <div class="col-span-12 md:col-span-6">
                    <BaseInput 
                        v-model="form.scheduler_oauth_url" 
                        label="OAuth Token URL"
                        placeholder="https://example.com/oauth/token" 
                        :error="errors?.scheduler_oauth_url"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-2">
                    <BaseInput 
                        v-model="form.scheduler_client_id" 
                        label="Client ID"
                        placeholder="your_client_id" 
                        :error="errors?.scheduler_client_id"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-2">
                    <BaseInput 
                        v-model="form.scheduler_client_secret" 
                        label="Client Secret"
                        type="password"
                        placeholder="••••••••••••" 
                        :error="errors?.scheduler_client_secret"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12">
                    <h3 class="text-sm font-bold text-slate-800 border-b border-slate-200 pb-2 mt-4">E-Invoice & E-Way Bill Integration</h3>
                    <p class="text-xs text-slate-500 mt-1">Configure credentials specific to this plant for government compliance portals (overrides Entity defaults).</p>
                </div>

                <div class="col-span-12 md:col-span-6">
                    <BaseInput 
                        v-model="form.einvoice_client_id" 
                        label="E-Invoice Client ID / Subscription Key"
                        placeholder="Enter Client ID / Subscription Key" 
                        :error="errors?.einvoice_client_id"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-6">
                    <BaseInput 
                        v-model="form.einvoice_secret" 
                        label="E-Invoice Client Secret / Password"
                        type="password"
                        placeholder="••••••••••••" 
                        :error="errors?.einvoice_secret"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-6">
                    <BaseInput 
                        v-model="form.ewaybill_client_id" 
                        label="E-Way Bill Client ID"
                        placeholder="Enter Client ID" 
                        :error="errors?.ewaybill_client_id"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-6">
                    <BaseInput 
                        v-model="form.ewaybill_secret" 
                        label="E-Way Bill Client Secret / Password"
                        type="password"
                        placeholder="••••••••••••" 
                        :error="errors?.ewaybill_secret"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.p-select) {
    @apply !bg-white dark:!bg-slate-800;
}
:deep(.p-inputtext) {
    @apply !bg-white dark:!bg-slate-800;
}
</style>
