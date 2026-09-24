<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import { ClipboardDocumentCheckIcon, CogIcon, PlusIcon, ChartBarIcon } from '@heroicons/vue/24/outline';

// Components
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import { usePermissions } from '@/Composables/usePermissions';
import DatePicker from 'primevue/datepicker';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import { formatDate } from '@/Utils/formatters';

interface Personnel {
    id: number;
    first_name: string;
    last_name: string | null;
    employee_code: string;
}

interface LeaveType {
    id: number;
    name: string;
    is_paid: boolean;
    max_days_per_year: number | null;
    carry_forward: boolean;
}

interface LeaveApplication {
    id: number;
    personnel_id: number;
    leave_type_id: number;
    from_date: string;
    to_date: string;
    days: number | string;
    reason: string | null;
    status: string;
    approved_by: number | null;
    approved_at: string | null;
    personnel: Personnel;
    leave_type: LeaveType;
    approver?: { username: string } | null;
}

interface EmployeeLeaveBalance {
    id: number;
    personnel_id: number;
    leave_type_id: number;
    year: number;
    opening_balance: number | string;
    accrued: number | string;
    used: number | string;
    balance: number | string;
    personnel?: Personnel;
    leave_type?: LeaveType;
}

const props = defineProps<{
    leaveApplications: LeaveApplication[];
    leaveTypes: LeaveType[];
    personnel: Personnel[];
    statuses: string[];
    leaveBalances?: EmployeeLeaveBalance[];
}>();

const page = usePage();
const { isSassOwner } = usePermissions();
const activeTab = ref('applications');
const editingAppId = ref<number | null>(null);

// Forms
const appForm = useForm({
    personnel_id: null as number | null,
    leave_type_id: null as number | null,
    from_date: null as any,
    to_date: null as any,
    days: 1.0,
    reason: '',
    status: 'pending',
});

const personnelOptions = computed(() =>
    props.personnel.map(p => ({ label: `${p.first_name} ${p.last_name || ''}`, value: p.id }))
);

const leaveTypeOptions = computed(() =>
    props.leaveTypes.map(t => ({ label: t.name, value: t.id }))
);

const statusOptions = computed(() =>
    props.statuses.map(s => ({ label: s.toUpperCase(), value: s }))
);

watch([() => appForm.from_date, () => appForm.to_date], ([newFrom, newTo]) => {
    if (newFrom && newTo) {
        const from = new Date(newFrom);
        const to = new Date(newTo);
        if (!isNaN(from.getTime()) && !isNaN(to.getTime())) {
            const diffTime = to.getTime() - from.getTime();
            const diffDays = Math.round(diffTime / (1000 * 3600 * 24)) + 1;
            if (diffDays > 0) {
                appForm.days = diffDays;
            }
        }
    }
});

// Application Actions
const editApp = (app: LeaveApplication) => {
    editingAppId.value = app.id;
    appForm.personnel_id = app.personnel_id;
    appForm.leave_type_id = app.leave_type_id;
    appForm.from_date = app.from_date ? new Date(app.from_date) : null;
    appForm.to_date = app.to_date ? new Date(app.to_date) : null;
    appForm.days = Number(app.days);
    appForm.reason = app.reason || '';
    appForm.status = app.status;
};

const resetAppForm = () => {
    editingAppId.value = null;
    appForm.reset();
    appForm.clearErrors();
};

const submitApp = () => {
    if (editingAppId.value) {
        appForm.put(route('leave-applications.update', editingAppId.value), {
            onSuccess: () => resetAppForm(),
        });
    } else {
        appForm.post(route('leave-applications.store'), {
            onSuccess: () => resetAppForm(),
        });
    }
};

const deleteApp = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will delete the leave application!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            appForm.delete(route('leave-applications.destroy', id), {
                onSuccess: () => Swal.fire('Deleted!', 'Leave application has been deleted.', 'success')
            });
        }
    });
};

const approveLeave = (id: number, status: string) => {
    router.post(route('leave-applications.approve', id), { status }, {
        preserveScroll: true,
    });
};

const getStatusSeverity = (status: string) => {
    switch (status) {
        case 'approved': return 'success';
        case 'pending': return 'warn';
        case 'rejected': return 'danger';
        case 'cancelled':
        default: return 'secondary';
    }
};

// Balance Actions
const editingBalanceId = ref<number | null>(null);

const balanceForm = useForm({
    personnel_id: null as number | null,
    leave_type_id: null as number | null,
    year: new Date().getFullYear(),
    opening_balance: 0,
    accrued: 0,
});

const editBalance = (balance: EmployeeLeaveBalance) => {
    editingBalanceId.value = balance.id;
    balanceForm.personnel_id = balance.personnel_id;
    balanceForm.leave_type_id = balance.leave_type_id;
    balanceForm.year = balance.year;
    balanceForm.opening_balance = Number(balance.opening_balance);
    balanceForm.accrued = Number(balance.accrued);
};

const resetBalanceForm = () => {
    editingBalanceId.value = null;
    balanceForm.reset();
    balanceForm.clearErrors();
};

const submitBalance = () => {
    if (editingBalanceId.value) {
        balanceForm.put(route('leave-balances.update', editingBalanceId.value), {
            onSuccess: () => resetBalanceForm(),
        });
    } else {
        balanceForm.post(route('leave-balances.store'), {
            onSuccess: () => resetBalanceForm(),
        });
    }
};

const deleteBalance = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will delete the employee leave balance record!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('leave-balances.destroy', id), {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Deleted!', 'Leave balance record deleted.', 'success')
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Leave Board">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <Tabs v-model:value="activeTab">
                    <TabList class="mb-6">
                        <Tab value="applications">
                            <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                                <ClipboardDocumentCheckIcon class="w-4 h-4" /> Leave Applications
                            </div>
                        </Tab>
                        <!-- <Tab value="balances">
                            <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                                <ChartBarIcon class="w-4 h-4" /> Leave Balances
                            </div>
                        </Tab> -->
                    </TabList>

                    <TabPanels class="!p-0">
                        <!-- APPLICATIONS TAB -->
                        <TabPanel value="applications">
                            <div class="space-y-6">
                                <!-- Application Form (3-column layout) -->
                                <BaseCard v-if="isSassOwner" class="text-sm">
                                    <template #header>
                                        <span class="text-md font-semibold uppercase text-gray-800 dark:text-gray-100">
                                            {{ editingAppId ? 'Edit Leave Application' : 'Request Time Off' }}
                                        </span>
                                    </template>

                                    <form @submit.prevent="submitApp" class="space-y-6">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div class="flex flex-col gap-2">
                                                <label
                                                    class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Select
                                                    Employee <span class="text-red-500">*</span></label>
                                                <BaseSelect v-model="appForm.personnel_id" :options="personnelOptions"
                                                    optionLabel="label" optionValue="value"
                                                    placeholder="Select Employee" filter class="w-full" />
                                                <small v-if="appForm.errors.personnel_id" class="p-error text-[10px]">{{
                                                    appForm.errors.personnel_id }}</small>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label
                                                    class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Leave
                                                    Category <span class="text-red-500">*</span></label>
                                                <BaseSelect v-model="appForm.leave_type_id" :options="leaveTypeOptions"
                                                    optionLabel="label" optionValue="value"
                                                    placeholder="Select Leave Type" class="w-full" />
                                                <small v-if="appForm.errors.leave_type_id"
                                                    class="p-error text-[10px]">{{
                                                        appForm.errors.leave_type_id }}</small>
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div class="flex flex-col gap-2">
                                                    <label
                                                        class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">From
                                                        Date <span class="text-red-500">*</span></label>
                                                    <DatePicker v-model="appForm.from_date" dateFormat="yy-mm-dd"
                                                        showIcon iconDisplay="input" placeholder="Start"
                                                        class="w-full" />
                                                </div>
                                                <div class="flex flex-col gap-2">
                                                    <label
                                                        class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">To
                                                        Date <span class="text-red-500">*</span></label>
                                                    <DatePicker v-model="appForm.to_date" dateFormat="yy-mm-dd" showIcon
                                                        iconDisplay="input" placeholder="End" class="w-full" />
                                                </div>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label
                                                    class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Days
                                                    Count</label>
                                                <BaseInput type="number" step="0.5" v-model="appForm.days" />
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label
                                                    class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Reason
                                                    / Description</label>
                                                <BaseInput v-model="appForm.reason" placeholder="Write message..." />
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label
                                                    class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Application
                                                    Status</label>
                                                <BaseSelect v-model="appForm.status" :options="statusOptions"
                                                    optionLabel="label" optionValue="value" placeholder="Status"
                                                    class="w-full" />
                                            </div>
                                        </div>

                                        <BaseFormActions :loading="appForm.processing"
                                            :label="editingAppId ? 'Update Application' : 'Submit Request'"
                                            :cancel-label="editingAppId ? 'Cancel' : 'Reset'"
                                            :mode="editingAppId ? 'edit' : 'add'"
                                            class="pt-6 border-t border-gray-100 dark:border-gray-700"
                                            @cancel="resetAppForm" />
                                    </form>
                                </BaseCard>

                                <!-- Applications List -->
                                <div class="bg-white dark:bg-slate-900 rounded-xl">
                                    <BaseDataTable :value="leaveApplications" dataKey="id" stripedRows
                                        heading="Time Off Applications" headingIcon="CalendarIcon" showSearch showSerial
                                        paginator :rows="30" :totalRecords="leaveApplications.length"
                                        class="p-datatable-sm">
                                        <Column header="Employee Name">
                                            <template #body="slotProps">
                                                <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                                    {{ slotProps.data.personnel?.first_name }} {{
                                                        slotProps.data.personnel?.last_name || '' }}
                                                </span>
                                            </template>
                                        </Column>
                                        <Column header="Leave Category">
                                            <template #body="slotProps">
                                                <span>{{ slotProps.data.leave_type?.name }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Leave Dates">
                                            <template #body="slotProps">
                                                <span>{{ formatDate(slotProps.data.from_date) }} to {{
                                                    formatDate(slotProps.data.to_date)
                                                }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Days">
                                            <template #body="slotProps">
                                                <span class="font-bold">{{ slotProps.data.days }} Days</span>
                                            </template>
                                        </Column>
                                        <Column header="Status">
                                            <template #body="slotProps">
                                                <Tag :severity="getStatusSeverity(slotProps.data.status)"
                                                    :value="slotProps.data.status.toUpperCase()" rounded />
                                            </template>
                                        </Column>
                                        <Column v-if="isSassOwner" header="Action / Approval" alignFrozen="right"
                                            frozen>
                                            <template #body="slotProps">
                                                <div class="flex justify-end gap-2">
                                                    <div v-if="slotProps.data.status === 'pending'" class="flex gap-1">
                                                        <BaseButton icon="pi pi-check" severity="success" size="small"
                                                            text rounded
                                                            @click="approveLeave(slotProps.data.id, 'approved')" />
                                                        <BaseButton icon="pi pi-times" severity="danger" size="small"
                                                            text rounded
                                                            @click="approveLeave(slotProps.data.id, 'rejected')" />
                                                    </div>
                                                    <BaseButton icon="pi pi-pencil" severity="info" text rounded
                                                        @click="editApp(slotProps.data)" />
                                                    <BaseButton icon="pi pi-trash" severity="danger" text rounded
                                                        @click="deleteApp(slotProps.data.id)" />
                                                </div>
                                            </template>
                                        </Column>
                                    </BaseDataTable>
                                </div>
                            </div>
                        </TabPanel>

                        <!-- LEAVE BALANCES TAB -->
                        <TabPanel value="balances">
                            <div class="space-y-6">
                                <!-- Balance Form (3-column layout) -->
                                <BaseCard v-if="isSassOwner" class="text-sm">
                                    <template #header>
                                        <span class="text-md font-semibold uppercase text-gray-800 dark:text-gray-100">
                                            {{ editingBalanceId
                                                ? 'Edit Employee Leave Balance' : 'Initialize / Set Employee Leave Balance'
                                            }}
                                        </span>
                                    </template>

                                    <form @submit.prevent="submitBalance" class="space-y-6">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div class="flex flex-col gap-2">
                                                <label
                                                    class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">
                                                    Employee <span class="text-red-500">*</span>
                                                </label>
                                                <BaseSelect v-model="balanceForm.personnel_id"
                                                    :options="personnelOptions" optionLabel="label" optionValue="value"
                                                    placeholder="Select Employee" filter class="w-full" />
                                                <small v-if="balanceForm.errors.personnel_id"
                                                    class="p-error text-[10px]">{{
                                                        balanceForm.errors.personnel_id }}</small>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label
                                                    class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">
                                                    Leave Category <span class="text-red-500">*</span>
                                                </label>
                                                <BaseSelect v-model="balanceForm.leave_type_id"
                                                    :options="leaveTypeOptions" optionLabel="label" optionValue="value"
                                                    placeholder="Select Leave Category" class="w-full" />
                                                <small v-if="balanceForm.errors.leave_type_id"
                                                    class="p-error text-[10px]">{{
                                                        balanceForm.errors.leave_type_id }}</small>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label
                                                    class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">
                                                    Year <span class="text-red-500">*</span>
                                                </label>
                                                <BaseInput type="number" v-model="balanceForm.year"
                                                    placeholder="e.g. 2026" />
                                                <small v-if="balanceForm.errors.year" class="p-error text-[10px]">{{
                                                    balanceForm.errors.year }}</small>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label
                                                    class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">
                                                    Opening Balance (Days)
                                                </label>
                                                <BaseInput type="number" step="0.5"
                                                    v-model="balanceForm.opening_balance" />
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label
                                                    class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">
                                                    Accrued Days
                                                </label>
                                                <BaseInput type="number" step="0.5" v-model="balanceForm.accrued" />
                                            </div>
                                        </div>

                                        <BaseFormActions :loading="balanceForm.processing"
                                            :label="editingBalanceId ? 'Update Balance' : 'Save Balance'"
                                            :cancel-label="editingBalanceId ? 'Cancel' : 'Reset'"
                                            :mode="editingBalanceId ? 'edit' : 'add'"
                                            class="pt-6 border-t border-gray-100 dark:border-gray-700"
                                            @cancel="resetBalanceForm" />
                                    </form>
                                </BaseCard>

                                <!-- Balances Datatable -->
                                <div class="bg-white dark:bg-slate-900 rounded-xl">
                                    <BaseDataTable :value="leaveBalances || []" dataKey="id" stripedRows
                                        heading="Employee Leave Balances" headingIcon="ChartBarIcon" showSearch
                                        showSerial paginator :rows="30" :totalRecords="(leaveBalances || []).length"
                                        class="p-datatable-sm">
                                        <Column header="Employee Name">
                                            <template #body="slotProps">
                                                <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                                    {{ slotProps.data.personnel?.first_name }} {{
                                                        slotProps.data.personnel?.last_name || '' }}
                                                </span>
                                            </template>
                                        </Column>
                                        <Column header="Leave Category">
                                            <template #body="slotProps">
                                                <span class="font-semibold text-gray-700 dark:text-gray-300">
                                                    {{ slotProps.data.leave_type?.name }}
                                                </span>
                                            </template>
                                        </Column>
                                        <Column header="Year">
                                            <template #body="slotProps">
                                                <span class="font-mono text-xs">{{ slotProps.data.year }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Opening Balance">
                                            <template #body="slotProps">
                                                <span>{{ slotProps.data.opening_balance }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Accrued">
                                            <template #body="slotProps">
                                                <span>{{ slotProps.data.accrued }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Used Days">
                                            <template #body="slotProps">
                                                <span class="text-amber-600 dark:text-amber-400 font-bold">{{
                                                    slotProps.data.used }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Net Balance">
                                            <template #body="slotProps">
                                                <Tag :severity="slotProps.data.balance > 0 ? 'success' : 'danger'"
                                                    :value="slotProps.data.balance" rounded />
                                            </template>
                                        </Column>
                                        <Column v-if="isSassOwner" header="Actions" alignFrozen="right" frozen>
                                            <template #body="slotProps">
                                                <div class="flex justify-end gap-2">
                                                    <BaseButton icon="pi pi-pencil" severity="info" text rounded
                                                        @click="editBalance(slotProps.data)" />
                                                    <BaseButton icon="pi pi-trash" severity="danger" text rounded
                                                        @click="deleteBalance(slotProps.data.id)" />
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
