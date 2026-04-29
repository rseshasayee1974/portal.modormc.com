<script setup lang="ts">
import { useSlots } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Skeleton from 'primevue/skeleton';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import InputGroup from 'primevue/inputgroup';
import InputGroupAddon from 'primevue/inputgroupaddon';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';

const props = withDefaults(
    defineProps<{
        value: any[];
        loading?: boolean;
        dataKey?: string;
        expandedRows?: any;
        first?: number;

        paginator?: boolean;
        rows?: number;
        totalRecords?: number;
        rowsPerPageOptions?: any[];
        paginatorPosition?: 'top' | 'bottom' | 'both';
        lazy?: boolean;

        filters?: any;
        globalFilterFields?: string[];
        filterMode?: 'lenient' | 'strict';
        filterDisplay?: 'row' | 'menu';

        stripedRows?: boolean;
        removableSort?: boolean;
        responsiveLayout?: 'scroll' | 'stack';
        class?: string;
        showSerial?: boolean;

        // Toolbar Props
        showSearch?: boolean;
        heading?: string;
        headingIcon?: string;

        // Built-in delete column
        deleteUrl?: (row: any) => string;
        deleteTitle?: string;
        deleteText?: string;
    }>(),
    {
        loading: false,
        paginator: true,
        first: 0,
        rows: 10,
        rowsPerPageOptions: () => [30, 50, 100, 200],
        paginatorPosition: 'bottom',
        lazy: false,
        stripedRows: true,
        responsiveLayout: 'scroll',
        filterMode: 'lenient',
        filterDisplay: 'menu',
        removableSort: false,
        showSerial: false,
        showSearch: false,
        heading : "",
        headingIcon:''
    }
);

const emit = defineEmits<{
    (e: 'update:first', val: number): void;
    (e: 'update:rows', val: number): void;
    (e: 'update:filters', val: any): void;
    (e: 'page', ev: any): void;
    (e: 'sort', ev: any): void;
    (e: 'filter', ev: any): void;
    (e: 'row-click', ev: any): void;
    (e: 'rowExpand', ev: any): void;
    (e: 'rowCollapse', ev: any): void;
    (e: 'update:expandedRows', val: any): void;
}>();

const slots = useSlots();

const getRowSerial = (index: number) => {
    return (props.first || 0) + index + 1;
};

const handleRowClick = (event: any) => {
    emit('row-click', event);
    
    // Auto-toggle expansion if the expansion slot is provided
    // and the click wasn't on an action button or the expander icon itself
    if (slots.expansion) {
        const target = event.originalEvent.target;
        const isExpander = target.closest('.p-row-toggler');
        const isAction = target.closest('button') || target.closest('a');
        
        if (!isExpander && !isAction) {
            const id = event.data[props.dataKey || 'id'];
            const newExpandedRows = props.expandedRows ? { ...props.expandedRows } : {};
            
            if (newExpandedRows[id]) {
                delete newExpandedRows[id];
            } else {
                // Usually we only want one row expanded at a time for these forms
                Object.keys(newExpandedRows).forEach(key => delete newExpandedRows[key]);
                newExpandedRows[id] = true;
            }
            
            emit('update:expandedRows', newExpandedRows);
        }
    }
};

const internalPageOptions = [
    { label: '30 Per Page', value: 30 },
    { label: '50 Per Page', value: 50 },
    { label: '100 Per Page', value: 100 },
    { label: '200 Per Page', value: 200 }
];

const handleSearch = (val: string) => {
    const newFilters = { ...props.filters };
    if (!newFilters.global) {
        newFilters.global = { value: null, matchMode: 'contains' };
    }
    newFilters.global.value = val;
    emit('update:filters', newFilters);
};
</script>

<template>
    <div class="base-datatable-wrapper border border-slate-200 rounded-sm overflow-hidden shadow-sm">

       

        <div v-if="showSearch" class="bg-slate-50/30 border-b border-slate-100 px-4 py-2.5 flex items-center justify-between">
            <div v-if="heading" class="mr-auto flex items-center gap-2"> 
                <i v-if="headingIcon" :class="[headingIcon, 'text-xl text-indigo-500']"></i>
                <h3 class="text-lg font-semibold text-slate-800">
                    {{ heading }}
                </h3>
               
            </div>
            <!-- Entries per page -->
             <div class="flex items-end justify-end gap-2">
            <Select
                :modelValue="rows"
                @update:modelValue="$emit('update:rows', $event)"
                :options="internalPageOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Entries"
                class="!h-10 !text-[11px] pt-1 !font-bold !bg-white !border-slate-200 !rounded-lg w-32 shadow-sm"
            />

            <!-- Search -->
            <InputGroup class="!rounded-md overflow-hidden border border-slate-200 shadow-sm group focus-within:border-indigo-400 transition-all bg-white h-10" style="width: 200px">
                <InputText 
                    :modelValue="filters?.global?.value" 
                    @update:modelValue="handleSearch"
                    placeholder="Search records..." 
                    class="!border-none !text-[13px] !font-bold !bg-transparent !px-3 h-full placeholder:text-slate-300" 
                />
                <InputGroupAddon class="!bg-transparent !border-none !px-2">
                    <div class="p-1 rounded-md bg-slate-50 group-focus-within:bg-indigo-50 transition-colors">
                        <MagnifyingGlassIcon class="w-3 h-3 text-slate-400 group-focus-within:text-indigo-500" />
                    </div>
                </InputGroupAddon>
            </InputGroup>
            </div>
        </div>

        <DataTable
            :value="value"
            :dataKey="dataKey || 'id'"
            :loading="loading"
            :lazy="lazy"
            :paginator="paginator"
            :first="first"
            :rows="rows"
            :totalRecords="totalRecords"
            :rowsPerPageOptions="rowsPerPageOptions"
            :paginatorPosition="paginatorPosition"
            :filters="filters"
            :globalFilterFields="globalFilterFields"
            :filterMode="filterMode"
            :filterDisplay="filterDisplay"
            :stripedRows="stripedRows"
            :removableSort="removableSort"
            :responsiveLayout="responsiveLayout"
            :expandedRows="expandedRows"
            @update:first="$emit('update:first', $event)"
            @update:rows="$emit('update:rows', $event)"
            @update:filters="$emit('update:filters', $event)"
            @update:expandedRows="$emit('update:expandedRows', $event)"
            class="p-datatable-sm"
            :class="[props.class, { 'cursor-pointer': slots.expansion }]"
            rowHover
            @page="$emit('page', $event)"
            @sort="$emit('sort', $event)"
            @filter="$emit('filter', $event)"
            @row-click="handleRowClick"
            @rowExpand="$emit('rowExpand', $event)"
            @rowCollapse="$emit('rowCollapse', $event)"
        >
            <template v-if="$slots.header" #header>
                <slot name="header" />
            </template>

            <!-- Default S.No Column -->
            <Column v-if="showSerial" header="S.No" style="width: 5rem">
                <template #body="slotProps">
                    <div class="font-semibold bg-gray-200/10 h-9 pt-2 rounded-3xl shadow-inner text-center text-slate-600 w-9 mx-auto">
                        {{ getRowSerial(slotProps.index) }}
                    </div>
                </template>
            </Column>

            <slot />

            <!-- Built-in Actions Column with Delete Button -->
            <Column v-if="deleteUrl" header="Actions" style="width: 70px; text-align: right">
                <template #body="slotProps">
                    <div class="flex justify-end">
                        <BaseDeleteButton
                            :url="deleteUrl(slotProps.data)"
                            :title="deleteTitle"
                            :text="deleteText"
                        />
                    </div>
                </template>
            </Column>

            <template v-if="loading" #loading>
                <div class="p-4 space-y-3">
                    <Skeleton height="1.5rem" />
                    <Skeleton height="1.5rem" />
                    <Skeleton height="1.5rem" />
                </div>
            </template>

            <template v-if="$slots.empty" #empty>
                <slot name="empty" />
            </template>

            <template v-if="$slots.expansion" #expansion="slotProps">
                <slot name="expansion" v-bind="slotProps" />
            </template>
        </DataTable>
    </div>
</template>

<style scoped>
/* ── Wrapper ─────────────────────────────────────────────── */
.base-datatable-wrapper {
    /* border-radius: 12px !important; */
    border-color: #e2e8f0 !important;
    box-shadow: 0 1px 4px rgba(99,102,241,.06), 0 1px 2px rgba(0,0,0,.04) !important;
    overflow: hidden;
}

/* ── Toolbar / Search bar ────────────────────────────────── */
:deep(.p-select-label) {
    font-size: 11px !important;
    padding: 0.2rem 0.3rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    color: #64748b !important;
}

/* ── Table Header ────────────────────────────────────────── */
:deep(.p-datatable .p-datatable-thead > tr > th) {
    background: linear-gradient(135deg, #f8faff 0%, #f1f5ff 100%) !important;
    border-bottom: 2px solid #e0e7ff !important;
    border-right: 1px solid #eef2ff !important;
    padding: 11px 14px !important;
    text-align: left !important;
    position: relative;
}

:deep(.p-datatable .p-datatable-thead > tr > th .p-column-header-content) {
    justify-content: flex-start !important;
    gap: 6px;
}

/* Header label text */
:deep(.p-datatable .p-datatable-thead > tr > th .p-column-title) {
    font-size: 9.5px !important;
    font-weight: 800 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.09em !important;
    color: #6366f1 !important;
    white-space: nowrap;
}

/* Sort icons */
:deep(.p-datatable .p-datatable-thead > tr > th .p-sortable-column-icon) {
    color: #a5b4fc !important;
    font-size: 10px !important;
    transition: color 0.2s, transform 0.2s;
    margin-left: 2px;
}
:deep(.p-datatable .p-datatable-thead > tr > th.p-sort-column .p-sortable-column-icon),
:deep(.p-datatable .p-datatable-thead > tr > th:hover .p-sortable-column-icon) {
    color: #6366f1 !important;
}
:deep(.p-datatable .p-datatable-thead > tr > th.p-sort-column) {
    background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%) !important;
}

/* ── Table Body ──────────────────────────────────────────── */
:deep(.p-datatable .p-datatable-tbody > tr > td) {
    padding: 9px 14px !important;
    border-color: #f1f5f9 !important;
    font-size: 13px;
    color: #334155;
    vertical-align: middle;
    transition: background 0.15s;
}

/* Striped rows */
:deep(.p-datatable.p-datatable-striped .p-datatable-tbody > tr:nth-child(even) > td) {
    background: #fafbff !important;
}

/* Row hover */
:deep(.p-datatable .p-datatable-tbody > tr:hover > td) {
    background: #eef2ff !important;
}

/* Row expansion cell */
:deep(.p-datatable .p-datatable-row-expansion > td) {
    padding: 0 !important;
    border-top: 2px solid #c7d2fe !important;
    border-bottom: 2px solid #c7d2fe !important;
    background: #f8faff !important;
}

/* ── Paginator ───────────────────────────────────────────── */
:deep(.p-datatable .p-paginator) {
    background: #f8faff !important;
    border-top: 1px solid #e0e7ff !important;
    padding: 8px 14px !important;
    font-size: 12px !important;
    border-radius: 0 0 12px 12px !important;
}
:deep(.p-datatable .p-paginator .p-paginator-page) {
    min-width: 28px !important;
    height: 28px !important;
    border-radius: 6px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    color: #64748b !important;
    transition: background 0.15s, color 0.15s;
}
:deep(.p-datatable .p-paginator .p-paginator-page.p-highlight) {
    background: linear-gradient(135deg, #6366f1, #4f46e5) !important;
    color: #fff !important;
    box-shadow: 0 2px 8px rgba(99,102,241,.35) !important;
}
:deep(.p-datatable .p-paginator .p-paginator-page:not(.p-highlight):hover) {
    background: #eef2ff !important;
    color: #6366f1 !important;
}
:deep(.p-datatable .p-paginator .p-paginator-prev,
       .p-datatable .p-paginator .p-paginator-next,
       .p-datatable .p-paginator .p-paginator-first,
       .p-datatable .p-paginator .p-paginator-last) {
    min-width: 28px !important;
    height: 28px !important;
    border-radius: 6px !important;
    color: #94a3b8 !important;
    transition: background 0.15s, color 0.15s;
}
:deep(.p-datatable .p-paginator .p-paginator-prev:hover,
       .p-datatable .p-paginator .p-paginator-next:hover) {
    background: #eef2ff !important;
    color: #6366f1 !important;
}

/* ── Row expander toggle ─────────────────────────────────── */
:deep(.p-row-toggler) {
    color: #a5b4fc !important;
    transition: color 0.15s, transform 0.2s;
}
:deep(.p-row-toggler:hover) {
    color: #6366f1 !important;
    transform: scale(1.15);
}
</style>
