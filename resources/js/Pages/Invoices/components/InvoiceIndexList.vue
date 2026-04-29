<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Swal from 'sweetalert2';
import { 
    ListBulletIcon, 
    TruckIcon,
    ExclamationCircleIcon,
    CheckCircleIcon
} from '@heroicons/vue/24/outline';
import InvoiceEditForm from './InvoiceEditForm.vue';
import BaseExpansionPanel from '@/Components/Base/BaseExpansionPanel.vue';

const props = defineProps<{
    invoices: any[];
    patrons: any[];
    taxes: any[];
    trucks: any[];
    accounts: any[];
}>();

const expandedRows = ref<Record<number, boolean>>({});
const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const getStatusSeverity = (status: string) => {
    switch (status) {
        case 'draft': return 'secondary';
        case 'approved': return 'info';
        case 'paid': return 'success';
        case 'cancelled': return 'danger';
        default: return 'secondary';
    }
};

const getTypeSeverity = (type: string) => {
    switch (type) {
        case 'sales': return 'success';
        case 'purchase': return 'warn';
        default: return 'info';
    }
};

const deleteInvoice = (invoice: any) => {
    Swal.fire({
        title: 'Void Invoice?',
        text: `Are you sure you want to void ${invoice.invoice_number}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Void'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('invoices.destroy', invoice.id));
        }
    });
};

const toggleEdit = (data: any) => {
    if (expandedRows.value[data.id]) {
        expandedRows.value = {};
    } else {
        expandedRows.value = { [data.id]: true };
    }
};

</script>

<template>
    <div class="bg-white dark:bg-slate-800 shadow-xl rounded-2xl border border-slate-200 dark:border-slate-700 p-8 overflow-hidden transition-all duration-300">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-6">
            <div class="flex items-center gap-5">
                <div class="w-12 h-12 rounded-xl bg-indigo-50 dark:bg-slate-900/50 flex items-center justify-center border border-indigo-100/50 dark:border-slate-700 shadow-sm">
                    <ListBulletIcon class="w-6 h-6 text-indigo-600" />
                </div>
                <div>
                    <h3 class="text-md font-black text-slate-800 dark:text-slate-100 uppercase tracking-tight">Invoice Directory</h3>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <p class="text-[10px] text-slate-400 font-extrabold uppercase tracking-[0.2em] leading-none">Complete Logistics History</p>
                    </div>
                </div>
            </div>
        </div>

        <BaseDataTable
            :value="invoices" 
            v-model:expandedRows="expandedRows"
            v-model:filters="filters"
            dataKey="id"
            paginator 
            :rows="30"
            stripedRows
            class="p-datatable-sm"
            showSearch
            showSerial
        >
            <Column field="invoice_number" header="Invoice #" sortable>
                <template #body="slotProps">
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2">
                            <span 
                                @click="toggleEdit(slotProps.data)"
                                class="text-sm font-bold text-indigo-600 cursor-pointer hover:underline uppercase"
                            >
                                {{ slotProps.data.invoice_number }}
                            </span>
                            <Tag v-if="slotProps.data.is_duplicate" value="DUPE" severity="danger" class="!text-[7px] !px-1" />
                        </div>
                        <div class="flex items-center gap-1.5 mt-1">
                             <Tag 
                                :severity="getTypeSeverity(slotProps.data.invoice_type)" 
                                :value="slotProps.data.invoice_type" 
                                class="!text-[8px] !font-black !uppercase !tracking-widest !rounded !px-1.5"
                            />
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">{{ new Date(slotProps.data.invoice_date).toLocaleDateString('en-GB') }}</span>
                            <span v-if="slotProps.data.period" class="text-[9px] font-bold text-slate-300 ml-1">({{ slotProps.data.period }})</span>
                        </div>
                    </div>
                </template>
            </Column>

            <Column field="partner.legal_name" header="Entity / Vehicle" sortable>
                <template #body="slotProps">
                    <span class="text-sm font-bold text-slate-700 dark:text-slate-200 uppercase tracking-tight block truncate max-w-[200px]">{{ slotProps.data.partner?.legal_name }}</span>
                    <div class="flex items-center gap-2 mt-0.5">
                        <div v-if="slotProps.data.truck" class="flex items-center gap-1">
                            <TruckIcon class="w-3 h-3 text-slate-400" />
                            <span class="text-[10px] font-bold text-indigo-500 uppercase">{{ slotProps.data.truck?.registration }}</span>
                        </div>
                        <p v-if="slotProps.data.account" class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Acc: {{ slotProps.data.account?.title }}</p>
                    </div>
                </template>
            </Column>

            <Column field="total_amount" header="Total Amount" sortable class="text-right font-black">
                <template #body="slotProps">
                    <div class="flex flex-col items-end">
                        <span class="text-sm text-slate-800">₹ {{ Number(slotProps.data.total_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
                        <div class="flex gap-1 mt-1">
                             <Tag 
                                v-if="Number(slotProps.data.shipping_charges) > 0"
                                :value="`Ship: ₹ ${Number(slotProps.data.shipping_charges).toLocaleString('en-IN')}`" 
                                class="!bg-slate-50 !text-slate-500 !text-[8px] font-black"
                            />
                            <Tag 
                                v-if="slotProps.data.tax_amount > 0"
                                :value="`GST Incl.`" 
                                class="!bg-emerald-50 !text-emerald-600 !text-[8px] font-black"
                            />
                        </div>
                    </div>
                </template>
            </Column>

            <Column field="status" header="Status" align="center">
                <template #body="slotProps">
                    <div class="flex flex-col gap-1 items-center">
                        <Tag 
                            :severity="getStatusSeverity(slotProps.data.status)" 
                            :value="slotProps.data.status" 
                            class="!text-[9px] !font-black !uppercase !tracking-widest !rounded-lg !px-2"
                        />
                        <div class="flex gap-2 mt-1">
                            <CheckCircleIcon v-if="slotProps.data.is_sent" class="w-4 h-4 text-emerald-500" title="Sent" />
                            <ExclamationCircleIcon v-else class="w-4 h-4 text-slate-200" title="Not Sent" />
                            <Tag v-if="slotProps.data.einvoice_status" :value="slotProps.data.einvoice_status" severity="info" class="!text-[7px] !px-1" />
                        </div>
                    </div>
                </template>
            </Column>

            <Column header="Actions" class="text-right">
                <template #body="slotProps">
                    <div class="flex justify-end gap-1">
                        <Button icon="pi pi-print" text rounded severity="secondary" />
                        <Button 
                            icon="pi pi-pencil" 
                            text rounded severity="info" 
                            @click.stop="toggleEdit(slotProps.data)" 
                            :disabled="slotProps.data.status !== 'draft'"
                        />
                        <Button 
                            icon="pi pi-trash" 
                            text rounded severity="danger" 
                            @click.stop="deleteInvoice(slotProps.data)"
                            :disabled="['approved', 'paid'].includes(slotProps.data.status)"
                        />
                    </div>
                </template>
            </Column>

            <template #expansion="slotProps">
                <div class="p-8 bg-slate-50/50 dark:bg-slate-900/10 border-t border-slate-100 dark:border-slate-800">
                    <BaseExpansionPanel :title="`Invoice Editor: ${slotProps.data.invoice_number}`">
                        <InvoiceEditForm 
                            :invoice="slotProps.data" 
                            :patrons="patrons"
                            :taxes="taxes"
                            :trucks="trucks"
                            :accounts="accounts"
                            @cancel="expandedRows = {}"
                        />
                    </BaseExpansionPanel>
                </div>
            </template>
        </BaseDataTable>
    </div>
</template>
