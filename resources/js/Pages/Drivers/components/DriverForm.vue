<script setup lang="ts">
import { 
    UserIcon, 
    IdentificationIcon,
    UserPlusIcon,
    LinkIcon
} from '@heroicons/vue/24/outline';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

const props = defineProps<{
    form: any;
    personnelOptions: any[];
    genderOptions: any[];
    licenseTypeOptions: any[];
    statusOptions: any[];
    resetForm: () => void;
    submit: () => void;
}>();
</script>

<template>
    <BaseCard class="text-sm">
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 w-full">
                <div class="flex items-center gap-2">
                    <UserPlusIcon class="w-5 h-5 text-indigo-500" />
                    <span class="text-md font-semibold uppercase text-gray-800 dark:text-gray-100">
                        New Driver Registry
                    </span>
                </div>
                
                <!-- Toggle mode -->
                <div class="flex p-1 bg-slate-100 dark:bg-slate-800 rounded-xl w-fit">
                    <button
                        type="button"
                        @click="form.is_promoting = false"
                        class="flex items-center gap-2 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider rounded-lg transition-all"
                        :class="!form.is_promoting ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-sm' : 'text-slate-400 hover:text-slate-650'"
                    >
                        <UserPlusIcon class="w-3.5 h-3.5" />
                        Create Profile
                    </button>
                    <button
                        type="button"
                        @click="form.is_promoting = true"
                        class="flex items-center gap-2 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider rounded-lg transition-all"
                        :class="form.is_promoting ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-sm' : 'text-slate-400 hover:text-slate-655'"
                    >
                        <LinkIcon class="w-3.5 h-3.5" />
                        Link Employee
                    </button>
                </div>
            </div>
        </template>

        <div class="py-1">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Section: Link existing employee -->
                <div v-if="form.is_promoting" class="grid grid-cols-1 md:grid-cols-2 gap-6 p-5 bg-slate-50 dark:bg-slate-850/40 rounded-2xl border border-slate-100 dark:border-slate-800/80">
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Select Employee <span class="text-red-500">*</span></label>
                        <BaseSelect 
                            v-model="form.personnel_id" 
                            :options="personnelOptions" 
                            optionLabel="label" 
                            optionValue="value" 
                            placeholder="Choose Employee to Promote" 
                            :error="form.errors.personnel_id"
                            filter
                        />
                        <span class="text-[9px] font-semibold text-slate-400 uppercase tracking-widest mt-1 block">
                            Only showing active employees not registered as drivers.
                        </span>
                    </div>
                </div>

                <!-- Section: Create new Personnel -->
                <div v-else class="grid grid-cols-1 md:grid-cols-5 gap-6">
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">First Name <span class="text-red-500">*</span></label>
                        <BaseInput v-model="form.first_name" placeholder="Enter first name" :class="{'p-invalid': form.errors.first_name}" />
                        <small v-if="form.errors.first_name" class="p-error text-[10px]">{{ form.errors.first_name }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Last Name</label>
                        <BaseInput v-model="form.last_name" placeholder="Enter last name" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Gender</label>
                        <BaseSelect v-model="form.gender" :options="genderOptions" optionLabel="label" optionValue="value" placeholder="Select Gender" class="w-full" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Date of Birth</label>
                        <BaseDatePicker v-model="form.date_of_birth" placeholder="Select DOB" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Joining Date</label>
                        <BaseDatePicker v-model="form.joining_date" placeholder="Select date" />
                    </div>
                </div>

                <!-- Driver License details -->
                <div class="border-t border-gray-100 dark:border-gray-800 pt-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
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
                </div>

                <BaseFormActions 
                    :loading="form.processing"
                    label="Register Driver"
                    cancel-label="Reset"
                    mode="add"
                    class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-800"
                    @cancel="resetForm"
                    @submit="submit"
                />
            </form>
        </div>
    </BaseCard>
</template>
