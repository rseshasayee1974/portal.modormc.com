<script setup lang="ts">
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import ToggleSwitch from 'primevue/toggleswitch';
import { BuildingOfficeIcon, TagIcon, MapPinIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps<{
    form: any;
    plants: any[];
    siteTypes: string[];
    isPrivileged: boolean;
    errors?: any;
    readonly?: boolean;
}>();

const activeTab = ref<'basic' | 'location'>('basic');
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Tab Pills -->
        <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-900 rounded-xl p-1 w-fit">
            <button
                v-for="tab in [
                    { key: 'basic', label: 'General Info', icon: BuildingOfficeIcon },
                    { key: 'location', label: 'Geo Location', icon: MapPinIcon },
                ]"
                :key="tab.key"
                @click="activeTab = tab.key"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all duration-300"
                :class="activeTab === tab.key 
                    ? 'bg-white dark:bg-slate-800 text-indigo-600 shadow-sm' 
                    : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
            >
                <component :is="tab.icon" class="w-4 h-4" />
                {{ tab.label }}
            </button>
        </div>

        <div class="form-content ">
            <!-- TAB: Basic Info -->
            <div v-show="activeTab === 'basic'" class="grid grid-cols-1 md:grid-cols-12 gap-5 animate-in fade-in slide-in-from-top-1 duration-300">
                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        v-model="form.name" 
                        label="Site Name *"
                        placeholder="e.g. Loading Bay A" 
                        :error="errors?.name"
                        :disabled="readonly"
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        v-model="form.code" 
                        label="Internal Code"
                        placeholder="e.g. S-01" 
                        :error="errors?.code"
                        :disabled="readonly"
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm uppercase"
                    />
                </div>

                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        v-model="form.site_address_1" 
                        label="Site Address"
                        placeholder="Full operational address" 
                        :error="errors?.site_address_1"
                        :disabled="readonly"
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-3">
                    <BaseInput 
                        v-model="form.zipcode" 
                        label="Zip Code"
                        placeholder="e.g. 380001" 
                        :error="errors?.zipcode"
                        :disabled="readonly"
                        inputClass="!rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm"
                    />
                </div>

                <div class="col-span-12 md:col-span-3 flex flex-col gap-1.5" v-if="isPrivileged">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Operation Type *</label>
                    <BaseSelect 
                        v-model="form.type" 
                        :options="isPrivileged ? siteTypes.map(t => ({label: t.toUpperCase(), value: t})) : [{label: 'UNLOADING', value: 'unloading'}]" 
                        optionLabel="label" 
                        optionValue="value" 
                        placeholder="Select Type"
                        class="!w-full !rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm"
                        :class="{'p-invalid': errors?.type}"
                        :disabled="readonly || !isPrivileged"
                    />
                    <small v-if="errors?.type" class="p-error px-1 text-[10px]">{{ Array.isArray(errors.type) ? errors.type[0] : errors.type }}</small>
                </div>

                <div v-if="isPrivileged" class="col-span-12 md:col-span-3 flex flex-col gap-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Parent Facility (Plant) *</label>
                    <BaseSelect 
                        v-model="form.plant_id" 
                        :options="plants" 
                        optionLabel="name" 
                        optionValue="id" 
                        placeholder="Select Plant" 
                        class="!w-full !rounded-md !border-slate-200 focus:!ring-indigo-100 font-medium text-sm"
                        :class="{'p-invalid': errors?.plant_id}"
                        :disabled="readonly"
                        filter
                    />
                    <small v-if="errors?.plant_id" class="p-error px-1 text-[10px]">{{ Array.isArray(errors.plant_id) ? errors.plant_id[0] : errors.plant_id }}</small>
                </div>

                <div class="col-span-12 md:col-span-3 flex flex-col gap-1.5 ">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Operational Status</label>
                    <div class="flex items-center gap-4 h-[42px] px-4 bg-slate-50 dark:bg-slate-900/50 rounded-md border border-slate-200 dark:border-slate-800 transition-all hover:border-indigo-100">
                        <ToggleSwitch 
                            :modelValue="form.status === 'Active'" 
                            @update:modelValue="form.status = $event ? 'Active' : 'InActive'" 
                            :disabled="readonly"
                        />
                        <div class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-wider" :class="form.status === 'Active' ? 'text-emerald-600' : 'text-rose-500'">
                                {{ form.status === 'Active' ? 'Node Online' : 'Node Offline' }}
                            </span>
                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-tight">{{ form.status === 'Active' ? 'Active in Directory' : 'Hidden from Loaders' }}</span>
                        </div>
                    </div>
                    <small v-if="errors?.status" class="p-error px-1 text-[10px]">{{ Array.isArray(errors.status) ? errors.status[0] : errors.status }}</small>
                </div>

                <!-- <div class="col-span-12">
                    <div class="flex items-center gap-4 p-4 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-100 dark:border-slate-800 transition-all hover:border-indigo-100">
                        <ToggleSwitch v-model="form.is_restricted" :disabled="readonly" />
                        <div class="flex flex-col">
                            <span class="text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider">Access Restriction</span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tight">{{ form.is_restricted ? 'Restricted Access' : 'Open Access' }}</span>
                        </div>
                    </div>
                </div> -->
            </div>

            <!-- TAB: Location -->
            <div v-show="activeTab === 'location'" class="grid grid-cols-1 md:grid-cols-12 gap-5 animate-in fade-in slide-in-from-top-1 duration-300">
                <div class="md:col-span-6">
                    <BaseInput 
                        v-model="form.latitude" 
                        label="Latitude"
                        placeholder="e.g. 23.0225" 
                        :error="errors?.latitude"
                        :disabled="readonly"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>

                <div class="md:col-span-6">
                    <BaseInput 
                        v-model="form.longitude" 
                        label="Longitude"
                        placeholder="e.g. 72.5714" 
                        :error="errors?.longitude"
                        :disabled="readonly"
                        inputClass="!w-full !rounded-md !border-slate-200 font-medium text-sm"
                    />
                </div>
                
                <div class="col-span-12   flex flex-col items-center justify-center border-2 border-dashed border-slate-100 dark:border-slate-800 rounded-xl bg-slate-50/30">
                    <MapPinIcon class="w-10 h-10 text-slate-200 mb-3" />
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] text-center">Interactive Map Preview coming soon</p>
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
