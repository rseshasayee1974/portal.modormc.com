<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import { BanknotesIcon, CogIcon, CalendarIcon, PlusIcon, SparklesIcon } from '@heroicons/vue/24/outline';

// Components
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import DatePicker from 'primevue/datepicker';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';

interface Personnel {
    id: number;
    first_name: string;
    last_name: string | null;
    employee_code: string;
}

interface SalaryComponent {
    id: number;
    name: string;
    type: string;
    calculation_type: string;
    default_value: number | string;
    is_taxable: boolean;
    is_statutory: boolean;
}

interface PayrollPeriod {
    id: number;
    name: string;
    from_date: string;
    to_date: string;
    status: string;
}

interface Payslip {
    id: number;
    payroll_period_id: number;
    personnel_id: number;
    payslip_no: string;
    working_days: number;
    present_days: number;
    absent_days: number;
    gross_salary: number | string;
    total_earnings: number | string;
    total_deductions: number | string;
    net_salary: number | string;
    status: string;
    personnel?: Personnel;
    payroll_period?: PayrollPeriod;
}

const props = defineProps<{
    payslips: Payslip[];
    payrollPeriods: PayrollPeriod[];
    personnel: Personnel[];
    salaryComponents: SalaryComponent[];
    statuses: string[];
}>();

const page = usePage();
const activeTab = ref('payslips');
const editingPeriodId = ref<number | null>(null);
const editingCompId = ref<number | null>(null);
const compliancePeriodId = ref<number | null>(null);

const downloadEcr = () => {
    if (!compliancePeriodId.value) {
        Swal.fire('Error', 'Please select a payroll cycle first.', 'error');
        return;
    }
    window.open(route('payslips.export-ecr', { payroll_period_id: compliancePeriodId.value }), '_blank');
};

const downloadEsic = () => {
    if (!compliancePeriodId.value) {
        Swal.fire('Error', 'Please select a payroll cycle first.', 'error');
        return;
    }
    window.open(route('payslips.export-esic', { payroll_period_id: compliancePeriodId.value }), '_blank');
};

// Forms
const genForm = useForm({
    payroll_period_id: null as number | null,
});

const periodForm = useForm({
    name: '',
    from_date: null as any,
    to_date: null as any,
    status: 'draft',
});

const compForm = useForm({
    name: '',
    type: 'earning',
    calculation_type: '₹',
    default_value: 0,
    is_taxable: true,
    is_statutory: false,
});

const periodOptions = computed(() => 
    props.payrollPeriods.map(p => ({ label: `${p.name} (${p.status.toUpperCase()})`, value: p.id }))
);

const typeOptions = [
    { label: 'EARNING', value: 'earning' },
    { label: 'DEDUCTION', value: 'deduction' },
];

const calcOptions = [
    { label: 'FIXED AMOUNT', value: '₹' },
    { label: '%', value: '%' },
    { label: 'FORMULA', value: 'formula' },
    { label: 'ATTENDANCE BASED', value: 'attendance_based' },
];

const statusOptions = [
    { label: 'DRAFT', value: 'draft' },
    { label: 'PROCESSING', value: 'processing' },
    { label: 'COMPLETED', value: 'completed' },
    { label: 'LOCKED', value: 'locked' },
    { label: 'FAILED', value: 'failed' },
];

// Period Actions
const editPeriod = (p: PayrollPeriod) => {
    editingPeriodId.value = p.id;
    periodForm.name = p.name;
    periodForm.from_date = p.from_date ? new Date(p.from_date) : null;
    periodForm.to_date = p.to_date ? new Date(p.to_date) : null;
    periodForm.status = p.status;
};

const resetPeriodForm = () => {
    editingPeriodId.value = null;
    periodForm.reset();
    periodForm.clearErrors();
};

const submitPeriod = () => {
    if (editingPeriodId.value) {
        periodForm.put(route('payroll-periods.update', editingPeriodId.value), {
            onSuccess: () => resetPeriodForm(),
        });
    } else {
        periodForm.post(route('payroll-periods.store'), {
            onSuccess: () => resetPeriodForm(),
        });
    }
};

const deletePeriod = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will delete the payroll period!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            periodForm.delete(route('payroll-periods.destroy', id), {
                onSuccess: () => Swal.fire('Deleted!', 'Payroll period has been deleted.', 'success')
            });
        }
    });
};

// Component Actions
const editComp = (c: SalaryComponent) => {
    editingCompId.value = c.id;
    compForm.name = c.name;
    compForm.type = c.type;
    compForm.calculation_type = c.calculation_type;
    compForm.default_value = Number(c.default_value);
    compForm.is_taxable = !!c.is_taxable;
    compForm.is_statutory = !!c.is_statutory;
};

const resetCompForm = () => {
    editingCompId.value = null;
    compForm.reset();
    compForm.clearErrors();
};

const submitComp = () => {
    if (editingCompId.value) {
        compForm.put(route('salary-components.update', editingCompId.value), {
            onSuccess: () => resetCompForm(),
        });
    } else {
        compForm.post(route('salary-components.store'), {
            onSuccess: () => resetCompForm(),
        });
    }
};

const deleteComp = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will delete the salary component!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            compForm.delete(route('salary-components.destroy', id), {
                onSuccess: () => Swal.fire('Deleted!', 'Salary component has been deleted.', 'success')
            });
        }
    });
};

// Generation
const submitGenerate = () => {
    genForm.post(route('payslips.generate'), {
        preserveScroll: true,
        preserveState: true,
    });
};

const deletePayslip = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will delete the payslip permanently!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('payslips.destroy', id), {
                // onSuccess: () => Swal.fire('Deleted!', 'Payslip has been deleted.', 'success')
            });
        }
    });
};

const getStatusSeverity = (status: string) => {
    switch (status) {
        case 'completed':
        case 'paid':
        case 'approved': return 'success';
        case 'processing':
        case 'draft': return 'info';
        case 'failed':
        case 'rejected': return 'danger';
        case 'locked':
        default: return 'secondary';
    }
};
</script>

<template>
    <AppLayout title="Payroll Panel">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <Tabs v-model:value="activeTab">
                    <TabList class="mb-6">
                        <Tab value="payslips">
                            <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                                <BanknotesIcon class="w-4 h-4" /> Payslips & Generation
                            </div>
                        </Tab>
                        <Tab value="components">
                            <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                                <CogIcon class="w-4 h-4" /> Salary Components
                            </div>
                        </Tab>
                        <Tab value="periods">
                            <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                                <CalendarIcon class="w-4 h-4" /> Payroll Cycles
                            </div>
                        </Tab>
                    </TabList>

                    <TabPanels class="!p-0">
                        <!-- PAYSLIPS TAB -->
                        <TabPanel value="payslips">
                            <div class="space-y-6">
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                    <div class="lg:col-span-2">
                                        <!-- Generate Form (3-column layout) -->
                                        <BaseCard class="text-sm">
                                            <template #header>
                                                <div class="flex items-center gap-2">
                                                    <SparklesIcon class="w-5 h-5 text-indigo-500" />
                                                    <span class="text-md font-semibold uppercase text-gray-800 dark:text-gray-100">
                                                        Bulk Payslip Processing Engine
                                                    </span>
                                                </div>
                                            </template>

                                            <form @submit.prevent="submitGenerate" class="space-y-6">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                    <div class="flex flex-col gap-2">
                                                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Select Processing Period <span class="text-red-500">*</span></label>
                                                        <BaseSelect v-model="genForm.payroll_period_id" :options="periodOptions" optionLabel="label" optionValue="value" placeholder="Select Period" class="w-full" />
                                                        <small v-if="genForm.errors.payroll_period_id" class="p-error text-[10px]">{{ genForm.errors.payroll_period_id }}</small>
                                                    </div>
                                                </div>

                                                <BaseFormActions 
                                                    :loading="genForm.processing"
                                                    label="Run Payslip Generation"
                                                    cancel-label="Reset"
                                                    mode="add"
                                                    class="pt-6 border-t border-gray-100 dark:border-gray-700"
                                                    @cancel="() => genForm.reset()"
                                                />
                                            </form>
                                        </BaseCard>
                                    </div>
                                    <div>
                                        <!-- Statutory Compliance Downloads -->
                                        <!-- <BaseCard class="text-sm">
                                            <template #header>
                                                <div class="flex items-center gap-2">
                                                    <CogIcon class="w-5 h-5 text-indigo-500" />
                                                    <span class="text-md font-semibold uppercase text-gray-800 dark:text-gray-100">
                                                        Statutory Compliance
                                                    </span>
                                                </div>
                                            </template>
                                            <div class="space-y-6">
                                                <div class="flex flex-col gap-2">
                                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Select Compliance Cycle <span class="text-red-500">*</span></label>
                                                    <BaseSelect v-model="compliancePeriodId" :options="periodOptions" optionLabel="label" optionValue="value" placeholder="Select Period" class="w-full" />
                                                </div>
                                                <div class="flex flex-col gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                                                    <BaseButton 
                                                        label="Download EPFO ECR Text File" 
                                                        icon="pi pi-download"
                                                        class="w-full justify-center text-xs font-bold"
                                                        severity="info"
                                                        @click="downloadEcr"
                                                    />
                                                    <BaseButton 
                                                        label="Download ESIC Portal CSV File" 
                                                        icon="pi pi-download"
                                                        class="w-full justify-center text-xs font-bold"
                                                        severity="success"
                                                        @click="downloadEsic"
                                                    />
                                                </div>
                                            </div>
                                        </BaseCard> -->
                                    </div>
                                </div>

                                <!-- Payslips List -->
                                <div class="bg-white dark:bg-slate-900 rounded-xl">
                                    <BaseDataTable 
                                        :value="payslips" 
                                        dataKey="id"
                                        stripedRows 
                                        heading="Generated Payslips"
                                        headingIcon="ReceiptPercentIcon"
                                        showSearch showSerial
                                        paginator
                                        :rows="30" 
                                        :totalRecords="payslips.length"
                                        class="p-datatable-sm"
                                    >
                                        <Column header="Payslip No">
                                            <template #body="slotProps">
                                                <span class="font-bold text-slate-700 dark:text-slate-300">{{ slotProps.data.payslip_no }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Employee Name">
                                            <template #body="slotProps">
                                                <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                                    {{ slotProps.data.personnel?.first_name }} {{ slotProps.data.personnel?.last_name || '' }}
                                                </span>
                                            </template>
                                        </Column>
                                        <Column header="Payroll Cycle">
                                            <template #body="slotProps">
                                                <span>{{ slotProps.data.payroll_period?.name }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Earnings / Deductions">
                                            <template #body="slotProps">
                                                <div class="flex flex-col text-[11px]">
                                                    <span>Gross: {{ Number(slotProps.data.gross_salary).toLocaleString('en-IN', {style: 'currency', currency: 'INR'}) }}</span>
                                                    <span class="text-red-500">Ded: {{ Number(slotProps.data.total_deductions).toLocaleString('en-IN', {style: 'currency', currency: 'INR'}) }}</span>
                                                </div>
                                            </template>
                                        </Column>
                                        <Column header="Net Salary">
                                            <template #body="slotProps">
                                                <span class="font-extrabold text-emerald-600 dark:text-emerald-400">
                                                    {{ Number(slotProps.data.net_salary).toLocaleString('en-IN', {style: 'currency', currency: 'INR'}) }}
                                                </span>
                                            </template>
                                        </Column>
                                        <Column header="Status">
                                            <template #body="slotProps">
                                                <Tag :severity="getStatusSeverity(slotProps.data.status)" :value="slotProps.data.status.toUpperCase()" rounded />
                                            </template>
                                        </Column>
                                        <Column header="Actions" alignFrozen="right" frozen>
                                            <template #body="slotProps">
                                                <div class="flex justify-end items-center gap-2">
                                                    <a 
                                                        :href="route('payslips.show', slotProps.data.id)" 
                                                        target="_blank"
                                                        class="p-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold rounded transition-all flex items-center gap-1"
                                                        title="Download PDF"
                                                    >
                                                        <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3Z" fill="#E21A1A" />
                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 6.5C12.5 6.5 13 8.5 12 10.5C11 8.5 11.5 6.5 12 6.5ZM9.5 14C8 14.5 6 15 7.5 16C9 17 10.5 15 9.5 14ZM14.5 14C16 15 17.5 16 16.5 16.8C15.5 17.6 13.5 15.5 14.5 14Z" fill="white" />
                                                            <path d="M12.2 11.2C12.8 11.8 14 12.8 13.8 13.5C13.6 14.2 11 15 10.2 13.8C9.4 12.6 11.6 10.6 12.2 11.2Z" fill="white" fill-opacity="0.8" />
                                                        </svg>
                                                    </a>
                                                    <BaseButton 
                                                        icon="pi pi-trash" 
                                                        severity="danger" 
                                                        text 
                                                        rounded 
                                                        @click="deletePayslip(slotProps.data.id)"
                                                    />
                                                </div>
                                            </template>
                                        </Column>
                                    </BaseDataTable>
                                </div>
                            </div>
                        </TabPanel>

                        <!-- COMPONENTS TAB -->
                        <TabPanel value="components">
                            <div class="space-y-6">
                                <!-- Component Form (3-column layout) -->
                                <BaseCard class="text-sm">
                                    <template #header>
                                        <span class="text-md font-semibold uppercase text-gray-800 dark:text-gray-100">
                                            {{ editingCompId ? 'Edit Salary Component' : 'Create Salary Component' }}
                                        </span>
                                    </template>

                                    <form @submit.prevent="submitComp" class="space-y-6">
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                            <div class="flex flex-col gap-2">
                                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Component Name <span class="text-red-500">*</span></label>
                                                <BaseInput v-model="compForm.name" placeholder="e.g. Basic Salary" :class="{'p-invalid': compForm.errors.name}" />
                                                <small v-if="compForm.errors.name" class="p-error text-[10px]">{{ compForm.errors.name }}</small>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Category Type</label>
                                                <BaseSelect v-model="compForm.type" :options="typeOptions" optionLabel="label" optionValue="value" placeholder="Select Type" class="w-full" />
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Calculation Type</label>
                                                <BaseSelect v-model="compForm.calculation_type" :options="calcOptions" optionLabel="label" optionValue="value" placeholder="Select Type" class="w-full" />
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Default Value</label>
                                                <BaseInput type="number" step="0.01" v-model="compForm.default_value" />
                                            </div>
                                            <div class="md:col-span-4 flex flex-col md:flex-row gap-6 mt-2">
                                                <!-- Subject to Tax Card -->
                                                <div 
                                                    @click="compForm.is_taxable = !compForm.is_taxable" 
                                                    class="flex-1 flex items-center justify-between p-4 rounded-xl border cursor-pointer transition-all duration-300 select-none bg-slate-50/50 dark:bg-slate-800/20"
                                                    :class="compForm.is_taxable 
                                                        ? 'border-indigo-500/50 shadow-sm shadow-indigo-500/5 dark:shadow-indigo-500/10 bg-indigo-50/20 dark:bg-indigo-950/10' 
                                                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                                >
                                                    <div class="flex flex-col gap-1">
                                                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">Subject to Tax</span>
                                                        <span class="text-[10px] text-gray-400">Determines if this component is taxable</span>
                                                    </div>
                                                    <ToggleSwitch v-model="compForm.is_taxable" @click.stop />
                                                </div>

                                                <!-- Statutory Deduction Card -->
                                                <div 
                                                    @click="compForm.is_statutory = !compForm.is_statutory" 
                                                    class="flex-1 flex items-center justify-between p-4 rounded-xl border cursor-pointer transition-all duration-300 select-none bg-slate-50/50 dark:bg-slate-800/20"
                                                    :class="compForm.is_statutory 
                                                        ? 'border-indigo-500/50 shadow-sm shadow-indigo-500/5 dark:shadow-indigo-500/10 bg-indigo-50/20 dark:bg-indigo-950/10' 
                                                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                                >
                                                    <div class="flex flex-col gap-1">
                                                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">Statutory Deduction (PF/ESI)</span>
                                                        <span class="text-[10px] text-gray-400">Flags PF, ESI, Professional Tax</span>
                                                    </div>
                                                    <ToggleSwitch v-model="compForm.is_statutory" @click.stop />
                                                </div>
                                            </div>
                                        </div>

                                        <BaseFormActions 
                                            :loading="compForm.processing"
                                            :label="editingCompId ? 'Update Component' : 'Save Component'"
                                            :cancel-label="editingCompId ? 'Cancel' : 'Reset'"
                                            :mode="editingCompId ? 'edit' : 'add'"
                                            class="pt-6 border-t border-gray-100 dark:border-gray-700"
                                            @cancel="resetCompForm"
                                        />
                                    </form>
                                </BaseCard>

                                <!-- Components List -->
                                <div class="bg-white dark:bg-slate-900 rounded-xl">
                                    <BaseDataTable 
                                        :value="salaryComponents" 
                                        dataKey="id"
                                        stripedRows 
                                        heading="Salary Structures Matrix"
                                        headingIcon="BriefcaseIcon"
                                        showSearch showSerial
                                        paginator
                                        :rows="30" 
                                        :totalRecords="salaryComponents.length"
                                        class="p-datatable-sm"
                                    >
                                        <Column header="Component Name">
                                            <template #body="slotProps">
                                                <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ slotProps.data.name }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Type">
                                            <template #body="slotProps">
                                                <Tag :severity="slotProps.data.type === 'earning' ? 'success' : 'danger'" :value="slotProps.data.type.toUpperCase()" rounded />
                                            </template>
                                        </Column>
                                        <Column header="Calculation Method">
                                            <template #body="slotProps">
                                                <span class="font-medium capitalize text-slate-700 dark:text-slate-300">
                                                    {{ slotProps.data.calculation_type.replace('_', ' ') }}
                                                </span>
                                            </template>
                                        </Column>
                                        <Column header="Default amount">
                                            <template #body="slotProps">
                                                <span>{{ Number(slotProps.data.default_value).toLocaleString('en-IN', {style: 'currency', currency: 'INR'}) }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Taxable">
                                            <template #body="slotProps">
                                                <Tag :severity="slotProps.data.is_taxable ? 'info' : 'secondary'" :value="slotProps.data.is_taxable ? 'YES' : 'NO'" rounded />
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
                                                        @click="editComp(slotProps.data)"
                                                    />
                                                    <BaseButton 
                                                        icon="pi pi-trash" 
                                                        severity="danger" 
                                                        text 
                                                        rounded 
                                                        @click="deleteComp(slotProps.data.id)"
                                                    />
                                                </div>
                                            </template>
                                        </Column>
                                    </BaseDataTable>
                                </div>
                            </div>
                        </TabPanel>

                        <!-- PERIODS TAB -->
                        <TabPanel value="periods">
                            <div class="space-y-6">
                                <!-- Period Form (3-column layout) -->
                                <BaseCard class="text-sm">
                                    <template #header>
                                        <span class="text-md font-semibold uppercase text-gray-800 dark:text-gray-100">
                                            {{ editingPeriodId ? 'Edit Payroll Cycle' : 'Open Payroll Cycle' }}
                                        </span>
                                    </template>

                                    <form @submit.prevent="submitPeriod" class="space-y-6">
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                            <div class="flex flex-col gap-2">
                                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Cycle Title <span class="text-red-500">*</span></label>
                                                <BaseInput v-model="periodForm.name" placeholder="e.g. May-2026" :class="{'p-invalid': periodForm.errors.name}" />
                                                <small v-if="periodForm.errors.name" class="p-error text-[10px]">{{ periodForm.errors.name }}</small>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">From Date <span class="text-red-500">*</span></label>
                                                <DatePicker v-model="periodForm.from_date" dateFormat="yy-mm-dd" showIcon iconDisplay="input" placeholder="Select Date" class="w-full" />
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">To Date <span class="text-red-500">*</span></label>
                                                <DatePicker v-model="periodForm.to_date" dateFormat="yy-mm-dd" showIcon iconDisplay="input" placeholder="Select Date" class="w-full" />
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Cycle Status</label>
                                                <BaseSelect v-model="periodForm.status" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Select Status" class="w-full" />
                                            </div>
                                        </div>

                                        <BaseFormActions 
                                            :loading="periodForm.processing"
                                            :label="editingPeriodId ? 'Update Cycle' : 'Open Cycle'"
                                            :cancel-label="editingPeriodId ? 'Cancel' : 'Reset'"
                                            :mode="editingPeriodId ? 'edit' : 'add'"
                                            class="pt-6 border-t border-gray-100 dark:border-gray-700"
                                            @cancel="resetPeriodForm"
                                        />
                                    </form>
                                </BaseCard>

                                <!-- Periods List -->
                                <div class="bg-white dark:bg-slate-900 rounded-xl">
                                    <BaseDataTable 
                                        :value="payrollPeriods" 
                                        dataKey="id"
                                        stripedRows 
                                        heading="Payroll Cycles History"
                                        headingIcon="CalendarIcon"
                                        showSearch showSerial
                                        paginator
                                        :rows="30" 
                                        :totalRecords="payrollPeriods.length"
                                        class="p-datatable-sm"
                                    >
                                        <Column header="Cycle Name">
                                            <template #body="slotProps">
                                                <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ slotProps.data.name }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Period">
                                            <template #body="slotProps">
                                                <span>{{ slotProps.data.from_date }} to {{ slotProps.data.to_date }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Status">
                                            <template #body="slotProps">
                                                <Tag :severity="getStatusSeverity(slotProps.data.status)" :value="slotProps.data.status.toUpperCase()" rounded />
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
                                                        @click="editPeriod(slotProps.data)"
                                                    />
                                                    <BaseButton 
                                                        icon="pi pi-trash" 
                                                        severity="danger" 
                                                        text 
                                                        rounded 
                                                        @click="deletePeriod(slotProps.data.id)"
                                                    />
                                                </div>
                                            </template>
                                        </Column>
                                    </BaseDataTable>
                                </div>
                            </div>
                        </TabPanel>
                    </TabPanels>
                </Tabs>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable-thead > tr > th) {
    @apply bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 font-bold uppercase text-[10px] tracking-wider py-4;
}
</style>
