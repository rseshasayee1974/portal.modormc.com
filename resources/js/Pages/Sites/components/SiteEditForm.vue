<script setup lang="ts">
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Button from 'primevue/button';
import ToggleSwitch from 'primevue/toggleswitch';

const props = defineProps<{
    form: any;
    siteId: number;
    title: string;
    plantOptions: any[];
    typeOptions: any[];
    resetForm: () => void;
    submit: () => void;
}>();

</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900/50 p-8 rounded-3xl border border-dashed border-indigo-200 dark:border-indigo-900/40 shadow-sm">
        <div class="flex justify-between items-center mb-8">
            <h3 class="flex items-center text-lg font-bold text-gray-800 dark:text-gray-200">
                <MapPinIcon class="w-6 h-6 mr-3 text-indigo-500" />
                Quick Update: <span class="ml-2 text-indigo-600 font-black">{{ title }}</span>
            </h3>
            <div class="flex gap-3">
                <Button label="Cancel" variant="text" @click="resetForm" class="px-6 rounded-xl" severity="secondary" />
                <Button label="Commit Changes" icon="pi pi-check" :loading="form.processing" @click="submit" class="px-6 rounded-xl shadow-lg shadow-indigo-500/20" />
            </div>
        </div>

        <form @submit.prevent="submit">
            <div class="grid grid-cols-12 gap-6">
                <!-- Row 1 -->
                <div class="col-span-12 md:col-span-3 flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Operation Name <span class="text-red-500">*</span></label>
                    <BaseInput v-model="form.name" placeholder="e.g. Loading Bay A" :invalid="!!form.errors.name" fluid />
                    <small v-if="form.errors.name" class="text-red-500 font-medium">{{ form.errors.name }}</small>
                </div>

                <div class="col-span-12 md:col-span-3 flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Internal Code</label>
                    <BaseInput v-model="form.code" placeholder="S-001" :invalid="!!form.errors.code" fluid />
                    <small v-if="form.errors.code" class="text-red-500 font-medium">{{ form.errors.code }}</small>
                </div>
                
                <div class="col-span-12 md:col-span-3 flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Parent Facility <span class="text-red-500">*</span></label>
                    <BaseSelect v-model="form.plant_id" :options="plantOptions" optionLabel="label" optionValue="value" :invalid="!!form.errors.plant_id" fluid />
                    <small v-if="form.errors.plant_id" class="text-red-500 font-medium">{{ form.errors.plant_id }}</small>
                </div>

                <div class="col-span-12 md:col-span-3 flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Logic Type <span class="text-red-500">*</span></label>
                    <BaseSelect v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" :invalid="!!form.errors.type" fluid />
                    <small v-if="form.errors.type" class="text-red-500 font-medium">{{ form.errors.type }}</small>
                </div>

                <!-- Row 2 -->
                <div class="col-span-12 md:col-span-3 flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">GPS Latitude</label>
                    <BaseInput v-model="form.latitude" placeholder="00.000000" :invalid="!!form.errors.latitude" fluid />
                    <small v-if="form.errors.latitude" class="text-red-500 font-medium">{{ form.errors.latitude }}</small>
                </div>
                
                <div class="col-span-12 md:col-span-3 flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">GPS Longitude</label>
                    <BaseInput v-model="form.longitude" placeholder="00.000000" :invalid="!!form.errors.longitude" fluid />
                    <small v-if="form.errors.longitude" class="text-red-500 font-medium">{{ form.errors.longitude }}</small>
                </div>

                <div class="col-span-12 md:col-span-3 flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Operational Status</label>
                    <div class="flex items-center gap-4 h-11 px-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-inner">
                        <ToggleSwitch 
                            :modelValue="form.status === 'Active'" 
                            @update:modelValue="form.status = $event ? 'Active' : 'InActive'" 
                        />
                        <span class="text-xs font-black uppercase tracking-widest transition-colors duration-300" :class="form.status === 'Active' ? 'text-emerald-600' : 'text-rose-500'">
                            {{ form.status === 'Active' ? 'ACTIVE' : 'INACTIVE' }}
                        </span>
                    </div>
                </div>

                <div class="col-span-12 md:col-span-3 flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Security Restriction</label>
                    <div class="flex items-center gap-4 h-11 px-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-inner">
                        <ToggleSwitch v-model="form.is_restricted" />
                        <span class="text-xs font-black uppercase tracking-widest" :class="form.is_restricted ? 'text-red-500' : 'text-green-600'">
                            {{ form.is_restricted ? 'Access Restricted' : 'Open Access' }}
                        </span>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>

<style scoped>
/* Scoped styles removed as Tailwind and PrimeVue handle tokens */
</style>

