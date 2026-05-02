<script setup lang="ts">
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { ref } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { BookOpenIcon } from '@heroicons/vue/24/outline';
import { Ledger } from '../useLedgerStore';

defineProps<{
    ledgers: Ledger[];
}>();

defineEmits<{
    (e: 'create'): void;
    (e: 'edit', ledger: Ledger): void;
    (e: 'view', ledger: Ledger): void;
    (e: 'delete', id: number): void;
}>();

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const rows = ref(15);
</script>

<template>
    <div class="card bg-white shadow-sm border border-gray-200 rounded-lg overflow-hidden">
        <BaseDataTable 
            :value="ledgers" 
            v-model:filters="filters"
            v-model:rows="rows"
            :globalFilterFields="['code', 'title', 'account_type.title']"
            showSerial 
            showSearch
            heading="General Ledger Master"
            headingIcon="BookOpenIcon"
            showExport
            exportFilename="general-ledger-report"
        >
            <template #toolbar>
                <Button label="New Ledger" icon="pi pi-plus" @click="$emit('create')" class="!rounded-lg !px-4 !h-10 !text-xs !font-black !uppercase !tracking-widest" />
            </template>
            <Column field="code" header="Code" sortable>
                <template #body="slotProps">
                    <span class="font-mono font-bold text-indigo-600">{{ slotProps.data.code }}</span>
                </template>
            </Column>

            <Column field="title" header="Ledger Title" sortable>
                <template #body="slotProps">
                    <span class="font-semibold text-gray-700">{{ slotProps.data.title }}</span>
                </template>
            </Column>

            <Column field="account_type.title" header="Category" sortable>
                <template #body="slotProps">
                    <Tag :value="slotProps.data.account_type?.title" severity="secondary" rounded pt:root:style="font-size: 10px !important" />
                </template>
            </Column>

            <Column field="is_pnl" header="P&L" class="text-center">
                <template #body="slotProps">
                    <i v-if="slotProps.data.is_pnl" class="pi pi-check text-green-500 font-bold"></i>
                    <i v-else class="pi pi-times text-gray-300"></i>
                </template>
            </Column>

            <Column field="status" header="Status" sortable>
                <template #body="slotProps">
                    <Tag :value="slotProps.data.status === 1 ? 'Active' : 'Inactive'" :severity="slotProps.data.status === 1 ? 'success' : 'secondary'" rounded pt:root:style="font-size: 8px" />
                </template>
            </Column>

            <Column header="Actions" class="text-right" style="width: 120px">
                <template #body="slotProps">
                    <div class="flex justify-end gap-1">
                        <Button icon="pi pi-eye" text rounded severity="secondary" @click="$emit('view', slotProps.data)" />
                        <Button icon="pi pi-pencil" text rounded severity="primary" @click="$emit('edit', slotProps.data)" />
                        <Button icon="pi pi-trash" text rounded severity="danger" @click="$emit('delete', slotProps.data.id)" />
                    </div>
                </template>
            </Column>
        </BaseDataTable>
    </div>
</template>
