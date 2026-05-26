<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Swal from 'sweetalert2';

import {
    WrenchScrewdriverIcon, MagnifyingGlassIcon, PencilSquareIcon,
    TrashIcon, PlusIcon, TagIcon, XMarkIcon, CheckIcon, CalendarIcon, ChevronDownIcon, ChevronUpIcon
} from '@heroicons/vue/24/outline';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import DatePicker from 'primevue/datepicker';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';

const page = usePage();

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
}>();

const searchQuery = ref('');
const editingId = ref<number | null>(null);
const expandedRows = ref<any[]>([]);

const machineOptions = computed(() => props.machines.map(m => ({ label: m.registration, value: m.id })));
const vendorOptions = computed(() => props.vendors.map(v => ({ label: v.legal_name, value: v.id })));

const voucherStatuses = [
    { label: 'Draft', value: 0 },
    { label: 'Approved', value: 1 },
    { label: 'Cancelled', value: 2 }
];

const invoiceStatuses = [
    { label: 'Unbilled', value: 0 },
    { label: 'Partially Billed', value: 1 },
    { label: 'Fully Billed', value: 2 }
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

const getInitialForm = () => ({
    partner_id: null as number | null,
    name: '',
    bill_number: '',
    billed_date: null as any,
    invoice_status: 0,
    status: 1,
    issued_date: null as any,
    lines: [] as Line[]
});

const form = useForm(getInitialForm());

const startEdit = (voucher: StockExhaust) => {
    editingId.value = voucher.id;
    form.partner_id = voucher.partner_id;
    form.name = voucher.name;
    form.bill_number = voucher.bill_number || '';
    form.billed_date = voucher.billed_date ? new Date(voucher.billed_date) : null;
    form.invoice_status = voucher.invoice_status;
    form.status = voucher.status;
    form.issued_date = voucher.issued_date ? new Date(voucher.issued_date) : null;
    form.lines = voucher.lines.map(l => ({
        ...l,
        issue_date: l.issue_date ? new Date(l.issue_date) : null,
        quantity_issued: l.quantity_issued ? Number(l.quantity_issued) : null,
        no_items_issued: Number(l.no_items_issued),
        changed_km: Number(l.changed_km)
    }));
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const cancelEdit = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const addLine = () => {
    form.lines.push({
        issue_date: new Date(),
        quantity_issued: null,
        no_items_issued: 1,
        units: 'pcs',
        issued_to: '',
        vehicle_no: null,
        changed_km: 0,
        notes: ''
    });
};

const removeLine = (index: number) => {
    form.lines.splice(index, 1);
};

const submitForm = () => {
    if (editingId.value) {
        form.put(route('stock-exhausts.update', editingId.value), {
            onSuccess: () => {
                cancelEdit();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Voucher modified', showConfirmButton: false, timer: 1500 });
            }
        });
    } else {
        form.post(route('stock-exhausts.store'), {
            onSuccess: () => {
                form.reset();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Voucher registered', showConfirmButton: false, timer: 1500 });
            }
        });
    }
};

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
            form.delete(route('stock-exhausts.destroy', id), {
                onSuccess: () => {
                    if (editingId.value === id) cancelEdit();
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

                <!-- ── Create / Edit Form Card ── -->
                <div class="bg-white dark:bg-slate-900 my-6 rounded-lg shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 overflow-hidden transition-all duration-300" :class="editingId ? 'ring-2 ring-indigo-500 ring-offset-4 dark:ring-offset-slate-950' : ''">
                    <div class="p-8">
                        <div class="flex items-center gap-4 mb-8">
                            <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600">
                                <WrenchScrewdriverIcon v-if="!editingId" class="w-6 h-6" />
                                <PencilSquareIcon v-else class="w-6 h-6" />
                            </div>
                            <div>
                                <h2 class="text-sm font-black text-slate-800 dark:text-slate-100 uppercase tracking-widest">
                                    {{ editingId ? 'Modify Stock Exhaust Entry' : 'Log Stock Exhaust' }}
                                </h2>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">
                                    Add voucher header details, dynamic items issued, and link to vehicles
                                </p>
                            </div>
                        </div>

                        <form @submit.prevent="submitForm" class="flex flex-col gap-6">
                            <!-- Header Info -->
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <BaseInput v-model="form.name" label="Voucher Label *" placeholder="E.g. Exhaust Tyre replacements, General Spare parts" :error="form.errors.name" />
                                </div>
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <label class="field-label">Partner / Transporter *</label>
                                    <BaseSelect v-model="form.partner_id" :options="vendorOptions" optionLabel="label" optionValue="value" placeholder="Select Partner" :error="form.errors.partner_id" />
                                </div>
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <BaseInput v-model="form.bill_number" label="Bill Number" placeholder="Bill reference" />
                                </div>

                                <div class="col-span-6 md:col-span-3 field-group">
                                    <label class="field-label">Billed Date *</label>
                                    <DatePicker v-model="form.billed_date" dateFormat="yy-mm-dd" class="!w-full h-10" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <label class="field-label">Issued Date *</label>
                                    <DatePicker v-model="form.issued_date" dateFormat="yy-mm-dd" class="!w-full h-10" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <label class="field-label">Voucher Status</label>
                                    <BaseSelect v-model="form.status" :options="voucherStatuses" optionLabel="label" optionValue="value" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <label class="field-label">Invoice Billing Status</label>
                                    <BaseSelect v-model="form.invoice_status" :options="invoiceStatuses" optionLabel="label" optionValue="value" />
                                </div>
                            </div>

                            <!-- Lines Form Section -->
                            <div class="mt-8 border-t border-slate-100 dark:border-slate-800 pt-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-widest">Issued Items (Lines)</h3>
                                    <button type="button" @click="addLine" class="flex items-center gap-2 px-4 py-2 bg-indigo-50 dark:bg-indigo-900/20 hover:bg-indigo-100 text-indigo-600 rounded-lg text-[9px] font-black uppercase tracking-widest transition-colors">
                                        <PlusIcon class="w-3.5 h-3.5" /> Add Row
                                    </button>
                                </div>

                                <div v-if="form.lines.length === 0" class="py-12 border-2 border-dashed border-slate-100 dark:border-slate-800 rounded-xl flex flex-col items-center justify-center bg-slate-50/20">
                                    <WrenchScrewdriverIcon class="w-8 h-8 text-slate-200 dark:text-slate-700 mb-2" />
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">No issued lines added</p>
                                </div>

                                <div v-else class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse min-w-[1200px]">
                                        <thead>
                                            <tr class="text-[9px] font-black uppercase text-slate-400 tracking-wider border-b border-slate-100 dark:border-slate-800 pb-3">
                                                <th class="py-3 pr-2 w-[180px]">Issued To *</th>
                                                <th class="py-3 px-2 w-[150px]">Vehicle *</th>
                                                <th class="py-3 px-2 w-[120px]">Qty Issued</th>
                                                <th class="py-3 px-2 w-[120px]">No of Items *</th>
                                                <th class="py-3 px-2 w-[100px]">Units *</th>
                                                <th class="py-3 px-2 w-[130px]">Changed KM *</th>
                                                <th class="py-3 px-2 w-[150px]">Issue Date *</th>
                                                <th class="py-3 px-2 w-[200px]">Notes</th>
                                                <th class="py-3 pl-2 w-[60px] text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(line, index) in form.lines" :key="index" class="border-b border-slate-50 dark:border-slate-800/50 hover:bg-slate-50/30">
                                                <td class="py-2 pr-2">
                                                    <BaseInput v-model="line.issued_to" placeholder="Person name" class="!h-9 text-xs" />
                                                </td>
                                                <td class="py-2 px-2">
                                                    <BaseSelect v-model="line.vehicle_no" :options="machineOptions" optionLabel="label" optionValue="value" placeholder="Select" class="!h-9 text-xs" />
                                                </td>
                                                <td class="py-2 px-2">
                                                    <BaseInput v-model="line.quantity_issued" placeholder="0.00" class="!h-9 text-xs" />
                                                </td>
                                                <td class="py-2 px-2">
                                                    <BaseInput v-model="line.no_items_issued" placeholder="1" class="!h-9 text-xs" />
                                                </td>
                                                <td class="py-2 px-2">
                                                    <BaseInput v-model="line.units" placeholder="pcs" class="!h-9 text-xs" />
                                                </td>
                                                <td class="py-2 px-2">
                                                    <BaseInput v-model="line.changed_km" placeholder="0" class="!h-9 text-xs" />
                                                </td>
                                                <td class="py-2 px-2">
                                                    <DatePicker v-model="line.issue_date" showTime hourFormat="24" dateFormat="yy-mm-dd" class="!w-full h-9 text-xs" />
                                                </td>
                                                <td class="py-2 px-2">
                                                    <BaseInput v-model="line.notes" placeholder="Remarks" class="!h-9 text-xs" />
                                                </td>
                                                <td class="py-2 pl-2 text-right">
                                                    <button type="button" @click="removeLine(index)" class="p-2 text-slate-300 hover:text-red-500 transition-colors">
                                                        <TrashIcon class="w-4 h-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 mt-4">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex items-center justify-center gap-3 h-12 px-8 rounded-2xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-black text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-indigo-100 dark:shadow-none transition-all duration-200 active:scale-95"
                                >
                                    <CheckIcon v-if="!form.processing" class="w-4 h-4 stroke-[3px]" />
                                    <span v-else class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                                    {{ editingId ? 'Update Voucher' : 'Register Voucher' }}
                                </button>
                                
                                <button
                                    v-if="editingId"
                                    @click="cancelEdit"
                                    type="button"
                                    class="flex items-center justify-center w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-slate-200 transition-all active:scale-95"
                                >
                                    <XMarkIcon class="w-6 h-6" />
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── DataTable Section ── -->
                <div class="bg-white dark:bg-slate-900 shadow-lg shadow-slate-200/40 dark:shadow-none rounded-lg border border-slate-100 dark:border-slate-800 overflow-hidden">
                    <DataTable
                        :value="filteredVouchers"
                        stripedRows
                        paginator
                        :rows="10"
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
:deep(.p-datepicker-input) {
    @apply h-10 text-sm font-bold border-slate-200 rounded-md !bg-white;
}

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
