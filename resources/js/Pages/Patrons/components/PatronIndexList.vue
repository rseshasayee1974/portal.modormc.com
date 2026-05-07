<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import type { Patron } from '../types';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import PatronRowEditPanel from './PatronRowEditPanel.vue';

const props = defineProps<{
    patrons: Patron[];
    searchQuery: string;
    expandedRows: Record<number, boolean>;
    editingId: number | null;
    editForm: any;
    patronTypes: any[];
    operationalStatuses: any[];
    states: any[];
    addBank: (form: any) => void;
    removeBank: (form: any, index: number) => void;
    totalRecords?: number;
    perPage?: number;
}>();

const emit = defineEmits<{
    'update:searchQuery': [value: string];
    'update:expandedRows': [value: Record<number, boolean>];
    'update:perPage': [value: number];
    'edit': [patron: Patron];
    'delete': [id: number];
    'submitEdit': [];
    'cancelEdit': [];
    'page': [event: any];
    'sort': [event: any];
}>();

const filters = ref({
    global: { value: props.searchQuery, matchMode: 'contains' }
});

watch(() => props.searchQuery, (newVal) => {
    filters.value.global.value = newVal;
});

watch(() => filters.value.global.value, (newVal) => {
    emit('update:searchQuery', newVal);
});

const operationalStatusMap: Record<string, string> = {
    active: 'success',
    paused: 'warn',
    blocked: 'danger',
    closed: 'secondary',
};

const getStatusSeverity = (status: string) => operationalStatusMap[status] || 'info';

const onRowClick = (event: any) => {
    const target = event.originalEvent.target as HTMLElement;
    if (target.closest('button') || target.closest('.p-button') || target.closest('a')) {
        return;
    }

    const data = event.data;
    const expanded = { ...props.expandedRows };
    
    if (expanded[data.id]) {
        delete expanded[data.id];
    } else {
        expanded[data.id] = true;
    }
    
    emit('update:expandedRows', expanded);
};

</script>

<template>
    <BaseDataTable
        :value="patrons"
        dataKey="id"
        stripedRows
        paginator
        :rows="perPage || 30"
        @update:rows="$emit('update:perPage', $event)"
        :totalRecords="totalRecords"
        heading="Patron Directory"
        headingIcon="pi pi-users"
        showSearch
        v-model:filters="filters"
        showSerial
        :expandedRows="expandedRows"
        @update:expandedRows="$emit('update:expandedRows', $event)"
        @row-click="onRowClick"
        @page="$emit('page', $event)"
        @sort="$emit('sort', $event)"
    >
        <Column field="legal_name" header="Patron / Entity" sortable>
            <template #body="slotProps">
                <div class="flex flex-col gap-1.5 py-1">
                    <div class="flex items-center gap-2.5">
                         <Tag 
                            v-if="slotProps.data.code"
                            :value="slotProps.data.code" 
                            class="!bg-indigo-50 dark:!bg-indigo-900/30 !text-indigo-600 dark:!text-indigo-400 !text-[9px] !font-black !uppercase !px-2 !py-0.5 !rounded-lg"
                        />
                        <span class="font-extrabold text-slate-800 dark:text-slate-200 text-sm tracking-tight">{{ slotProps.data.legal_name }}</span>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <Tag
                            v-for="t in (Array.isArray(slotProps.data.patron_type) ? slotProps.data.patron_type : [slotProps.data.patron_type])"
                            :key="t"
                            :value="t"
                            severity="info"
                            pt:root:style="font-size: 8px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em"
                            rounded
                        />
                    </div>
                </div>
            </template>
        </Column>

        <Column header="Primary Contact">
            <template #body="slotProps">
                <div v-if="slotProps.data.contacts?.[0]" class="flex flex-col gap-0.5">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ slotProps.data.contacts[0].name }}</span>
                    <div class="flex items-center gap-1.5 opacity-60">
                        <i class="pi pi-phone text-[10px]"></i>
                        <span class="text-[10px] font-medium tracking-tight">{{ slotProps.data.contacts[0].mobile || 'No Phone' }}</span>
                    </div>
                </div>
                <span v-else class="text-slate-300 dark:text-slate-700 text-xs italic">Not assigned</span>
            </template>
        </Column>

        <Column header="Identifiers">
            <template #body="slotProps">
                <div class="flex flex-col gap-1">
                    <div v-if="slotProps.data.gstin" class="flex items-center gap-1.5">
                        <span class="text-[9px] font-black bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 px-1.5 py-0.5 rounded uppercase">GST</span>
                        <span class="text-xs font-mono font-bold text-slate-600 dark:text-slate-400">{{ slotProps.data.gstin }}</span>
                    </div>
                    <div v-if="slotProps.data.pan_no" class="flex items-center gap-1.5">
                        <span class="text-[9px] font-black bg-blue-50 dark:bg-blue-900/20 text-blue-600 px-1.5 py-0.5 rounded uppercase">PAN</span>
                        <span class="text-xs font-mono font-bold text-slate-600 dark:text-slate-400">{{ slotProps.data.pan_no }}</span>
                    </div>
                    <span v-if="!slotProps.data.gstin && !slotProps.data.pan_no" class="text-slate-300 dark:text-slate-700 text-xs">-</span>
                </div>
            </template>
        </Column>

        <Column field="operational_status" header="Status" sortable style="width: 140px">
            <template #body="slotProps">
                <Tag 
                    :value="slotProps.data.operational_status" 
                    :severity="getStatusSeverity(slotProps.data.operational_status)" 
                    rounded 
                    pt:root:style="font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; padding: 4px 10px" 
                />
            </template>
        </Column>

        <Column header="" class="text-right" style="width: 80px">
            <template #body="slotProps">
                <div class="flex justify-center">
                    <Button 
                        icon="pi pi-trash"  
                        text 
                        rounded 
                        severity="danger" 
                        class="hover:!bg-red-50 dark:hover:!bg-red-900/10"
                        @click="$emit('delete', slotProps.data.id)" 
                    />
                </div>
            </template>
        </Column>

        <template #expansion="slotProps">
            <div class="p-6 bg-slate-50/50 dark:bg-slate-950/40 border-y border-slate-100 dark:border-slate-800">
                <PatronRowEditPanel
                    v-if="editingId === slotProps.data.id"
                    :patron-id="slotProps.data.id"
                    :form="editForm"
                    :patron-types="patronTypes"
                    :operational-statuses="operationalStatuses"
                    :states="states"
                    :add-bank="() => addBank(editForm)"
                    :remove-bank="(index: number) => removeBank(editForm, index)"
                    @submit="$emit('submitEdit')"
                    @cancel="$emit('cancelEdit')"
                />
                <div v-else class="flex items-center justify-center py-12 text-slate-400">
                    <i class="pi pi-spin pi-spinner mr-3"></i>
                    <span class="text-sm font-bold uppercase tracking-widest">Preparing Editor...</span>
                </div>
            </div>
        </template>

        <template #empty>
            <div class="py-24 flex flex-col items-center justify-center opacity-30">
                <i class="pi pi-users text-7xl mb-4"></i>
                <p class="text-xl font-black uppercase tracking-widest">No Patrons Found</p>
            </div>
        </template>
    </BaseDataTable>
</template>

<style scoped>
:deep(.p-datatable-thead > tr > th) {
    @apply !bg-slate-50/80 dark:!bg-slate-900/50 !text-slate-400 !font-black !text-[10px] !uppercase !tracking-[0.2em] !py-6 !px-6 !border-b !border-slate-100 dark:!border-slate-800;
}

:deep(.p-datatable-tbody > tr) {
    @apply !transition-all !duration-300 cursor-pointer;
}

:deep(.p-datatable-tbody > tr:hover) {
    @apply !bg-indigo-50/40 dark:!bg-indigo-900/10;
}

:deep(.p-datatable-tbody > tr > td) {
    @apply !py-5 !px-6 !border-b !border-slate-50 dark:!border-slate-800/60;
}

:deep(.p-datatable-row-expansion > td) {
    @apply !p-0;
}

:deep(.p-paginator) {
    @apply !bg-white dark:!bg-slate-900 !border-t !border-slate-100 dark:!border-slate-800 !py-6 !px-6;
}

:deep(.p-paginator-current) {
    @apply !text-[11px] !font-black !text-slate-400 !uppercase !tracking-widest;
}

:deep(.p-paginator-element) {
    @apply !text-slate-500 !rounded-xl !transition-all !w-10 !h-10 !m-0.5;
}

:deep(.p-paginator-element.p-highlight) {
    @apply !bg-indigo-600 !text-white !shadow-lg !shadow-indigo-200 dark:!shadow-none;
}
</style>
