<script setup lang="ts">
import { 
    UserIcon, 
    IdentificationIcon,
    PencilSquareIcon
} from '@heroicons/vue/24/outline';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';

const props = defineProps<{
    form: any;
    driverId: number;
    genderOptions: any[];
    licenseTypeOptions: any[];
    statusOptions: any[];
    resetForm: () => void;
    submit: () => void;
}>();
</script>

<template>
    <div class="p-2">
        <div class="flex items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-2">
                <PencilSquareIcon class="w-5 h-5 text-indigo-500" />
                <span class="text-xs font-black uppercase text-gray-800 dark:text-gray-100 tracking-wider">
                    Edit Driver: <span class="text-indigo-600 dark:text-indigo-400 font-bold">{{ form.first_name }} {{ form.last_name || '' }}</span>
                </span>
            </div>
            <BaseButton 
                label="Close" 
                text 
                severity="secondary" 
                @click="resetForm" 
                size="small"
            />
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Base Personnel Fields -->
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">First Name <span class="text-red-500">*</span></label>
                    <BaseInput v-model="form.first_name" placeholder="Enter first name" :class="{'p-invalid': form.errors.first_name}" />
                    <small v-if="form.errors.first_name" class="p-error text-[10px]">{{ form.errors.first_name }}</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Last Name</label>
                    <BaseInput v-model="form.last_name" placeholder="Enter last name" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 pt-2">
                <!-- Driver Specific Fields -->
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">License Number <span class="text-red-500">*</span></label>
                    <BaseInput v-model="form.license_number" placeholder="Enter license number" :class="{'p-invalid': form.errors.license_number}" />
                    <small v-if="form.errors.license_number" class="p-error text-[10px]">{{ form.errors.license_number }}</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">License Type <span class="text-red-500">*</span></label>
                    <BaseSelect v-model="form.license_type" :options="licenseTypeOptions" optionLabel="label" optionValue="value" placeholder="Select Type" class="w-full" />
                    <small v-if="form.errors.license_type" class="p-error text-[10px]">{{ form.errors.license_type }}</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">License Expiry Date</label>
                    <BaseDatePicker v-model="form.license_expiry_date" placeholder="Select date" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Status</label>
                    <BaseSelect v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Select Status" class="w-full" />
                </div>
            </div>

            <BaseFormActions 
                :loading="form.processing"
                update-label="Update Driver details"
                cancel-label="Discard Changes"
                mode="update"
                class="pt-6 border-t border-gray-100 dark:border-gray-800"
                @cancel="resetForm"
                @submit="submit"
            />
        </form>
    </div>
</template>
