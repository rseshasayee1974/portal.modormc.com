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

const props = defineProps<{
    quotations: any[];
    patrons: { id: number; legal_name: string }[];
    sites: { id: number; name: string }[];
    mixDesigns: { id: number; title: string; code?: string; rate?: number }[];
    taxes: { id: number; title?: string; tax_name?: string; rate?: number; tax_rate?: number }[];
    vehicles: { id: number; registration: string }[];
    unitOptions : {id: number, unit_code: string}[];
    drivers: { id: number; first_name: string; last_name: string }[];
}>();

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
                    timer: 2500,
                });
            },
        });
    });
};

const collapseExpandedRows = () => {
    expandedRows.value = {};
};

const conversionOptions = [
    { label: 'None', value: 0 },
    { label: 'Sales Order', value: 1 },
    { label: 'Rejected', value: -1 },
];

const updateConversion = (quotation: any) => {
    router.patch(route('quotations.convert', quotation.id), {
        is_salesorder: quotation.is_salesorder
    }, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Conversion status updated.',
                showConfirmButton: false,
                timer: 2000,
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
                />

                <hr class="border-slate-200 border-dashed" />

                <div class="bg-white shadow-xl sm:rounded-lg p-8">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                        <div>
                            <h3 class="text-md font-semibold text-slate-800 uppercase">Quotation Directory</h3>
                            <p class="text-[10px] text-slate-400 font-black uppercase tracking-[0.2em] leading-none mt-1">
                                Manage estimates and revisions
                            </p>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                            <BaseSelect 
                                v-model="rows" 
                                :options="entriesOptions" 
                                optionLabel="label" 
                                optionValue="value"
                                class="!h-10 w-20 flex items-center justify-center font-bold text-xs"
                                :pt="{
                                    root: { class: 'border border-slate-300 rounded-md' },
                                    label: { class: 'text-xs p-2' }
                                }"
                            />
                            <BaseInput
                                v-model="filters.global.value"
                                placeholder="Search by Ref, Customer, Site"
                                inputClass="!h-9 !w-72"
                            />
                            <BaseSelect
                                v-model="filters.status.value"
                                :options="stateOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Filter status"
                                class="w-44"
                            />
                        </div>
                    </div>

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
                    >


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
                                    <div class="text-xs bg-gray-100 p-1 rounded-md  w-fit text-slate-500">{{ slotProps.data.site?.name || 'Main Site' }}</div>
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
                                <div class="flex flex-col gap-1.5">
                                    
                                    
                                    <!-- Conversion Dropdown - Only if Approved (2) -->
                                    <div v-if="Number(slotProps.data.status) === 2" class="mt-1">
                                        <Select 
                                            v-model="slotProps.data.is_salesorder"
                                            :options="conversionOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            @change="updateConversion(slotProps.data)"
                                            class="!text-[9px] !h-7 !w-24 font-bold uppercase"
                                            :pt="{
                                                label: { class: '!p-1 !px-2' },
                                                dropdown: { class: '!w-6' }
                                            }"
                                        />
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column header="Actions">
                            <template #body="slotProps">
                                <div class="flex justify-end gap-2">
                                    <Tag :value="getStatusLabel(slotProps.data.status)" :severity="getStatusSeverity(slotProps.data.status)" rounded />
                                    <BaseButton
                                        icon="pi pi-file-pdf"
                                        severity="info"
                                        variant="text"
                                        rounded
                                        @click.stop="printQuotation(slotProps.data, 'download')"
                                        title="Download PDF"
                                    />
                                    <BaseButton
                                        icon="pi pi-print"
                                        severity="secondary"
                                        variant="text"
                                        rounded
                                        @click.stop="printQuotation(slotProps.data, 'report')"
                                        title="Print Receipt"
                                    />
                                    
                                    <!-- <BaseButton
                                        icon="pi pi-pencil"
                                        :severity="[2, 3].includes(Number(slotProps.data.status)) ? 'secondary' : 'warn'"
                                        variant="text"
                                        rounded
                                        @click.stop="toggleExpand(slotProps.data)"
                                        :title="[2, 3].includes(Number(slotProps.data.status)) ? 'View Locked' : 'Edit'"
                                    /> -->
                                    <BaseButton
                                        icon="pi pi-trash"
                                        severity="danger"
                                        variant="text"
                                        rounded
                                        @click.stop="deleteQuotation(slotProps.data)"
                                        :disabled="[2, 3].includes(Number(slotProps.data.status))"
                                        class="disabled:opacity-30"
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
                                    @updated="collapseExpandedRows"
                                />
                            </BaseExpansionPanel>
                        </template>
                    </BaseDataTable>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>

</style>

