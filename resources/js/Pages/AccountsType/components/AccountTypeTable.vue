<script setup lang="ts">
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import { ref } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { Squares2X2Icon } from '@heroicons/vue/24/outline';
import { AccountsType } from '../useAccountsTypeStore';

defineProps<{
    accountTypes: AccountsType[];
}>();

defineEmits<{
    (e: 'create'): void;
    (e: 'edit', type: AccountsType): void;
    (e: 'view', type: AccountsType): void;
    (e: 'delete', id: number): void;
}>();

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const rows = ref(10);
</script>

<template>
    <div class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-lg">
          
        <div class="bg-gray-50/50 dark:bg-gray-900/20 border-b border-gray-100 dark:border-gray-700/50 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                        <Squares2X2Icon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <h2 class="text-md font-semibold text-gray-800 dark:text-gray-100 uppercase ">Account Types</h2>
                        <p class="text-[10px] text-gray-500 font-medium uppercase tracking-[4px]">Classification Groups</p>
                    </div>
                </div>
                <Button label="New Type" icon="pi pi-plus" @click="$emit('create')" severity="primary" class="!rounded-md !px-4" />
            </div>
        </div>
             
        <BaseDataTable 
            :value="accountTypes" 
            v-model:filters="filters"
            v-model:rows="rows"
            :globalFilterFields="['code', 'title', 'account.title', 'parent.title']"
            showSerial
            showSearch
        >
           
            
            <Column field="code" header="Code" sortable style="width: 100px"></Column>
            <Column field="title" header="Title" sortable></Column>
            <Column header="Account">
                <template #body="slotProps">
                    {{ slotProps.data.account?.title || 'Unknown' }}
                </template>
            </Column>
            <Column header="Parent Type">
                <template #body="slotProps">
                    {{ slotProps.data.parent?.title || '-' }}
                </template>
            </Column>
            <Column header="Status" style="width: 100px">
                <template #body="slotProps">
                    <span :class="[
                        'px-2 py-0.5 rounded-full text-[10px] uppercase font-bold',
                        slotProps.data.status === 1 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
                    ]">
                        {{ slotProps.data.status === 1 ? 'Active' : 'Inactive' }}
                    </span>
                </template>
            </Column>
            <Column header="Actions" alignFrozen="right" frozen style="width: 120px" class="text-right">
                <template #body="slotProps">
                    <div class="flex justify-end gap-2">
                        <Button icon="pi pi-eye" text rounded @click="$emit('view', slotProps.data)" severity="secondary" />
                        <Button icon="pi pi-pencil" text rounded @click="$emit('edit', slotProps.data)" severity="info" />
                        <Button icon="pi pi-trash" text rounded @click="$emit('delete', slotProps.data.id)" severity="danger" />
                    </div>
                </template>
            </Column>
        </BaseDataTable>
    </div>
</template>
