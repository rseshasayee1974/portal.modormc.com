<script setup lang="ts">
import { APP_LOCALE } from '@/Utils/locale';
import { entityLocaleTime, entityLocaleDate } from '@/Utils/entityDateTime';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import { CalendarDaysIcon, BuildingOfficeIcon, UserGroupIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

// Components
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import Tag from 'primevue/tag';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';

interface Department {
    id: number;
    name: string;
    code?: string;
}

interface Designation {
    id: number;
    name: string;
    code?: string;
}

interface Personnel {
    id: number;
    first_name: string;
    last_name: string | null;
    employee_code: string;
    department_id?: number | null;
    designation_id?: number | null;
    department?: Department | null;
    designation?: Designation | null;
    shift_start_time?: string | null;
    shift_end_time?: string | null;
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
    is_early_departure: boolean;
    source: string;
    notes?: string | null;
    personnel: Personnel;
    shift?: Shift | null;
}

const props = defineProps<{
    attendances: Attendance[];
    personnel: Personnel[];
    departments: Department[];
    shifts: Shift[];
    statuses: string[];
    sources: string[];
}>();

const page = usePage();
const editingId = ref<number | null>(null);
const expandedRows = ref<Record<number, boolean>>({});
const attendanceDate = ref<Date>(new Date());
const selectedDepartmentId = ref<number | null>(null);
const searchQuery = ref<string>('');
const tableFilters = ref({
    global: { value: null, matchMode: 'contains' }
});

interface EmployeeAttendanceRow {
    personnel_id: number;
    status: string;
    check_in: Date | null;
    check_out: Date | null;
    worked_hours: number;
    overtime_hours: number;
    late_hours: number;
    is_early_departure: boolean;
    source: string;
    notes: string;
}

const employeeRows = ref<Record<number, EmployeeAttendanceRow>>({});

const initializeRows = () => {
    props.personnel.forEach(p => {
        let defaultCheckIn: Date | null = null;
        let defaultCheckOut: Date | null = null;

        const baseDate = attendanceDate.value ? new Date(attendanceDate.value) : new Date();

        if (p.shift_start_time) {
            const [sh, sm, ss] = p.shift_start_time.split(':').map(Number);
            defaultCheckIn = new Date(baseDate);
            defaultCheckIn.setHours(sh, sm, ss || 0, 0);
        }
        if (p.shift_end_time) {
            const [eh, em, es] = p.shift_end_time.split(':').map(Number);
            defaultCheckOut = new Date(baseDate);
            defaultCheckOut.setHours(eh, em, es || 0, 0);
            if (defaultCheckIn && defaultCheckOut <= defaultCheckIn) {
                defaultCheckOut.setDate(defaultCheckOut.getDate() + 1);
            }
        }

        const existingRow = employeeRows.value[p.id];

        employeeRows.value[p.id] = {
            personnel_id: p.id,
            status: existingRow ? existingRow.status : 'present',
            check_in: existingRow && existingRow.check_in ? existingRow.check_in : defaultCheckIn,
            check_out: existingRow && existingRow.check_out ? existingRow.check_out : defaultCheckOut,
            worked_hours: 0,
            overtime_hours: 0,
            late_hours: 0,
            is_late: false,
            is_early_departure: false,
            source: 'manual',
            notes: existingRow && existingRow.notes ? existingRow.notes : '',
        };

        updateMetricsForPersonnel(p.id);
    });
};

const updateMetricsForPersonnel = (pId: number) => {
    const row = employeeRows.value[pId];
    if (!row) return;

    if (row.status === 'absent') {
        row.worked_hours = 0;
        row.overtime_hours = 0;
        row.late_hours = 0;
        row.is_late = false;
        row.is_early_departure = false;
        return;
    }

    const person = props.personnel.find(p => p.id === pId);
    if (!person) return;

    if (row.check_in && row.check_out) {
        const inTime = row.check_in instanceof Date ? row.check_in.getTime() : new Date(row.check_in).getTime();
        const outTime = row.check_out instanceof Date ? row.check_out.getTime() : new Date(row.check_out).getTime();

        if (outTime > inTime) {
            let worked = (outTime - inTime) / (1000 * 60 * 60);
            row.worked_hours = Number(Math.min(24, Math.max(0, worked)).toFixed(1));
        } else {
            row.worked_hours = 0;
        }
    } else {
        row.worked_hours = 0;
    }

    // Shift start calculation (late hours)
    if (person.shift_start_time && row.check_in) {
        const [sh, sm, ss] = person.shift_start_time.split(':').map(Number);
        const inDate = row.check_in instanceof Date ? row.check_in : new Date(row.check_in);
        const shiftStartTime = new Date(inDate);
        shiftStartTime.setHours(sh, sm, ss || 0, 0);

        if (inDate > shiftStartTime) {
            let late = (inDate.getTime() - shiftStartTime.getTime()) / (1000 * 60 * 60);
            row.late_hours = Number(Math.min(24, Math.max(0, late)).toFixed(1));
            row.is_late = true;
        } else {
            row.late_hours = 0;
            row.is_late = false;
        }
    } else {
        row.late_hours = 0;
        row.is_late = false;
    }

    // Overtime & Early departure calculation
    if (person.shift_start_time && person.shift_end_time) {
        const [sh, sm, ss] = person.shift_start_time.split(':').map(Number);
        const [eh, em, es] = person.shift_end_time.split(':').map(Number);
        let shiftStart = new Date(1970, 0, 1, sh, sm, ss || 0);
        let shiftEnd = new Date(1970, 0, 1, eh, em, es || 0);
        if (shiftEnd <= shiftStart) {
            shiftEnd.setDate(shiftEnd.getDate() + 1);
        }
        let scheduledHrs = (shiftEnd.getTime() - shiftStart.getTime()) / (1000 * 60 * 60);

        if (row.worked_hours > scheduledHrs) {
            let ot = row.worked_hours - scheduledHrs;
            row.overtime_hours = Number(Math.min(24, Math.max(0, ot)).toFixed(1));
        } else {
            row.overtime_hours = 0;
        }

        if (row.check_out) {
            const outDate = row.check_out instanceof Date ? row.check_out : new Date(row.check_out);
            const shiftEndTime = new Date(outDate);
            shiftEndTime.setHours(eh, em, es || 0, 0);
            row.is_early_departure = outDate < shiftEndTime;
        } else {
            row.is_early_departure = false;
        }
    } else {
        row.overtime_hours = 0;
        row.is_early_departure = false;
    }
};

watch(attendanceDate, () => {
    initializeRows();
});

watch(() => props.personnel, () => {
    initializeRows();
}, { immediate: true });

const departmentOptions = computed(() => [
    { label: 'All Departments', value: null },
    ...props.departments.map(d => ({ label: d.name, value: d.id }))
]);

const statusOptions = computed(() =>
    props.statuses.map(s => ({ label: s.toUpperCase().replace('_', ' '), value: s }))
);

const sourceOptions = computed(() =>
    props.sources.map(s => ({ label: s.toUpperCase(), value: s }))
);

const filteredPersonnel = computed(() => {
    if (!props.personnel || !Array.isArray(props.personnel)) return [];

    const q = (searchQuery.value || '').toLowerCase().trim();
    const deptId = selectedDepartmentId.value;

    return props.personnel.filter(p => {
        if (!p) return false;

        const matchesDept = deptId === null || deptId === undefined || p.department_id === deptId;
        if (!matchesDept) return false;

        if (!q) return true;

        const firstName = p.first_name ? String(p.first_name).toLowerCase() : '';
        const lastName = p.last_name ? String(p.last_name).toLowerCase() : '';
        const fullName = `${firstName} ${lastName}`.trim();
        const code = p.employee_code ? String(p.employee_code).toLowerCase() : '';
        const desig = p.designation?.name ? String(p.designation.name).toLowerCase() : '';
        const dept = p.department?.name ? String(p.department.name).toLowerCase() : '';

        return fullName.includes(q) ||
            code.includes(q) ||
            desig.includes(q) ||
            dept.includes(q);
    });
});

const markAllPresent = () => {
    filteredPersonnel.value.forEach(p => {
        if (employeeRows.value[p.id]) {
            employeeRows.value[p.id].status = 'present';
            updateMetricsForPersonnel(p.id);
        }
    });
};

const markAllAbsent = () => {
    filteredPersonnel.value.forEach(p => {
        if (employeeRows.value[p.id]) {
            employeeRows.value[p.id].status = 'absent';
            employeeRows.value[p.id].check_in = null;
            employeeRows.value[p.id].check_out = null;
            updateMetricsForPersonnel(p.id);
        }
    });
};

// Single Edit Form handling
const singleForm = useForm({
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
    notes: '',
});

const editAttendance = (att: Attendance) => {
    if (editingId.value === att.id) {
        // Toggle close if already editing this row
        resetSingleEdit();
        return;
    }

    editingId.value = att.id;
    singleForm.personnel_id = att.personnel_id;
    singleForm.shift_id = att.shift_id;
    singleForm.attendance_date = att.attendance_date ? new Date(att.attendance_date) : new Date();
    singleForm.check_in = att.check_in ? new Date(att.check_in) : null;
    singleForm.check_out = att.check_out ? new Date(att.check_out) : null;
    singleForm.worked_hours = Number(att.worked_hours);
    singleForm.overtime_hours = Number(att.overtime_hours);
    singleForm.late_hours = Number(att.late_hours);
    singleForm.status = att.status;
    singleForm.is_late = !!att.is_late;
    singleForm.is_early_departure = !!att.is_early_departure;
    singleForm.source = att.source || 'manual';
    singleForm.notes = att.notes || '';

    expandedRows.value = { [att.id]: true };
};

const resetSingleEdit = () => {
    editingId.value = null;
    expandedRows.value = {};
    singleForm.reset();
    singleForm.clearErrors();
};

const updateSingleAttendance = () => {
    if (!editingId.value) return;
    singleForm.put(route('attendances.update', editingId.value), {
        onSuccess: () => resetSingleEdit(),
    });
};

const submitDepartmentAttendance = () => {
    const listToSubmit = filteredPersonnel.value.map(p => {
        const row = employeeRows.value[p.id];
        return {
            personnel_id: p.id,
            status: row?.status || 'present',
            check_in: row?.status !== 'absent' && row?.check_in ? row.check_in : null,
            check_out: row?.status !== 'absent' && row?.check_out ? row.check_out : null,
            worked_hours: row?.worked_hours || 0,
            overtime_hours: row?.overtime_hours || 0,
            late_hours: row?.late_hours || 0,
            is_late: !!row?.is_late,
            is_early_departure: !!row?.is_early_departure,
            source: 'manual',
            notes: row?.notes || '',
        };
    });

    if (listToSubmit.length === 0) {
        Swal.fire('No Employees', 'No employees found for the selected department filter.', 'info');
        return;
    }

    const payload = {
        attendance_date: attendanceDate.value ? entityLocaleDate(attendanceDate.value, 'en-CA') : entityLocaleDate(new Date(), 'en-CA'),
        attendances: listToSubmit,
    };

    router.post(route('attendances.store'), payload, {
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Attendance Saved',
                text: `Successfully logged attendance for ${listToSubmit.length} employee(s).`,
                timer: 2000,
                showConfirmButton: false,
            });
        },
    });
};

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
                preserveState: true
            });
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

const formatDate = (date: string | Date) => {
    if (!date) return '-';
    return entityLocaleDate(date, APP_LOCALE, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

const formatDuration = (hours: number | string | null | undefined, status?: string) => {
    if (hours === null || hours === undefined || hours === '') return '-';
    const num = Number(hours);
    if (isNaN(num)) return String(hours);
    if (num === 0) return '0 h';
    return num === 1 ? '1 hr' : `${num} hrs`;
};

const formatOtDuration = (hours: number | string | null | undefined) => {
    if (hours === null || hours === undefined || hours === '') return '0 h';
    const num = Number(hours);
    if (isNaN(num)) return String(hours);
    if (num === 0) return '0 h';
    return num === 1 ? '1 hr' : `${num} hrs`;
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
    <AppLayout title="Attendance Dashboard">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-4">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- Department-based Attendance Logging Section (Compact High-Density Table Layout) -->
                <BaseCard class="text-sm">
                    <template #header>
                        <div class="flex items-center justify-between gap-4 py-1">
                            <div class="flex items-center gap-2">
                                <BuildingOfficeIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                                <span
                                    class="text-sm font-bold uppercase tracking-wider text-gray-800 dark:text-gray-100">
                                    Department Attendance Logging
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Tag severity="info" :value="`${filteredPersonnel.length} Employee(s)`" rounded
                                    class="text-[11px]" />
                            </div>
                        </div>
                    </template>

                    <div class="space-y-3">
                        <!-- Toolbar: Compact Filters & Quick Batch Actions -->
                        <div
                            class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 bg-slate-50/80 dark:bg-slate-800/40 p-2.5 rounded-xl border border-gray-100 dark:border-gray-700">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 flex-1">
                                <!-- Attendance Date -->
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase text-gray-400 shrink-0">Date:</span>
                                    <BaseDatePicker v-model="attendanceDate" dateFormat="yy-mm-dd" showIcon
                                        iconDisplay="input" placeholder="Select Date" class="w-full text-xs" />
                                </div>

                                <!-- Department Dropdown -->
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase text-gray-400 shrink-0">Dept:</span>
                                    <BaseSelect v-model="selectedDepartmentId" :options="departmentOptions"
                                        optionLabel="label" optionValue="value" placeholder="Select Department"
                                        class="w-full text-xs" />
                                </div>

                                <!-- Search Filter -->
                                <div class="flex items-center gap-2">
                                    <div class="relative w-full">
                                        <MagnifyingGlassIcon class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none z-10" />
                                        <BaseInput v-model="searchQuery" placeholder="Search employee or role..."
                                            class="w-full text-xs" inputClass="!pl-8" />
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Batch Actions -->
                            <div
                                class="flex items-center gap-2 shrink-0 border-t lg:border-t-0 pt-2 lg:pt-0 border-gray-200 dark:border-gray-700 justify-end">
                                <BaseButton label="All Present" icon="pi pi-check" severity="success" size="small" text
                                    class="!py-1 !px-2.5 !text-xs font-semibold" @click="markAllPresent" />
                                <BaseButton label="All Absent" icon="pi pi-times" severity="danger" size="small" text
                                    class="!py-1 !px-2.5 !text-xs font-semibold" @click="markAllAbsent" />
                            </div>
                        </div>

                        <!-- Dense Table View for Employee Rows -->
                        <div v-if="filteredPersonnel.length === 0"
                            class="py-10 text-center text-gray-400 dark:text-gray-500 bg-gray-50/50 dark:bg-slate-800/20 rounded-xl border border-dashed border-gray-200 dark:border-gray-700">
                            <UserGroupIcon class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-600 mb-1" />
                            <p class="text-xs font-medium">No employees found matching the selected department filter.
                            </p>
                        </div>

                        <div v-else
                            class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-xs bg-white dark:bg-slate-900">
                            <div class="max-h-[500px] overflow-y-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead
                                        class="sticky top-0 bg-slate-100 dark:bg-slate-800/90 backdrop-blur text-gray-600 dark:text-gray-300 font-bold uppercase text-[10px] tracking-wider z-10 border-b border-gray-200 dark:border-gray-700">
                                        <tr>
                                            <th class="py-2.5 px-3.5 w-3/12">Employee & Role</th>
                                            <th class="py-2.5 px-3 w-2/12">Status</th>
                                            <th class="py-2.5 px-3 w-2/12">Check In</th>
                                            <th class="py-2.5 px-3 w-2/12">Check Out</th>
                                            <th class="py-2.5 px-3 w-2/12">Notes</th>
                                            <th class="py-2.5 px-3.5 w-1/12 text-right">Metrics</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        <tr v-for="p in filteredPersonnel" :key="p.id"
                                            class="hover:bg-indigo-50/20 dark:hover:bg-slate-800/50 transition-colors">
                                            <!-- Employee Details -->
                                            <td class="py-2 px-3.5">
                                                <div class="flex items-center gap-2.5">
                                                    <div
                                                        class="w-7 h-7 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs shrink-0 border border-indigo-200/80 dark:border-indigo-800/80">
                                                        {{ (p.first_name[0] || '').toUpperCase() }}
                                                    </div>
                                                    <div class="flex items-start gap-1 flex-col min-w-0">
                                                        <span
                                                            class="font-bold text-gray-800 dark:text-gray-100 text-xs">
                                                            {{ p.first_name }} {{ p.last_name || '' }}
                                                        </span>
                                                        <div>
                                                            <Tag :value="p.designation?.name || p.department?.name || 'Staff'"
                                                                severity="info"
                                                                class="text-[9px] uppercase font-semibold !py-0.5 !px-1.5"
                                                                rounded />
                                                            <span v-if="p.shift_start_time && p.shift_end_time"
                                                                class="text-[10px] text-gray-400">
                                                                • {{ p.shift_start_time.substring(0, 5) }} - {{
                                                                    p.shift_end_time.substring(0, 5) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Status Select -->
                                            <td class="py-2 px-3">
                                                <BaseSelect v-if="employeeRows[p.id]"
                                                    v-model="employeeRows[p.id].status" :options="statusOptions"
                                                    optionLabel="label" optionValue="value" class="w-full text-xs"
                                                    @change="updateMetricsForPersonnel(p.id)" />
                                            </td>

                                            <!-- Check In -->
                                            <td class="py-2 px-3">
                                                <BaseDatePicker v-if="employeeRows[p.id]"
                                                    v-model="employeeRows[p.id].check_in" showTime timeOnly
                                                    hourFormat="12" :showIcon="false" placeholder="Check In"
                                                    class="w-full text-xs"
                                                    :disabled="employeeRows[p.id].status === 'absent'"
                                                    @change="updateMetricsForPersonnel(p.id)" />
                                            </td>

                                            <!-- Check Out -->
                                            <td class="py-2 px-3">
                                                <BaseDatePicker v-if="employeeRows[p.id]"
                                                    v-model="employeeRows[p.id].check_out" showTime timeOnly
                                                    hourFormat="12" :showIcon="false" placeholder="Check Out"
                                                    class="w-full text-xs"
                                                    :disabled="employeeRows[p.id].status === 'absent'"
                                                    @change="updateMetricsForPersonnel(p.id)" />
                                            </td>
                                            
                                            <!-- Notes -->
                                            <td class="py-2 px-3">
                                                <BaseInput v-if="employeeRows[p.id]"
                                                    v-model="employeeRows[p.id].notes"
                                                    placeholder="Remarks..."
                                                    class="w-full text-xs" />
                                            </td>

                                            <!-- Real-time Metrics Pills -->
                                            <td class="py-2 px-3.5 text-right">
                                                <div v-if="employeeRows[p.id] && employeeRows[p.id].status !== 'absent'"
                                                    class="flex items-center gap-1.5 justify-end text-[10px]">
                                                    <span
                                                        class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-medium"
                                                        title="Worked Hours">
                                                        {{ employeeRows[p.id].worked_hours }} hrs
                                                    </span>
                                                    <span v-if="employeeRows[p.id].late_hours > 0"
                                                        class="px-1.5 py-0.5 rounded bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 font-semibold"
                                                        title="Late Hours">
                                                        Late: {{ employeeRows[p.id].late_hours }}h
                                                    </span>
                                                    <span v-if="employeeRows[p.id].overtime_hours > 0"
                                                        class="px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 font-semibold"
                                                        title="Overtime Hours">
                                                        OT: {{ employeeRows[p.id].overtime_hours }}h
                                                    </span>
                                                </div>
                                                <span v-else
                                                    class="text-[10px] font-bold text-red-500 uppercase tracking-wider">ABSENT</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Submit Action -->
                        <div class="flex justify-end pt-3 border-t border-gray-100 dark:border-gray-700">
                            <BaseButton label="Log Department Attendance" icon="pi pi-check-circle" severity="primary"
                                @click="submitDepartmentAttendance" :disabled="filteredPersonnel.length === 0" />
                        </div>
                    </div>
                </BaseCard>

                <!-- Attendances Register Table -->
                <div class="bg-white dark:bg-slate-900 rounded-xl">
                    <BaseDataTable :value="attendances" dataKey="id" stripedRows heading="Attendance Register"
                        headingIcon="CalendarIcon" showSearch showSerial paginator :rows="30"
                        v-model:filters="tableFilters"
                        :globalFilterFields="['personnel.first_name', 'personnel.last_name', 'personnel.employee_code', 'personnel.designation.name', 'personnel.department.name', 'status', 'source']"
                        :totalRecords="attendances.length" v-model:expandedRows="expandedRows" class="p-datatable-sm">
                        <Column header="Date">
                            <template #body="slotProps">
                                <span class="font-semibold">{{ formatDate(slotProps.data.attendance_date) }}</span>
                            </template>
                        </Column>
                        <Column header="Employee Name">
                            <template #body="slotProps">
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ slotProps.data.personnel?.first_name }} {{
                                            slotProps.data.personnel?.last_name || '' }}
                                    </span>
                                    <span class="text-[10px] text-gray-400">{{ slotProps.data.personnel?.employee_code
                                        }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="Role / Dept">
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.personnel?.designation?.name || slotProps.data.personnel?.department?.name || 'Staff'"
                                    severity="info" class="text-[10px]" rounded />
                            </template>
                        </Column>
                        <Column header="Shift Timings">
                            <template #body="slotProps">
                                <span>
                                    {{
                                        slotProps.data.personnel?.shift_start_time &&
                                            slotProps.data.personnel?.shift_end_time
                                            ? `${slotProps.data.personnel.shift_start_time} -
                                    ${slotProps.data.personnel.shift_end_time}`
                                            : (slotProps.data.shift ? `${slotProps.data.shift.start_time} -
                                    ${slotProps.data.shift.end_time}` : '-')
                                    }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Clock In/Out">
                            <template #body="slotProps">
                                <div class="flex flex-col text-[11px]">
                                    <span>IN: {{ slotProps.data.check_in ? entityLocaleTime(slotProps.data.check_in) :
                                        '-' }}</span>
                                    <span>OUT: {{ slotProps.data.check_out ? entityLocaleTime(slotProps.data.check_out)
                                        : '-' }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="Hours Details">
                            <template #body="slotProps">
                                <div class="flex flex-col text-[11px]">
                                    <span>Worked: {{ formatDuration(slotProps.data.worked_hours, slotProps.data.status)
                                        }}</span>
                                    <span>OT: {{ formatOtDuration(slotProps.data.overtime_hours) }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="Status">
                            <template #body="slotProps">
                                <Tag :severity="getStatusSeverity(slotProps.data.status)"
                                    :value="slotProps.data.status.toUpperCase()" rounded />
                            </template>
                        </Column>
                        <Column header="Source">
                            <template #body="slotProps">
                                <span class="text-xs uppercase font-medium">{{ slotProps.data.source }}</span>
                            </template>
                        </Column>
                        <Column header="Notes">
                            <template #body="slotProps">
                                <span class="text-xs text-gray-500 line-clamp-1" :title="slotProps.data.notes">{{ slotProps.data.notes || '-' }}</span>
                            </template>
                        </Column>
                        <Column header="Actions" alignFrozen="right" frozen>
                            <template #body="slotProps">
                                <div class="flex justify-end gap-2">
                                    <BaseButton icon="pi pi-pencil"
                                        :severity="editingId === slotProps.data.id ? 'primary' : 'info'" text rounded
                                        @click="editAttendance(slotProps.data)" title="Edit Record" />
                                    <BaseButton icon="pi pi-trash" severity="danger" text rounded
                                        @click="deleteAttendance(slotProps.data.id)" title="Delete Record" />
                                </div>
                            </template>
                        </Column>

                        <!-- Row Expansion Edit Form -->
                        <template #expansion="slotProps">
                            <div
                                class="p-4 bg-indigo-50/50 dark:bg-slate-800/80 border-y border-indigo-100 dark:border-indigo-900/50">
                                <div
                                    class="flex items-center justify-between mb-3 pb-2 border-b border-indigo-100 dark:border-slate-700">
                                    <div class="flex items-center gap-2">
                                        <CalendarDaysIcon class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                                        <span
                                            class="text-xs font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">
                                            Edit Attendance Record #{{ slotProps.data.id }} — {{
                                                slotProps.data.personnel?.first_name }} {{
                                                slotProps.data.personnel?.last_name || '' }}
                                        </span>
                                    </div>
                                    <BaseButton icon="pi pi-times" label="Close" severity="secondary" text size="small"
                                        @click="resetSingleEdit" class="!py-0.5 !px-2 !text-xs" />
                                </div>

                                <form @submit.prevent="updateSingleAttendance" class="space-y-3">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                                        <div class="flex flex-col gap-1">
                                            <label
                                                class="text-[10px] font-bold uppercase text-gray-500 dark:text-gray-400">Attendance
                                                Date <span class="text-red-500">*</span></label>
                                            <BaseDatePicker v-model="singleForm.attendance_date" dateFormat="yy-mm-dd"
                                                showIcon iconDisplay="input" class="w-full text-xs" />
                                            <small v-if="singleForm.errors.attendance_date"
                                                class="p-error text-[10px]">{{ singleForm.errors.attendance_date
                                                }}</small>
                                        </div>

                                        <div class="flex flex-col gap-1">
                                            <label
                                                class="text-[10px] font-bold uppercase text-gray-500 dark:text-gray-400">Status
                                                <span class="text-red-500">*</span></label>
                                            <BaseSelect v-model="singleForm.status" :options="statusOptions"
                                                optionLabel="label" optionValue="value" class="w-full text-xs" />
                                            <small v-if="singleForm.errors.status" class="p-error text-[10px]">{{
                                                singleForm.errors.status }}</small>
                                        </div>

                                        <div class="flex flex-col gap-1">
                                            <label
                                                class="text-[10px] font-bold uppercase text-gray-500 dark:text-gray-400">Check
                                                In Time</label>
                                            <BaseDatePicker v-model="singleForm.check_in" showTime timeOnly
                                                hourFormat="12" :showIcon="false" placeholder="Select Check In"
                                                class="w-full text-xs" :disabled="singleForm.status === 'absent'" />
                                            <small v-if="singleForm.errors.check_in" class="p-error text-[10px]">{{
                                                singleForm.errors.check_in }}</small>
                                        </div>

                                        <div class="flex flex-col gap-1">
                                            <label
                                                class="text-[10px] font-bold uppercase text-gray-500 dark:text-gray-400">Check
                                                Out Time</label>
                                            <BaseDatePicker v-model="singleForm.check_out" showTime timeOnly
                                                hourFormat="12" :showIcon="false" placeholder="Select Check Out"
                                                class="w-full text-xs" :disabled="singleForm.status === 'absent'" />
                                            <small v-if="singleForm.errors.check_out" class="p-error text-[10px]">{{
                                                singleForm.errors.check_out }}</small>
                                        </div>

                                        <div class="flex flex-col gap-1">
                                            <label
                                                class="text-[10px] font-bold uppercase text-gray-500 dark:text-gray-400">Source</label>
                                            <BaseSelect v-model="singleForm.source" :options="sourceOptions"
                                                optionLabel="label" optionValue="value" class="w-full text-xs" />
                                            <small v-if="singleForm.errors.source" class="p-error text-[10px]">{{
                                                singleForm.errors.source }}</small>
                                        </div>
                                    </div>
                                    
                                    <div class="flex flex-col gap-1">
                                        <label
                                            class="text-[10px] font-bold uppercase text-gray-500 dark:text-gray-400">Notes / Remarks</label>
                                        <BaseInput v-model="singleForm.notes"
                                            placeholder="Enter notes..."
                                            class="w-full text-xs" />
                                        <small v-if="singleForm.errors.notes" class="p-error text-[10px]">{{
                                            singleForm.errors.notes }}</small>
                                    </div>

                                    <div
                                        class="flex justify-end gap-2 pt-2 border-t border-indigo-100 dark:border-slate-700">
                                        <BaseButton label="Cancel" severity="secondary" size="small" outlined
                                            @click="resetSingleEdit" class="!py-1 !px-3 !text-xs" />
                                        <BaseButton label="Update Record" severity="primary" size="small" type="submit"
                                            :loading="singleForm.processing" icon="pi pi-check"
                                            class="!py-1 !px-3 !text-xs" />
                                    </div>
                                </form>
                            </div>
                        </template>
                    </BaseDataTable>
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
