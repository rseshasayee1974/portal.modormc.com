<script setup lang="ts">
import { ref, computed } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import { 
    CubeIcon, 
    MapPinIcon, 
    CalendarDaysIcon, 
    ArrowTrendingUpIcon,
    TableCellsIcon,
    FunnelIcon
} from '@heroicons/vue/24/outline';

interface Stock {
    id: number;
    date: string;
    plant_id: number;
    plant?: { name: string };
    product_id: number;
    product?: { title: string; code: string };
    uom_id: number;
    uom?: { unit_code: string };
    opening_quantity: string | number;
    quantity: string | number;
    status: string;
}

const props = defineProps<{
    stocks: Stock[];
    plants?: any[];
}>();

const perPage = ref(30);
const filters = ref({
    global: { value: null, matchMode: 'contains' }
});

const filteredStocks = computed(() => props.stocks);
</script>

<template>
    <div class="stock-table-card">
        <!-- Premium Table Header -->
        <div class="table-header-bar">
            <div class="flex items-center gap-3">
                <div class="header-icon-box">
                    <CubeIcon class="w-5 h-5 text-indigo-600" />
                </div>
                <div>
                    <h2 class="header-title">Stock Inventory</h2>
                    <p class="header-subtitle">Real-time product quantities across all sites</p>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="header-stat">
                    <span class="stat-label">Total Records</span>
                    <span class="stat-value">{{ stocks.length }}</span>
                </div>
                <!-- Optional: Filter Toggle or other global actions -->
                <!-- <button class="action-btn-outline"><FunnelIcon class="w-3.5 h-3.5" /><span>Filters</span></button> -->
            </div>
        </div>

        <BaseDataTable
            v-model:filters="filters"
            v-model:rows="perPage"
            :value="filteredStocks"
            dataKey="id"
            :rowsPerPageOptions="[30, 50, 100, 200]"
            stripedRows
            showSearch
            :globalFilterFields="['product.title', 'product.code', 'plant.name']"
            showSerial
            class="stock-table-custom"
        >
            <!-- Date Column -->
            <Column field="date" header="Date" sortable style="min-width: 120px">
                <template #body="{ data }">
                    <div class="flex items-center gap-2">
                        <CalendarDaysIcon class="w-3.5 h-3.5 text-slate-400" />
                        <span class="text-xs font-semibold text-slate-600">{{ new Date(data.date).toLocaleDateString() }}</span>
                    </div>
                </template>
            </Column>

            <!-- Site/Plant Column -->
            <Column field="plant.name" header="Site / Plant" sortable>
                <template #body="{ data }">
                    <div class="flex items-center gap-2">
                        <MapPinIcon class="w-3.5 h-3.5 text-slate-400" />
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-tight">{{ data.plant?.name || '---' }}</span>
                    </div>
                </template>
            </Column>

            <!-- Product Column -->
            <Column field="product.title" header="Product / Material" sortable>
                <template #body="{ data }">
                    <div class="flex flex-col">
                        <span class="text-sm font-black text-indigo-900 leading-tight">{{ data.product?.title || 'Unknown' }}</span>
                        <span class="text-[10px] font-bold text-slate-400 mt-0.5 tracking-widest uppercase">{{ data.product?.code || 'NO-CODE' }}</span>
                    </div>
                </template>
            </Column>

            <!-- Opening Stock -->
            <Column field="opening_quantity" header="Opening Qty" sortable class="text-right">
                <template #body="{ data }">
                    <span class="text-xs font-bold text-slate-500 font-mono">
                        {{ Number(data.opening_quantity).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                    </span>
                </template>
            </Column>

            <!-- Current Stock -->
            <Column field="quantity" header="Current Stock" sortable class="text-right">
                <template #body="{ data }">
                    <div class="flex flex-col items-end">
                        <span class="text-sm font-black text-indigo-600 font-mono">
                            {{ Number(data.quantity).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                        </span>
                        <span class="text-[10px] font-bold text-slate-400 uppercase">{{ data.uom?.unit_code || 'UNT' }}</span>
                    </div>
                </template>
            </Column>

            <!-- Status -->
            <Column field="status" header="Status" sortable style="width: 100px">
                <template #body="{ data }">
                    <Tag 
                        :value="data.status || 'Active'" 
                        :severity="data.status === 'Inactive' ? 'secondary' : 'success'"
                        rounded
                        class="text-[9px] font-black uppercase tracking-wider px-2"
                    />
                </template>
            </Column>
            
            <template #empty>
                <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                    <CubeIcon class="w-12 h-12 mb-3 opacity-20" />
                    <p class="text-sm font-bold uppercase tracking-widest">No stock data available</p>
                </div>
            </template>
        </BaseDataTable>
    </div>
</template>

<style scoped>
.stock-table-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

/* ── Header Bar ─────────────────────────────────────────── */
.table-header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    background: linear-gradient(to right, #ffffff, #f8faff);
    border-bottom: 2px solid #eef2ff;
}

.header-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: #eef2ff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: inset 0 2px 4px rgba(99, 102, 241, 0.1);
}

.header-title {
    font-size: 15px;
    font-weight: 800;
    color: #1e1b4b;
    margin: 0;
    letter-spacing: -0.01em;
}

.header-subtitle {
    font-size: 11px;
    color: #64748b;
    margin: 2px 0 0 0;
    font-weight: 500;
}

.header-stat {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.stat-label {
    font-size: 9px;
    font-weight: 800;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stat-value {
    font-size: 14px;
    font-weight: 900;
    color: #6366f1;
    font-mono: true;
}

/* ── Custom Table Adjustments ───────────────────────────── */
:deep(.stock-table-custom .p-datatable-thead > tr > th) {
    padding: 12px 14px !important;
}

:deep(.stock-table-custom .p-datatable-tbody > tr > td) {
    padding: 12px 14px !important;
}

.action-btn-outline {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: white;
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
}

.action-btn-outline:hover {
    border-color: #6366f1;
    color: #6366f1;
    background: #f5f7ff;
}
</style>
