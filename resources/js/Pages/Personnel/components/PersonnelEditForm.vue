<script setup lang="ts">
import { 
    UserIcon, 
    PhoneIcon, 
    BriefcaseIcon, 
    CalendarDaysIcon,
    PlusIcon,
    TrashIcon,
    IdentificationIcon,
    CreditCardIcon,
    BuildingOfficeIcon,
    ShieldCheckIcon,
    BanknotesIcon
} from '@heroicons/vue/24/outline';
import BaseCard from '@/Components/Base/BaseCard.vue';
import Swal from 'sweetalert2';

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
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';

import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    personnel: any;
    activeTab: string;
    employmentTypeOptions: any[];
    genderOptions: any[];
    statusOptions: any[];
    contactTypeOptions: any[];
    patronOptions: any[];
    departmentOptions: any[];
    designationOptions: any[];
    managerOptions: any[];
    salaryComponentOptions: any[];
}>();

const emit = defineEmits(['update:activeTab', 'success', 'cancel']);

const handleTabUpdate = (val: string) => {
    emit('update:activeTab', val);
};

const handlePrimaryToggle = (index: number, val: any) => {
    if (val) {
        form.contacts.forEach((c: any, i: number) => {
            if (i !== index) {
                c.is_primary = false;
            }
        });
    }
};

const parseDate = (val: any): Date | null => {
    if (!val) return null;
    const clean = typeof val === 'string' ? val.split('T')[0] : val;
    const d = new Date(clean);
    return isNaN(d.getTime()) ? null : d;
};

const formatDateStr = (val: any): string | null => {
    if (!val) return null;
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(val)) return val;
    const d = val instanceof Date ? val : new Date(String(val).split('T')[0]);
    if (isNaN(d.getTime())) return null;
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
};

const formCache: Record<number, any> = {};

const getInitialData = () => ({
    employee_code: props.personnel.employee_code || '',
    first_name: props.personnel.first_name || '',
    last_name: props.personnel.last_name || '',
    email: props.personnel.email || '',
    mobile: props.personnel.mobile || '',
    department_id: props.personnel.department_id,
    designation_id: props.personnel.designation_id,
    reporting_manager_id: props.personnel.reporting_manager_id,
    employment_type: props.personnel.employment_type || 'permanent',
    gender: props.personnel.gender,
    status: props.personnel.status || 'active',
    date_of_birth: parseDate(props.personnel.date_of_birth),
    joining_date: parseDate(props.personnel.joining_date),
    exit_date: parseDate(props.personnel.exit_date),
    pan: props.personnel.pan || '',
    aadhaar: props.personnel.aadhaar || '',
    uan: props.personnel.uan || '',
    esi_number: props.personnel.esi_number || '',
    bank_account_no: props.personnel.bank_account_no || '',
    bank_ifsc: props.personnel.bank_ifsc || '',
    bank_name: props.personnel.bank_name || '',
    contacts: (props.personnel.contacts || []).map((c: any) => ({
        ...c,
        is_primary: c.is_primary === true || c.is_primary === 1 || c.is_primary === '1',
    })),
    patron_ids: (props.personnel.patrons || []).map((patron: any) => patron.id),
    salary_structures: (props.personnel.salary_structures || []).map((s: any) => ({
        id: s.id,
        salary_component_id: s.salary_component_id,
        amount: Number(s.amount),
        effective_from: parseDate(s.effective_from),
        effective_to: parseDate(s.effective_to),
    })),
});

const form = useForm(formCache[props.personnel.id] || getInitialData());

import { watch, onBeforeUnmount } from 'vue';

watch(() => form.data(), (newVal) => {
    formCache[props.personnel.id] = newVal;
}, { deep: true });

onBeforeUnmount(() => {
    if (!form.hasErrors) {
        // Option to clear if we don't need it, but keeping it helps.
    }
});

const addContact = () => {
    form.contacts.push({
        contact_type: '',
        contact_value: '',
        is_primary: form.contacts.length === 0
    });
};

const removeContact = (index: number) => {
    form.contacts.splice(index, 1);
};

const addSalaryStructure = () => {
    form.salary_structures.push({
        salary_component_id: null,
        amount: 0,
        effective_from: new Date(),
        effective_to: null
    });
};

const removeSalaryStructure = (index: number) => {
    form.salary_structures.splice(index, 1);
};

const submit = () => {
    // Client-side validation
    let hasContactError = false;
    let hasSalaryError = false;
    const errors: Record<string, string> = {};

    (form.contacts || []).forEach((contact: any, index: number) => {
        if (!contact.contact_type) {
            errors[`contacts.${index}.contact_type`] = 'The contact type field is required.';
            hasContactError = true;
        }
        if (!contact.contact_value) {
            errors[`contacts.${index}.contact_value`] = 'The contact detail field is required.';
            hasContactError = true;
        }
    });

    (form.salary_structures || []).forEach((struct: any, index: number) => {
        if (!struct.salary_component_id) {
            errors[`salary_structures.${index}.salary_component_id`] = 'The salary component field is required.';
            hasSalaryError = true;
        }
        if (struct.amount === null || struct.amount === undefined || struct.amount === '') {
            errors[`salary_structures.${index}.amount`] = 'The amount field is required.';
            hasSalaryError = true;
        } else if (isNaN(Number(struct.amount)) || Number(struct.amount) < 0) {
            errors[`salary_structures.${index}.amount`] = 'The amount must be a positive number.';
            hasSalaryError = true;
        }
        if (!struct.effective_from) {
            errors[`salary_structures.${index}.effective_from`] = 'The effective from date is required.';
            hasSalaryError = true;
        }
    });

    if (Object.keys(errors).length > 0) {
        form.setError(errors);
        if (hasContactError) {
            emit('update:activeTab', 'contacts');
        } else if (hasSalaryError) {
            emit('update:activeTab', 'salary_structure');
        }
        return;
    }

    const payload = {
        ...form.data(),
        date_of_birth: formatDateStr(form.date_of_birth),
        joining_date:  formatDateStr(form.joining_date),
        exit_date:     formatDateStr(form.exit_date),
        salary_structures: (form.salary_structures || []).map((s: any) => ({
            ...s,
            effective_from: formatDateStr(s.effective_from),
            effective_to:   formatDateStr(s.effective_to),
        })),
    };
    form.transform(() => payload).put(route('personnel.update', props.personnel.id), {
        onSuccess: () => {
            delete formCache[props.personnel.id];
            emit('success');
        },
        onError: (errs) => {
            const errorKeys = Object.keys(errs);
            let targetTab = 'details';

            if (errorKeys.some(k => k.startsWith('contacts.'))) {
                targetTab = 'contacts';
            } else if (errorKeys.some(k => k.startsWith('salary_structures.'))) {
                targetTab = 'salary_structure';
            } else if (errorKeys.length > 0) {
                const firstKey = errorKeys[0];
                const tabMapping: Record<string, string> = {
                    department_id: 'employment', designation_id: 'employment', reporting_manager_id: 'employment', employment_type: 'employment', joining_date: 'employment', exit_date: 'employment',
                    pan: 'statutory', aadhaar: 'statutory', uan: 'statutory', esi_number: 'statutory',
                    bank_account_no: 'finance', bank_ifsc: 'finance', bank_name: 'finance',
                    email: 'contacts', mobile: 'contacts',
                    patron_ids: 'patrons'
                };
                targetTab = tabMapping[firstKey] || 'details';
            }
            
            emit('update:activeTab', targetTab);
            
            setTimeout(() => {
                const invalidElements = document.getElementsByClassName('p-invalid');
                if (invalidElements.length > 0) {
                    invalidElements[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 100);

            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: errs[errorKeys[0]],
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000
            });
        },
        onFinish: () => form.transform((d: any) => d),
    });
};
</script>

<template>
    <div class="text-sm">
        <Tabs :value="activeTab" @update:value="handleTabUpdate">
            <TabList class="mb-4">
                <Tab value="details">
                    <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                        <UserIcon class="w-4 h-4" /> Personal
                    </div>
                </Tab>
                <Tab value="employment">
                    <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                        <BriefcaseIcon class="w-4 h-4" /> Employment
                    </div>
                </Tab>
                <Tab value="statutory">
                    <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                        <ShieldCheckIcon class="w-4 h-4" /> Statutory
                    </div>
                </Tab>
                <Tab value="finance">
                    <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                        <CreditCardIcon class="w-4 h-4" /> Financial
                    </div>
                </Tab>
                <Tab value="salary_structure">
                    <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                        <BanknotesIcon class="w-4 h-4" /> Salary Structure
                    </div>
                </Tab>
                <Tab value="contacts">
                    <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                        <PhoneIcon class="w-4 h-4" /> Contacts
                    </div>
                </Tab>
                <Tab value="patrons">
                    <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                        <BuildingOfficeIcon class="w-4 h-4" /> Links
                    </div>
                </Tab>
            </TabList>

            <TabPanels class="!p-0 bg-transparent">
                <!-- PERSONAL DETAILS -->
                <TabPanel value="details">
                    <div class="space-y-6 py-2">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Employee Code</label>
                                <div class="flex items-center h-10 px-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-slate-800/50 text-gray-700 dark:text-gray-300 text-xs font-bold tracking-widest gap-2 select-none">
                                    <span class="pi pi-id-card text-[10px] text-indigo-400"></span>
                                    {{ form.employee_code }}
                                </div>
                            </div>
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
                                <DatePicker v-model="form.date_of_birth" dateFormat="yy-mm-dd" showIcon iconDisplay="input" placeholder="Select Date" class="w-full" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Current Status</label>
                                <BaseSelect v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Current Status" class="w-full" />
                            </div>
                        </div>
                    </div>
                </TabPanel>

                <!-- EMPLOYMENT DETAILS -->
                <TabPanel value="employment">
                    <div class="space-y-6 py-2">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Department</label>
                                <BaseSelect v-model="form.department_id" :options="departmentOptions" optionLabel="label" optionValue="value" placeholder="Select Department" filter class="w-full" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Designation</label>
                                <BaseSelect v-model="form.designation_id" :options="designationOptions" optionLabel="label" optionValue="value" placeholder="Select Designation" filter class="w-full" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Reporting Manager</label>
                                <BaseSelect v-model="form.reporting_manager_id" :options="managerOptions" optionLabel="label" optionValue="value" placeholder="Select Manager" filter class="w-full" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Employment Type <span class="text-red-500">*</span></label>
                                <BaseSelect v-model="form.employment_type" :options="employmentTypeOptions" optionLabel="label" optionValue="value" placeholder="Select Type" class="w-full" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Joining Date <span class="text-red-500">*</span></label>
                                <DatePicker v-model="form.joining_date" dateFormat="yy-mm-dd" showIcon iconDisplay="input" placeholder="Select Date" class="w-full" />
                                <small v-if="form.errors.joining_date" class="p-error text-[10px]">{{ form.errors.joining_date }}</small>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Exit Date</label>
                                <DatePicker v-model="form.exit_date" dateFormat="yy-mm-dd" showIcon iconDisplay="input" placeholder="Select Date" class="w-full" />
                            </div>
                        </div>
                    </div>
                </TabPanel>

                <!-- STATUTORY DETAILS -->
                <TabPanel value="statutory">
                    <div class="space-y-6 py-2">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">PAN Number</label>
                                <BaseInput v-model="form.pan" placeholder="ABCDE1234F" :class="{'p-invalid': form.errors.pan}" />
                                <small v-if="form.errors.pan" class="p-error text-[10px]">{{ form.errors.pan }}</small>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Aadhaar Number</label>
                                <BaseInput v-model="form.aadhaar" placeholder="1234 5678 9012" :class="{'p-invalid': form.errors.aadhaar}" />
                                <small v-if="form.errors.aadhaar" class="p-error text-[10px]">{{ form.errors.aadhaar }}</small>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">UAN (PF Number)</label>
                                <BaseInput v-model="form.uan" placeholder="100XXXXXXXXX" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">ESI Number</label>
                                <BaseInput v-model="form.esi_number" placeholder="ESI No." />
                            </div>
                        </div>
                    </div>
                </TabPanel>

                <!-- FINANCIAL DETAILS -->
                <TabPanel value="finance">
                    <div class="space-y-6 py-2">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Bank Account Number</label>
                                <BaseInput v-model="form.bank_account_no" placeholder="Enter bank account no" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Bank IFSC Code</label>
                                <BaseInput v-model="form.bank_ifsc" placeholder="SBIN0001234" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Bank Name</label>
                                <BaseInput v-model="form.bank_name" placeholder="State Bank of India" />
                            </div>
                        </div>
                    </div>
                </TabPanel>

                <!-- SALARY STRUCTURE -->
                <TabPanel value="salary_structure">
                    <div class="space-y-6 py-2">
                        <div v-for="(struct, index) in form.salary_structures" :key="index" class="bg-gray-50/50 dark:bg-slate-800/30 border border-gray-100 dark:border-gray-700 p-6 rounded-xl relative group">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Salary Component <span class="text-red-500">*</span></label>
                                    <BaseSelect v-model="struct.salary_component_id" :options="salaryComponentOptions" optionLabel="label" optionValue="value" placeholder="Select Component" filter class="w-full" :error="form.errors[`salary_structures.${index}.salary_component_id`]" />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Monthly Amount <span class="text-red-500">*</span></label>
                                    <BaseInput type="number" step="0.01" v-model="struct.amount" placeholder="0.00" class="w-full" :error="form.errors[`salary_structures.${index}.amount`]" />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Effective From <span class="text-red-500">*</span></label>
                                    <BaseDatePicker v-model="struct.effective_from" iconDisplay="input" placeholder="Select Date" class="w-full" :error="form.errors[`salary_structures.${index}.effective_from`]" />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Effective To</label>
                                    <BaseDatePicker v-model="struct.effective_to" iconDisplay="input" placeholder="Select Date" class="w-full" :error="form.errors[`salary_structures.${index}.effective_to`]" />
                                </div>
                            </div>
                            <BaseActionButton 
                                icon="pi pi-trash" 
                                severity="danger" 
                                tooltip="Remove Component Allocation"
                                class="absolute -top-2 -right-2 opacity-0 group-hover:opacity-100 transition-opacity" 
                                @click="removeSalaryStructure(index)"
                            />
                        </div>
                        <BaseButton 
                            type="button"
                            severity="info" 
                            outlined 
                            class="w-full text-indigo-600 border-dashed" 
                            label="Allocate Salary Component"
                            icon="pi pi-plus"
                            @click="addSalaryStructure"
                        />
                    </div>
                </TabPanel>

                <!-- CONTACTS MATRIX -->
                <TabPanel value="contacts">
                    <div class="space-y-6 py-2">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Primary Email</label>
                                <BaseInput v-model="form.email" placeholder="employee@company.com" :class="{'p-invalid': form.errors.email}" />
                                <small v-if="form.errors.email" class="p-error text-[10px]">{{ form.errors.email }}</small>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Primary Mobile</label>
                                <BaseInput v-model="form.mobile" placeholder="+91 XXXXX XXXXX" />
                            </div>
                        </div>

                        <div v-for="(contact, index) in form.contacts" :key="index" class="bg-gray-50/50 dark:bg-slate-800/30 border border-gray-100 dark:border-gray-700 p-6 rounded-xl relative group">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Contact Type <span class="text-red-500">*</span></label>
                                    <BaseSelect v-model="contact.contact_type" :options="contactTypeOptions" optionLabel="label" optionValue="value" placeholder="e.g. Mobile" class="w-full" :error="form.errors[`contacts.${index}.contact_type`]" />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Contact Detail <span class="text-red-500">*</span></label>
                                    <BaseInput v-model="contact.contact_value" placeholder="Enter value..." class="w-full" :error="form.errors[`contacts.${index}.contact_value`]" />
                                </div>
                            </div>
                            <div class="flex items-center gap-2 mt-4">
                                <ToggleSwitch v-model="contact.is_primary" @update:modelValue="(val) => handlePrimaryToggle(index, val)" />
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

                <!-- ASSOCIATED PATRONS -->
                <TabPanel value="patrons">
                    <div class="space-y-6 py-2">
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
                label="Commit Changes"
                cancel-label="Cancel"
                mode="edit"
                class="mt-8 pt-6 border-t border-gray-100 dark:border-gray-700"
                @cancel="emit('cancel')"
                @submit="submit"
            />
        </Tabs>
    </div>
</template>
