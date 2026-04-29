<script setup lang="ts">
import { ref, computed } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import BaseInput from '@/Components/Base/BaseInput.vue';

const props = defineProps<{
    trips: any[];
    options: any;
}>();

const emit = defineEmits(['view', 'delete', 'edit', 'create']);

const searchQuery = ref('');

const statusMap: any = {
    0: { label: 'Draft / In-Transit', severity: 'warn' },
    1: { label: 'Arrived / Weighing', severity: 'info' },
    2: { label: 'Settled / Closed', severity: 'success' },
    3: { label: 'Billed / Complete', severity: 'primary' },
};

const filteredTrips = computed(() => {
    if (!searchQuery.value) return props.trips;
    const q = searchQuery.value.toLowerCase();
    return props.trips.filter((t: any) => 
        (t.reference_id && t.reference_id.toLowerCase().includes(q)) ||
        (t.party?.legal_name && t.party.legal_name.toLowerCase().includes(q))
    );
});

const getStatus = (status: number) => statusMap[status] || statusMap[0];
</script>

<template>
    <div class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-lg text-sm">
        <DataTable :value="filteredTrips" stripedRows class="p-datatable-sm" paginator :rows="15">
            <template #header>
                <div class="flex items-center justify-between">
                    <span class="text-xl font-semibold uppercase tracking-tight">Recent Voyages</span>
                    <div class="flex items-center gap-2">
                        <span class="p-input-icon-left">
                            <i class="pi pi-search ml-2" />
                            <BaseInput v-model="searchQuery" placeholder="Search Manifest..."  />
                        </span>
                        <Button label="New Voyage" icon="pi pi-plus"  @click="emit('create')" />
                    </div>
                </div>
            </template>

            <Column header="S.No" style="width: 70px">
                <template #body="slotProps">
                    <span class="text-gray-400 font-bold">{{ slotProps.index + 1 }}</span>
                </template>
            </Column>
            <Column field="reference_id" header="Manifest / Ref" sortable>
                <template #body="slotProps">
                    <span class="font-mono font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">
                        {{ slotProps.data.reference_id || `#${slotProps.data.id}` }}
                    </span>
                </template>
            </Column>

            <Column header="Voyage Path">
                <template #body="slotProps">
                    <div class="flex items-center gap-2 text-gray-600 font-semibold">
                        <span>{{ slotProps.data.load_site?.name }}</span>
                        <i class="pi pi-arrow-right text-[10px] opacity-30" />
                        <span>{{ slotProps.data.unload_site?.name || 'In-Transit' }}</span>
                    </div>
                </template>
            </Column>

            <Column header="Cargo / Patron">
                <template #body="slotProps">
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-800">{{ slotProps.data.party?.legal_name || '-' }}</span>
                        <span class="text-[10px] text-indigo-500 font-bold uppercase tracking-wider">{{ slotProps.data.product?.title }}</span>
                    </div>
                </template>
            </Column>

            <Column header="Status">
                <template #body="slotProps">
                    <Tag 
                        :value="getStatus(slotProps.data.status?.trip_status).label" 
                        :severity="getStatus(slotProps.data.status?.trip_status).severity"
                        rounded 
                        pt:root:style="font-size: 9px"
                    />
                </template>
            </Column>

            <Column header="Actions" class="text-right" style="width: 150px">
                <template #body="slotProps">
                    <div class="flex justify-end gap-1">
                        <Button icon="pi pi-eye" text rounded  severity="info" @click="emit('view', slotProps.data)" />
                        <Button icon="pi pi-pencil" text rounded  severity="warn" @click="emit('edit', slotProps.data)" />
                        <Button icon="pi pi-trash" text rounded  severity="danger" @click="emit('delete', slotProps.data)" />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>
</template>

