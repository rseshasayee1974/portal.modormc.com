<script setup lang="ts">
import { computed, ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import type { Patron } from '../types';
import BaseInput from '@/Components/Base/BaseInput.vue';
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
}>();

const emit = defineEmits<{
    'update:searchQuery': [value: string];
    'update:expandedRows': [value: Record<number, boolean>];
    edit: [patron: Patron];
    delete: [id: number];
    submitEdit: [];
    cancelEdit: [];
}>();

const search = computed({
    get: () => props.searchQuery ?? '',
    set: (v: string) => emit('update:searchQuery', v),
});

const perPage = ref(30);

const operationalStatusMap: Record<string, string> = {
    active: 'success',
    paused: 'warn',
    blocked: 'danger',
    closed: 'secondary',
};

const getStatusSeverity = (status: string) => operationalStatusMap[status] || 'info';

const onRowClick = (event: any) => {
    // Prevent toggling if the click is on a button or an interactive element
    const target = event.originalEvent.target as HTMLElement;
    if (target.closest('button') || target.closest('.p-button') || target.closest('a')) {
        return;
    }

    const data = event.data;
    const expanded = { ...props.expandedRows };
    
    if (expanded[data.id]) {
        delete expanded[data.id];
    } else {
        // Option: close others if needed, but here we toggle per row
        expanded[data.id] = true;
    }
    
    emit('update:expandedRows', expanded);
};

</script>

<template>
    <div class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
        <DataTable
            :value="patrons"
            dataKey="id"
            stripedRows
            paginator
            :rows="perPage"
            paginatorTemplate="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
            currentPageReportTemplate="{first} to {last} of {totalRecords}"
            class="p-datatable-sm"
            :expandedRows="expandedRows"
            @update:expandedRows="$emit('update:expandedRows', $event)"
            @row-click="onRowClick"
            row-hover
        >
            <template #header>
                <div class="header-premium flex flex-col sm:flex-row justify-between items-center gap-4 px-1 py-1">
                    <!-- Left Side: Title & Info -->
                    <div class="flex items-center gap-4">
                        <div class="flex items-center justify-center w-11 h-11 rounded-2xl bg-indigo-600 shadow-lg shadow-indigo-200 dark:shadow-indigo-900/20 transform transition-transform hover:scale-105">
                            <i class="pi pi-users text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-extrabold text-slate-800 dark:text-slate-100 leading-none">List of Patron</h3>
                            <div class="flex items-center gap-2 mt-1.5">
                                <span class="flex h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest leading-none">Management Explorer</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Side: Controls -->
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <div class="per-page-selector relative">
                            <BaseSelect 
                                v-model="perPage" 
                                :options="[30, 50, 100, 200]" 
                                class="!h-10 !w-24 !bg-slate-50 !border-slate-200 !rounded-xl !text-xs font-bold !text-slate-500 hover:!bg-white hover:!border-indigo-300 transition-all shadow-sm"
                                appendTo="body"
                            />
                        </div>

                        <div class="w-full max-w-xs sm:w-72 relative group">
                            <BaseInput
                                v-model="search"
                                placeholder="Search manifests by name, ID..."
                                inputClass="!h-10 !pl-10 !bg-slate-50 !border-slate-200 !rounded-xl !text-xs font-medium !text-slate-700 group-hover:!bg-white group-hover:!border-indigo-300 transition-all shadow-sm focus:!ring-2 focus:!ring-indigo-100"
                                fieldClass="!gap-0"
                            />
                            <div class="absolute left-3.5 top-1/2 -translate-y-1/2">
                                <i class="pi pi-search text-slate-400 group-hover:text-indigo-500 transition-colors text-sm" />
                            </div>
                            <!-- Subtle shortcut hint -->
                            <!-- <div class="absolute right-3 top-1/2 -translate-y-1/2 hidden md:flex items-center bg-white border border-slate-200 px-1.5 py-0.5 rounded-md shadow-xs pointer-events-none">
                                <span class="text-[8px] font-black text-slate-300 tracking-tighter">CMD+K</span>
                            </div> -->
                        </div>
                    </div>
                </div>
            </template>

            <!-- Removed expander column to enable full row click expansion -->

            <Column header="S.No" style="width: 70px" align="center">
                <template #body="slotProps">
                    <div class="flex items-center justify-center">
                        <span class="flex items-center justify-center w-8 h-8 rounded-full bg-slate-100 text-slate-600 font-black text-[11px] shadow-sm border border-slate-100/50">
                            {{ slotProps.index + 1 }}
                        </span>
                    </div>
                </template>
            </Column>

            <Column field="legal_name" header="Patron / Entity" sortable>
                <template #body="slotProps">
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2">
                             <Tag 
                                v-if="slotProps.data.code"
                                :value="slotProps.data.code" 
                                class="!bg-indigo-50 !text-indigo-600 !text-[9px] !font-black !uppercase !px-2 !py-0.5 !rounded-md"
                            />
                            <span class="font-bold text-gray-800">{{ slotProps.data.legal_name }}</span>
                        </div>
                        <div class="flex gap-1 mt-1">
                            <Tag
                                v-for="t in (Array.isArray(slotProps.data.patron_type) ? slotProps.data.patron_type : [slotProps.data.patron_type])"
                                :key="t"
                                :value="t"
                                severity="info"
                                pt:root:style="font-size: 8px; padding: 1px 4px"
                            />
                        </div>
                    </div>
                </template>
            </Column>

            <Column header="Primary Contact">
                <template #body="slotProps">
                    <div v-if="slotProps.data.contacts?.[0]" class="flex flex-col">
                        <span class="text-xs font-semibold">{{ slotProps.data.contacts[0].name }}</span>
                        <span class="text-[10px] text-gray-500">{{ slotProps.data.contacts[0].mobile || slotProps.data.contacts[0].email }}</span>
                    </div>
                    <span v-else class="text-gray-300 text-xs">-</span>
                </template>
            </Column>

            <Column header="Location">
                <template #body="slotProps">
                    <div v-if="slotProps.data.gstin" class="flex flex-col">
                        <span class="text-xs font-semibold">{{ slotProps.data.gstin }}</span>
                    </div>
                    <span v-else class="text-gray-300 text-xs">No GST</span>
                    <!-- <div v-if="slotProps.data.contacts?.[0]?.addresses?.[0]" class="flex max-w-xs flex-col text-xs text-gray-600">
                        <span class="truncate">
                            {{ slotProps.data.contacts[0].addresses[0].line_1 }}
                            <template v-if="slotProps.data.contacts[0].addresses[0].line_2">
                                , {{ slotProps.data.contacts[0].addresses[0].line_2 }}
                            </template>
                        </span>
                        <span class="truncate text-[10px] text-gray-500">
                            {{ slotProps.data.contacts[0].addresses[0].city }}
                            <template v-if="slotProps.data.contacts[0].addresses[0].zipcode">
                                - {{ slotProps.data.contacts[0].addresses[0].zipcode }}
                            </template>
                        </span>
                    </div>
                    <span v-else class="text-gray-300 text-xs">-</span> -->
                </template>
            </Column>

            <Column field="operational_status" header="Status" sortable>
                <template #body="slotProps">
                    <Tag :value="slotProps.data.operational_status" :severity="getStatusSeverity(slotProps.data.operational_status)" rounded pt:root:style="font-size: 11px" />
                </template>
            </Column>

            <Column header="Actions" class="text-right" style="width: 120px">
                <template #body="slotProps">
                    <div class="flex justify-center  gap-1">
                        <!-- <Button icon="pi pi-pencil" text rounded size="small" severity="warn" @click="$emit('edit', slotProps.data)" /> -->
                        <Button icon="pi pi-trash"  text rounded size="small" severity="danger" @click="$emit('delete', slotProps.data.id)" />
                    </div>
                </template>
            </Column>

            <template #expansion="slotProps">
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
                <div v-else class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-500">
                    Loading patron editor...
                </div>
            </template>
        </DataTable>
    </div>
</template>

<style scoped>
:deep(.p-datatable-thead > tr > th) {
    @apply !bg-slate-50/80 !text-slate-400 !font-extrabold !text-[10px] !uppercase !tracking-widest !py-5 !border-b !border-slate-100;
}

:deep(.p-datatable-tbody > tr) {
    @apply !transition-all !duration-200 cursor-pointer;
}

:deep(.p-datatable-tbody > tr:hover) {
    @apply !bg-indigo-50/30;
}

:deep(.p-datatable-tbody > tr > td) {
    @apply !py-4 !border-b !border-slate-50;
}

/* Expansion row styling */
:deep(.p-datatable-row-expansion > td) {
    @apply !bg-slate-50/50 !p-0;
}

/* Paginator polish */
:deep(.p-paginator) {
    @apply !bg-white !border-t !border-slate-100 !py-3;
}

:deep(.p-paginator-current) {
    @apply !text-[11px] !font-bold !text-slate-400 !uppercase !tracking-tighter;
}

:deep(.p-paginator-element) {
    @apply !text-slate-500 !rounded-lg !transition-all;
}

:deep(.p-paginator-element:hover) {
    @apply !bg-indigo-50 !text-indigo-600;
}

:deep(.p-paginator-element.p-highlight) {
    @apply !bg-indigo-600 !text-white !shadow-md !shadow-indigo-100;
}
</style>
