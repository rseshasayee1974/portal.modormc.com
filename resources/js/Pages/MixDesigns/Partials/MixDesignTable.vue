<script setup lang="ts">
import { ref, computed } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import { BeakerIcon, UserIcon, CubeIcon, TableCellsIcon } from '@heroicons/vue/24/outline';
import MixDesignEditForm from './MixDesignEditForm.vue';

const props = defineProps<{
    mixDesigns: any[];
    products: any[];
    units: any[];
    partners: any[];
    defaultUomId?: number | null;
    designTypes: any[];
}>();

const expandedRows = ref<Record<number, boolean>>({});
const perPage = ref(30);
const filters = ref({ global: { value: null, matchMode: 'contains' } });

const filteredDesigns = computed(() => props.mixDesigns);

const onSaved = () => { expandedRows.value = {}; };
</script>

<template>
    <div class="mix-table-container">
        <!-- Table Title Bar -->
        <div class="table-title-bar">
            <div class="flex items-center gap-3">
                <div class="table-title-icon">
                    <TableCellsIcon class="w-4 h-4 text-indigo-500" />
                </div>
                <div>
                    <h2 class="table-title-text">Mix Designs</h2>
                    <p class="table-title-sub">Concrete ingredient matrices & grade assignments</p>
                </div>
            </div>
            <div class="table-title-badge">
                <BeakerIcon class="w-3 h-3" />
                <span>{{ filteredDesigns.length }} Records</span>
            </div>
        </div>

        <BaseDataTable
            v-model:expandedRows="expandedRows"
            v-model:filters="filters"
            v-model:rows="perPage"
            :value="filteredDesigns"
            dataKey="id"
            :rowsPerPageOptions="[30, 50, 100, 200]"
            showSearch
            class="unit-datatable"
            :globalFilterFields="['design_name', 'design_code', 'design_type']"
            showSerial
            :deleteUrl="(row) => route('mixdesigns.destroy', row.id)"
            deleteTitle="Delete Mix Design?"
            deleteText="This mix design and all its ingredients will be removed."
        >
            <!-- Design Name -->
            <Column header="Design Name" sortable field="design_name">
                <template #body="slotProps">
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-800 uppercase tracking-tight">{{ slotProps.data.design_name }}</span>
                        <div class="flex items-center gap-2 mt-0.5">
                            <BeakerIcon class="w-3 h-3 text-slate-300" />
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ slotProps.data.design_code || '---' }}</span>
                        </div>
                    </div>
                </template>
            </Column>

            <!-- Grade -->
            <Column header="Grade" sortable field="design_type" style="width: 150px">
                <template #body="slotProps">
                    <Tag severity="success" rounded class="text-[9px] font-black uppercase tracking-widest">
                        {{ slotProps.data.design_type || 'N/A' }}
                    </Tag>
                </template>
            </Column>

            <!-- Partner -->
            <Column header="Partner" sortable field="partner.legal_name" style="width: 150px">
                <template #body="slotProps">
                    <div class="flex items-center gap-2">
                        <UserIcon class="w-3.5 h-3.5 text-slate-300" />
                        <span class="text-xs text-slate-600">{{ slotProps.data.partner?.legal_name || '—' }}</span>
                    </div>
                </template>
            </Column>

            <!-- Ingredients -->
            <Column header="Ingredients" style="width: 200px">
                <template #body="slotProps">
                    <div class="items-preview">
                        <div v-for="item in slotProps.data.items.slice(0, 2)" :key="item.id" class="preview-tag">
                            {{ item.product?.title?.split(' ')[0] }}: <span class="font-black">{{ item.actual_quantity }}</span>
                        </div>
                        <div v-if="slotProps.data.items.length > 2" class="preview-tag opacity-60">
                            +{{ slotProps.data.items.length - 2 }} more
                        </div>
                    </div>
                </template>
            </Column>

            <!-- Rate -->
            <Column header="Rate / m³" sortable field="rate_per_qty" style="width: 150px">
                <template #body="slotProps">
                    <span class="font-black text-indigo-600 font-mono text-sm">
                        ₹{{ Number(slotProps.data.rate_per_qty || 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                    </span>
                </template>
            </Column>

            <!-- Unit -->
            <!-- <Column header="Unit" style="width: 80px">
                <template #body="slotProps">
                    <span class="text-xs text-slate-500 font-bold">{{ slotProps.data.unit?.unit_code || '—' }}</span>
                </template>
            </Column> -->

            <!-- Row Expansion: Edit Form -->
            <template #expansion="{ data }">
                <div class="expansion-panel">
                    <div class="pb-2 flex items-center gap-2">
                        <BeakerIcon class="w-4 h-4 text-indigo-500" />
                        <span class="text-[11px] font-black uppercase text-indigo-900 tracking-widest">Editing Mix Design</span>
                    </div>
                    <MixDesignEditForm
                        :design="data"
                        :products="products"
                        :units="units"
                        :partners="partners"
                        :defaultUomId="defaultUomId"
                        :designTypes="designTypes"
                        @cancel="expandedRows = {}"
                        @saved="onSaved"
                    />
                </div>
            </template>
        </BaseDataTable>
    </div>
</template>

<style scoped></style>
