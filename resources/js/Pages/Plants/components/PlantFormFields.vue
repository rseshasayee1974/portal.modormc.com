<script setup lang="ts"> 
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import ToggleSwitch from 'primevue/toggleswitch';
import { BuildingOffice2Icon, MapPinIcon, PhoneIcon } from '@heroicons/vue/24/outline';
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
const isIdentityLockedInEdit = computed(() => isEditMode.value && !props.canEditIdentityOnUpdate);
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
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Legal Entity *</label>
                    <BaseSelect
                        v-model="form.entity_id"
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
                        label="Plant Code *"
                        placeholder="XYZ-01" 
                        :error="errors?.code"
                        :disabled="isIdentityLockedInEdit"
                        :hint="isIdentityLockedInEdit ? 'Only Saas Owner can edit plant code.' : undefined"
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        v-model="form.name" 
                        label="Plant Name *"
                        placeholder="Enter facility name" 
                        :error="errors?.name"
                        :disabled="isIdentityLockedInEdit"
                        :hint="isIdentityLockedInEdit ? 'Only Saas Owner can edit plant name.' : undefined"
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
                        class="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-3">
                    <BaseInput 
                        v-model="form.address.line_1" 
                        label="Address Line 1"
                        placeholder="Flat, Building, Street" 
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
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-3">
                    <BaseInput 
                        v-model="form.contact.email" 
                        label="Email ID"
                        placeholder="example@company.com" 
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-3">
                    <BaseInput 
                        v-model="form.contact.mobile" 
                        label="Mobile Number"
                        placeholder="+91 00000 00000" 
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
