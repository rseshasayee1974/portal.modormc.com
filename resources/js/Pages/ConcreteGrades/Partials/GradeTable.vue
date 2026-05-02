<script setup lang="ts">
import { ref, computed } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import { 
    PencilSquareIcon,
    DocumentTextIcon,
    TableCellsIcon,
} from '@heroicons/vue/24/outline';
import GradeEditForm from './GradeEditForm.vue';

const props = defineProps<{
    grades: any[];
    products: any[];
}>();

const expandedRows = ref<Record<number, boolean>>({});
const perPage = ref(30);
const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

// The BaseDataTable handles filtering internally via the filters object
const filteredGrades = computed(() => props.grades);

const onSaved = () => {
    expandedRows.value = {};
};
</script>

<template>
    <div class="grade-table-container">
        <BaseDataTable 
            v-model:expandedRows="expandedRows"
            v-model:filters="filters"
            v-model:rows="perPage"
            :value="filteredGrades" 
            dataKey="id"
            :rowsPerPageOptions="[30, 50, 100, 200]"
            class="grade-datatable"
            showSearch
            :globalFilterFields="['name', 'concrete_code']"
            showSerial
            heading="Mix Specifications Registry"
            headingIcon="ClipboardDocumentListIcon"
            showExport
            exportFilename="concrete-grades-report"
            :deleteUrl="(row) => route('concretegrades.destroy', row.id)"
            deleteTitle="Delete Mix Grade?"
        >
            <template #toolbar>
                <div class="flex items-center gap-2 px-3 py-1 bg-indigo-50/50 rounded-lg border border-indigo-100">
                    <DocumentTextIcon class="w-3.5 h-3.5 text-indigo-500" />
                    <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">{{ grades.length }} Specifications</span>
                </div>
            </template>
            <Column header="Mix Specification" sortable field="name">
                <template #body="slotProps">
                    <div class="flex flex-col">
                        <span class="text-sm font-black text-slate-800 uppercase tracking-tight">{{ slotProps.data.name }}</span>
                        <div class="flex items-center gap-2 mt-0.5">
                            <DocumentTextIcon class="w-3 h-3 text-slate-300" />
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ slotProps.data.concrete_code || '---' }}</span>
                        </div>
                    </div>
                </template>
            </Column>

            <Column header="Yield Ratio" style="width: 140px" sortable field="concrete_ratio">
                <template #body="slotProps">
                    <div class="ratio-badge">
                        {{ slotProps.data.concrete_ratio || 'N/A' }}
                    </div>
                </template>
            </Column>

            <Column header="Composition Overview">
                <template #body="slotProps">
                    <div class="items-preview">
                        <div v-for="item in slotProps.data.items.slice(0, 3)" :key="item.id" class="preview-tag">
                            {{ item.product?.title?.split(' ')[0] }}: <span class="font-black">{{ item.quantity }}</span>
                        </div>
                        <div v-if="slotProps.data.items.length > 3" class="preview-tag opacity-60">
                            +{{ slotProps.data.items.length - 3 }} more
                        </div>
                    </div>
                </template>
            </Column>

            <Column header="Status" style="width: 110px" sortable field="status">
                <template #body="slotProps">
                    <Tag 
                        :severity="slotProps.data.status ? 'success' : 'secondary'" 
                        rounded 
                        class="status-pill"
                    >
                        {{ slotProps.data.status ? 'ACTIVE' : 'INACTIVE' }}
                    </Tag>
                </template>
            </Column>

            <template #expansion="{ data }">
                <div class="expansion-panel">
                    <div class="pb-2">
                        <div class="flex items-center gap-2">
                            <PencilSquareIcon class="w-4 h-4 text-indigo-500" />
                            <span class="text-[11px] font-black uppercase text-indigo-900 tracking-widest">Editing Mix Definition</span>
                        </div>
                    </div>
                    <div class="expansion-contqent">
                        <GradeEditForm 
                            :grade="data" 
                            :products="products" 
                            @cancel="expandedRows = {}" 
                            @saved="onSaved"
                        />
                    </div>
                </div>
            </template>
        </BaseDataTable>
    </div>
</template>

<style scoped>
.grade-table-container { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }

.ratio-badge { display: inline-block; padding: 4px 10px; background: #f0f7ff; color: #0284c7; border-radius: 8px; font-size: 11px; font-weight: 800; border: 1px solid #e0f2fe; }

.items-preview { display: flex; flex-wrap: wrap; gap: 4px; }
.preview-tag { padding: 2px 6px; background: #f1f5f9; border-radius: 4px; font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; }

.status-pill { font-size: 9px !important; font-weight: 800 !important; tracking-widest: 0.05em; padding: 2px 8px !important; }

.expansion-panel { padding: 20px 24px; background: linear-gradient(135deg, #eef2ff 0%, #f0f9ff 100%); border-left: 4px solid #6366f1; border-top: 1px solid #c7d2fe; border-bottom: 1px solid #c7d2fe; }
</style>
