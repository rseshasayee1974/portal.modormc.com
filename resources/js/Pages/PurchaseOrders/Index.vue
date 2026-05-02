<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import InputGroup from 'primevue/inputgroup';
import InputGroupAddon from 'primevue/inputgroupaddon';
import Tag from 'primevue/tag';
import Swal from 'sweetalert2';
import { 
    ShoppingCartIcon, 
    MagnifyingGlassIcon,
    ListBulletIcon,
    PencilSquareIcon,
    TrashIcon,
    PrinterIcon,
    DocumentTextIcon,
    PlusCircleIcon
} from '@heroicons/vue/24/outline';
import PurchaseOrderForm from './PurchaseOrderForm.vue';
import PurchaseOrderEditWrapper from './components/PurchaseOrderEditWrapper.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { InputText } from 'primevue';
import BaseExpansionPanel from '@/Components/Base/BaseExpansionPanel.vue';

const props = defineProps({
    purchaseOrders: Array,
    vendors: Array,
    ref_no: Object,
    currencies: Array,
    taxes: Array,
    accounts:Array,
    products: Array,
    productUnits: Array,
});

const states = [
    { label: 'All States', value: null },
    { label: 'Draft', value: 'draft' },
    { label: 'Approved', value: 'approved' },
    { label: 'Billed', value: 'billed' },
    { label: 'Cancelled', value: 'cancel' }
];
const pageSizeOptions = [30, 50, 100];

const filters = ref({
    global: { value: null, matchMode: 'contains' },
    state: { value: null, matchMode: 'equals' },
});

const expandedRows = ref({});
const first = ref(0);
const rows = ref(30);

const dateFrom = ref(null);
const dateTo = ref(null);

const filteredPurchaseOrders = computed(() => {
    let result = props.purchaseOrders;
    
    if (dateFrom.value) {
        const from = new Date(dateFrom.value);
        from.setHours(0, 0, 0, 0);
        result = result.filter(po => new Date(po.date_order) >= from);
    }
    
    if (dateTo.value) {
        const to = new Date(dateTo.value);
        to.setHours(23, 59, 59, 999);
        result = result.filter(po => new Date(po.date_order) <= to);
    }
    
    return result;
});

const onExpandedRowsUpdate = (rows) => {
    expandedRows.value = rows;
};

const getStatusSeverity = (state) => {
    switch (state) {
        case 'draft': return 'secondary';
        case 'approved': return 'info';
        case 'billed': return 'success';
        case 'cancel': return 'danger';
        default: return 'secondary';
    }
};

const deleteOrder = (order) => {
   
    Swal.fire({
        title: 'Delete Confirmation',
        text: `Are you sure you want to delete PO ${order.po_number}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            if (Number(order.receipt_status) > 0) {
                Swal.fire({ icon: 'error', title: 'Action Denied', text: 'Cannot delete a Purchase Order with received items.' });
                return;
            }
            router.delete(route('purchaseorder.destroy', order.id), {
                onSuccess: () => Swal.fire({ toast: true, position: 'topend', icon: 'success', title: 'Deleted successfully', showConfirmButton: false, timer: 3000 })
            });
        }
    });
};

const downloadPdf = (id, template = 'downloadable') => {
    window.open(route('purchaseorder.download', { purchase_order: id, template: template }), '_blank');
};

const toggleEdit = (data) => {
    if (expandedRows.value[data.id]) {
        expandedRows.value = {};
    } else {
        expandedRows.value = { [data.id]: true };
    }
};

const formatOrderDate = (date) => {
    if (!date) return '--';
    const parsed = new Date(date);
    if (Number.isNaN(parsed.getTime())) return '--';

    const day = String(parsed.getDate()).padStart(2, '0');
    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const year = parsed.getFullYear();

    return `${day}-${month}-${year}`;
};

const formatStateLabel = (state) => {
    if (!state) return '--';
    return String(state).toUpperCase().replace('_', ' ');
};

</script>

<template>
    <AppLayout title="Purchase Orders">
        <div class="py-2 px-4 ">
            <ModuleSubTopNav />
            <div class="max-w-7xl mx-auto mt-4 space-y-4">
                
                <!-- Create Form at the Top -->
                <div>
                    <PurchaseOrderForm   
                        :ref_no="ref_no"
                        :vendors="vendors"  
                        :currencies="currencies" 
                        :taxes="taxes" 
                        :products="products" 
                        :productUnits="productUnits" 
                    />
                </div>

                <hr class="border-slate-200 border-dashed" />

                <!-- Listing Table -->
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-xl">
                    <BaseDataTable
                        :value="filteredPurchaseOrders" 
                        v-model:expandedRows="expandedRows"
                        v-model:first="first"
                        v-model:rows="rows"
                        v-model:filters="filters"
                        v-model:dateFrom="dateFrom"
                        v-model:dateTo="dateTo"
                        dataKey="id"
                        paginator 
                        :rowsPerPageOptions="[30, 50, 100]"
                        paginatorPosition="bottom"
                        stripedRows
                        removableSort
                        class="p-datatable-sm po-table"
                        filterDisplay="menu"
                        :globalFilterFields="['po_number', 'vendor.legal_name', 'ref_no']"
                        showSearch
                        showSerial
                        heading="Purchase Order Directory"
                        headingIcon="ShoppingCartIcon"
                        showExport
                        exportFilename="purchase-orders"
                    >
                        <template #toolbar>
                            <div class="flex items-center gap-2">
                                <Link :href="route('inwards.index')">
                                    <Button label=" " icon="pi pi-archive" severity="secondary" text class="!h-9" v-tooltip.bottom="'View Inwards'" />
                                </Link>

                                <BaseSelect 
                                    v-model="filters['state'].value" 
                                    :options="states" 
                                    optionLabel="label" 
                                    optionValue="value" 
                                    placeholder="Filter Status" 
                                    class="w-44 !h-9 !rounded-lg !border-slate-300 !text-[11px]"
                                    pt:label:class="!px-3 !py-1"
                                />
                            </div>
                        </template>

                        <Column field="po_number" header="PO Number" sortable>
                            <template #body="slotProps">
                                <div>
                                    <a 
                                        href="#" 
                                        class="text-slate-600 font-semibold text-sm hover:text-slate-800  decoration-2 underline-offset-4"
                                        @click.prevent="toggleEdit(slotProps.data)"
                                    >{{ slotProps.data.po_number }}</a>
                                    <!-- <div class="text-xs text-slate-400 mt-1 bg-gray-100 w-fit tracking-wider font-semibold">{{ slotProps.data.ref_no || '--' }}</div> -->
                                </div>
                            </template>
                        </Column>

                        <Column field="vendor.legal_name" header="Vendor" sortable>
                            <template #body="slotProps">
                                <div>
                                    <div class="font-medium text-slate-800">{{ slotProps.data.vendor?.legal_name }}</div>
                                    <!-- <div class="text-xs text-slate-500">{{ slotProps.data.plant?.name }}</div> -->
                                </div>
                            </template>
                        </Column>

                        <Column field="date_order" header="Order Date" sortable>
                            <template #body="slotProps">
                                <div class="text-slate-600 text-sm">
                                    {{ formatOrderDate(slotProps.data.date_order) }}
                                </div>
                            </template>
                        </Column>

                        <Column field="amount_total" header="Amount" sortable>
                            <template #body="slotProps">
                                <div>
                                    <div class="font-bold text-sm text-slate-900">
                                        <span class="text-xs text-slate-400 mr-1">{{ slotProps.data.currency?.currency_code }}</span>
                                       <span class="text-sm" v-if="slotProps.data.amount_total > 0">  {{ Number(slotProps.data.amount_total).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                                       
                                    </div>
                                    <div v-if="slotProps.data.tax_amount > 0" class="text-[10px] text-emerald-500 bg-emerald-50 px-1 inline-block rounded">Tax Incl.</div>
                                </div>
                            </template>
                        </Column>

                        <Column field="state" header="Status" align="center">
                            <template #body="slotProps">
                                <Tag class="text-xs" :severity="getStatusSeverity(slotProps.data.state)" :value="formatStateLabel(slotProps.data.state)" rounded-full />
                            </template>
                        </Column>

                        <Column header="Actions">
                            <template #body="slotProps">
                                <div class="flex justify-end gap-2">
                                    <Button icon="pi pi-file" severity="info" text rounded @click.stop="downloadPdf(slotProps.data.id)" title="Premium PDF" />
                                    <Button icon="pi pi-print" severity="secondary" text rounded @click.stop="downloadPdf(slotProps.data.id, 'printable')" title="Printable Form" />
                                    <!-- <Button icon="pi pi-pencil" :severity="slotProps.data.receipt_status > 0 ? 'secondary' : 'warn'" text rounded @click.stop="toggleEdit(slotProps.data)" :title="slotProps.data.receipt_status > 0 ? 'View Locked PO' : 'Edit Inline'" /> -->
                                    <Button icon="pi pi-trash" severity="danger" text rounded @click.stop="deleteOrder(slotProps.data)" title="Delete" :disabled="slotProps.data.receipt_status > 0" class="disabled:opacity-30" />
                                </div>
                            </template>
                        </Column>

                        <template #expansion="slotProps">
                            <BaseExpansionPanel :title="slotProps.data.reference || 'Purchase Order'">
                                <PurchaseOrderEditWrapper 
                                    :key="slotProps.data.id"
                                    :purchaseOrder="slotProps.data"
                                    :vendors="vendors"
                                    :currencies="currencies"
                                    :taxes="taxes"
                                    :products="products"
                                    :productUnits="productUnits"
                                    :accounts="accounts"
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

