<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import PaymentCreateForm from './PaymentCreateForm.vue';
import PaymentEditForm from './PaymentEditForm.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseExpansionPanel from '@/Components/Base/BaseExpansionPanel.vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Swal from 'sweetalert2';
import {
    BanknotesIcon,
    PencilSquareIcon,
    TrashIcon,
    ArrowUpRightIcon,
    ArrowDownLeftIcon
} from '@heroicons/vue/24/outline';

const page = usePage();

const props = defineProps<{
    payments: any[];
    ledgers: { id: number; title: string }[];
    patrons: { id: number; legal_name: string }[];
}>();

const filters = ref({
    global: { value: null, matchMode: 'contains' },
    transaction_type: { value: null, matchMode: 'equals' },
});

const expandedRows = ref({});
const first = ref(0);
const rows = ref(30);

const dateFrom = ref(null);
const dateTo = ref(null);

const filteredPayments = computed(() => {
    let result = props.payments;
    
    if (dateFrom.value) {
        const from = new Date(dateFrom.value);
        from.setHours(0, 0, 0, 0);
        result = result.filter(p => new Date(p.transaction_date || p.created_at) >= from);
    }
    
    if (dateTo.value) {
        const to = new Date(dateTo.value);
        to.setHours(23, 59, 59, 999);
        result = result.filter(p => new Date(p.transaction_date || p.created_at) <= to);
    }
    
    return result;
});

const deleteTransaction = (id: number) => {
    Swal.fire({
        title: 'Void this record?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Void'
    }).then(res => {
        if (res.isConfirmed) {
            router.delete(route('payments.destroy', id), {
                onSuccess: () => {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Transaction voided', showConfirmButton: false, timer: 3000 });
                }
            });
        }
    });
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '—';
    const pureDate = dateStr.split('T')[0];
    const parts = pureDate.split('-');
    if (parts.length !== 3) return pureDate;
    const [y, m, d] = parts;
    return `${d}-${m}-${y}`;
};

const toggleEdit = (data: any) => {
    if (expandedRows.value[data.id]) {
        expandedRows.value = {};
    } else {
        expandedRows.value = { [data.id]: true };
    }
};

const ledgerOptions = computed(() => props.ledgers.map(l => ({ label: l.title, value: l.id })));
const patronOptions = computed(() => props.patrons.map(p => ({ label: p.legal_name, value: p.id })));

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: flash.success, showConfirmButton: false, timer: 3000 });
}, { immediate: true, deep: true });

import { router } from '@inertiajs/vue3';
</script>

<template>
    <AppLayout title="Journal Records">
        <template #header><ModuleSubTopNav /></template>

        <div class="py-2 px-4 bg-slate-50/50 dark:bg-slate-900 min-h-screen">
            <div class="max-w-7xl mx-auto mt-4 space-y-6">

                <!-- Creation Desk -->
                <PaymentCreateForm 
                    :ledgers="ledgers"
                    :patrons="patrons"
                />

                <hr class="border-slate-200 border-dashed" />

                <!-- Listing Table -->
                <div class="bg-white dark:bg-slate-800 shadow-xl sm:rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700">
                    <BaseDataTable
                        :value="filteredPayments" 
                        v-model:expandedRows="expandedRows"
                        v-model:first="first"
                        v-model:rows="rows"
                        v-model:filters="filters"
                        v-model:dateFrom="dateFrom"
                        v-model:dateTo="dateTo"
                        dataKey="id"
                        paginator 
                        :rowsPerPageOptions="[30, 50, 100]"
                        stripedRows
                        removableSort
                        class="p-datatable-sm"
                        filterDisplay="menu"
                        :globalFilterFields="['reference', 'patron.legal_name', 'ledger.title']"
                        showSearch
                        showSerial
                        heading="Journal Directory"
                        headingIcon="BanknotesIcon"
                        showExport
                        exportFilename="payment-records"
                    >
                        <Column field="transaction_date" header="Date" sortable>
                            <template #body="slotProps">
                                <div class="text-[11px] text-slate-500 font-bold uppercase tracking-tight">
                                    {{ formatDate(slotProps.data.transaction_date || slotProps.data.created_at) }}
                                </div>
                            </template>
                        </Column>

                        <Column field="transaction_type" header="Type" sortable>
                            <template #body="slotProps">
                                <Tag 
                                    :severity="slotProps.data.transaction_type === 'payment' ? 'warn' : 'success'"
                                    rounded
                                    class="font-black text-[10px] px-3"
                                >
                                    <div class="flex items-center gap-1">
                                        <ArrowUpRightIcon v-if="slotProps.data.transaction_type === 'payment'" class="w-3 h-3" />
                                        <ArrowDownLeftIcon v-else class="w-3 h-3" />
                                        <span>{{ slotProps.data.transaction_type === 'payment' ? 'PAYMENT' : 'RECEIPT' }}</span>
                                    </div>
                                </Tag>
                            </template>
                        </Column>

                        <Column field="ledger.title" header="Journal / Partner" sortable>
                            <template #body="slotProps">
                                <div>
                                    <div class="font-black text-slate-700 dark:text-gray-200 uppercase text-xs tracking-tighter">
                                        {{ slotProps.data.ledger?.title || '—' }}
                                    </div>
                                    <div class="text-[10px] text-indigo-500 font-bold uppercase tracking-wider">
                                        {{ slotProps.data.patron?.legal_name || 'General Ledger' }}
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column field="amount" header="Amount" sortable textAlign="right">
                            <template #body="slotProps">
                                <div :class="`font-mono font-black text-sm ${slotProps.data.transaction_type === 'receipt' ? 'text-emerald-600' : 'text-slate-800 dark:text-gray-100'}`">
                                    ₹ {{ Number(slotProps.data.amount).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                </div>
                            </template>
                        </Column>

                        <Column header="Actions" textAlign="right">
                            <template #body="slotProps">
                                <div class="flex justify-end gap-2">
                                    <Button 
                                        icon="pi pi-pencil" 
                                        severity="secondary" 
                                        text 
                                        rounded 
                                        @click.stop="toggleEdit(slotProps.data)"
                                        title="Edit Record"
                                    >
                                        <template #icon>
                                            <PencilSquareIcon class="w-4 h-4 text-emerald-600" />
                                        </template>
                                    </Button>
                                    <Button 
                                        icon="pi pi-trash" 
                                        severity="danger" 
                                        text 
                                        rounded 
                                        @click.stop="deleteTransaction(slotProps.data.id)"
                                        title="Void"
                                    >
                                        <template #icon>
                                            <TrashIcon class="w-4 h-4" />
                                        </template>
                                    </Button>
                                </div>
                            </template>
                        </Column>

                        <template #expansion="slotProps">
                            <BaseExpansionPanel :title="slotProps.data.reference || 'Transaction Details'">
                                <PaymentEditForm 
                                    :payment="slotProps.data"
                                    :ledgerOptions="ledgerOptions"
                                    :patronOptions="patronOptions"
                                    @success="expandedRows = {}"
                                    @close="expandedRows = {}"
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
:deep(.p-datatable-thead > tr > th) {
    @apply bg-slate-50/50 dark:bg-slate-900/50 text-slate-400 font-black uppercase text-[10px] tracking-widest py-4 px-6 border-b border-slate-100 dark:border-slate-700;
}
:deep(.p-datatable-tbody > tr > td) {
    @apply py-4 px-6 border-b border-slate-50 dark:border-slate-700/50;
}
</style>


