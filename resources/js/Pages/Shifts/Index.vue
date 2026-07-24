<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import { ClockIcon, UserGroupIcon, CalendarDaysIcon, PlusIcon, SparklesIcon } from '@heroicons/vue/24/outline';

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
import ToggleSwitch from 'primevue/toggleswitch';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import Tag from 'primevue/tag';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';

interface Shift {
    id: number;
    shift_name: string;
    start_time: string;
    end_time: string;
    grace_time: string | null;
    working_hours: number | string;
    is_night_shift: boolean;
}

interface Personnel {
    id: number;
    first_name: string;
    last_name: string | null;
    employee_code: string;
}

interface EmployeeShift {
    id: number;
    personnel_id: number;
    shift_id: number;
    effective_from: string;
    effective_to: string | null;
    personnel: Personnel;
    shift: Shift;
}

const props = defineProps<{
    shifts: Shift[];
    personnel: Personnel[];
    employeeShifts: EmployeeShift[];
}>();

const page = usePage();
const { isSassOwner } = usePermissions();
const activeTab = ref('shifts');
const editingShiftId = ref<number | null>(null);

// Forms
const shiftForm = useForm({
    shift_name: '',
    start_time: '',
    end_time: '',
    grace_time: '',
    working_hours: 8.0,
    is_night_shift: false,
});

const assignForm = useForm({
    personnel_id: null as number | null,
    shift_id: null as number | null,
    effective_from: null as any,
    effective_to: null as any,
});

const personnelOptions = computed(() => 
    props.personnel.map(p => ({ label: `${p.first_name} ${p.last_name || ''} (${p.employee_code})`, value: p.id }))
);

const shiftOptions = computed(() => 
    props.shifts.map(s => ({ label: `${s.shift_name} (${s.start_time} - ${s.end_time})`, value: s.id }))
);

// Shift Actions
const parseTime = (timeStr: string | null) => {
    if (!timeStr) return null;
    const d = new Date();
    const [h, m, s] = timeStr.split(':');
    d.setHours(Number(h), Number(m), Number(s || 0));
    return d;
};

const editShift = (shift: Shift) => {
    editingShiftId.value = shift.id;
    shiftForm.shift_name = shift.shift_name;
    shiftForm.start_time = parseTime(shift.start_time);
    shiftForm.end_time = parseTime(shift.end_time);
    shiftForm.grace_time = parseTime(shift.grace_time || '');
    shiftForm.working_hours = Number(shift.working_hours);
    shiftForm.is_night_shift = !!shift.is_night_shift;
};

const resetShiftForm = () => {
    editingShiftId.value = null;
    shiftForm.reset();
    shiftForm.clearErrors();
};

const formatTimeStr = (val: any): string | null => {
    if (!val) return null;
    if (typeof val === 'string' && /^\d{2}:\d{2}(:\d{2})?$/.test(val)) {
        return val.length === 5 ? `${val}:00` : val;
    }
    const d = val instanceof Date ? val : new Date(val);
    if (isNaN(d.getTime())) return null;
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    const ss = String(d.getSeconds()).padStart(2, '0');
    return `${hh}:${mm}:${ss}`;
};

const submitShift = () => {
    const payload = {
        ...shiftForm.data(),
        start_time: formatTimeStr(shiftForm.start_time),
        end_time: formatTimeStr(shiftForm.end_time),
        grace_time: formatTimeStr(shiftForm.grace_time),
    };

    if (editingShiftId.value) {
        shiftForm.transform(() => payload).put(route('shifts.update', editingShiftId.value), {
            onSuccess: () => resetShiftForm(),
            onFinish: () => shiftForm.transform((d: any) => d),
        });
    } else {
        shiftForm.transform(() => payload).post(route('shifts.store'), {
            onSuccess: () => resetShiftForm(),
            onFinish: () => shiftForm.transform((d: any) => d),
        });
    }
};

const deleteShift = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This action will delete the shift definition!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('shifts.destroy', id), {
                preserveScroll: true,
                preserveState: true
            });
        }
    });
};

// Assignment Actions
const submitAssign = () => {
    assignForm.post(route('shifts.assign'), {
        onSuccess: () => {
            assignForm.reset();
            assignForm.clearErrors();
        }
    });
};

const unassignShift = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will remove the employee shift assignment!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, remove it!'
    }).then((result) => {
        if (result.isConfirmed) {
              router.delete(route('shifts.unassign', id), {
                preserveScroll: true,
                preserveState: true
            });
        }
    });
};

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
    <AppLayout title="Shift Planner">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <Tabs v-model:value="activeTab">
                    <TabList class="mb-6">
                        <Tab value="shifts">
                            <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                                <ClockIcon class="w-4 h-4" /> Shift Scheduler
                            </div>
                        </Tab>
                        <Tab value="assignments">
                            <div class="flex items-center gap-2 font-bold uppercase text-[10px] tracking-widest">
                                <UserGroupIcon class="w-4 h-4" /> Roster / Assignments
                            </div>
                        </Tab>
                    </TabList>

                    <TabPanels class="!p-0">
                        <!-- SHIFTS TAB -->
                        <TabPanel value="shifts">
                            <div class="space-y-6">
                                <!-- Shift Form (3-column layout) -->
                                <BaseCard v-if="isSassOwner" class="text-sm">
                                    <template #header>
                                        <div class="flex items-center gap-2">
                                            <SparklesIcon class="w-5 h-5 text-indigo-500" />
                                            <span class="text-md font-semibold uppercase text-gray-800 dark:text-gray-100">
                                                {{ editingShiftId ? 'Edit Shift Configuration' : 'Design New Shift' }}
                                            </span>
                                        </div>
                                    </template>

                                    <form @submit.prevent="submitShift" class="space-y-6">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div class="flex flex-col gap-2">
                                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Shift Name <span class="text-red-500">*</span></label>
                                                <BaseInput v-model="shiftForm.shift_name" placeholder="e.g. Day Shift" :class="{'p-invalid': shiftForm.errors.shift_name}" />
                                                <small v-if="shiftForm.errors.shift_name" class="p-error text-[10px]">{{ shiftForm.errors.shift_name }}</small>
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div class="flex flex-col gap-2">
                                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Start Time <span class="text-red-500">*</span></label>
                                                    <BaseDatePicker v-model="shiftForm.start_time" mode="time" show-time :hour12="false" />
                                                </div>
                                                <div class="flex flex-col gap-2">
                                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">End Time <span class="text-red-500">*</span></label>
                                                    <BaseDatePicker v-model="shiftForm.end_time" show-time mode="time" :hour12="false" />
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div class="flex flex-col gap-2">
                                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Grace Time</label>
                                                    <BaseDatePicker v-model="shiftForm.grace_time" show-time mode="time" :hour12="false" />
                                                </div>
                                                <div class="flex flex-col gap-2">
                                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Working Hours</label>
                                                    <BaseInput type="number" step="0.1" v-model="shiftForm.working_hours" />
                                                </div>
                                            </div>
                                            <div 
                                                @click="shiftForm.is_night_shift = !shiftForm.is_night_shift" 
                                                class="flex items-center justify-between p-4 rounded-xl border cursor-pointer transition-all duration-300 select-none bg-slate-50/50 dark:bg-slate-800/20"
                                                :class="shiftForm.is_night_shift 
                                                    ? 'border-indigo-500/50 shadow-sm shadow-indigo-500/5 dark:shadow-indigo-500/10 bg-indigo-50/20 dark:bg-indigo-950/10' 
                                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                            >
                                                <div class="flex flex-col gap-1">
                                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">Night Shift Schedule</span>
                                                    <span class="text-[10px] text-gray-400">Is this shift active overnight?</span>
                                                </div>
                                                <ToggleSwitch v-model="shiftForm.is_night_shift" @click.stop />
                                            </div>
                                        </div>

                                        <BaseFormActions 
                                            :loading="shiftForm.processing"
                                            :label="editingShiftId ? 'Update Shift' : 'Save Shift'"
                                            :cancel-label="editingShiftId ? 'Cancel' : 'Reset'"
                                            :mode="editingShiftId ? 'edit' : 'add'"
                                            class="pt-6 border-t border-gray-100 dark:border-gray-700"
                                            @cancel="resetShiftForm"
                                        />
                                    </form>
                                </BaseCard>

                                <!-- Shifts List -->
                                <div class="bg-white dark:bg-slate-900 rounded-xl">
                                    <BaseDataTable 
                                        :value="shifts" 
                                        dataKey="id"
                                        stripedRows 
                                        heading="Shift Schedule Definitions"
                                        headingIcon="ClockIcon"
                                        showSearch showSerial
                                        paginator
                                        :rows="30" 
                                        :totalRecords="shifts.length"
                                        class="p-datatable-sm"
                                    >
                                        <Column header="Shift Name">
                                            <template #body="slotProps">
                                                <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ slotProps.data.shift_name }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Timings">
                                            <template #body="slotProps">
                                                <span class="font-semibold">{{ slotProps.data.start_time }} - {{ slotProps.data.end_time }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Grace Time">
                                            <template #body="slotProps">
                                                <span>{{ slotProps.data.grace_time || '-' }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Working Hours">
                                            <template #body="slotProps">
                                                <span>{{ slotProps.data.working_hours }} Hrs</span>
                                            </template>
                                        </Column>
                                        <Column header="Night Shift">
                                            <template #body="slotProps">
                                                <Tag :severity="slotProps.data.is_night_shift ? 'warn' : 'info'" :value="slotProps.data.is_night_shift ? 'YES' : 'NO'" rounded />
                                            </template>
                                        </Column>
                                        <Column v-if="isSassOwner" header="Actions" alignFrozen="right" frozen>
                                            <template #body="slotProps">
                                                <div class="flex justify-end gap-2">
                                                    <BaseButton 
                                                        icon="pi pi-pencil" 
                                                        severity="info" 
                                                        text 
                                                        rounded 
                                                        @click="editShift(slotProps.data)"
                                                    />
                                                    <BaseButton 
                                                        icon="pi pi-trash" 
                                                        severity="danger" 
                                                        text 
                                                        rounded 
                                                        @click="deleteShift(slotProps.data.id)"
                                                    />
                                                </div>
                                            </template>
                                        </Column>
                                    </BaseDataTable>
                                </div>
                            </div>
                        </TabPanel>

                        <!-- ASSIGNMENTS TAB -->
                        <TabPanel value="assignments">
                            <div class="space-y-6">
                                <!-- Shift Assignment Form -->
                                <BaseCard v-if="isSassOwner" class="text-sm">
                                    <template #header>
                                        <div class="flex items-center gap-2">
                                            <CalendarDaysIcon class="w-5 h-5 text-indigo-500" />
                                            <span class="text-md font-semibold uppercase text-gray-800 dark:text-gray-100">
                                                Link Employee to Shift
                                            </span>
                                        </div>
                                    </template>

                                    <form @submit.prevent="submitAssign" class="space-y-6">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div class="flex flex-col gap-2">
                                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Select Employee <span class="text-red-500">*</span></label>
                                                <BaseSelect v-model="assignForm.personnel_id" :options="personnelOptions" optionLabel="label" optionValue="value" placeholder="Select Employee" filter class="w-full" />
                                                <small v-if="assignForm.errors.personnel_id" class="p-error text-[10px]">{{ assignForm.errors.personnel_id }}</small>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Select Shift Schedule <span class="text-red-500">*</span></label>
                                                <BaseSelect v-model="assignForm.shift_id" :options="shiftOptions" optionLabel="label" optionValue="value" placeholder="Select Shift" class="w-full" />
                                                <small v-if="assignForm.errors.shift_id" class="p-error text-[10px]">{{ assignForm.errors.shift_id }}</small>
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div class="flex flex-col gap-2">
                                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Effective From <span class="text-red-500">*</span></label>
                                                    <DatePicker v-model="assignForm.effective_from" dateFormat="yy-mm-dd" showIcon iconDisplay="input" placeholder="Start Date" class="w-full" />
                                                    <small v-if="assignForm.errors.effective_from" class="p-error text-[10px]">{{ assignForm.errors.effective_from }}</small>
                                                </div>
                                                <div class="flex flex-col gap-2">
                                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Effective To</label>
                                                    <DatePicker v-model="assignForm.effective_to" dateFormat="yy-mm-dd" showIcon iconDisplay="input" placeholder="End Date (Optional)" class="w-full" />
                                                </div>
                                            </div>
                                        </div>

                                        <BaseFormActions 
                                            :loading="assignForm.processing"
                                            label="Assign Shift"
                                            cancel-label="Reset"
                                            mode="add"
                                            class="pt-6 border-t border-gray-100 dark:border-gray-700"
                                            @cancel="() => { assignForm.reset(); assignForm.clearErrors(); }"
                                        />
                                    </form>
                                </BaseCard>

                                <!-- Assignments List -->
                                <div class="bg-white dark:bg-slate-900 rounded-xl">
                                    <BaseDataTable 
                                        :value="employeeShifts" 
                                        dataKey="id"
                                        stripedRows 
                                        heading="Employee Shift Rosters"
                                        headingIcon="UserGroupIcon"
                                        showSearch showSerial
                                        paginator
                                        :rows="30" 
                                        :totalRecords="employeeShifts.length"
                                        class="p-datatable-sm"
                                    >
                                        <Column header="Employee Code">
                                            <template #body="slotProps">
                                                <span class="font-semibold">{{ slotProps.data.personnel?.employee_code }}</span>
                                            </template>
                                        </Column>
                                        <Column header="Employee Name">
                                            <template #body="slotProps">
                                                <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                                    {{ slotProps.data.personnel?.first_name }} {{ slotProps.data.personnel?.last_name || '' }}
                                                </span>
                                            </template>
                                        </Column>
                                        <Column header="Shift Plan">
                                            <template #body="slotProps">
                                                <span class="font-semibold text-slate-700 dark:text-slate-300">
                                                    {{ slotProps.data.shift?.shift_name }} ({{ slotProps.data.shift?.start_time }} - {{ slotProps.data.shift?.end_time }})
                                                </span>
                                            </template>
                                        </Column>
                                        <Column header="Effective Period">
                                            <template #body="slotProps">
                                                <span>{{ slotProps.data.effective_from }} to {{ slotProps.data.effective_to || 'Present' }}</span>
                                            </template>
                                        </Column>
                                        <Column v-if="isSassOwner" header="Actions" alignFrozen="right" frozen>
                                            <template #body="slotProps">
                                                <div class="flex justify-end gap-2">
                                                    <BaseButton 
                                                        icon="pi pi-trash" 
                                                        severity="danger" 
                                                        text 
                                                        rounded 
                                                        @click="unassignShift(slotProps.data.id)"
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
