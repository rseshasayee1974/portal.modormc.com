<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
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
import { ShoppingBagIcon, CpuChipIcon } from '@heroicons/vue/24/outline';
import SalesOrderCreateForm from './components/SalesOrderCreateForm.vue';
import SalesOrderEditForm from './components/SalesOrderEditForm.vue';

const props = defineProps<{
    salesOrders: any[];
    patrons?: any[];
    sites?: any[];
    quotations?: any[];
    mixDesigns?: any[];
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
    { label: 'Partial Dispatch', value: 2 },
    { label: 'Completed', value: 3 },
];

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
            return 'Confirmed';
        case 2:
            return 'Partial Dispatch';
        case 3:
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

const deleteSalesOrder = (salesOrder: any) => {
    Swal.fire({
        title: 'Delete Sales Order?',
        text: `Are you sure you want to delete this Sales Order?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete',
    }).then((result) => {
        if (!result.isConfirmed) return;

        router.delete(route('salesorders.destroy', salesOrder.id), {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Sales Order deleted successfully.',
                    showConfirmButton: false,
                    timer: 1500,
                });
            },
        });
    });
};

const convertToWorkOrder = (salesOrder: any) => {
    Swal.fire({
        title: 'Generate Work Order?',
        text: 'This will create scheduled Work Orders for each item in this Sales Order.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'Yes, generate',
    }).then((result) => {
        if (!result.isConfirmed) return;

        router.post(route('salesorders.convert-workorder', salesOrder.id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Work Orders generated successfully.',
                    showConfirmButton: false,
                    timer: 1500,
                });
            },
        });
    });
};

const activeMenuId = ref<number | null>(null);

const toggleMenu = (event: Event, id: number) => {
    event.stopPropagation();
    activeMenuId.value = activeMenuId.value === id ? null : id;
};

const closeAllMenus = () => {
    activeMenuId.value = null;
};

onMounted(() => {
    window.addEventListener('click', closeAllMenus);
});

onUnmounted(() => {
    window.removeEventListener('click', closeAllMenus);
});
</script>

<template>
    <AppLayout title="Sales Orders">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="px-4 py-5 md:px-6 space-y-4">
            <!-- Header section -->
            <div class="rounded-xl border border-slate-200 bg-gradient-to-r from-slate-900 via-indigo-900 to-sky-800 px-5 py-4 text-white shadow">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-white/10 p-2 text-indigo-100">
                        <ShoppingBagIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight">Sales Order Dashboard</h1>
                        <p class="mt-1 text-xs text-slate-200">View customer Sales Orders, track which user converted them, and generate corresponding Work Orders.</p>
                    </div>
                </div>
            </div>

            <!-- Create Form -->
            <SalesOrderCreateForm
                :patrons="patrons"
                :sites="sites"
                :quotations="quotations"
                :mix-designs="mixDesigns"
            />

            <!-- List Of Sales Orders -->
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-xl">
                <BaseDataTable
                    v-model:expandedRows="expandedRows"
                    :value="salesOrders"
                    v-model:filters="filters"
                    dataKey="id"
                    paginator
                    stripedRows
                    removableSort
                    rowHover
                    filterDisplay="menu"
                    showSerial
                    heading="Sales Orders Directory"
                    headingIcon="ShoppingBagIcon"
                    showExport
                    showSearch
                    exportFilename="sales-orders-directory"
                    :globalFilterFields="['patron.legal_name', 'site.name', 'quotation.reference', 'converted_by_role', 'converted_by_department']"
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

                    <Column field="order_date" header="Date" sortable>
                        <template #body="slotProps">
                            <span class="text-slate-600 dark:text-slate-300 text-sm font-medium">{{ formatDate(slotProps.data.order_date) }}</span>
                        </template>
                    </Column>

                    <Column field="patron.legal_name" header="Customer" sortable>
                        <template #body="slotProps">
                            <div>
                                <div class="font-bold text-md text-slate-800 dark:text-slate-100">
                                    {{ slotProps.data.patron?.legal_name || '--' }}
                                </div>
                                <span class="text-indigo-600 dark:text-indigo-400 font-semibold font-mono text-xs">
                                    {{ slotProps.data.quotation.reference || 'Draft' }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <Column field="quotation.reference" header="Loading Site" sortable>
                        <template #body="slotProps">
                            <div v-if="slotProps.data.quotation" class="flex flex-col gap-0.5">
                                <div class="text-xs bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded w-fit text-slate-600 dark:text-slate-300 mt-0.5">
                                    {{ slotProps.data.site?.name || 'Main Site' }}
                                </div>
                                
                            </div>
                            <span v-else class="text-slate-400 text-xs font-bold">Direct Sales Order</span>
                        </template>
                    </Column>

                   
                    <!-- <Column header="Work Orders">
                        <template #body="slotProps">
                            <div class="flex flex-wrap gap-1">
                                <template v-if="slotProps.data.work_orders && slotProps.data.work_orders.length > 0">
                                    <span
                                        v-for="wo in slotProps.data.work_orders"
                                        :key="wo.id"
                                        class="bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200 px-1.5 py-0.5 rounded font-mono text-[10px] font-bold"
                                    >
                                        {{ wo.prefix }}{{ wo.order_no }}
                                    </span>
                                </template>
                                <span v-else class="text-slate-400 text-xs italic">No Work Orders</span>
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
                            <div class="relative inline-block text-left">
                                <button
                                    type="button"
                                    class="inline-flex justify-center items-center w-8 h-8 rounded-full text-slate-500 hover:text-slate-700 hover:bg-slate-100 focus:outline-none transition-all duration-200"
                                    @click.stop="toggleMenu($event, slotProps.data.id)"
                                    v-tooltip.top="'Actions'"
                                >
                                    <i class="pi pi-ellipsis-v text-sm font-bold"></i>
                                </button>

                                <!-- Dropdown Menu -->
                                <transition
                                    enter-active-class="transition ease-out duration-100"
                                    enter-from-class="transform opacity-0 scale-95"
                                    enter-to-class="transform opacity-100 scale-100"
                                    leave-active-class="transition ease-in duration-75"
                                    leave-from-class="transform opacity-100 scale-100"
                                    leave-to-class="transform opacity-0 scale-95"
                                >
                                    <div
                                        v-if="activeMenuId === slotProps.data.id"
                                        class="absolute right-0 mt-2 w-56 rounded-xl bg-white dark:bg-slate-800 shadow-2xl border border-slate-200/80 dark:border-slate-700/80 z-[1000] focus:outline-none divide-y divide-slate-100 dark:divide-slate-700/50 py-1"
                                        @click.stop
                                    >
                                        <!-- Group 1: WO Generation -->
                                        <div class="py-1 text-left">
                                            <button
                                                v-if="!slotProps.data.work_orders || slotProps.data.work_orders.length === 0"
                                                class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                @click="convertToWorkOrder(slotProps.data); activeMenuId = null;"
                                            >
                                                <i class="pi pi-cog mr-2 text-indigo-500 font-bold"></i>
                                                Create Work Order
                                            </button>
                                            <div v-else class="flex w-full items-center px-4 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                                <i class="pi pi-check-circle mr-2 text-emerald-500 font-bold"></i>
                                                WO Generated
                                            </div>
                                        </div>

                                        <!-- Group 2: Edit Sales Order -->
                                        <div class="py-1 text-left">
                                            <button
                                                class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                @click="toggleEditRow(slotProps.data); activeMenuId = null;"
                                            >
                                                <i class="pi pi-pencil mr-2 text-amber-500 font-bold"></i>
                                                Edit Sales Order
                                            </button>
                                        </div>

                                        <!-- Group 3: Delete Sales Order -->
                                        <div class="py-1 text-left">
                                            <button
                                                v-if="!slotProps.data.has_workorders"
                                                class="flex w-full items-center px-4 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20 transition-colors"
                                                @click="deleteSalesOrder(slotProps.data); activeMenuId = null;"
                                            >
                                                <i class="pi pi-trash mr-2 text-rose-500 font-bold"></i>
                                                Delete Sales Order
                                            </button>
                                            <div
                                                v-else
                                                class="flex w-full items-center px-4 py-2 text-xs font-semibold text-slate-400 cursor-not-allowed"
                                                v-tooltip.right="'Sales Order cannot be deleted as it has Work Orders'"
                                            >
                                                <i class="pi pi-trash mr-2 text-slate-400 font-bold"></i>
                                                Delete (Locked)
                                            </div>
                                        </div>
                                    </div>
                                </transition>
                            </div>
                        </template>
                    </Column>

                    <template #expansion="{ data }">
                        <div class="p-3">
                            <SalesOrderEditForm
                            :quotations="quotations"
                                :salesOrder="data"
                                :patrons="patrons"
                                :sites="sites"
                                :mixDesigns="mixDesigns"
                                @saved="expandedRows = {}"
                                @cancel="expandedRows = {}"
                            />
                        </div>
                    </template>
                </BaseDataTable>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
</style>
