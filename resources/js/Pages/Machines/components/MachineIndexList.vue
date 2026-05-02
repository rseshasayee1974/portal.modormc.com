<script setup lang="ts">
import { ref, computed } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import { 
    IdentificationIcon, 
    TruckIcon,
    TableCellsIcon,
    Cog6ToothIcon
} from '@heroicons/vue/24/outline';
import MachineRowEditPanel from './MachineRowEditPanel.vue';

const props = defineProps<{
    machines: any[];
    searchQuery: string;
    expandedRows: Record<number, boolean>;
    editingId: number | null;
    editForm: any;
    transportOwnerOptions: any[];
    vehicleOptions: any[];
    docTypeOptions: any[];
    addDocument: (form: any) => void;
    removeDocument: (form: any, index: number) => void;
    addLoan: (form: any) => void;
    removeLoan: (form: any, index: number) => void;
}>();

const emit = defineEmits<{
    'update:searchQuery': [value: string];
    'update:expandedRows': [value: Record<number, boolean>];
    edit: [machine: any];
    delete: [id: number];
    submitEdit: [];
    cancelEdit: [];
}>();

const perPage = ref(30);
const filters = ref({
    global: { value: null, matchMode: 'contains' }
});

const onRowClick = (event: any) => {
    const target = event.originalEvent.target as HTMLElement;
    if (target.closest('button') || target.closest('.p-button') || target.closest('a')) {
        return;
    }

    const data = event.data;
    const expanded = { ...props.expandedRows };
    
    if (expanded[data.id]) {
        delete expanded[data.id];
        emit('cancelEdit');
    } else {
        const newExpanded = { [data.id]: true };
        emit('update:expandedRows', newExpanded);
        emit('edit', data);
    }
};
</script>

<template>
    <div class="machine-table-container">
        <BaseDataTable
            v-model:expandedRows="props.expandedRows"
            v-model:filters="filters"
            v-model:rows="perPage"
            :value="machines"
            dataKey="id"
            :rowsPerPageOptions="[30, 50, 100, 200]"
            stripedRows
            showSearch
            :globalFilterFields="['registration', 'vehicle_model', 'vehicle_type']"
            showSerial
            heading="Fleet Inventory Master"
            headingIcon="TruckIcon"
            showExport
            exportFilename="fleet-report"
            @update:expandedRows="$emit('update:expandedRows', $event)"
            @row-click="onRowClick"
            :deleteUrl="(row) => route('machines.destroy', row.id)"
            deleteTitle="Decommission Asset?"
            deleteText="This will remove the machine from active reporting."
        >
            <template #toolbar>
                <div class="flex items-center gap-2 px-3 py-1 bg-indigo-50/50 rounded-lg border border-indigo-100">
                    <TruckIcon class="w-3.5 h-3.5 text-indigo-500" />
                    <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">{{ machines.length }} Assets Enrolled</span>
                </div>
            </template>

            <!-- Columns -->
            <Column field="registration" header="Asset Identity" sortable style="min-width: 200px">
                <template #body="slotProps">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-slate-50 border border-slate-100 text-slate-400">
                            <IdentificationIcon class="w-5 h-5" />
                        </div>
                        <div class="flex flex-col">
                            <span class="font-black text-slate-800 uppercase tracking-tight">{{ slotProps.data.registration }}</span>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest leading-none">{{ slotProps.data.vehicle_model || 'Standard Model' }}</span>
                        </div>
                    </div>
                </template>
            </Column>

            <Column field="vehicle_type" header="Classification" sortable>
                <template #body="slotProps">
                    <Tag 
                        :value="slotProps.data.vehicle_type || 'Unclassified'" 
                        class="!bg-indigo-50 !text-indigo-600 !text-[9px] !font-black !uppercase !px-2 !py-0.5 !rounded-lg !border !border-indigo-100"
                    />
                </template>
            </Column>

            <Column field="capacity" header="Tech Specs" align="center">
                <template #body="slotProps">
                    <div class="flex items-center justify-center gap-4">
                        <div class="flex flex-col items-center">
                            <span class="text-[8px] font-black text-slate-300 uppercase mb-0.5">Ton</span>
                            <span class="text-[11px] font-black text-slate-700">{{ slotProps.data.capacity || '—' }}</span>
                        </div>
                        <div class="w-px h-5 bg-slate-100"></div>
                        <div class="flex flex-col items-center">
                            <span class="text-[8px] font-black text-slate-300 uppercase mb-0.5">Year</span>
                            <span class="text-[11px] font-black text-slate-700">{{ slotProps.data.make_year || '—' }}</span>
                        </div>
                    </div>
                </template>
            </Column>

            <Column header="Status" style="width: 120px" align="center">
                <template #body="slotProps">
                    <div class="flex items-center justify-center gap-2">
                         <span class="flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                         <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Active</span>
                    </div>
                </template>
            </Column>

            <!-- Row Expansion -->
            <template #expansion="slotProps">
                <div v-if="editingId === slotProps.data.id">
                    <MachineRowEditPanel
                        :machine-id="slotProps.data.id"
                        :form="editForm"
                        :transportOwnerOptions="transportOwnerOptions"
                        :vehicle-options="vehicleOptions"
                        :doc-type-options="docTypeOptions"
                        :add-document="() => addDocument(editForm)"
                        :remove-document="(index: number) => removeDocument(editForm, index)"
                        :add-loan="() => addLoan(editForm)"
                        :remove-loan="(index: number) => removeLoan(editForm, index)"
                        @submit="$emit('submitEdit')"
                        @cancel="$emit('cancelEdit')"
                    />
                </div>
                <div v-else class="py-12 flex flex-col items-center justify-center gap-3 bg-slate-50/10">
                     <Cog6ToothIcon class="w-8 h-8 text-slate-200 animate-spin-slow" />
                     <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Hydrating Asset Parameters...</p>
                </div>
            </template>

            <!-- Empty State -->
            <template #empty>
                <div class="py-20 flex flex-col items-center gap-4 text-slate-400">
                    <TruckIcon class="w-12 h-12 opacity-20" />
                    <div class="text-center">
                        <p class="text-[11px] font-black uppercase tracking-widest">Fleet Registry Empty</p>
                        <p class="text-[10px] font-medium mt-1">Enroll your logistics assets above.</p>
                    </div>
                </div>
            </template>
        </BaseDataTable>
    </div>
</template>

<style scoped>
.machine-table-container {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

@keyframes spin-slow {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.animate-spin-slow {
  animation: spin-slow 8s linear infinite;
}
</style>

<style scoped>
</style>
