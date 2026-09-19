<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Swal from 'sweetalert2';

import MachineTrackerCreateForm from './components/MachineTrackerCreateForm.vue';
import MachineTrackerIndexList from './components/MachineTrackerIndexList.vue';
import {
    SHIFT_OPTIONS,
    getInitialTrackerForm,
    type MachineTracker,
    type OptionItem
} from './types';

declare const route: any;

const props = defineProps<{
    trackers: MachineTracker[];
    machines: any[];
    operators: any[];
}>();

const page = usePage();

const editingId = ref<number | null>(null);
const expandedRows = ref<Record<number, boolean>>({});

const machineOptions = computed<OptionItem[]>(() =>
    props.machines.map(m => ({ label: m.registration, value: m.id }))
);

const operatorOptions = computed<OptionItem[]>(() =>
    props.operators.map(u => ({
        label: u.label || `${u.first_name || ''} ${u.last_name || ''}`.trim() || String(u.id),
        value: u.id ?? u.value
    }))
);

const createForm = useForm(getInitialTrackerForm());
const editForm = useForm(getInitialTrackerForm());

const populateEditForm = (tracker: MachineTracker) => {
    editForm.machine_id = tracker.machine_id;
    editForm.operation_type = tracker.operation_type || '';
    editForm.category = tracker.category || '';
    editForm.operator_id = tracker.operator_id;
    editForm.opening = tracker.opening;
    editForm.closing = tracker.closing;
    editForm.odometer_start = Number(tracker.odometer_start || 0);
    editForm.odometer_end = Number(tracker.odometer_end || 0);
    editForm.hourmeter_start = Number(tracker.hourmeter_start || 0);
    editForm.hourmeter_end = Number(tracker.hourmeter_end || 0);
    editForm.eb_start = Number(tracker.eb_start || 0);
    editForm.eb_close = Number(tracker.eb_close || 0);
    editForm.opening_hsd = Number(tracker.opening_hsd || 0);
    editForm.closing_hsd = Number(tracker.closing_hsd || 0);
    editForm.notes = tracker.notes || '';
    editForm.fuel = Number(tracker.fuel || 0);
    editForm.fuel_filled_on = tracker.fuel_filled_on;
    editForm.last_fuel_filled_km = Number(tracker.last_fuel_filled_km || 0);
    editForm.fuel_filled_km = Number(tracker.fuel_filled_km || 0);
    editForm.pump_name = tracker.pump_name || '';
    editForm.pump_reading = tracker.pump_reading || '';
    editForm.amount = Number(tracker.amount || 0);
    editForm.shift = tracker.shift;
};

// ── Handlers ────────────────────────────────────────────────────────────────

const submitCreate = () => {
    createForm.clearErrors();
    let hasError = false;

    if (!createForm.machine_id) {
        createForm.setError('machine_id', 'The machine / vehicle asset is required.');
        hasError = true;
    }

    if (createForm.shift === null || createForm.shift === undefined || createForm.shift === '') {
        createForm.setError('shift', 'The shift field is required.');
        hasError = true;
    }

    if (createForm.eb_start === null || createForm.eb_start === undefined || createForm.eb_start === 0) {
        createForm.setError('eb_start', 'The EB start reading is required.');
        hasError = true;
    }

    if (createForm.eb_close === null || createForm.eb_close === undefined || createForm.eb_close === 0) {
        createForm.setError('eb_close', 'The EB close reading is required.');
        hasError = true;
    }

    if (createForm.last_fuel_filled_km === null || createForm.last_fuel_filled_km === undefined || createForm.last_fuel_filled_km === 0) {
        createForm.setError('last_fuel_filled_km', 'The last fuel filled km is required.');
        hasError = true;
    }

    if (hasError) return;

    createForm.post(route('machine-trackers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.clearErrors();
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Tracker log saved successfully',
                showConfirmButton: false,
                timer: 1800
            });
        }
    });
};

const resetCreateForm = () => {
    createForm.reset();
    createForm.clearErrors();
};

const startEdit = (tracker: MachineTracker) => {
    editingId.value = tracker.id;
    editForm.clearErrors();
    populateEditForm(tracker);
    expandedRows.value = { [tracker.id]: true };
};

const handleExpandedRowsUpdate = (newExpandedRows: any) => {
    const activeId = Object.keys(newExpandedRows).map(Number).find(id => newExpandedRows[id]);

    if (activeId) {
        const tracker = props.trackers.find(t => t.id === activeId);
        if (tracker) {
            editingId.value = activeId;
            editForm.clearErrors();
            populateEditForm(tracker);
            expandedRows.value = { [activeId]: true };
            return;
        }
    }

    cancelEdit();
};

const submitEdit = () => {
    if (!editingId.value) return;

    editForm.clearErrors();
    let hasError = false;

    if (!editForm.machine_id) {
        editForm.setError('machine_id', 'The machine / vehicle asset is required.');
        hasError = true;
    }

    if (editForm.shift === null || editForm.shift === undefined || editForm.shift === '') {
        editForm.setError('shift', 'The shift field is required.');
        hasError = true;
    }

    if (editForm.eb_start === null || editForm.eb_start === undefined || editForm.eb_start === 0) {
        editForm.setError('eb_start', 'The EB start reading is required.');
        hasError = true;
    }

    if (editForm.eb_close === null || editForm.eb_close === undefined || editForm.eb_close === 0) {
        editForm.setError('eb_close', 'The EB close reading is required.');
        hasError = true;
    }

    if (editForm.last_fuel_filled_km === null || editForm.last_fuel_filled_km === undefined || editForm.last_fuel_filled_km === 0) {
        editForm.setError('last_fuel_filled_km', 'The last fuel filled km is required.');
        hasError = true;
    }

    if (hasError) return;

    editForm.put(route('machine-trackers.update', editingId.value), {
        preserveScroll: true,
        onSuccess: () => {
            cancelEdit();
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Tracker log updated successfully',
                showConfirmButton: false,
                timer: 1800
            });
        }
    });
};

const cancelEdit = () => {
    editingId.value = null;
    expandedRows.value = {};
    editForm.reset();
    editForm.clearErrors();
};

const deleteTracker = (id: number) => {
    Swal.fire({
        title: 'Delete Tracker Entry?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel',
        customClass: { popup: 'rounded-3xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('machine-trackers.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    if (editingId.value === id) cancelEdit();
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Deleted successfully',
                        showConfirmButton: false,
                        timer: 1800
                    });
                }
            });
        }
    });
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: flash.success,
            showConfirmButton: false,
            timer: 1800
        });
    }
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Daily Machine Tracker">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <Head title="Daily Machine Tracker | Fleet" />

        <div class="my-6">
            <div class="max-w-[1600px] mx-auto space-y-8 px-4 sm:px-6 lg:px-8">

                <!-- ── Create Form Section (v1) ── -->
                <section>
                    <MachineTrackerCreateForm :form="createForm" :machineOptions="machineOptions"
                        :shiftOptions="SHIFT_OPTIONS" :operatorOptions="operatorOptions" :errors="createForm.errors"
                        :processing="createForm.processing" @save="submitCreate" @reset="resetCreateForm" />
                </section>

                <!-- ── Table & Inline Row Edit Section (v1 & v2) ── -->
                <section>
                    <MachineTrackerIndexList :trackers="trackers" :expandedRows="expandedRows" :editingId="editingId"
                        :editForm="editForm" :machineOptions="machineOptions" :shiftOptions="SHIFT_OPTIONS"
                        :operatorOptions="operatorOptions" :errors="editForm.errors" :processing="editForm.processing"
                        @update:expandedRows="handleExpandedRowsUpdate" @edit="startEdit" @delete="deleteTracker"
                        @submitEdit="submitEdit" @cancelEdit="cancelEdit" />
                </section>

            </div>
        </div>
    </AppLayout>
</template>
