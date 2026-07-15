<script setup lang="ts">
import { ref, watch, nextTick , computed} from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Popover from 'primevue/popover';
import { ShoppingBagIcon, CpuChipIcon } from '@heroicons/vue/24/outline';
import CustomerPOCreateForm from './components/CustomerPOCreateForm.vue';
import CustomerPOEditForm from './components/CustomerPOEditForm.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps<{
    customerPOs: any[];
    patrons?: any[];
    sites?: any[];
    quotations?: any[];
    mixDesigns?: any[];
    salesExecutives?: any[];
    concretePumpOptions?: any[];
    taxes?: any[];
    pumpTypeOptions?: any[];
}>();

const filters = ref({
    global: { value: null, matchMode: 'contains' },
    status: { value: null, matchMode: 'equals' },
});

const expandedRows = ref<any>({});

const toggleEditRow = (row: any) => {
    const id = row.id;
    if (expandedRows.value[id]) {
        expandedRows.value = {};
    } else {
        expandedRows.value = { [id]: true };
    }
};

const stateOptions = [
    { label: 'All Statuses', value: null },
    { label: 'Draft', value: 0 },
    { label: 'Confirmed', value: 1 },
    { label: 'Completed', value: 2 },
];

const formatDate = (date: string | null) => {
    if (!date) return '--';
    const parsed = new Date(date);
    if (Number.isNaN(parsed.getTime())) return '--';
    return parsed.toLocaleDateString('en-IN');
};

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(value || 0));

const getStatusLabel = (status: number) => {
    switch (Number(status)) {
        case 0:
            return 'Draft';
        case 1:
            return 'Confirmed';
        case 2:
            return 'Completed';
        default:
            return 'Unknown';
    }
};

const getStatusSeverity = (status: number) => {
    switch (Number(status)) {
        case 0:
            return 'secondary';
        case 1:
            return 'success';
        case 2:
            return 'info';
        case 3:
            return 'success';
        default:
            return 'secondary';
    }
};

const getCustomerPOTotalQty = (customerPO: any) => {
    return customerPO.items?.reduce((sum: number, item: any) => sum + Number(item.quantity || 0), 0) || 0;
};

const getCustomerPOCompletedQty = (customerPO: any) => {
    return customerPO?.sales_orders?.reduce((sum: number, wo: any) => sum + Number(wo.total_qty || 0), 0) || 0;
};

const isCustomerPOCompleted = (customerPO: any) => {
    const total = getCustomerPOTotalQty(customerPO);
    if (total === 0) return true;
    const completed = getCustomerPOCompletedQty(customerPO);
    return completed >= total;
};

const getCustomerPOProgressPercent = (customerPO: any) => {
    const total = getCustomerPOTotalQty(customerPO);
    if (total === 0) return 0;
    const completed = getCustomerPOCompletedQty(customerPO);
    return Math.min(100, Math.round((completed / total) * 100));
};
// --- Delete restriction helpers ---
const { isAdmin } = usePermissions();

const hasSalesOrders = (customerPO: any) => {
    return getCustomerPOCompletedQty(customerPO) > 0;
};

const canDeleteCustomerPO = (customerPO: any): boolean => {
    if (!customerPO) return false;
    
    const completedQty = getCustomerPOCompletedQty(customerPO);
    
    // Rule: allow delete when nothing has been allocated to Sales Orders yet
    // Admin bypass: allow delete but with a warning
    return completedQty === 0 || isAdmin.value;
};

const getDeleteRestrictionReason = (customerPO: any): string => {
    if (!customerPO) return 'Invalid PO';
    
    const completedQty = getCustomerPOCompletedQty(customerPO);
    
    if (completedQty === 0) return ''; // no restriction
    
    return `Cannot delete — ${completedQty} m³ already allocated to Sales Orders`;
};

const deleteCustomerPO = (customerPO: any) => {
    if (!canDeleteCustomerPO(customerPO)) {
        Swal.fire({
            icon: 'error',
            title: 'Delete blocked',
            text: getDeleteRestrictionReason(customerPO),
        });
        return;
    }

    const completedQty = getCustomerPOCompletedQty(customerPO);
    const isWarning = completedQty > 0;

    Swal.fire({
        title: isWarning ? 'Warning: Sales Orders Exist!' : 'Delete Customer PO?',
        text: isWarning 
            ? `${getDeleteRestrictionReason(customerPO)}. Are you sure you want to proceed?`
            : `Are you sure you want to delete this Customer PO?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete',
    }).then((result) => {
        if (!result.isConfirmed) return;

        router.delete(route('customer-po.destroy', customerPO.id), {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Customer PO deleted successfully.',
                    showConfirmButton: false,
                    timer: 1500,
                });
            },
        });
    });
};

const convertToSalesOrder = (customerPO: any) => {
    const total = getCustomerPOTotalQty(customerPO);
    const completed = getCustomerPOCompletedQty(customerPO);
    const remainingQty = Math.max(0, total - completed);
    const defaultQty = remainingQty > 0? remainingQty : 1;

    Swal.fire({
        title: 'Generate Sales Order',
        text: 'Enter the quantity for the Sales Order:',
        input: 'number',
        inputValue: defaultQty,
        inputLabel: 'Quantity (m³)',
        inputPlaceholder: 'Enter quantity',
        inputAttributes: {
            min: '0.001',
            max: String(remainingQty),
            step: '0.1',
            required: 'true'
        },
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'Yes, generate',
        inputValidator: (value) => {
            if (!value || parseFloat(value) <= 0) {
                return 'Please enter a valid quantity greater than 0!';
            }
            
            if (remainingQty > 0 && parseFloat(value) > remainingQty) {
                return 'Quantity cannot be greater than the remaining quantity (' + remainingQty + ' m³)!';
            }
        }
    }).then((result) => {
        if (!result.isConfirmed ||!result.value) return;

        router.post(route('customer-po.convert-salesorder', customerPO.id), {
            quantity: result.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Sales Orders generated successfully.',
                    showConfirmButton: false,
                    timer: 1500,
                });
            },
        });
    });
};

const printCustomerPO = (po: any, action: string = 'view') => {
    window.open(route('print.document', { module: 'customer_pos', id: po.id, action }), '_blank');
};

const activeCustomerPO = ref<any>(null);
const actionMenu = ref();

const toggleActionMenu = (event: Event, salesOrder: any) => {
    event.stopPropagation();
    activeCustomerPO.value = salesOrder;
    if (actionMenu.value) {
        actionMenu.value.toggle(event);
    }
};

const closeAllMenus = () => {
    if (actionMenu.value) {
        actionMenu.value.hide();
    }
};

const onActionMenuShow = () => {
    nextTick(() => {
        const el = document.getElementById('so-action-menu');
        if (el) {
            const top = parseFloat(el.style.top) || 0;
            const height = el.offsetHeight || 0;
            el.style.top = `${top - height - 10}px`;
        }
    });
};

watch(() => props.customerPOs, () => {
    console.log('customerPOs updated');
});
</script>

<template>
    <AppLayout title="Customer POs">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="px-4 py-5 md:px-6 space-y-4">
            <!-- Create Form -->
            <CustomerPOCreateForm
                :patrons="patrons"
                :sites="sites"
                :quotations="quotations"
                :mix-designs="mixDesigns"
                :salesExecutives="salesExecutives"
                :concretePumpOptions="concretePumpOptions"
                :taxes="taxes"
                :pumpTypeOptions="pumpTypeOptions"
            />

            <!-- List Of Sales Orders -->
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-xl">
                <BaseDataTable
                    v-model:expandedRows="expandedRows"
                    :value="customerPOs"
                    v-model:filters="filters"
                    dataKey="id"
                    paginator
                    stripedRows
                    removableSort
                    rowHover
                    filterDisplay="menu"
                    showSerial
                    heading="Customer POs Directory"
                    headingIcon="ShoppingBagIcon"
                    showExport
                    showSearch
                    exportFilename="customer-pos-directory"
                    :globalFilterFields="['reference', 'patron.legal_name', 'site.name', 'quotation.reference']"
                >
                    <template #toolbar>
                        <div class="flex items-center gap-2">
                            <BaseSelect
                                v-model="filters.status.value"
                                :options="stateOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Filter Status"
                                class="w-44!h-9!rounded-lg!border-slate-300!text-"
                                pt:label:class="!px-3!py-1"
                            />
                        </div>
                    </template>

                    <Column field="order_date" header="Date" sortable>
                        <template #body="slotProps">
                            <span class="text-slate-600 dark:text-slate-300 text-sm font-medium">{{ formatDate(slotProps.data.order_date) }}</span>
                        </template>
                    </Column>

                    <Column field="reference" header="Ref #" sortable>
                        <template #body="slotProps">
                            <span class="text-slate-800 dark:text-slate-100 text-sm font-bold font-mono">{{ slotProps.data.reference || '--' }}</span>
                        </template>
                    </Column>

                    <Column field="patron.legal_name" header="Customer" sortable>
                        <template #body="slotProps">
                            <div>
                                <div class="font-bold text-md text-slate-800 dark:text-slate-100">
                                    {{ slotProps.data.patron?.legal_name || '--' }}
                                </div>
                                <span v-if="slotProps.data.quotation?.reference" class="text-indigo-600 dark:text-indigo-400 font-semibold font-mono text-xs">
                                    {{ slotProps.data.quotation.reference }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <!-- <Column field="quotation.reference" header="Loading Site" sortable>
                        <template #body="slotProps">
                            <div v-if="slotProps.data.quotation" class="flex flex-col gap-0.5">
                                <div class="text-slate-400 text-xs font-bold">
                                    {{ slotProps.data.site?.name || 'Main Site' }}
                                </div>
                            </div>
                            <span v-else class="text-slate-400 text-xs font-bold">{{ slotProps.data.site?.name || '' }}</span>
                        </template>
                    </Column> -->

                    <Column header="Mix Designs / Grades">
                        <template #body="slotProps">
                            <div class="flex flex-wrap gap-1 max-w-[250px]">
                                <span v-for="item in slotProps.data.items" :key="item.id" class="text-[10.5px] font-bold bg-indigo-50/70 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 px-2.5 py-0.5 rounded-full border border-indigo-100/70 dark:border-indigo-900/30 whitespace-nowrap">
                                    {{ item.mix_design?.design_name || item.mix_design?.title || '-' }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <!-- <Column field="amount_untaxed" header="Untaxed Amt" sortable>
                        <template #body="slotProps">
                            <span class="text-slate-600 dark:text-slate-300 text-sm font-mono">{{ formatCurrency(slotProps.data.amount_untaxed) }}</span>
                        </template>
                    </Column>

                    <Column field="amount_tax" header="Tax Amt" sortable>
                        <template #body="slotProps">
                            <span class="text-slate-600 dark:text-slate-300 text-sm font-mono">{{ formatCurrency(slotProps.data.amount_tax) }}</span>
                        </template>
                    </Column> -->

                    <Column field="amount_total" header="Total Amt" sortable>
                        <template #body="slotProps">
                            <span class="font-bold text-indigo-700 dark:text-indigo-400 text-sm font-mono">{{ formatCurrency(slotProps.data.amount_total) }}</span>
                        </template>
                    </Column>
                    <!-- <Column header="WO Status">
                        <template #body="slotProps">
                            <div class="space-y-1 min-w-">
                                <div class="flex items-center justify-between">
                                    <span class="text- font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        {{ getCustomerPOCompletedQty(slotProps.data) }} / {{ getCustomerPOTotalQty(slotProps.data) }} m³
                                    </span>
                                    <span
                                        class="text- font-black tabular-nums"
                                        :class="isCustomerPOCompleted(slotProps.data)? 'text-emerald-600' : 'text-indigo-600'"
                                    >
                                        {{ getCustomerPOProgressPercent(slotProps.data) }}%
                                    </span>
                                </div>
                                <div class="w-full h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="isCustomerPOCompleted(slotProps.data)? 'bg-emerald-500' : 'bg-indigo-500'"
                                        :style="{ width: getCustomerPOProgressPercent(slotProps.data) + '%' }"
                                    ></div>
                                </div>
                                <div v-if="isCustomerPOCompleted(slotProps.data)" class="flex items-center gap-1">
                                    <i class="pi pi-check-circle text- text-emerald-500"></i>
                                    <span class="text- font-bold text-emerald-600 uppercase tracking-widest">Fully Allocated</span>
                                </div>
                            </div>
                        </template>
                    </Column> -->

                    <Column field="status" header="Status" sortable>
                        <template #body="slotProps">
                            <Tag :value="getStatusLabel(slotProps.data.status)" :severity="getStatusSeverity(slotProps.data.status)" rounded />
                        </template>
                    </Column>

                    <Column header="Actions" headerStyle="width: 5rem; text-align: center" bodyStyle="overflow: visible; text-align: center">
                        <template #body="slotProps">
                            <button
                                type="button"
                                class="inline-flex justify-center items-center w-8 h-8 rounded-full text-slate-500 hover:text-slate-700 hover:bg-slate-100 focus:outline-none transition-all duration-200"
                                @click.stop="toggleActionMenu($event, slotProps.data)"
                                v-tooltip.top="'Actions'"
                            >
                                <i class="pi pi-ellipsis-v text-sm font-bold"></i>
                            </button>
                        </template>
                    </Column>

                    <template #expansion="{ data }">
                        <div class="p-3">
                            <CustomerPOEditForm
                                :quotations="quotations"
                                :customerPO="data"
                                :patrons="patrons"
                                :sites="sites"
                                :mixDesigns="mixDesigns"
                                :salesExecutives="salesExecutives"
                                :concretePumpOptions="concretePumpOptions"
                                :taxes="taxes"
                                :pumpTypeOptions="pumpTypeOptions"
                                @saved="expandedRows = {}"
                                @cancel="expandedRows = {}"
                            />
                        </div>
                    </template>
                </BaseDataTable>
            </div>
        </div>
        <Popover
            ref="actionMenu"
            class="!shadow-2xl!border!border-slate-200/80 dark:!border-slate-700/80!rounded-xl overflow-hidden"
            style="padding: 0; width: 14rem;"
            :pt="{ root: { id: 'so-action-menu' } }"
            @show="onActionMenuShow"
        >
            <div v-if="activeCustomerPO" class="divide-y divide-slate-100 dark:divide-slate-700/50 py-1 bg-white dark:bg-slate-800 text-left">
                <!-- Group 1: WO Generation -->
                <div class="py-1">
                    <button
                        v-if="!isCustomerPOCompleted(activeCustomerPO)"
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        @click="convertToSalesOrder(activeCustomerPO); closeAllMenus();"
                    >
                        <i class="pi pi-cog mr-2 text-indigo-500 font-bold"></i>
                        Create Sales Order
                    </button>
                    <div v-else class="flex w-full items-center px-4 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                        <i class="pi pi-check-circle mr-2 text-emerald-500 font-bold"></i>
                        Fully Allocated
                    </div>
                </div>

                <!-- Group 2: Print/Download PDF -->
                <div class="py-1">
                    <button
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        @click="printCustomerPO(activeCustomerPO, 'view'); closeAllMenus();"
                    >
                        <i class="pi pi-print mr-2 text-indigo-500 font-bold"></i>
                        Print PO
                    </button>
                    <button
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        @click="printCustomerPO(activeCustomerPO, 'download'); closeAllMenus();"
                    >
                        <i class="pi pi-file-pdf mr-2 text-indigo-500 font-bold"></i>
                        Download PDF
                    </button>
                </div>

                <!-- Group 3: Delete Customer PO -->
                <div class="py-1">
                    <button
                        v-if="canDeleteCustomerPO(activeCustomerPO)"
                        class="flex w-full items-center px-4 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20 transition-colors"
                        @click="deleteCustomerPO(activeCustomerPO); closeAllMenus();"
                    >
                        <i class="pi pi-trash mr-2 text-rose-500 font-bold"></i>
                        Delete Customer PO
                    </button>
                    <div
                        v-else
                        class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-400 cursor-not-allowed"
                        v-tooltip.right="getDeleteRestrictionReason(activeCustomerPO)"
                    >
                        <i class="pi pi-trash mr-2 text-slate-400 font-bold"></i>
                        Delete (Locked)
                    </div>
                </div>
            </div>
        </Popover>
    </AppLayout>
</template>

<style scoped>
</style>