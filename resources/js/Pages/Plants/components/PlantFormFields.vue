<script setup lang="ts"> 
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import ToggleSwitch from 'primevue/toggleswitch';
import { BuildingOffice2Icon, MapPinIcon, PhoneIcon, LinkIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

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

const activeTab = ref<'basic' | 'location' | 'contact'>('basic');
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
// const isIdentityLockedInEdit = computed(() => isEditMode.value && !props.canEditIdentityOnUpdate);
// console.log('isIdentityLockedInEdit',isIdentityLockedInEdit.value);
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
                <div class="col-span-12 md:col-span-3 flex flex-col gap-1.5">
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

                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        v-model="form.code" 
                        label="Plant Code"
                        required
                        placeholder="XYZ-01" 
                        :error="errors?.code"
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        v-model="form.name" 
                        label="Plant Name"
                        required
                        placeholder="Enter facility name" 
                        :error="errors?.name"
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-3">
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

                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        v-model="form.mobile_number" 
                        label="Admin Mobile"
                        placeholder="9876543210" 
                        :error="errors?.mobile_number"
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        v-model="form.gstin" 
                        label="GSTIN"
                        placeholder="22AAAAA0000A1Z5" 
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm uppercase"
                    />
                </div>

                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        v-model="form.plant_type" 
                        label="Plant Type"
                        placeholder="e.g. RMC, Crusher" 
                        :error="errors?.plant_type"
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-3">
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
                </div>

                <div class="md:col-span-3 flex flex-col gap-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Main Unit</label>
                    <div class="flex items-center gap-3 h-11 px-3 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-100 dark:border-slate-800">
                        <ToggleSwitch v-model="form.is_main" />
                        <span class="text-[11px] font-bold text-slate-500 uppercase">Headquarters</span>
                    </div>
                </div>

                <div class="md:col-span-3 flex flex-col gap-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Active</label>
                    <div class="flex items-center gap-3 h-11 px-3 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-100 dark:border-slate-800">
                        <ToggleSwitch v-model="form.is_active" />
                        <span class="text-[11px] font-bold text-slate-500 uppercase">Operational</span>
                    </div>
                </div>
            </div>

            <!-- TAB: Location -->
            <div v-show="activeTab === 'location'" class="grid grid-cols-1 md:grid-cols-12 gap-5 animate-in fade-in slide-in-from-top-1 duration-300">
                <div class="md:col-span-3">
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

                <div class="md:col-span-3">
                    <BaseInput 
                        v-model="form.address.line_1" 
                        label="Address Line 1"
                        placeholder="Flat, Building, Street" 
                        :error="errors?.['address.line_1']"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-3">
                    <BaseInput 
                        v-model="form.address.line_2" 
                        label="Address Line 2"
                        placeholder="Locality, Landmark" 
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-3">
                    <BaseInput 
                        v-model="form.address.city" 
                        label="City"
                        placeholder="Enter City" 
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-3 flex flex-col gap-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">State</label>
                    <BaseSelect
                        v-model="form.address.state_id"
                        :options="states"
                        optionLabel="state_name"
                        optionValue="id"
                        filter
                        clearable
                        placeholder="Select State"
                        class="!w-full !rounded-xl !border-slate-200 font-medium text-sm px-1"
                    />
                </div>

                <div class="md:col-span-3">
                    <BaseInput 
                        v-model="form.address.zipcode" 
                        label="Zipcode"
                        placeholder="6-digit code" 
                        :error="errors?.['address.zipcode']"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-3">
                    <BaseInput 
                        v-model="form.latitude" 
                        label="Latitude"
                        placeholder="19.0760" 
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-3">
                    <BaseInput 
                        v-model="form.longitude" 
                        label="Longitude"
                        placeholder="72.8777" 
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>
            </div>

            <!-- TAB: Contact -->
            <div v-show="activeTab === 'contact'" class="grid grid-cols-1 md:grid-cols-12 gap-5 animate-in fade-in slide-in-from-top-1 duration-300">
                <div class="md:col-span-3">
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
                <div class="md:col-span-3">
                    <BaseInput 
                        v-model="form.contact.name" 
                        label="Contact Person Name"
                        placeholder="Full Name" 
                        :error="errors?.['contact.name']"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-3">
                    <BaseInput 
                        v-model="form.contact.email" 
                        label="Email ID"
                        type="email"
                        placeholder="example@company.com" 
                        :error="errors?.['contact.email']"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-3">
                    <BaseInput 
                        v-model="form.contact.mobile" 
                        label="Mobile Number"
                        placeholder="+91 00000 00000" 
                        :error="errors?.['contact.mobile']"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-3">
                    <BaseInput 
                        v-model="form.contact.alt_mobile" 
                        label="Alt. Mobile"
                        placeholder="Additional Number" 
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-3">
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

                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        v-model="form.scheduler_client_id" 
                        label="Client ID"
                        placeholder="your_client_id" 
                        :error="errors?.scheduler_client_id"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        v-model="form.scheduler_client_secret" 
                        label="Client Secret"
                        type="password"
                        placeholder="••••••••••••" 
                        :error="errors?.scheduler_client_secret"
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
