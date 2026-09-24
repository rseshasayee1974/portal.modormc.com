<script setup lang="ts">
import { ref, watch } from 'vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import SiteRowEditPanel from './SiteRowEditPanel.vue';
import { usePermissions } from '@/Composables/usePermissions';
import {
    MapPinIcon,
    PencilSquareIcon,
    TrashIcon,
    BuildingOffice2Icon,
    ArrowTopRightOnSquareIcon,
    GlobeAltIcon
} from '@heroicons/vue/24/outline';

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
    patrons: any[];
}>();

const emit = defineEmits<{
    'update:searchQuery': [value: string];
    'update:expandedRows': [value: any];
    'delete': [id: number];
    'submitEdit': [];
    'cancelEdit': [];
}>();

const { isAdmin, isSuperAdmin } = usePermissions();

const filters = ref({
    global: { value: props.searchQuery ?? null, matchMode: 'contains' },
});

watch(() => filters.value.global.value, (v) => {
    emit('update:searchQuery', v ?? '');
});

watch(() => props.searchQuery, (newVal) => {
    filters.value.global.value = newVal;
});

const toggleRow = (id: number) => {
    if (props.expandedRows && props.expandedRows[id]) {
        emit('update:expandedRows', {});
    } else {
        emit('update:expandedRows', { [id]: true });
    }
};

const formatFullAddress = (site: any) => {
    return [
        site.site_address_1,
        site.site_address_2,
        site.city,
        site.district,
        site.state,
        site.zipcode,
        site.country
    ].filter(Boolean).join(', ');
};
</script>

<template>
    <div
        class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden">
        <BaseDataTable :value="sites" v-model:filters="filters"
            :globalFilterFields="['name', 'code', 'type', 'patron.legal_name', 'site_address_1', 'city', 'state']"
            showSearch
            showSerial
            heading="Logistic Sites & Nodes"
            headingIcon="pi pi-map-marker"
            :rows="15"
            :expandedRows="expandedRows"
            @update:expandedRows="$emit('update:expandedRows', $event)"
        >
            <!-- <Column expander style="width: 2.5rem" /> -->

            <!-- Site Type -->
            <Column field="type" header="Type" sortable style="width: 130px">
                <template #body="{ data }">
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider border"
                        :class="data.type === 'loading'
                            ? 'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800'
                            : 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800'">
                        {{ data.type }}
                    </span>
                </template>
            </Column>

            <!-- Site Name & Address -->
            <Column field="name" header="Site & Address" sortable>
                <template #body="{ data }">
                    <div class="flex flex-col gap-1 max-w-[420px] py-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-100">
                                {{ data.name }}
                            </span>
                            <span v-if="data.code"
                                class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 uppercase">
                                {{ data.code }}
                            </span>
                        </div>

                        <!-- Address Line -->
                        <div v-if="formatFullAddress(data)"
                            class="text-[11px] text-slate-500 dark:text-slate-400 flex items-start gap-1 leading-relaxed">
                            <MapPinIcon class="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5" />
                            <span class="truncate" :title="formatFullAddress(data)">{{ formatFullAddress(data) }}</span>
                        </div>

                        <!-- GPS Coordinates Badge -->
                        <div v-if="data.latitude && data.longitude" class="flex items-center gap-1.5 pt-0.5">
                            <a :href="`https://www.google.com/maps?q=${data.latitude},${data.longitude}`"
                                target="_blank" rel="noopener noreferrer" @click.stop
                                class="inline-flex items-center gap-1 text-[10px] text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 font-semibold"
                                title="View coordinates on Google Maps">
                                <GlobeAltIcon class="w-3 h-3" />
                                <span>{{ Number(data.latitude).toFixed(4) }}, {{ Number(data.longitude).toFixed(4)
                                    }}</span>
                                <ArrowTopRightOnSquareIcon class="w-2.5 h-2.5" />
                            </a>
                        </div>
                    </div>
                </template>
            </Column>

            <!-- Customer / Patron -->
            <Column header="Customer / Patron" sortable field="patron.legal_name">
                <template #body="{ data }">
                    <div v-if="data.patrons_data && data.patrons_data.length > 0"
                        class="flex flex-wrap gap-1 max-w-[220px]">
                        <span v-for="pat in data.patrons_data.slice(0, 2)" :key="pat.id"
                            class="text-[10px] font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md border border-slate-200/60 dark:border-slate-700 truncate max-w-[200px]"
                            :title="pat.legal_name">
                            {{ pat.legal_name }}
                        </span>
                        <span v-if="data.patrons_data.length > 2"
                            class="text-[10px] font-semibold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/40 px-2 py-0.5 rounded-md border border-indigo-200/60 dark:border-indigo-700 cursor-help"
                            :title="data.patrons_data.slice(2).map((p: any) => p.legal_name).join(', ')">
                            +{{ data.patrons_data.length - 2 }} more
                        </span>
                    </div>
                    <span v-else-if="data.patron?.legal_name"
                        class="text-[10px] font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md border border-slate-200/60 dark:border-slate-700 truncate max-w-[200px]"
                        :title="data.patron.legal_name">
                        {{ data.patron.legal_name }}
                    </span>
                    <span v-else class="text-slate-300 dark:text-slate-600 text-xs italic font-mono">-</span>
                </template>
            </Column>

            <!-- Parent Plant -->
            <Column field="plant.name" header="Parent Plant" sortable v-if="isPrivileged">
                <template #body="{ data }">
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ data.plant?.name ||
                            '-' }}</span>
                        <span v-if="data.plant?.code"
                            class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">{{
                            data.plant?.code }}</span>
                    </div>
                </template>
            </Column>

            <!-- Status -->
            <Column header="Status" align="center" style="width: 130px" headerClass="text-center">
                <template #body="{ data }">
                    <div class="flex items-center gap-1.5 justify-center">
                        <span class="w-2 h-2 rounded-full shrink-0"
                            :class="(data.status === 'Active' || !data.status) ? 'bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,0.4)]' : 'bg-rose-500'"></span>
                        <Tag :value="data.status || 'Active'"
                            :severity="(data.status === 'Active' || !data.status) ? 'success' : 'danger'"
                            class="!text-[10px] !font-bold !uppercase !tracking-wider !px-2 !py-0.5 !rounded-md" />
                    </div>
                </template>
            </Column>

            <!-- Actions -->
            <Column header="Actions" class="text-right" headerClass="text-right" style="width: 100px">
                <template #body="{ data }">
                    <div class="flex items-center justify-end gap-1">
                        <!-- Edit Button (Toggles Inline Expansion) -->
                        <!-- <button
                            type="button"
                            @click.stop="toggleRow(data.id)"
                            class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
                            :class="{ 'text-indigo-600 bg-indigo-50 dark:bg-indigo-900/40': expandedRows && expandedRows[data.id] }"
                            :title="expandedRows && expandedRows[data.id] ? 'Collapse Panel' : 'Edit Site'">
                            <PencilSquareIcon class="w-4 h-4" />
                        </button> -->

                        <!-- Delete Button -->
                        <Button v-if="isSuperAdmin || !data.is_in_use" icon="pi pi-trash" text rounded size="small"
                            severity="danger" class="!hover:bg-red-50 !w-7 !h-7" title="Delete Site"
                            @click.stop="$emit('delete', data.id)" />
                        <Tag v-else value="In Use" severity="secondary"
                            class="!text-[8px] !font-bold !uppercase !tracking-wider !rounded-md"
                            title="This site cannot be deleted because it is linked to other records." />
                    </div>
                </template>
            </Column>

            <!-- Row Expansion Panel -->
            <template #expansion="{ data }">
                <SiteRowEditPanel v-if="editingId === data.id" :site-id="data.id" :form="editForm" :plants="plants"
                    :site-types="siteTypes" :is-privileged="isPrivileged" :errors="errors" :processing="processing"
                    :patrons="patrons" @submit="$emit('submitEdit')" @cancel="$emit('cancelEdit')" />
                <div v-else class="p-8 flex flex-col items-center justify-center bg-slate-50/50 dark:bg-slate-900/20">
                    <div class="w-8 h-8 rounded-full border-2 border-slate-200 border-t-indigo-600 animate-spin mb-2">
                    </div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest animate-pulse">Loading Site
                        Form...</p>
                </div>
            </template>

            <!-- Empty State -->
            <template #empty>
                <div class="py-12 flex flex-col items-center justify-center text-slate-400 text-center">
                    <MapPinIcon class="w-10 h-10 text-slate-300 dark:text-slate-600 mb-2" />
                    <span class="font-bold text-xs text-slate-600 dark:text-slate-300">No logistic sites found</span>
                    <span class="text-[11px] text-slate-400 mt-0.5">Use the registration form above to create your first
                        operational delivery site.</span>
                </div>
            </template>
        </BaseDataTable>
    </div>
</template>
