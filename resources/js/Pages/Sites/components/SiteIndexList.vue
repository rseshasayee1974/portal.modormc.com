<script setup lang="ts">
import { computed, ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import BaseInput from '@/Components/Base/BaseInput.vue';
import { MagnifyingGlassIcon, MapIcon } from '@heroicons/vue/24/outline';
import SiteRowEditPanel from './SiteRowEditPanel.vue';

const props = defineProps<{
    sites: any[];
    searchQuery: string;
    expandedRows: any;
    editingId: number | null;
    editForm: any;
    plants: any[];
    siteTypes: string[];
    isPrivileged: boolean;
    errors?: any;
    processing?: boolean;
}>();

const emit = defineEmits<{
    'update:searchQuery': [value: string];
    'update:expandedRows': [value: any];
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
    <div class="card bg-white dark:bg-slate-800 shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden transition-all duration-300">
        <DataTable
            :value="sites"
            dataKey="id"
            stripedRows
            :paginator="true"
            :rows="15"
            paginatorTemplate="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
            currentPageReportTemplate="{first} to {last} of {totalRecords}"
            class="p-datatable-sm"
            :expandedRows="expandedRows"
            @update:expandedRows="$emit('update:expandedRows', $event)"
            @row-click="onRowClick"
            row-hover
        >
            <template #header>
                <div class="header-premium flex flex-col sm:flex-row justify-between items-center gap-6 px-1 py-2">
                    <div class="flex items-center gap-5">
                        <div class="flex items-center justify-center w-14 h-14 rounded-[20px] bg-gradient-to-br from-indigo-500 to-indigo-700 shadow-lg shadow-indigo-200 dark:shadow-none transform transition-transform hover:rotate-6 duration-300">
                            <MapIcon class="w-8 h-8 text-white" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 leading-none tracking-tight uppercase">Sites</h3>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-[0.2em] leading-none text-wrap">Active Operational Sites Registry</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-4 w-full sm:w-auto">
                        <div class="w-full max-w-xs sm:w-80 relative group">
                            <BaseInput
                                v-model="search"
                                placeholder="Search by name, code..."
                                inputClass="!w-full !h-12 !pl-11 !pr-4 !bg-slate-50 dark:!bg-slate-900/50 !border-slate-200 dark:!border-slate-700 !rounded-2xl !text-sm font-medium !text-slate-700 dark:!text-slate-200 focus:!ring-4 focus:!ring-indigo-100 dark:focus:!ring-indigo-900/20 focus:!bg-white dark:focus:!bg-slate-900 !transition-all shadow-sm"
                            />
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-indigo-500">
                                <MagnifyingGlassIcon class="h-4 w-4 text-slate-400 group-focus-within:text-indigo-500 transition-colors" />
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <Column header="S.No" style="width: 80px" align="center">
                <template #body="slotProps">
                    <div class="flex items-center justify-center">
                        <span class="flex items-center justify-center w-9 h-9 rounded-full bg-slate-50 dark:bg-slate-900/80 text-slate-500 dark:text-slate-400 font-black text-[11px] shadow-sm border border-slate-100 dark:border-slate-800">
                            {{ slotProps.index + 1 }}
                        </span>
                    </div>
                </template>
            </Column>

            <Column field="name" header="Type" sortable>
                <template #body="slotProps">
                    <div class="flex flex-col gap-1">
                        <span class="text-xs font-bold text-slate-700 uppercase dark:text-slate-300">{{ slotProps.data.type }}</span>
                    </div>
                </template>
            </Column>

            <Column field="name" header="Site Name" sortable>
                <template #body="slotProps">
                    <div class="flex flex-col gap-1 max-w-[400px]">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ slotProps.data.name }}</span>
                        
                        <div v-if="slotProps.data.site_address_1" class="text-[10px] font-medium text-slate-500 uppercase tracking-tight line-clamp-1">
                            {{ slotProps.data.site_address_1 }} {{ slotProps.data.zipcode ? `(${slotProps.data.zipcode})` : '' }}
                        </div>

                        <div class="flex items-center gap-2">
                            <Tag 
                                v-if="slotProps.data.code"
                                :value="slotProps.data.code" 
                                class="!bg-indigo-50 !text-indigo-600 !text-[9px] !font-black !uppercase !px-2 !py-0.5 !rounded-md"
                            />
                        </div>
                    </div>
                </template>
            </Column>

            <Column field="plant.name" header="Plant Name" sortable v-if="isPrivileged">
                <template #body="slotProps">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ slotProps.data.plant?.name }}</span>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">{{ slotProps.data.plant?.code }}</span>
                    </div>
                </template>
            </Column>

            <Column header="Status" align="center" style="width: 150px">
                <template #body="slotProps">
                    <div class="flex items-center gap-2 justify-center">
                        <span 
                            class="h-2 w-2 rounded-full" 
                            :class="slotProps.data.status === 'Active' ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]' : 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.4)]'"
                        ></span>
                        <Tag 
                            :value="slotProps.data.status ?? 'Active'" 
                            :severity="slotProps.data.status === 'Active' ? 'success' : 'danger'"
                            class="!text-[10px] !font-black !uppercase !tracking-widest !rounded-md"
                        />
                    </div>
                </template>
            </Column>

            <Column header="Actions" class="text-right" style="width: 100px">
                <template #body="slotProps">
                    <div class="flex justify-end gap-1">
                        <Button 
                            icon="pi pi-trash" 
                            text 
                            rounded 
                            size="small" 
                            severity="danger" 
                            class="!hover:bg-red-50"
                            @click.stop="$emit('delete', slotProps.data.id)" 
                        />
                    </div>
                </template>
            </Column>

            <template #expansion="slotProps">
                <SiteRowEditPanel
                    v-if="editingId === slotProps.data.id"
                    :site-id="slotProps.data.id"
                    :form="editForm"
                    :plants="plants"
                    :site-types="siteTypes"
                    :is-privileged="isPrivileged"
                    :errors="errors"
                    :processing="processing"
                    @submit="$emit('submitEdit')"
                    @cancel="$emit('cancelEdit')"
                />
                <div v-else class=" flex flex-col items-center justify-center bg-slate-50/30 dark:bg-slate-900/10">
                    <div class="w-12 h-12 rounded-full border-4 border-slate-200 border-t-indigo-600 animate-spin mb-4"></div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest animate-pulse text-center">Configuring Site Parameters...</p>
                </div>
            </template>
        </DataTable>
    </div>
</template>

<style scoped>
</style>
