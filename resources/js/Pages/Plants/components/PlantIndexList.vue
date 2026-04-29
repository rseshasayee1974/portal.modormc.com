<script setup lang="ts">
import { computed, ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseActionButton from '@/Components/Base/BaseActionButton.vue';
import { MagnifyingGlassIcon, BuildingOfficeIcon } from '@heroicons/vue/24/outline';
import PlantRowEditPanel from './PlantRowEditPanel.vue';

const props = defineProps<{
    plants: any[];
    searchQuery: string;
    expandedRows: any;
    editingId: number | null;
    editForm: any;
    entities: any[];
    addressTypes: any[];
    contactTypes: any[];
    states: any[];
    errors?: any;
    processing?: boolean;
    totalRecords?: number;
    perPage?: number;
    loading?: boolean;
}>();

const emit = defineEmits<{
    'update:searchQuery': [value: string];
    'update:expandedRows': [value: any];
    'search': [];
    'page': [event: any];
    'sort': [event: any];
    'delete': [id: number];
    'submitEdit': [];
    'cancelEdit': [];
}>();

const search = computed({
    get: () => props.searchQuery ?? '',
    set: (v: string) => emit('update:searchQuery', v),
});

const onRowClick = (event: any) => {
    const target = event.originalEvent.target as HTMLElement;
    if (target.closest('button') || target.closest('.p-button') || target.closest('a') || target.closest('.p-checkbox')) {
        return;
    }

    const data = event.data;
    const expanded = { ...props.expandedRows };
    
    if (expanded[data.id]) {
        delete expanded[data.id];
    } else {
        // Toggle expansion for this row only (or you can allow multiple if desired)
        // Here we match Patron UI behavior: toggle current
        expanded[data.id] = true;
    }
    
    emit('update:expandedRows', expanded);
};

</script>

<template>
    <BaseDataTable
        :value="plants"
        dataKey="id"
        stripedRows
        paginator
        :rows="perPage || 30"
        @update:rows="$emit('update:perPage', $event)"
        :totalRecords="totalRecords"
        lazy
        heading="Plant Directory"
        headingIcon="pi pi-building"
        showSearch
        showSerial
        :expandedRows="expandedRows"
        @update:expandedRows="$emit('update:expandedRows', $event)"
        @row-click="onRowClick"
        @page="$emit('page', $event)"
        @sort="$emit('sort', $event)"
    >
        <Column field="name" header="Plant Detail" sortable>
            <template #body="slotProps">
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-semibold text-slate-800 uppercase dark:text-slate-100 tracking-tight leading-none mb-0.5">{{ slotProps.data.name }}</span>
                    <div class="flex items-center gap-2">
                        <Tag 
                            :value="slotProps.data.code" 
                            class="!bg-slate-100 !text-slate-500 !text-[9px] !font-semibold !uppercase !px-2 !py-0.5 !rounded-md"
                        />
                        <Tag 
                            v-if="slotProps.data.plant_type"
                            :value="slotProps.data.plant_type" 
                            class="!bg-indigo-50 !text-indigo-600 !text-[9px] !font-black !uppercase !px-2 !py-0.5 !rounded-md"
                        />
                        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-tighter">{{ slotProps.data.entity?.legal_name }}</span>
                    </div>
                </div>
            </template>
        </Column>

        <Column header="Primary Contact">
            <template #body="slotProps">
                <div v-if="slotProps.data.contacts?.[0]" class="flex flex-col">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ slotProps.data.contacts[0].name }}</span>
                    <div class="flex items-center gap-1.5 mt-0.5">
                         <span class="text-[10px] font-bold text-slate-400">{{ slotProps.data.contacts[0].mobile || 'N/A' }}</span>
                    </div>
                </div>
                <span v-else class="text-slate-300 text-xs font-medium italic">Not available</span>
            </template>
        </Column>

        <Column header="Location">
            <template #body="slotProps">
                <div v-if="slotProps.data.addresses?.[0]" class="flex flex-col max-w-[200px]">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300 truncate">{{ slotProps.data.addresses[0].city }}</span>
                    <span class="text-[10px] font-bold text-slate-400 truncate mt-0.5">{{ slotProps.data.addresses[0].line_1 }}</span>
                </div>
                <span v-else class="text-slate-300 text-xs font-medium italic">No address</span>
            </template>
        </Column>

        <Column field="is_active" header="Status" sortable style="width: 120px">
            <template #body="slotProps">
                <div class="flex items-center gap-2">
                    <i
                        class="pi text-xs"
                        :class="slotProps.data.is_active ? 'pi-check-circle text-emerald-500 drop-shadow-[0_0_6px_rgba(16,185,129,0.4)]' : 'pi-times-circle text-slate-300'"
                    ></i>
                    <span class="text-[11px] font-black uppercase tracking-wider" :class="slotProps.data.is_active ? 'text-emerald-600' : 'text-slate-400'">
                        {{ slotProps.data.is_active ? 'Operational' : 'Inactive' }}
                    </span>
                </div>
            </template>
        </Column>

        <Column header="Actions" class="text-right" style="width: 100px">
            <template #body="slotProps">
                <div class="flex justify-end gap-1">
                    <BaseActionButton
                        icon="pi pi-trash"
                        severity="danger"
                        tooltip="Delete Plant"
                        @click.stop="$emit('delete', slotProps.data.id)"
                    />
                </div>
            </template>
        </Column>

        <template #expansion="slotProps">
            <PlantRowEditPanel
                v-if="editingId === slotProps.data.id"
                :plant-id="slotProps.data.id"
                :form="editForm"
                :entities="entities"
                :address-types="addressTypes"
                :contact-types="contactTypes"
                :states="states"
                :errors="errors"
                :processing="processing"
                @submit="$emit('submitEdit')"
                @cancel="$emit('cancelEdit')"
            />
            <div v-else class="p-12 flex flex-col items-center justify-center bg-slate-50/30 dark:bg-slate-900/10">
                <div class="w-12 h-12 rounded-full border-4 border-slate-200 border-t-indigo-600 animate-spin mb-4"></div>
                <p class="text-xs font-black text-slate-400 uppercase tracking-widest animate-pulse">Initializing Editor Securely...</p>
            </div>
        </template>

        <template #empty>
            <div class="p-20 flex flex-col items-center justify-center text-center">
                <div class="w-20 h-20 rounded-3xl bg-slate-50 dark:bg-slate-900/50 flex items-center justify-center mb-6">
                    <i class="pi pi-search text-3xl text-slate-300"></i>
                </div>
                <h4 class="text-lg font-black text-slate-700 dark:text-slate-200 uppercase tracking-tight">No Plants Found</h4>
                <p class="text-sm text-slate-400 font-medium max-w-xs mt-2">Try adjusting your search or filters to find what you're looking for.</p>
            </div>
        </template>
    </BaseDataTable>
</template>

<style scoped>
:deep(.p-datatable-thead > tr > th) {
    @apply !bg-slate-50/50 dark:!bg-slate-900/50 !text-slate-400 !font-black !text-[10px] !uppercase !tracking-[0.2em] !py-6 !border-b !border-slate-100 dark:!border-slate-800;
}

:deep(.p-datatable-tbody > tr) {
    @apply !transition-all !duration-300 cursor-pointer;
}

:deep(.p-datatable-tbody > tr:hover) {
    @apply !bg-indigo-50/20 dark:!bg-indigo-900/10;
}

:deep(.p-datatable-tbody > tr > td) {
    @apply !py-5 !border-b !border-slate-50/50 dark:!border-slate-800/50;
}

:deep(.p-datatable-row-expansion > td) {
    @apply !bg-white dark:!bg-slate-900 !p-0;
}

:deep(.p-paginator) {
    @apply !bg-transparent !border-t !border-slate-100 dark:!border-slate-800 !py-5;
}

:deep(.p-paginator-current) {
    @apply !text-[11px] !font-black !text-slate-400 !uppercase !tracking-widest;
}

:deep(.p-paginator-element) {
    @apply !text-slate-500 !rounded-xl !transition-all !w-10 !h-10 !text-xs !font-black;
}

:deep(.p-paginator-element.p-highlight) {
    @apply !bg-indigo-600 !text-white !shadow-lg !shadow-indigo-200 dark:!shadow-none;
}
</style>
