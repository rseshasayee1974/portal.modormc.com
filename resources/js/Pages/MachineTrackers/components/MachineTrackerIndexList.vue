<script setup lang="ts">
import { ref } from 'vue';
import Column from 'primevue/column';
import { PencilSquareIcon, TrashIcon, ClockIcon } from '@heroicons/vue/24/outline';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import MachineTrackerEditForm from './MachineTrackerEditForm.vue';
import type { MachineTracker, OptionItem } from '../types';

const props = defineProps<{
    trackers: MachineTracker[];
    expandedRows: Record<number, boolean>;
    editingId: number | null;
    editForm: any;
    machineOptions: OptionItem[];
    shiftOptions: OptionItem[];
    operatorOptions: OptionItem[];
    errors?: any;
    processing?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:expandedRows', val: any): void;
    (e: 'edit', tracker: MachineTracker): void;
    (e: 'delete', id: number): void;
    (e: 'submitEdit'): void;
    (e: 'cancelEdit'): void;
}>();

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const getShiftLabel = (shiftVal: number) => {
    const found = props.shiftOptions.find(s => s.value === shiftVal);
    return found ? found.label : 'General';
};
</script>

<template>
    <div
        class="bg-white dark:bg-slate-900 shadow-xl shadow-slate-200/40 dark:shadow-none rounded-2xl border border-slate-100 dark:border-slate-800 overflow-hidden">
        <BaseDataTable :value="trackers" v-model:filters="filters"
            :globalFilterFields="['machine.registration', 'operation_type', 'operator.username', 'notes', 'pump_name', 'category']"
            showSearch showSerial heading="Tracker Ledger" headingIcon="ClockIcon" :rows="30"
            :expandedRows="expandedRows" @update:expandedRows="emit('update:expandedRows', $event)"
            class="tracker-table">
            <!-- Expander Column -->
            <Column expander style="width: 3rem" />

            <!-- Machine / Vehicle Asset -->
            <Column header="Vehicle" sortable field="machine.registration">
                <template #body="slotProps">
                    <span
                        class="font-mono text-xs font-bold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800/80 px-2 py-1 rounded-md">
                        {{ slotProps.data.machine?.registration || '—' }}
                    </span>
                </template>
            </Column>

            <!-- Shift -->
            <Column header="Shift" sortable field="shift">
                <template #body="slotProps">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                        {{ getShiftLabel(slotProps.data.shift) }}
                    </span>
                </template>
            </Column>

            <!-- Operator -->
            <Column header="Operator" sortable field="operator.first_name">
                <template #body="slotProps">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                        {{ slotProps.data.operator ? (slotProps.data.operator.first_name + ' ' +
                            (slotProps.data.operator.last_name || '')).trim() : '—' }}
                    </span>
                </template>
            </Column>

            <!-- Odometer Readings -->
            <Column header="Odometer (Start/End)">
                <template #body="slotProps">
                    <span class="text-xs font-mono font-medium text-slate-600 dark:text-slate-300">
                        {{ Number(slotProps.data.odometer_start || 0) }} – {{ Number(slotProps.data.odometer_end || 0)
                        }}
                    </span>
                </template>
            </Column>

            <!-- Hourmeter Readings -->
            <Column header="Hourmeter (Start/End)">
                <template #body="slotProps">
                    <span class="text-xs font-mono font-medium text-slate-600 dark:text-slate-300">
                        {{ Number(slotProps.data.hourmeter_start || 0) }} – {{ Number(slotProps.data.hourmeter_end || 0)
                        }}
                    </span>
                </template>
            </Column>

            <!-- EB Start / Close -->
            <Column header="EB Start/Close">
                <template #body="slotProps">
                    <span class="text-xs font-mono font-medium text-slate-500 dark:text-slate-400">
                        {{ Number(slotProps.data.eb_start || 0) }} – {{ Number(slotProps.data.eb_close || 0) }}
                    </span>
                </template>
            </Column>

            <!-- Fuel Filled & Amount -->
            <Column header="Fuel Filled">
                <template #body="slotProps">
                    <div class="flex flex-col font-mono text-xs">
                        <span class="text-emerald-600 dark:text-emerald-400 font-bold">
                            {{ Number(slotProps.data.fuel || 0) }} L
                        </span>
                        <span class="text-[10px] text-slate-400">
                            ₹{{ Number(slotProps.data.amount || 0).toLocaleString('en-IN', {
                                minimumFractionDigits: 2,
                            maximumFractionDigits: 2 }) }}
                        </span>
                    </div>
                </template>
            </Column>

            <!-- Actions / Control -->
            <Column header="Control" style="width: 120px" align="right">
                <template #body="slotProps">
                    <div class="flex justify-end items-center gap-2">
                        <button type="button" @click.stop="emit('edit', slotProps.data)"
                            class="flex items-center justify-center w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-all active:scale-95"
                            title="Modify / Edit">
                            <PencilSquareIcon class="w-4 h-4" />
                        </button>
                        <button type="button" @click.stop="emit('delete', slotProps.data.id)"
                            class="flex items-center justify-center w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-500 hover:bg-rose-100 dark:hover:bg-rose-900/50 transition-all active:scale-95"
                            title="Delete">
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </template>
            </Column>

            <!-- ── Row Expansion: Inline Edit Form (v2) ── -->
            <template #expansion="slotProps">
                <MachineTrackerEditForm :tracker="slotProps.data" :form="editForm" :machineOptions="machineOptions"
                    :shiftOptions="shiftOptions" :operatorOptions="operatorOptions" :errors="errors || editForm.errors"
                    :processing="processing || editForm.processing" @submit="emit('submitEdit')"
                    @cancel="emit('cancelEdit')" />
            </template>

            <!-- Empty State -->
            <template #empty>
                <div class="py-16 flex flex-col items-center gap-3">
                    <div
                        class="w-14 h-14 rounded-2xl bg-slate-50 dark:bg-slate-800/60 flex items-center justify-center text-slate-400">
                        <ClockIcon class="w-7 h-7 stroke-[1.5]" />
                    </div>
                    <div class="text-center">
                        <p class="text-xs font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                            No Tracker Logs Available
                        </p>
                        <p class="text-[11px] font-medium text-slate-400 dark:text-slate-500 mt-0.5">
                            Submit a new machine log sheet using the form above.
                        </p>
                    </div>
                </div>
            </template>
        </BaseDataTable>
    </div>
</template>
