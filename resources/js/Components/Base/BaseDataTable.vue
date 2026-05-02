<script setup lang="ts">
import { useSlots, ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Skeleton from 'primevue/skeleton';
import Select from 'primevue/select';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputGroup from 'primevue/inputgroup';
import InputGroupAddon from 'primevue/inputgroupaddon';
import { 
    MagnifyingGlassIcon, 
    ListBulletIcon, 
    ClipboardDocumentListIcon, 
    FunnelIcon, 
    TruckIcon, 
    CubeIcon, 
    BeakerIcon, 
    UserGroupIcon, 
    BuildingOfficeIcon, 
    BookOpenIcon, 
    ArchiveBoxIcon,
    PlusIcon,
    ArrowPathIcon,
    CheckCircleIcon,
    ArrowDownTrayIcon,
    CreditCardIcon,
    DocumentTextIcon,
    TagIcon,
    ShoppingCartIcon
} from '@heroicons/vue/24/outline';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import Popover from 'primevue/popover';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';

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

        // Export Props
        showExport?: boolean;
        exportFilename?: string;

        // Advanced Filter Props
        dateFrom?: any;
        dateTo?: any;
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
        headingIcon:'',
        showExport: false,
        exportFilename: 'report'
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
    (e: 'update:dateFrom', val: any): void;
    (e: 'update:dateTo', val: any): void;
}>();

const slots = useSlots();

const getRowSerial = (index: number) => {
    return (props.first || 0) + index + 1;
};

const handleRowClick = (event: any) => {
    if (!event || !event.originalEvent) return;
    emit('row-click', event);
    
    // Auto-toggle expansion if the expansion slot is provided
    // and the click wasn't on an action button or the expander icon itself
    if (slots.expansion) {
        const target = event.originalEvent.target;
        if (!target) return;
        
        const isExpander = target.closest('.p-row-toggler');
        const isAction = target.closest('button') || target.closest('a');
        
        if (!isExpander && !isAction) {
            let newExpandedRows;
            const id = event.data?.[props.dataKey || 'id'];
            if (id === undefined) return;

            if (Array.isArray(props.expandedRows)) {
                newExpandedRows = [...props.expandedRows];
                const index = newExpandedRows.findIndex(row => (row[props.dataKey || 'id'] || row) === id);
                if (index > -1) {
                    newExpandedRows.splice(index, 1);
                } else {
                    // Usually we only want one row expanded at a time for these forms
                    newExpandedRows = [event.data];
                }
            } else {
                newExpandedRows = props.expandedRows ? { ...props.expandedRows } : {};
                if (newExpandedRows[id]) {
                    delete newExpandedRows[id];
                } else {
                    // Single expansion mode: clear others
                    Object.keys(newExpandedRows).forEach(key => delete newExpandedRows[key]);
                    newExpandedRows[id] = true;
                }
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

const dt = ref();

const exportCSV = () => {
    dt.value.exportCSV();
};

const printReport = () => {
    window.print();
};

const IconMap = {
    ListBulletIcon,
    MagnifyingGlassIcon,
    ClipboardDocumentListIcon,
    TruckIcon,
    CubeIcon,
    BeakerIcon,
    UserGroupIcon,
    BuildingOfficeIcon,
    BookOpenIcon,
    ArchiveBoxIcon,
    PlusIcon,
    ArrowPathIcon,
    CheckCircleIcon,
    ArrowDownTrayIcon,
    CreditCardIcon,
    DocumentTextIcon,
    TagIcon,
    ShoppingCartIcon
};

const filterPopover = ref();
const toggleFilterPopover = (event: any) => {
    filterPopover.value.toggle(event);
};
</script>

<template>
    <div class="base-datatable-wrapper border border-slate-200 rounded-sm overflow-hidden shadow-sm">
        
        <!-- Print Only Header -->
        <div class="print-only-header hidden">
            <div class="flex justify-between items-end border-b-2 border-slate-900 pb-4 mb-6">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 uppercase tracking-tighter">{{ heading || 'Data Report' }}</h1>
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-1">Generated: {{ new Date().toLocaleString('en-IN') }}</p>
                </div>
                <div class="text-right">
                    <h2 class="text-xl font-black text-indigo-600 tracking-tighter">MODOR RMC</h2>
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest leading-none">Portal Reporting System</p>
                </div>
            </div>
        </div>

        <div v-if="heading || showSearch || $slots.toolbar" class="no-print bg-white border-b border-slate-100 px-5 py-4 flex flex-wrap items-center justify-between gap-4">
            <div v-if="heading" class="flex items-center gap-3"> 
                <div v-if="headingIcon" class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-50 to-blue-50 flex items-center justify-center shadow-sm border border-indigo-100/50">
                    <component v-if="IconMap[headingIcon]" :is="IconMap[headingIcon]" class="w-6 h-6 text-indigo-600" />
                    <i v-else :class="[headingIcon, 'text-xl text-indigo-500']"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-800 tracking-tight leading-none">
                        {{ heading }}
                    </h3>
                    <div v-if="value?.length > 0" class="flex items-center gap-2 mt-1">
                        <div class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ value.length }} Records Found</span>
                    </div>
                </div>
            </div>
           
            <div class="flex items-center flex-wrap gap-3 ml-auto">
                <slot name="toolbar"></slot>

                <div v-if="showSearch" class="flex items-center gap-3">
                    <Select
                        :modelValue="rows"
                        @update:modelValue="$emit('update:rows', $event)"
                        :options="internalPageOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Entries"
                        class="!h-10 !text-[11px] !font-bold !bg-slate-50 !border-slate-200 !rounded-lg w-32 shadow-sm"
                    />

                    <InputGroup class="!rounded-lg overflow-hidden border border-slate-200 shadow-sm group focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-50 transition-all bg-white h-10" style="width: 240px">
                        <InputGroupAddon class="!bg-transparent !border-none !px-3">
                            <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 group-focus-within:text-indigo-500 transition-colors" />
                        </InputGroupAddon>
                        <InputText 
                            :modelValue="filters?.global?.value" 
                            @update:modelValue="handleSearch"
                            placeholder="Quick search..." 
                            class="!border-none !text-[13px] !font-semibold !bg-transparent !px-0 h-full placeholder:text-slate-400 placeholder:font-medium" 
                        />
                    </InputGroup>
                    
                    <Button 
                        icon="pi pi-filter" 
                        severity="secondary" 
                        text 
                        rounded 
                        class="!h-10 !w-10 !border !border-slate-200 !bg-white !shadow-sm hover:!border-indigo-400 group transition-all"
                        v-tooltip.bottom="'Advanced Filters'"
                        @click="toggleFilterPopover"
                    />
                </div>
            </div>

        </div>

        <DataTable
            ref="dt"
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
            :exportFilename="exportFilename"
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

        <Popover ref="filterPopover" class="!shadow-2xl !border !border-slate-200 !rounded-xl overflow-hidden">
            <div class="flex flex-col w-80 p-5 gap-6">
                <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
                        <FunnelIcon class="w-4 h-4" />
                    </div>
                    <span class="text-sm font-bold text-slate-800 uppercase tracking-wider">Advanced Filters</span>
                </div>

                <div class="flex flex-col gap-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">From Date</label>
                            <BaseDatePicker 
                                :modelValue="dateFrom" 
                                @update:modelValue="$emit('update:dateFrom', $event)"
                                class="!h-9"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">To Date</label>
                            <BaseDatePicker 
                                :modelValue="dateTo" 
                                @update:modelValue="$emit('update:dateTo', $event)"
                                class="!h-9"
                            />
                        </div>
                    </div>
                </div>

                <div v-if="$slots.filters" class="flex flex-col gap-4 pt-4 border-t border-slate-100">
                    <slot name="filters" />
                </div>

                <div v-if="showExport" class="flex flex-col gap-3 pt-4 border-t border-slate-100">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Export Options</label>
                    <div class="grid grid-cols-2 gap-3">
                        <Button 
                            label="Excel" 
                            icon="pi pi-file-excel" 
                            severity="success" 
                            class="!py-2 !text-xs font-bold shadow-sm"
                            @click="exportCSV" 
                        />
                        <Button 
                            label="PDF" 
                            icon="pi pi-print" 
                            severity="danger" 
                            class="!py-2 !text-xs font-bold shadow-sm"
                            @click="printReport" 
                        />
                    </div>
                </div>
            </div>
        </Popover>
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
