<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import { UserGroupIcon } from '@heroicons/vue/24/outline';
import PersonnelForm from './components/PersonnelForm.vue';
import PersonnelEditForm from './components/PersonnelEditForm.vue';

// Components
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import BaseButton from '@/Components/Base/BaseButton.vue';

interface Contact {
    contact_id?: string;
    contact_type: string;
    contact_value: string;
    is_primary: boolean;
}

interface Patron {
    id: number;
    legal_name: string;
}

interface Department {
    id: number;
    name: string;
}

interface Designation {
    id: number;
    name: string;
}

interface Personnel {
    id: number;
    employee_code: string;
    first_name: string;
    last_name: string | null;
    full_name: string;
    email: string | null;
    mobile: string | null;
    employment_type: string;
    gender: string | null;
    status: string;
    date_of_birth: string | null;
    joining_date: string;
    exit_date: string | null;
    pan: string | null;
    aadhaar: string | null;
    uan: string | null;
    esi_number: string | null;
    bank_account_no: string | null;
    bank_ifsc: string | null;
    bank_name: string | null;
    department_id: number | null;
    designation_id: number | null;
    reporting_manager_id: number | null;
    contacts: Contact[];
    patrons: Patron[];
    department?: Department | null;
    designation?: Designation | null;
    reporting_manager?: Personnel | null;
    salary_structures?: any[];
}

const props = defineProps<{
    personnel: Personnel[];
    patrons: Patron[];
    departments: Department[];
    designations: Designation[];
    managers: Personnel[];
    employmentTypes: string[];
    genders: string[];
    statuses: string[];
    contactTypes: string[];
    salaryComponents: any[];
}>();

const page = usePage();
const editingId = ref<number | null>(null);
const searchQuery = ref('');
const activeTabCreate = ref('details');
const activeTabEdit = ref('details');
const expandedRows = ref<Record<number, boolean>>({});

const filteredPersonnel = computed(() => {
    if (!searchQuery.value) return props.personnel;
    const q = searchQuery.value.toLowerCase();
    return props.personnel.filter((p: Personnel) => 
        p.first_name.toLowerCase().includes(q) ||
        (p.last_name && p.last_name.toLowerCase().includes(q)) ||
        p.employee_code.toLowerCase().includes(q) ||
        (p.email && p.email.toLowerCase().includes(q))
    );
});

const getInitialForm = () => ({
    employee_code: '',
    first_name: '',
    last_name: '',
    email: '',
    mobile: '',
    department_id: null as number | null,
    designation_id: null as number | null,
    reporting_manager_id: null as number | null,
    employment_type: 'permanent',
    gender: 'male' as string | null,
    status: 'active',
    date_of_birth: null as any,
    joining_date: null as any,
    exit_date: null as any,
    pan: '',
    aadhaar: '',
    uan: '',
    esi_number: '',
    bank_account_no: '',
    bank_ifsc: '',
    bank_name: '',
    contacts: [] as Contact[],
    patron_ids: [] as number[],
    salary_structures: [] as any[],
});

const createForm = useForm(getInitialForm());
const editForm = useForm(getInitialForm());

const resetEditForm = () => {
    editingId.value = null;
    expandedRows.value = {};
    editForm.reset();
    editForm.clearErrors();
    activeTabEdit.value = 'details';
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

const editPersonnel = (p: Personnel) => {
    editingId.value = p.id;
    editForm.employee_code = p.employee_code;
    editForm.first_name = p.first_name;
    editForm.last_name = p.last_name || '';
    editForm.email = p.email || '';
    editForm.mobile = p.mobile || '';
    editForm.department_id = p.department_id;
    editForm.designation_id = p.designation_id;
    editForm.reporting_manager_id = p.reporting_manager_id;
    editForm.employment_type = p.employment_type;
    editForm.gender = p.gender;
    editForm.status = p.status;
    editForm.date_of_birth = parseDate(p.date_of_birth);
    editForm.joining_date = parseDate(p.joining_date);
    editForm.exit_date = parseDate(p.exit_date);
    editForm.pan = p.pan || '';
    editForm.aadhaar = p.aadhaar || '';
    editForm.uan = p.uan || '';
    editForm.esi_number = p.esi_number || '';
    editForm.bank_account_no = p.bank_account_no || '';
    editForm.bank_ifsc = p.bank_ifsc || '';
    editForm.bank_name = p.bank_name || '';
    editForm.contacts = (p.contacts || []).map((c: any) => ({
        ...c,
        is_primary: c.is_primary === true || c.is_primary === 1 || c.is_primary === '1',
    }));
    editForm.patron_ids = p.patrons.map(patron => patron.id);
    editForm.salary_structures = (p.salary_structures || []).map((s: any) => ({
        id: s.id,
        salary_component_id: s.salary_component_id,
        amount: Number(s.amount),
        effective_from: parseDate(s.effective_from),
        effective_to: parseDate(s.effective_to),
    }));
};

const submitCreate = () => {
    createForm.post(route('personnel.store'), {
        onSuccess: () => {
            createForm.reset();
            createForm.clearErrors();
            activeTabCreate.value = 'details';
        },
    });
};

const submitEdit = () => {
    if (editingId.value) {
        const payload = {
            ...editForm.data(),
            date_of_birth: formatDateStr(editForm.date_of_birth),
            joining_date:  formatDateStr(editForm.joining_date),
            exit_date:     formatDateStr(editForm.exit_date),
            salary_structures: (editForm.salary_structures || []).map((s: any) => ({
                ...s,
                effective_from: formatDateStr(s.effective_from),
                effective_to:   formatDateStr(s.effective_to),
            })),
        };
        editForm.transform(() => payload).put(route('personnel.update', editingId.value), {
            onSuccess: () => resetEditForm(),
            onFinish: () => editForm.transform((d: any) => d),
        });
    }
};

const deletePersonnel = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            createForm.delete(route('personnel.destroy', id), {
                onSuccess: () => Swal.fire('Deleted!', 'Personnel record has been deleted.', 'success')
            });
        }
    });
};

const addContact = (f: any) => {
    f.contacts.push({
        contact_type: '',
        contact_value: '',
        is_primary: f.contacts.length === 0
    });
};

const removeContact = (f: any, index: number) => {
    f.contacts.splice(index, 1);
};

const addSalaryStructure = (f: any) => {
    f.salary_structures.push({
        salary_component_id: null,
        amount: 0,
        effective_from: new Date(),
        effective_to: null
    });
};

const removeSalaryStructure = (f: any, index: number) => {
    f.salary_structures.splice(index, 1);
};

const empTypeOptions = computed(() => (props.employmentTypes || []).map(t => ({ label: t.toUpperCase(), value: t })));
const genderOptions = computed(() => (props.genders || []).map(t => ({ label: t.toUpperCase(), value: t })));
const statusOptions = computed(() => (props.statuses || []).map(t => ({ label: t.toUpperCase(), value: t })));
const contactTypeOptions = computed(() => (props.contactTypes || []).map(t => ({ label: t, value: t })));
const patronOptions = computed(() => (props.patrons || []).map(p => ({ label: p.legal_name, value: p.id })));
const deptOptions = computed(() => (props.departments || []).map(d => ({ label: d.name, value: d.id })));
const desgOptions = computed(() => (props.designations || []).map(d => ({ label: d.name, value: d.id })));
const managerOptions = computed(() => (props.managers || []).map(m => ({ label: `${m.first_name} ${m.last_name || ''} (${m.employee_code})`, value: m.id })));
const salaryCompOptions = computed(() => (props.salaryComponents || []).map(c => ({ label: `${c.name} (${c.type.toUpperCase()})`, value: c.id })));

watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.success) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true,
                icon: 'success',
                title: flash.success
            });
        }
    },
    { immediate: true, deep: true }
);
</script>

<template>
    <AppLayout title="Personnel Directory">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="space-y-6">
                    
                    <!-- Creation Form Container -->
                    <div id="top-form-container">
                        <PersonnelForm 
                            :form="createForm"
                            v-model:activeTab="activeTabCreate"
                            :employmentTypeOptions="empTypeOptions"
                            :genderOptions="genderOptions"
                            :statusOptions="statusOptions"
                            :contactTypeOptions="contactTypeOptions"
                            :patronOptions="patronOptions"
                            :departmentOptions="deptOptions"
                            :designationOptions="desgOptions"
                            :managerOptions="managerOptions"
                            :resetForm="() => { activeTabCreate = 'details'; createForm.reset(); }"
                            :addContact="() => addContact(createForm)"
                            :removeContact="(index: number) => removeContact(createForm, index)"
                            :salaryComponentOptions="salaryCompOptions"
                            :addSalaryStructure="() => addSalaryStructure(createForm)"
                            :removeSalaryStructure="(index: number) => removeSalaryStructure(createForm, index)"
                            :submit="submitCreate"
                        />
                    </div>

                    <!-- Personnel Directory -->
                    <div class="bg-white dark:bg-slate-900 rounded-xl">
                        <BaseDataTable 
                            :value="filteredPersonnel" 
                            v-model:expandedRows="expandedRows"
                            dataKey="id"
                            stripedRows 
                            heading="Personnel Directory"
                            headingIcon="UserGroupIcon"
                            showSearch showSerial
                            paginator
                            :rows="10" 
                            :totalRecords="filteredPersonnel.length"
                            class="p-datatable-sm"
                            showExport
                            exportFilename="personnel-directory"
                        >
                            <template #toolbar>
                                <div class="flex items-center gap-2 px-3 py-1 bg-slate-50 rounded-lg border border-slate-100">
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ filteredPersonnel.length }} total personnel</span>
                                </div>
                            </template>

                            <Column header="Employee Code">
                                <template #body="slotProps">
                                    <span class="font-bold">{{ slotProps.data.employee_code }}</span>
                                </template>
                            </Column>
                            <Column header="Full Name">
                                <template #body="slotProps">
                                    <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ slotProps.data.first_name }} {{ slotProps.data.last_name || '' }}
                                    </span>
                                </template>
                            </Column>
                            <Column header="Dept / Desg">
                                <template #body="slotProps">
                                    <div class="flex flex-col text-[11px]">
                                        <span class="font-bold">{{ slotProps.data.department ? slotProps.data.department.name : '-' }}</span>
                                        <span class="text-slate-500">{{ slotProps.data.designation ? slotProps.data.designation.name : '-' }}</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="Status / Type">
                                <template #body="slotProps">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-xs font-semibold capitalize">{{ slotProps.data.employment_type }}</span>
                                        <Tag 
                                            :severity="slotProps.data.status === 'active' ? 'success' : 'danger'" 
                                            :value="slotProps.data.status.toUpperCase()"
                                            rounded
                                            class="w-fit"
                                        />
                                    </div>
                                </template>
                            </Column>
                            <Column header="Contacts">
                                <template #body="slotProps">
                                    <div class="flex flex-col text-[11px]">
                                        <span>{{ slotProps.data.email || '-' }}</span>
                                        <span>{{ slotProps.data.mobile || '-' }}</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="Actions" alignFrozen="right" frozen>
                                <template #body="slotProps">
                                    <div class="flex justify-end gap-2">
                                        <BaseButton 
                                            icon="pi pi-pencil" 
                                            severity="info" 
                                            text 
                                            rounded 
                                            @click="editPersonnel(slotProps.data); expandedRows = { [slotProps.data.id]: true }"
                                        />
                                        <BaseButton 
                                            icon="pi pi-trash" 
                                            severity="danger" 
                                            text 
                                            rounded 
                                            @click="deletePersonnel(slotProps.data.id)"
                                        />
                                    </div>
                                </template>
                            </Column>
                            <template #expansion="slotProps">
                                <div class="p-4 border rounded-xl bg-gray-50/50 dark:bg-slate-800/50">
                                    <PersonnelEditForm 
                                        :form="editForm"
                                        :personnelId="slotProps.data.id"
                                        :activeTab="activeTabEdit"
                                        @update:activeTab="(val: string) => activeTabEdit = val"
                                        :employmentTypeOptions="empTypeOptions"
                                        :genderOptions="genderOptions"
                                        :statusOptions="statusOptions"
                                        :contactTypeOptions="contactTypeOptions"
                                        :patronOptions="patronOptions"
                                        :departmentOptions="deptOptions"
                                        :designationOptions="desgOptions"
                                        :managerOptions="managerOptions"
                                        :resetForm="resetEditForm"
                                        :addContact="() => addContact(editForm)"
                                        :removeContact="(index: number) => removeContact(editForm, index)"
                                        :salaryComponentOptions="salaryCompOptions"
                                        :addSalaryStructure="() => addSalaryStructure(editForm)"
                                        :removeSalaryStructure="(index: number) => removeSalaryStructure(editForm, index)"
                                        :submit="submitEdit"
                                    />
                                </div>
                            </template>
                        </BaseDataTable>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable-thead > tr > th) {
    @apply bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 font-bold uppercase text-[10px] tracking-wider py-4;
}
</style>
