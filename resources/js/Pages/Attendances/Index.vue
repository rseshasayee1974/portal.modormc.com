<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import { IdentificationIcon, PencilSquareIcon, TrashIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline';

// Components
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';

interface Personnel {
    id: number;
    first_name: string;
    last_name: string | null;
    employee_code: string;
}

interface Shift {
    id: number;
    shift_name: string;
    start_time: string;
    end_time: string;
}

interface Attendance {
    id: number;
    personnel_id: number;
    shift_id: number | null;
    attendance_date: string;
    check_in: string | null;
    check_out: string | null;
    worked_hours: number | string;
    overtime_hours: number | string;
    late_hours: number | string;
    status: string;
    is_late: boolean;
    is_early_departure: boolean;
    source: string;
    personnel: Personnel;
    shift?: Shift | null;
}

const props = defineProps<{
    attendances: Attendance[];
    personnel: Personnel[];
    shifts: Shift[];
    statuses: string[];
    sources: string[];
}>();

const page = usePage();
const editingId = ref<number | null>(null);

const form = useForm({
    personnel_id: null as number | null,
    shift_id: null as number | null,
    attendance_date: null as any,
    check_in: null as any,
    check_out: null as any,
    worked_hours: 0,
    overtime_hours: 0,
    late_hours: 0,
    status: 'present',
    is_late: false,
    is_early_departure: false,
    source: 'manual',
});

const personnelOptions = computed(() => 
    props.personnel.map(p => ({ label: `${p.first_name} ${p.last_name || ''} (${p.employee_code})`, value: p.id }))
);

const shiftOptions = computed(() => 
    props.shifts.map(s => ({ label: `${s.shift_name} (${s.start_time} - ${s.end_time})`, value: s.id }))
);

const statusOptions = computed(() => 
    props.statuses.map(s => ({ label: s.toUpperCase().replace('_', ' '), value: s }))
);

const sourceOptions = computed(() => 
    props.sources.map(s => ({ label: s.toUpperCase(), value: s }))
);

const editAttendance = (att: Attendance) => {
    editingId.value = att.id;
    form.personnel_id = att.personnel_id;
    form.shift_id = att.shift_id;
    form.attendance_date = att.attendance_date ? new Date(att.attendance_date) : null;
    form.check_in = att.check_in ? new Date(att.check_in) : null;
    form.check_out = att.check_out ? new Date(att.check_out) : null;
    form.worked_hours = Number(att.worked_hours);
    form.overtime_hours = Number(att.overtime_hours);
    form.late_hours = Number(att.late_hours);
    form.status = att.status;
    form.is_late = !!att.is_late;
    form.is_early_departure = !!att.is_early_departure;
    form.source = att.source;
};

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    form.clearErrors();
    let hasError = false;
    
    if (Number(form.worked_hours) > 24) {
        form.setError('worked_hours', 'The worked hours field must not be greater than 24.');
        hasError = true;
    }
    if (Number(form.overtime_hours) > 24) {
        form.setError('overtime_hours', 'The overtime hours field must not be greater than 24.');
        hasError = true;
    }
    if (Number(form.late_hours) > 24) {
        form.setError('late_hours', 'The late hours field must not be greater than 24.');
        hasError = true;
    }
    
    if (hasError) return;

    if (editingId.value) {
        form.put(route('attendances.update', editingId.value), {
            onSuccess: () => resetForm(),
        });
    } else {
        form.post(route('attendances.store'), {
            onSuccess: () => resetForm(),
        });
    }
};

watch([() => form.check_in, () => form.check_out, () => form.shift_id], ([checkIn, checkOut, shiftId]) => {
    if (checkIn && checkOut) {
        const inTime = checkIn instanceof Date ? checkIn.getTime() : new Date(checkIn).getTime();
        const outTime = checkOut instanceof Date ? checkOut.getTime() : new Date(checkOut).getTime();
        
        if (outTime > inTime) {
            let worked = (outTime - inTime) / (1000 * 60 * 60);
            form.worked_hours = Number(Math.min(24, Math.max(0, worked)).toFixed(1));
        }
    }
    
    if (shiftId && checkIn) {
        const shift = props.shifts.find(s => s.id === shiftId);
        if (shift) {
            const [sh, sm, ss] = shift.start_time.split(':').map(Number);
            const inDate = checkIn instanceof Date ? checkIn : new Date(checkIn);
            const shiftStartTime = new Date(inDate);
            shiftStartTime.setHours(sh, sm, ss || 0, 0);
            
            if (inDate > shiftStartTime) {
                let late = (inDate.getTime() - shiftStartTime.getTime()) / (1000 * 60 * 60);
                form.late_hours = Number(Math.min(24, Math.max(0, late)).toFixed(1));
            }
        }
    }
    
    if (shiftId && form.worked_hours > 0) {
        const shift = props.shifts.find(s => s.id === shiftId);
        if (shift) {
            const [sh, sm, ss] = shift.start_time.split(':').map(Number);
            const [eh, em, es] = shift.end_time.split(':').map(Number);
            let shiftStart = new Date(1970, 0, 1, sh, sm, ss || 0);
            let shiftEnd = new Date(1970, 0, 1, eh, em, es || 0);
            if (shiftEnd <= shiftStart) {
                shiftEnd.setDate(shiftEnd.getDate() + 1);
            }
            let scheduledHrs = (shiftEnd.getTime() - shiftStart.getTime()) / (1000 * 60 * 60);
            if (form.worked_hours > scheduledHrs) {
                let ot = form.worked_hours - scheduledHrs;
                form.overtime_hours = Number(Math.min(24, Math.max(0, ot)).toFixed(1));
            }
        }
    }
});

const deleteAttendance = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This action will delete the attendance log!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
              router.delete(route('attendances.destroy', id), {
                preserveScroll: true,
                preserveState: true});
        }
    });
};

const getStatusSeverity = (status: string) => {
    switch (status) {
        case 'present': return 'success';
        case 'absent': return 'danger';
        case 'half_day': return 'warn';
        case 'leave': return 'info';
        case 'on_duty': return 'help';
        case 'holiday':
        case 'weekoff':
        default: return 'secondary';
    }
};
</script>

<template>
    <AppLayout title="Attendance Dashboard">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="space-y-6">
                    
                    <!-- Form Container (3-column layout) -->
                    <BaseCard class="text-sm">
                        <template #header>
                            <div class="flex items-center gap-2">
                                <CalendarDaysIcon class="w-5 h-5 text-indigo-500" />
                                <span class="text-md font-semibold uppercase text-gray-800 dark:text-gray-100">
                                    {{ editingId ? 'Edit Attendance Entry' : 'Manual Attendance Logging' }}
                                </span>
                            </div>
                        </template>

                        <form @submit.prevent="submitForm" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Select Employee <span class="text-red-500">*</span></label>
                                    <BaseSelect v-model="form.personnel_id" :options="personnelOptions" optionLabel="label" optionValue="value" placeholder="Select Employee" filter class="w-full" :disabled="!!editingId" />
                                    <small v-if="form.errors.personnel_id" class="p-error text-[10px]">{{ form.errors.personnel_id }}</small>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Attendance Date <span class="text-red-500">*</span></label>
                                    <BaseDatePicker v-model="form.attendance_date" hour-format="12" dateFormat="yy-mm-dd" showIcon iconDisplay="input" placeholder="Select Date" class="w-full" :disabled="!!editingId" />
                                    <small v-if="form.errors.attendance_date" class="p-error text-[10px]">{{ form.errors.attendance_date }}</small>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Assigned Shift</label>
                                    <BaseSelect v-model="form.shift_id" :options="shiftOptions" optionLabel="label" optionValue="value" placeholder="Select Shift" class="w-full" />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Check In Time</label>
                                        <BaseDatePicker v-model="form.check_in" showTime hourFormat="12" :showIcon=false iconDisplay="input" placeholder="Check In" class="w-full" />
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Check Out Time</label>
                                        <BaseDatePicker v-model="form.check_out" showTime hourFormat="12" :showIcon=false iconDisplay="input" placeholder="Check Out" class="w-full" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-4">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Worked Hrs</label>
                                        <BaseInput type="number" step="0.1" min="0" max="24" v-model="form.worked_hours" :error="form.errors.worked_hours" />
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Overtime Hrs</label>
                                        <BaseInput type="number" step="0.1" min="0" max="24" v-model="form.overtime_hours" :error="form.errors.overtime_hours" />
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Late Hrs</label>
                                        <BaseInput type="number" step="0.1" min="0" max="24" v-model="form.late_hours" :error="form.errors.late_hours" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Attendance Status <span class="text-red-500">*</span></label>
                                        <BaseSelect v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Status" class="w-full" />
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Logging Source</label>
                                        <BaseSelect v-model="form.source" :options="sourceOptions" optionLabel="label" optionValue="value" placeholder="Source" class="w-full" />
                                    </div>
                                </div>

                                <div class="md:col-span-3 flex flex-col md:flex-row gap-6 mt-2">
                                    <!-- Late Arrival Card -->
                                    <div 
                                        @click="form.is_late = !form.is_late" 
                                        class="flex-1 flex items-center justify-between p-4 rounded-xl border cursor-pointer transition-all duration-300 select-none bg-slate-50/50 dark:bg-slate-800/20"
                                        :class="form.is_late 
                                            ? 'border-indigo-500/50 shadow-sm shadow-indigo-500/5 dark:shadow-indigo-500/10 bg-indigo-50/20 dark:bg-indigo-950/10' 
                                            : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                    >
                                        <div class="flex flex-col gap-1">
                                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">Late Arrival</span>
                                            <span class="text-[10px] text-gray-400">Flag employee as arriving late for shift</span>
                                        </div>
                                        <ToggleSwitch v-model="form.is_late" @click.stop />
                                    </div>

                                    <!-- Early Departure Card -->
                                    <div 
                                        @click="form.is_early_departure = !form.is_early_departure" 
                                        class="flex-1 flex items-center justify-between p-4 rounded-xl border cursor-pointer transition-all duration-300 select-none bg-slate-50/50 dark:bg-slate-800/20"
                                        :class="form.is_early_departure 
                                            ? 'border-indigo-500/50 shadow-sm shadow-indigo-500/5 dark:shadow-indigo-500/10 bg-indigo-50/20 dark:bg-indigo-950/10' 
                                            : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                    >
                                        <div class="flex flex-col gap-1">
                                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">Early Departure</span>
                                            <span class="text-[10px] text-gray-400">Flag employee as leaving shift before scheduled end</span>
                                        </div>
                                        <ToggleSwitch v-model="form.is_early_departure" @click.stop />
                                    </div>
                                </div>
                            </div>

                            <BaseFormActions 
                                :loading="form.processing"
                                :label="editingId ? 'Update Log' : 'Log Attendance'"
                                :cancel-label="editingId ? 'Cancel' : 'Reset'"
                                :mode="editingId ? 'edit' : 'add'"
                                class="pt-6 border-t border-gray-100 dark:border-gray-700"
                                @cancel="resetForm"
                            />
                        </form>
                    </BaseCard>

                    <!-- Attendances List -->
                    <div class="bg-white dark:bg-slate-900 rounded-xl">
                        <BaseDataTable 
                            :value="attendances" 
                            dataKey="id"
                            stripedRows 
                            heading="Attendance Register"
                            headingIcon="CalendarIcon"
                            showSearch showSerial
                            paginator
                            :rows="30" 
                            :totalRecords="attendances.length"
                            class="p-datatable-sm"
                        >
                            <Column header="Date">
                                <template #body="slotProps">
                                    <span class="font-semibold">{{ slotProps.data.attendance_date }}</span>
                                </template>
                            </Column>
                            <Column header="Employee Name">
                                <template #body="slotProps">
                                    <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ slotProps.data.personnel?.first_name }} {{ slotProps.data.personnel?.last_name || '' }}
                                    </span>
                                </template>
                            </Column>
                            <Column header="Shift Timings">
                                <template #body="slotProps">
                                    <span>{{ slotProps.data.shift ? slotProps.data.shift.shift_name : '-' }}</span>
                                </template>
                            </Column>
                            <Column header="Clock In/Out">
                                <template #body="slotProps">
                                    <div class="flex flex-col text-[11px]">
                                        <span>IN: {{ slotProps.data.check_in ? new Date(slotProps.data.check_in).toLocaleTimeString() : '-' }}</span>
                                        <span>OUT: {{ slotProps.data.check_out ? new Date(slotProps.data.check_out).toLocaleTimeString() : '-' }}</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="Hours Details">
                                <template #body="slotProps">
                                    <div class="flex flex-col text-[11px]">
                                        <span>Worked: {{ slotProps.data.worked_hours }} h</span>
                                        <span>OT: {{ slotProps.data.overtime_hours }} h</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="Status">
                                <template #body="slotProps">
                                    <Tag :severity="getStatusSeverity(slotProps.data.status)" :value="slotProps.data.status.toUpperCase()" rounded />
                                </template>
                            </Column>
                            <Column header="Source">
                                <template #body="slotProps">
                                    <span class="text-xs uppercase font-medium">{{ slotProps.data.source }}</span>
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
                                            @click="editAttendance(slotProps.data)"
                                        />
                                        <BaseButton 
                                            icon="pi pi-trash" 
                                            severity="danger" 
                                            text 
                                            rounded 
                                            @click="deleteAttendance(slotProps.data.id)"
                                        />
                                    </div>
                                </template>
                            </Column>
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
