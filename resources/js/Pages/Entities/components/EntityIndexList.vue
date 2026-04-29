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
import EntityRowEditPanel from './EntityRowEditPanel.vue';
import type { Entity } from '@/Pages/Entities/useEntityStore';

const props = defineProps<{
    entities: Entity[];
    searchQuery: string;
    expandedRows: Record<number, boolean>;
    editingId: number | null;
    editForm: any;
    entityTypes: { id: number; type: string }[];
    addressTypes: { id: number; type: string }[];
    contactTypes: { id: number; type: string }[];
    bankAccountTypes: { id: number; type: string }[];
    countries: { id: number; country_name: string }[];
    stateCodes: { id: number; state_name: string; state_code: string; country_id: number }[];
    isSuperAdmin?: boolean;
}>();

const emit = defineEmits<{
    'update:searchQuery': [value: string];
    'update:expandedRows': [value: Record<number, boolean>];
    delete: [id: number];
    switchEntity: [id: number];
    submitEdit: [];
    cancelEdit: [];
}>();

const search = computed({
    get: () => props.searchQuery ?? '',
    set: (v: string) => emit('update:searchQuery', v),
});

const perPage = ref(30);

const entityTypeLabel = (typeId: number | null) => {
    if (!typeId) return '—';
    return props.entityTypes.find(t => t.id === typeId)?.type ?? '—';
};

const onRowClick = (event: any) => {
    const target = event.originalEvent.target as HTMLElement;
    if (target.closest('button') || target.closest('.p-button') || target.closest('a')) return;

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
        :value="entities"
        dataKey="id"
        stripedRows
        paginator
        :rows="perPage"
        @update:rows="perPage = $event"
        heading="List of Entities"
        headingIcon="pi pi-building"
        showSearch
        showSerial
        :expandedRows="expandedRows"
        @update:expandedRows="$emit('update:expandedRows', $event)"
        @row-click="onRowClick"
    >
        <!-- ── Organization Column ── -->
        <Column field="legal_name" header="Organization" sortable>
            <template #body="slotProps">
                <div class="flex items-center gap-3">
                    <!-- Avatar icon -->
                    <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-violet-100 dark:bg-violet-900/30 flex-shrink-0 shadow-sm">
                        <i class="pi pi-building text-violet-500 dark:text-violet-400 text-sm"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-800 dark:text-gray-100 leading-tight">
                            {{ slotProps.data.legal_name }}
                        </span>
                        <span
                            v-if="slotProps.data.alias"
                            class="text-[10px] text-gray-400 uppercase font-black tracking-wider leading-tight mt-0.5"
                        >
                            {{ slotProps.data.alias }}
                        </span>
                    </div>
                </div>
            </template>
        </Column>

        <!-- ── Type Column ── -->
        <Column header="Type" style="width: 140px">
            <template #body="slotProps">
                <Tag
                    :value="entityTypeLabel(slotProps.data.entity_type)"
                    severity="secondary"
                    rounded
                    pt:root:style="font-size: 10px; padding: 2px 8px"
                />
            </template>
        </Column>

        <!-- ── Contact Column ── -->
        <Column field="email" header="Contact" sortable>
            <template #body="slotProps">
                <div class="flex flex-col gap-0.5">
                    <span v-if="slotProps.data.email" class="text-xs text-gray-600 dark:text-gray-300 font-medium">
                        {{ slotProps.data.email }}
                    </span>
                    <span v-if="slotProps.data.url" class="text-[10px] text-violet-500 dark:text-violet-400 truncate max-w-xs">
                        {{ slotProps.data.url }}
                    </span>
                    <span v-if="!slotProps.data.email && !slotProps.data.url" class="text-gray-300 text-xs">—</span>
                </div>
            </template>
        </Column>

        <!-- ── Status Column ── -->
        <Column header="Status" style="width: 100px" align="center">
            <template #body="slotProps">
                <div class="flex items-center justify-center gap-2">
                    <!-- Active/Inactive Icon -->
                    <i 
                        :class="[
                            slotProps.data.is_active ? 'pi pi-check-circle text-green-500' : 'pi pi-times-circle text-gray-300',
                            'text-lg'
                        ]"
                        v-tooltip.top="slotProps.data.is_active ? 'Active' : 'Inactive'"
                    ></i>
                    
                    <!-- Suspended Icon -->
                    <i 
                        v-if="slotProps.data.is_suspended"
                        class="pi pi-exclamation-triangle text-red-500 text-lg animate-pulse"
                        v-tooltip.top="'Suspended'"
                    ></i>
                </div>
            </template>
        </Column>

        <!-- ── Actions Column ── -->
        <Column header="Actions" class="text-right" style="width: 130px">
            <template #body="slotProps">
                <div class="flex justify-center gap-1">
                    <BaseActionButton
                        v-if="isSuperAdmin"
                        icon="pi pi-arrows-h"
                        severity="warning"
                        tooltip="Switch to this Entity"
                        @click="$emit('switchEntity', slotProps.data.id)"
                    />
                    <BaseActionButton
                        icon="pi pi-trash"
                        severity="danger"
                        tooltip="Delete Entity"
                        @click="$emit('delete', slotProps.data.id)"
                    />
                </div>
            </template>
        </Column>

        <!-- ── Expanded Row: Inline Edit Panel ── -->
        <template #expansion="slotProps">
            <EntityRowEditPanel
                v-if="editingId === slotProps.data.id"
                :entity-id="slotProps.data.id"
                :form="editForm"
                :entity-types="entityTypes"
                :address-types="addressTypes"
                :contact-types="contactTypes"
                :bank-account-types="bankAccountTypes"
                :countries="countries"
                :state-codes="stateCodes"
                @submit="$emit('submitEdit')"
                @cancel="$emit('cancelEdit')"
            />
            <div v-else class="px-6 py-4 flex items-center gap-3 text-sm text-gray-400">
                <i class="pi pi-spin pi-spinner text-violet-400"></i>
                Loading entity editor...
            </div>
        </template>
    </BaseDataTable>
</template>

<style scoped>
</style>
