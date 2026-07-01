<script setup lang="ts">
import { ref, watch } from 'vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import SiteRowEditPanel from './SiteRowEditPanel.vue';
import { usePermissions } from '@/Composables/usePermissions';

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

const { isAdmin } = usePermissions();

const filters = ref({
    global: { value: props.searchQuery ?? null, matchMode: 'contains' },
});

watch(() => filters.value.global.value, (v) => {
    emit('update:searchQuery', v ?? '');
});

watch(() => props.searchQuery, (newVal) => {
    filters.value.global.value = newVal;
});
</script>

<template>
    <BaseDataTable
        :value="sites"
        v-model:filters="filters"
        :globalFilterFields="['name', 'code', 'type']"
        showSearch
        showSerial
        heading="Sites"
        headingIcon="MapIcon"
        :rows="15"
        :expandedRows="expandedRows"
        @update:expandedRows="$emit('update:expandedRows', $event)"
    >
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
                        :class="(slotProps.data.status === 'Active' || !slotProps.data.status) ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]' : 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.4)]'"
                    ></span>
                    <Tag 
                        :value="slotProps.data.status || 'Active'" 
                        :severity="(slotProps.data.status === 'Active' || !slotProps.data.status) ? 'success' : 'danger'"
                        class="!text-[10px] !font-black !uppercase !tracking-widest !rounded-md"
                    />
                </div>
            </template>
        </Column>

        <Column header="Actions" class="text-right" style="width: 100px">
            <template #body="slotProps">
                <div class="flex justify-end gap-1">
                    <Button 
                        v-if="isAdmin || !slotProps.data.is_in_use"
                        icon="pi pi-trash" 
                        text 
                        rounded 
                        size="small" 
                        severity="danger" 
                        class="!hover:bg-red-50"
                        @click.stop="$emit('delete', slotProps.data.id)" 
                    />
                    <Tag 
                        v-else
                        value="In Use" 
                        severity="secondary"
                        class="!text-[8px] !font-black !uppercase !tracking-widest !rounded-md"
                        title="This site cannot be deleted because it is linked to other records."
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
    </BaseDataTable>
</template>

<style scoped>
</style>
