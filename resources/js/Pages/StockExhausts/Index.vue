<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Swal from 'sweetalert2';

import {
    WrenchScrewdriverIcon, MagnifyingGlassIcon, PencilSquareIcon,
    TrashIcon
} from '@heroicons/vue/24/outline';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';

// Partials
import StockExhaustCreateForm from './Partials/StockExhaustCreateForm.vue';
import StockExhaustEditForm from './Partials/StockExhaustEditForm.vue';

interface Line {
    id?: number;
    issue_date: any;
    quantity_issued: number | null;
    no_items_issued: number;
    units: string;
    issued_to: string;
    vehicle_no: number | null;
    changed_km: number;
    notes: string | null;
}

interface StockExhaust {
    id: number;
    partner_id: number | null;
    name: string;
    bill_number: string | null;
    billed_date: any;
    invoice_status: number;
    status: number;
    issued_date: any;
    plant_id: number;
    lines: Line[];
    partner?: {
        id: number;
        legal_name: string;
    };
}

const props = defineProps<{
    stockExhausts: StockExhaust[];
    machines: any[];
    vendors: any[];
    products: any[];
    units: any[];
}>();

const page = usePage();
const searchQuery = ref('');
const editingVoucher = ref<StockExhaust | null>(null);
const expandedRows = ref<any[]>([]);

const voucherStatuses = [
    { label: 'Draft', value: 0 },
    { label: 'Approved', value: 1 },
    { label: 'Cancelled', value: 2 }
];

const filteredVouchers = computed(() => {
    if (!searchQuery.value) return props.stockExhausts;
    const q = searchQuery.value.toLowerCase();
    return props.stockExhausts.filter((s: any) =>
        s.name?.toLowerCase().includes(q) ||
        s.bill_number?.toLowerCase().includes(q) ||
        s.partner?.legal_name?.toLowerCase().includes(q)
    );
});

const startEdit = (voucher: StockExhaust) => {
    editingVoucher.value = voucher;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const cancelEdit = () => {
    editingVoucher.value = null;
};

const deleteForm = useForm({});

const deleteVoucher = (id: number) => {
    Swal.fire({
        title: 'Delete Stock Exhaust Voucher?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete',
        customClass: { popup: 'rounded-3xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            deleteForm.delete(route('stock-exhausts.destroy', id), {
                onSuccess: () => {
                    if (editingVoucher.value?.id === id) cancelEdit();
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Deleted successfully', showConfirmButton: false, timer: 1500 });
                }
            });
        }
    });
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: flash.success, showConfirmButton: false, timer: 1500 });
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Stock Exhaust Management">
        <template #header><ModuleSubTopNav /></template>

        <div class="my-5">
            <div class="max-w-7xl">

                <!-- ── Create / Edit Form component rendering ── -->
                <StockExhaustEditForm 
                    v-if="editingVoucher" 
                    :voucher="editingVoucher"
                    :machines="machines"
                    :vendors="vendors"
                    :products="products"
                    :units="units"
                    @cancel="cancelEdit"
                    @success="cancelEdit"
                />

                <StockExhaustCreateForm 
                    v-else
                    :machines="machines"
                    :vendors="vendors"
                    :products="products"
                    :units="units"
                />

                <!-- ── DataTable Section ── -->
                <div class="bg-white dark:bg-slate-900 shadow-lg shadow-slate-200/40 dark:shadow-none rounded-lg border border-slate-100 dark:border-slate-800 overflow-hidden">
                    <DataTable
                        :value="filteredVouchers"
                        stripedRows
                        paginator
                        :rows="30"
                        v-model:expandedRows="expandedRows"
                        paginatorTemplate="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
                        currentPageReportTemplate="{first}–{last} of {totalRecords}"
                        class="stock-exhaust-table"
                        row-hover
                        dataKey="id"
                    >
                        <template #header>
                            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 px-4 py-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-1.5 h-8 bg-indigo-500 rounded-full"></div>
                                    <h3 class="text-sm font-black text-slate-800 dark:text-slate-100 uppercase tracking-widest">Stock Exhaust Ledger</h3>
                                </div>
                                
                                <div class="relative group w-full sm:w-72">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <MagnifyingGlassIcon class="h-4 w-4 text-slate-400 group-focus-within:text-indigo-500 transition-colors" />
                                    </div>
                                    <BaseInput
                                        v-model="searchQuery"
                                        placeholder="Quick Search..."
                                        class="!w-full !pl-11 !pr-4 !bg-slate-50 dark:!bg-slate-800 !border-none !rounded-xl !text-xs !font-bold !text-slate-600 dark:!text-slate-300 focus:!ring-4 focus:!ring-indigo-50 dark:focus:!ring-indigo-900/10 transition-all"
                                    />
                                </div>
                            </div>
                        </template>

                        <!-- Row Expansion Trigger -->
                        <Column expander style="width: 3rem" />

                        <!-- Name -->
                        <Column header="Voucher Description" sortable field="name">
                            <template #body="slotProps">
                                <span class="font-bold text-slate-700 dark:text-slate-200">
                                    {{ slotProps.data.name }}
                                </span>
                            </template>
                        </Column>

                        <!-- Partner -->
                        <Column header="Partner / Vendor" sortable field="partner.legal_name">
                            <template #body="slotProps">
                                <span class="text-xs text-slate-600 font-medium">
                                    {{ slotProps.data.partner?.legal_name }}
                                </span>
                            </template>
                        </Column>

                        <!-- Bill Number -->
                        <Column header="Bill Number" sortable field="bill_number">
                            <template #body="slotProps">
                                <span class="text-xs font-mono text-slate-600">
                                    {{ slotProps.data.bill_number || '—' }}
                                </span>
                            </template>
                        </Column>

                        <!-- Billed Date -->
                        <Column header="Billed Date">
                            <template #body="slotProps">
                                <span class="text-xs font-mono text-slate-500">
                                    {{ new Date(slotProps.data.billed_date).toLocaleDateString() }}
                                </span>
                            </template>
                        </Column>

                        <!-- Issued Date -->
                        <Column header="Issued Date">
                            <template #body="slotProps">
                                <span class="text-xs font-mono text-slate-500">
                                    {{ new Date(slotProps.data.issued_date).toLocaleDateString() }}
                                </span>
                            </template>
                        </Column>

                        <!-- Voucher Status -->
                        <Column header="Status" sortable field="status">
                            <template #body="slotProps">
                                <span 
                                    class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full"
                                    :class="{
                                        'bg-emerald-50 text-emerald-600': slotProps.data.status === 1,
                                        'bg-slate-100 text-slate-500': slotProps.data.status === 0 || slotProps.data.status === 2
                                    }"
                                >
                                    {{ voucherStatuses.find(s => s.value === slotProps.data.status)?.label }}
                                </span>
                            </template>
                        </Column>

                        <!-- Controls -->
                        <Column header="Control" style="width: 120px" align="right">
                            <template #body="slotProps">
                                <div class="flex justify-end items-center gap-2">
                                    <button
                                        @click="startEdit(slotProps.data)"
                                        class="flex items-center justify-center w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 hover:bg-indigo-100 transition-all active:scale-95"
                                        title="Modify"
                                    >
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </button>
                                    <button
                                        @click="deleteVoucher(slotProps.data.id)"
                                        class="flex items-center justify-center w-9 h-9 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-500 hover:bg-red-100 transition-all active:scale-95"
                                        title="Remove"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </template>
                        </Column>

                        <!-- Row Expansion Template (Issued Lines) -->
                        <template #rowexpansion="slotProps">
                            <div class="p-6 bg-slate-50/50 dark:bg-slate-900/50 rounded-xl border border-slate-100 dark:border-slate-800 m-2">
                                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Issued Spares / Fuel Details</h4>
                                <DataTable :value="slotProps.data.lines" class="lines-subtable">
                                    <Column field="product.title" header="Product" />
                                    <Column field="issued_to" header="Issued To" />
                                    <Column field="vehicle.registration" header="Vehicle No" />
                                    <Column field="quantity_issued" header="Quantity Issued" />
                                    <Column field="no_items_issued" header="Items Issued" />
                                    <Column field="units" header="Units" />
                                    <Column header="Changed Km">
                                        <template #body="subProps">
                                            {{ Number(subProps.data.changed_km).toLocaleString() }} Km
                                        </template>
                                    </Column>
                                    <Column header="Issue Date">
                                        <template #body="subProps">
                                            {{ new Date(subProps.data.issue_date).toLocaleString() }}
                                        </template>
                                    </Column>
                                    <Column field="notes" header="Notes" />
                                </DataTable>
                            </div>
                        </template>

                        <!-- Empty State -->
                        <template #empty>
                            <div class="py-20 flex flex-col items-center gap-4">
                                <div class="w-16 h-16 rounded-3xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center">
                                    <WrenchScrewdriverIcon class="w-8 h-8 text-slate-200 dark:text-slate-700" />
                                </div>
                                <div class="text-center">
                                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">No Stock Exhaust Vouchers</p>
                                    <p class="text-[10px] font-medium text-slate-300 dark:text-slate-600 mt-1">Submit a new stock exhaust entry above.</p>
                                </div>
                            </div>
                        </template>
                    </DataTable>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.stock-exhaust-table .p-datatable-thead > tr > th) {
    @apply !bg-slate-50/50 dark:!bg-slate-950/50 !text-slate-400 !font-black !text-[10px] !uppercase !tracking-[0.2em] !py-6 !border-b !border-slate-100 dark:!border-slate-800 !border-none;
}

:deep(.stock-exhaust-table .p-datatable-tbody > tr) {
    @apply !transition-all !duration-300;
}

:deep(.stock-exhaust-table .p-datatable-tbody > tr:hover) {
    @apply !bg-indigo-50/20 dark:!bg-indigo-900/10;
}

:deep(.stock-exhaust-table .p-datatable-tbody > tr > td) {
    @apply !py-5 !border-b !border-slate-50 dark:!border-slate-800/50 !bg-transparent;
}

:deep(.stock-exhaust-table .p-paginator) {
    @apply !bg-transparent !border-t !border-slate-100 dark:!border-slate-800 !py-6;
}

:deep(.stock-exhaust-table .p-paginator-current) {
    @apply !text-[11px] !font-black !text-slate-300 !uppercase !tracking-widest;
}

:deep(.stock-exhaust-table .p-paginator-element) {
    @apply !text-slate-400 !rounded-2xl !transition-all !w-11 !text-xs !font-black;
}

:deep(.stock-exhaust-table .p-paginator-element:hover) {
    @apply !bg-indigo-50/50 !text-indigo-600;
}

:deep(.stock-exhaust-table .p-paginator-element.p-highlight) {
    @apply !bg-indigo-600 !text-white !shadow-xl !shadow-indigo-200 dark:!shadow-none;
}

:deep(.p-datatable-striped .p-datatable-tbody > tr:nth-child(even)) {
    @apply !bg-slate-50/40 dark:!bg-slate-800/20;
}

:deep(.lines-subtable .p-datatable-thead > tr > th) {
    @apply !bg-slate-100/50 dark:!bg-slate-800/50 !text-slate-500 !font-bold !text-[9px] !uppercase !py-3;
}
:deep(.lines-subtable .p-datatable-tbody > tr > td) {
    @apply !py-2 !text-xs !text-slate-600 dark:!text-slate-300;
}
</style>
