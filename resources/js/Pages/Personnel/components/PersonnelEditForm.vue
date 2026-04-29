<script setup lang="ts">
import { 
    UserIcon, 
    PhoneIcon, 
    BriefcaseIcon, 
    PlusIcon,
    TrashIcon,
    IdentificationIcon,
    ArrowPathIcon,
    PencilSquareIcon
} from '@heroicons/vue/24/outline';
import BaseCard from '@/Components/Base/BaseCard.vue';

// PrimeVue
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import MultiSelect from 'primevue/multiselect';
import DatePicker from 'primevue/datepicker';
import ToggleSwitch from 'primevue/toggleswitch';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseActionButton from '@/Components/Base/BaseActionButton.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

const props = defineProps<{
    form: any;
    personnelId: number;
    activeTab: string;
    employeeTypeOptions: any[];
    genderOptions: any[];
    statusOptions: any[];
    contactTypeOptions: any[];
    patronOptions: any[];
    resetForm: () => void;
    addContact: () => void;
    removeContact: (index: number) => void;
    submit: () => void;
}>();

const emit = defineEmits(['update:activeTab']);

const handleTabUpdate = (val: string) => {
    emit('update:activeTab', val);
};
</script>

<template>
    <!-- <BaseCard class="text-sm"> -->
        <!-- <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <PencilSquareIcon class="w-5 h-5 text-indigo-500" />
                    <span class="text-md font-semibold uppercase text-gray-800 dark:text-gray-100">
                        Edit Personnel: <span class="text-indigo-600 dark:text-indigo-400">{{ form.first_name }} {{ form.last_name }}</span>
                    </span>
                </div>
                <BaseButton 
                    label="Close" 
                    text 
                    severity="secondary" 
                    @click="resetForm" 
                />
            </div>
        </template> -->

        <div class="">
            <Tabs :value="activeTab" @update:value="handleTabUpdate">
                <TabList class="pb-4">
                    <Tab value="details">
                        <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                            <UserIcon class="w-4 h-4" /> Personnel Info
                        </div>
                    </Tab>
                    <Tab value="contacts">
                        <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                            <PhoneIcon class="w-4 h-4" /> Contact Matrix
                        </div>
                    </Tab>
                    <Tab value="patrons">
                        <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                            <IdentificationIcon class="w-4 h-4" /> Links
                        </div>
                    </Tab>
                </TabList>

                <TabPanels class="!p-0">
                    <TabPanel value="details">
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
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
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Employee Type <span class="text-red-500">*</span></label>
                                    <BaseSelect v-model="form.employee_type" :options="employeeTypeOptions" optionLabel="label" optionValue="value" placeholder="Select Type" class="w-full">
                                        <template #value="slotProps">
                                            <div v-if="slotProps.value" class="flex items-center gap-2">
                                                <BriefcaseIcon class="w-4 h-4 text-gray-400" />
                                                <span class="text-xs">{{ slotProps.value }}</span>
                                            </div>
                                            <span v-else class="text-xs text-gray-400">{{ slotProps.placeholder }}</span>
                                        </template>
                                    </BaseSelect>
                                    <small v-if="form.errors.employee_type" class="p-error text-[10px]">{{ form.errors.employee_type }}</small>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Gender</label>
                                    <BaseSelect v-model="form.gender" :options="genderOptions" optionLabel="label" optionValue="value" placeholder="Select Gender" class="w-full" />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Status</label>
                                    <BaseSelect v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Current Status" class="w-full" />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Date of Birth</label>
                                    <DatePicker v-model="form.date_of_birth" dateFormat="yy-mm-dd" showIcon iconDisplay="input" placeholder="Select date" class="w-full" />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Joining Date</label>
                                    <DatePicker v-model="form.joining_date" dateFormat="yy-mm-dd" showIcon iconDisplay="input" placeholder="Select date" class="w-full" />
                                </div>
                            </div>
                        </div>
                    </TabPanel>

                    <TabPanel value="contacts">
                        <div class="space-y-6">
                            <div v-for="(contact, index) in form.contacts" :key="index" class="bg-gray-50/50 dark:bg-slate-800/30 border border-gray-100 dark:border-gray-700 p-6 rounded-xl relative group">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Contact Type</label>
                                        <BaseSelect v-model="contact.contact_type" :options="contactTypeOptions" optionLabel="label" optionValue="value" placeholder="e.g. Mobile" class="w-full" />
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Contact Detail</label>
                                        <BaseInput v-model="contact.contact_value" placeholder="Enter value..." class="w-full" />
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 mt-4">
                                    <ToggleSwitch v-model="contact.is_primary" />
                                    <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Primary Contact Point</span>
                                </div>
                                <BaseActionButton 
                                    icon="pi pi-trash" 
                                    severity="danger" 
                                    tooltip="Remove Contact"
                                    class="absolute -top-2 -right-2 opacity-0 group-hover:opacity-100 transition-opacity" 
                                    @click="removeContact(index)"
                                />
                            </div>
                            <BaseButton 
                                type="button"
                                severity="info" 
                                outlined 
                                class="w-full text-indigo-600 border-dashed" 
                                label="Integrate New Communication Channel"
                                icon="pi pi-plus"
                                @click="addContact"
                            />
                        </div>
                    </TabPanel>

                    <TabPanel value="patrons">
                        <div class="space-y-6">
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Associated Patrons</label>
                                <MultiSelect 
                                    v-model="form.patron_ids" 
                                    :options="patronOptions" 
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="Connect to Patrons..."
                                    filter
                                    class="w-full"
                                />
                            </div>
                        </div>
                    </TabPanel>
                </TabPanels>

                <BaseFormActions 
                    :loading="form.processing"
                    update-label="Update Registry"
                    cancel-label="Discard Changes"
                    mode="update"
                    class="pt-2 border-t border-gray-100 dark:border-gray-700"
                    @cancel="resetForm"
                    @submit="submit"
                />
            </Tabs>
        </div>
    <!-- </BaseCard> -->
</template>


