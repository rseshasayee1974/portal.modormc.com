<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Select from 'primevue/select';
import BaseButton from '@/Components/Base/BaseButton.vue';
import QuotationCreateForm from './components/QuotationCreateForm.vue';
import QuotationEditForm from './components/QuotationEditForm.vue';
import BaseExpansionPanel from '@/Components/Base/BaseExpansionPanel.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import Popover from 'primevue/popover';
import { ListBulletIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    quotations: any[];
    patrons: { id: number; legal_name: string }[];
    sites: { id: number; name: string }[];
    mixDesigns: { id: number; title: string; code?: string; rate?: number }[];
    taxes: { id: number; title?: string; tax_name?: string; rate?: number; tax_rate?: number }[];
    vehicles: { id: number; registration: string }[];
    unitOptions : {id: number, unit_code: string}[];
    drivers: { id: number; first_name: string; last_name: string }[];
    salesExecutives: any[];
    concretePumpOptions?: any[];
}>();

// console.log('quotations', props.quotations);

const stateOptions = [
    { label: 'All Statuses', value: null },
    { label: 'Draft', value: 0 },
    { label: 'Sent', value: 1 },
    { label: 'Accepted', value: 2 },
    { label: 'Rejected', value: 3 },
];

const filters = ref({
    global: { value: null, matchMode: 'contains' },
    status: { value: null, matchMode: 'equals' },
});
const entriesOptions = [
    { label: '30', value: 30 },
    { label: '50', value: 50 },
    { label: '100', value: 100 },
];
const expandedRows = ref({});
const first = ref(0);
const rows = ref(entriesOptions[0].value);

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(value || 0));

const formatDate = (date: string | null) => {
    if (!date) return '--';
    const parsed = new Date(date);
    if (Number.isNaN(parsed.getTime())) return '--';
    return parsed.toLocaleDateString('en-IN');
};

const getStatusLabel = (status: number) => {
    switch (Number(status)) {
        case 0:
            return 'Draft';
        case 1:
            return 'Sent';
        case 2:
            return 'Accepted';
        case 3:
            return 'Rejected';
        default:
            return 'Unknown';
    }
};

const getStatusSeverity = (status: number) => {
    switch (Number(status)) {
        case 0:
            return 'secondary';
        case 1:
            return 'info';
        case 2:
            return 'success';
        case 3:
            return 'danger';
        default:
            return 'secondary';
    }
};

const printQuotation = (quotation: any, action: string = 'report') => {
    const routeName = action === 'report' ? 'quotations.report' : 'quotations.download';
    window.open(route(routeName, quotation.id), '_blank');
};

const deleteQuotation = (quotation: any) => {
    Swal.fire({
        title: 'Delete Quotation?',
        text: `Are you sure you want to delete ${quotation.reference || 'this quotation'}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete',
    }).then((result) => {
        if (!result.isConfirmed) return;

        if ([2, 3].includes(Number(quotation.status))) {
            Swal.fire({ icon: 'error', title: 'Action Denied', text: 'Finalized quotations cannot be deleted.' });
            return;
        }

        router.delete(route('quotations.destroy', quotation.id), {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Quotation deleted successfully.',
                    showConfirmButton: false,
                    timer: 1500,
                });
            },
        });
    });
};

const collapseExpandedRows = () => {
    expandedRows.value = {};
};

const actionPopover = ref<InstanceType<typeof Popover> | null>(null);
const activeActionRow = ref<any>(null);

const openActions = (event: Event, row: any) => {
    activeActionRow.value = row;
    actionPopover.value?.toggle(event);
};

const printQuote = () => {
    const row = activeActionRow.value;
    if (!row) return;
    printQuotation(row, 'report');
    actionPopover.value?.hide();
};

const downloadQuotePDF = () => {
    const row = activeActionRow.value;
    if (!row) return;
    printQuotation(row, 'download');
    actionPopover.value?.hide();
};

const handleDeleteQuote = () => {
    const row = activeActionRow.value;
    if (!row) return;
    deleteQuotation(row);
    actionPopover.value?.hide();
};

const convertToCustomerPO = (val: number) => {
    const row = activeActionRow.value;
    if (!row) return;
    row.is_customer_po = val;
    updateConversion(row);
    actionPopover.value?.hide();
};

const toggleExpand = (row: any) => {
    const id = row.id;
    if (expandedRows.value[id]) {
        expandedRows.value = {};
    } else {
        expandedRows.value = { [id]: true };
    }
};

const hasActiveSalesOrders = (quotation: any) => {
    const pos = quotation.customer_p_os || quotation.customerPOs || [];
    return pos.some((po: any) => po.has_salesorders);
};


const conversionOptions = [
    { label: 'None', value: 0 },
    { label: 'Customer PO', value: 1 },
    { label: 'Rejected', value: -1 },
];

const updateConversion = (quotation: any) => {
    router.patch(route('quotations.convert', quotation.id), {
        is_customer_po: quotation.is_customer_po
    }, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Conversion status updated.',
                showConfirmButton: false,
                timer: 1500,
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Quotations">
        <div class="py-2 px-4">
            <ModuleSubTopNav />

            <div class="max-w-7xl mx-auto mt-4 space-y-4">
                <QuotationCreateForm
                    :patrons="patrons"
                    :sites="sites"
                    :unitOptions="unitOptions"
                    :mixDesigns="mixDesigns"
                    :taxes="taxes"
                    :salesExecutives="salesExecutives"
                    :concretePumpOptions="concretePumpOptions"
                />

                <hr class="border-slate-200 border-dashed" />

                <!-- Listing Table -->
                <div class="bg-white shadow-xl sm:rounded-xl">
                    <BaseDataTable
                        :value="quotations"
                        v-model:first="first"
                        v-model:rows="rows"
                        v-model:filters="filters"
                        v-model:expandedRows="expandedRows"
                        dataKey="id"
                        paginator
                        stripedRows
                        removableSort
                        rowHover
                        filterDisplay="menu"
                        class="quotation-table cursor-pointer"
                        :globalFilterFields="['reference', 'patron.legal_name', 'site.name']"
                        showSerial
                        heading="Quotation Directory"
                        headingIcon="ListBulletIcon"
                        showExport
                        showSearch
                        exportFilename="quotation-directory"
                    >
                        <template #toolbar>
                            <div class="flex items-center gap-2">
                                <BaseSelect
                                    v-model="filters.status.value"
                                    :options="stateOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="Filter Status"
                                    class="w-44 !h-9 !rounded-lg !border-slate-300 !text-[11px]"
                                    pt:label:class="!px-3 !py-1"
                                />
                            </div>
                        </template>


                        <Column field="reference" header="Reference" sortable>
                            <template #body="slotProps">
                                <button
                                    class="text-indigo-700 font-inter text-sm"
                                    type="button"
                                    @click.stop="toggleExpand(slotProps.data)"
                                >
                                    {{ slotProps.data.reference || 'DRAFT' }}
                                </button>
                            </template>
                        </Column>

                        <Column field="patron.legal_name" header="Customer" sortable>
                            <template #body="slotProps">
                                <div>
                                    <div class="font-medium text-md text-slate-800">{{ slotProps.data.patron?.legal_name || '--' }}</div>
                                    <div class="text-xs bg-gray-100 p-1 rounded-md  w-fit text-slate-500">{{ slotProps.data.site?.name || '-' }}</div>
                                </div>
                            </template>
                        </Column>

                        <Column header="Mix Designs / Grades">
                            <template #body="slotProps">
                                <div class="flex flex-wrap gap-1 max-w-[250px]">
                                    <span v-for="item in slotProps.data.items" :key="item.id" class="text-[10.5px] font-bold bg-indigo-50/70 text-indigo-700 px-2.5 py-0.5 rounded-full border border-indigo-100/70 whitespace-nowrap">
                                        {{ item.mix_design?.design_name || item.mix_design?.title || '-' }}
                                    </span>
                                </div>
                            </template>
                        </Column>

                        <Column field="quote_date" header="Quote Date" sortable>
                            <template #body="slotProps">
                                <span class="text-slate-600 text-sm">{{ formatDate(slotProps.data.quote_date) }}</span>
                            </template>
                        </Column>

                        <Column field="validity_date" header="Validity" sortable>
                            <template #body="slotProps">
                                <span class="text-slate-600 text-sm">{{ formatDate(slotProps.data.validity_date) }}</span>
                            </template>
                        </Column>

                        <Column field="amount_total" header="Amount" sortable>
                            <template #body="slotProps">
                                <span class="font-bold text-slate-900">{{ formatCurrency(slotProps.data.amount_total) }}</span>
                            </template>
                        </Column>

                        <Column field="status" header="Status">
                            <template #body="slotProps">
                                <div class="flex flex-col gap-1 items-start">
                                    <Tag :value="getStatusLabel(slotProps.data.status)" :severity="getStatusSeverity(slotProps.data.status)" rounded />
                                    <!-- Conversion Badge - Only if status is Approved (2) and converted/rejected -->
                                    <template v-if="Number(slotProps.data.status) === 2">
                                        <span v-if="slotProps.data.is_customer_po === 1" class="inline-flex items-center gap-1 text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-100/50 px-2 py-0.5 rounded-full mt-1">
                                            Converted to PO
                                        </span>
                                        <span v-else-if="slotProps.data.is_customer_po === -1" class="inline-flex items-center gap-1 text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-100/50 px-2 py-0.5 rounded-full mt-1">
                                            Rejected (Conversion)
                                        </span>
                                    </template>
                                </div>
                            </template>
                        </Column>

                        <Column header="Actions" style="width: 60px">
                            <template #body="slotProps">
                                <div class="flex justify-end">
                                    <!-- Popover trigger -->
                                    <Button
                                        icon="pi pi-ellipsis-v"
                                        text
                                        rounded
                                        severity="secondary"
                                        v-tooltip.top="'More Actions'"
                                        @click.stop="openActions($event, slotProps.data)"
                                    />
                                </div>
                            </template>
                        </Column>

                        <template #expansion="slotProps">
                            <BaseExpansionPanel :title="slotProps.data.reference || 'Draft Quotation'">
                                <QuotationEditForm
                                    :quotation="slotProps.data"
                                    :patrons="patrons"
                                    :sites="sites"
                                    :mixDesigns="mixDesigns"
                                    :unitOptions="unitOptions"
                                    :taxes="taxes"
                                    :salesExecutives="salesExecutives"
                                    :concretePumpOptions="concretePumpOptions"
                                    @updated="collapseExpandedRows"
                                />
                            </BaseExpansionPanel>
                        </template>
                    </BaseDataTable>

                    <!-- Actions Popover -->
                    <Popover ref="actionPopover" class="z-50">
                        <div class="flex flex-col gap-1 p-1 min-w-[200px]">
                            <button
                                @click="printQuote"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors w-full text-left"
                            >
                                <i class="pi pi-print text-indigo-500"></i>
                                View / Print Quote
                            </button>
                            <button
                                @click="downloadQuotePDF"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors w-full text-left"
                            >
                                <i class="pi pi-file-pdf text-emerald-500"></i>
                                Download PDF
                            </button>

                            <!-- Conversion Options (only if Approved/Accepted: status = 2) -->
                            <template v-if="Number(activeActionRow?.status) === 2">
                                <hr class="border-slate-100 my-1" />
                                <span class="text-[10px] uppercase font-bold text-slate-400 px-3 py-1 block">Conversion Options</span>
                                
                                <button
                                    v-if="activeActionRow?.is_customer_po !== 1"
                                    @click="convertToCustomerPO(1)"
                                    :disabled="hasActiveSalesOrders(activeActionRow)"
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700 transition-colors w-full text-left disabled:opacity-50"
                                >
                                    <i class="pi pi-check text-indigo-500"></i>
                                    Convert to Customer PO
                                </button>
                                <button
                                    v-if="activeActionRow?.is_customer_po !== -1"
                                    @click="convertToCustomerPO(-1)"
                                    :disabled="hasActiveSalesOrders(activeActionRow)"
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-rose-600 hover:bg-rose-50 hover:text-rose-700 transition-colors w-full text-left disabled:opacity-50"
                                >
                                    <i class="pi pi-times text-rose-500"></i>
                                    Reject Conversion
                                </button>
                                <button
                                    v-if="activeActionRow?.is_customer_po !== 0"
                                    @click="convertToCustomerPO(0)"
                                    :disabled="hasActiveSalesOrders(activeActionRow)"
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-700 transition-colors w-full text-left disabled:opacity-50"
                                >
                                    <i class="pi pi-undo text-slate-500"></i>
                                    Reset Conversion
                                </button>
                            </template>

                            <!-- Delete Option (only if NOT Approved or Rejected: status != 2 and status != 3) -->
                            <template v-if="![2, 3].includes(Number(activeActionRow?.status))">
                                <hr class="border-slate-100 my-1" />
                                <button
                                    @click="handleDeleteQuote"
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors w-full text-left"
                                >
                                    <i class="pi pi-trash text-red-500"></i>
                                    Delete Quotation
                                </button>
                            </template>
                        </div>
                    </Popover>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>

</style>

