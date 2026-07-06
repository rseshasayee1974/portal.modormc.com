<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Swal from 'sweetalert2';
import { 
    ListBulletIcon, 
    TruckIcon, 
    ExclamationCircleIcon,
    CheckCircleIcon
} from '@heroicons/vue/24/outline';
import InvoiceEditForm from './InvoiceEditForm.vue';
import BaseExpansionPanel from '@/Components/Base/BaseExpansionPanel.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps<{
    invoices: any[];
    patrons: any[];
    taxes: any[];
    accounts: any[];
    mixdesign: any[];
    units: any[];
    machines: any[];
}>();

const { can,isAdmin, isSuperAdmin } = usePermissions();

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
        text: `Are you sure you want to void ${invoice.invoice_number}? This will reverse accounting entries.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Void',
        cancelButtonText: 'No, Keep'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('invoices.destroy', { invoice: invoice.encrypted_id }), {
                onSuccess: () => {
                    Swal.fire({
                        title: 'Voided!',
                        text: 'Invoice has been voided successfully.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                },
                onError: (errors: any) => {
                    Swal.fire('Error', errors.error || 'Failed to void invoice. Please check permissions.', 'error');
                }
            });
        }
    });
};

const loadingRows = ref<Record<number, boolean>>({});
const invoiceDetails = ref<Record<number, any>>({});

const fetchInvoiceDetails = async (data: any) => {
    const id = data.id;
    const encryptedId = data.encrypted_id;
    if (loadingRows.value[id] || invoiceDetails.value[id]) return;
    
    loadingRows.value[id] = true;
    try {
        const response = await axios.get(route('invoices.show', encryptedId));
        invoiceDetails.value[id] = response.data;
    } catch (error) {
        console.error('Failed to fetch invoice details', error);
    } finally {
        loadingRows.value[id] = false;
    }
};
const toggleEdit = async (data: any) => {
    const id = data.id;
    const isExpanded = !!expandedRows.value[id];
    
    if (!isExpanded) {
        await fetchInvoiceDetails(data);
    }
    
    // Reset all expanded rows to ensure only one is open at a time
    const nextExpanded = {};
    if (!isExpanded) {
        nextExpanded[id] = true;
    }
    
    expandedRows.value = nextExpanded;
};

const onRowExpand = (event: any) => {
    fetchInvoiceDetails(event.data);
};

const showPreviewModal = ref(false);
const previewUrl = ref('');

const printInvoice = (data: any) => {
    if (typeof data.is_duplicate !== 'undefined') {
        data.is_duplicate = 1;
    }
    previewUrl.value = route('print.document', { module: 'invoices', id: data.encrypted_id, action: 'view' });
    showPreviewModal.value = true;
};

const printOriginal = (data: any) => {
    window.open(route('print.document', { module: 'invoices', id: data.encrypted_id, action: 'download', force: 'original' }), '_blank');
};

const printDuplicate = (data: any) => {
    window.open(route('print.document', { module: 'invoices', id: data.encrypted_id, action: 'download', force: 'duplicate' }), '_blank');
};

const showShareModal = ref(false);
const selectedShareInvoice = ref<any>(null);
const shareExpiry = ref('7');
const shareLink = ref('');
const isGeneratingLink = ref(false);

const openShareInvoice = (invoice: any) => {
    selectedShareInvoice.value = invoice;
    shareExpiry.value = '7';
    shareLink.value = '';
    showShareModal.value = true;
};

// console.log('invoice ',props.invoices);
// console.table(props.invoices, ['is_duplicate']);

const generateShareLink = async () => {
    if (!selectedShareInvoice.value) return;
    
    isGeneratingLink.value = true;
    try {
        const response = await axios.post(route('invoices.share', { id: selectedShareInvoice.value.encrypted_id }), {
            document_type: 'invoice',
            document_id: selectedShareInvoice.value.id,
            expiry: shareExpiry.value
        });
        
        if (response.data && response.data.url) {
            shareLink.value = response.data.url;
        } else {
            Swal.fire('Error', 'Failed to generate share link.', 'error');
        }
    } catch (error: any) {
        console.error('Error sharing invoice:', error);
        Swal.fire('Error', error.response?.data?.message || 'An error occurred while generating the link.', 'error');
    } finally {
        isGeneratingLink.value = false;
    }
};

const copyShareLink = async () => {
    try {
        await navigator.clipboard.writeText(shareLink.value);
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Share link copied to clipboard!',
            showConfirmButton: false,
            timer: 2000
        });
    } catch (err) {
        console.error('Failed to copy text: ', err);
    }
};

const shareWhatsApp = () => {
    const text = encodeURIComponent(`Here is the link to view the invoice: ${shareLink.value}`);
    window.open(`https://api.whatsapp.com/send?text=${text}`, '_blank');
};

const shareEmail = () => {
    const subject = encodeURIComponent(`Invoice #${selectedShareInvoice.value?.full_number || ''}`);
    const body = encodeURIComponent(`Dear Customer,\n\nPlease find the secure link to view your invoice online:\n\n${shareLink.value}\n\nThank you.`);
    window.open(`mailto:?subject=${subject}&body=${body}`, '_blank');
};

</script>

<template>
    <div class="bg-white dark:bg-slate-800 shadow-xl rounded-lg border border-slate-200 dark:border-slate-700  overflow-hidden transition-all duration-300">
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
            @rowExpand="onRowExpand"
            @row-click="toggleEdit($event.data)"
            heading="Invoice Directory"
            headingIcon="DocumentTextIcon"
            showExport
            exportFilename="invoice-directory-report"
        >
            <Column field="invoice_number" header="Invoice #" sortable>
                <template #body="slotProps">
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2">
                            <span 
                                class="text-sm font-bold text-indigo-600 hover:underline uppercase"
                            >
                                {{ slotProps.data.full_number }}
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

            <Column field="partner.legal_name" header="Patron" sortable>
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
                        <!-- <Tag 
                            :severity="getStatusSeverity(slotProps.data.status)" 
                            :value="slotProps.data.status" 
                            class="!text-[9px] !font-black !uppercase !tracking-widest !rounded-lg !px-2"
                        /> -->
                        <!-- <div class="flex gap-2 mt-1 items-center">
                            <CheckCircleIcon v-if="slotProps.data.is_sent" class="w-4 h-4 text-emerald-500" title="Sent" />
                            <ExclamationCircleIcon v-else class="w-4 h-4 text-slate-200" title="Not Sent" />
                        </div> -->
                        <div class="flex flex-col gap-1 mt-1 items-center">
                            <!-- E-Invoice status tag -->
                            <Tag 
                                v-if="slotProps.data.einvoice_status === 'generated'"
                                value="E-INV: ACTIVE" 
                                severity="success" 
                                class="!text-[8px] !font-black !px-1.5 !py-0.5" 
                                title="E-Invoice IRN Active"
                            />
                            <Tag 
                                v-else-if="slotProps.data.einvoice_status === 'cancelled'"
                                value="E-INV: CANCELLED" 
                                severity="danger" 
                                class="!text-[8px] !font-black !px-1.5 !py-0.5" 
                                title="E-Invoice IRN Cancelled"
                            />
                            <Tag 
                                v-else
                                value="E-INV: PENDING" 
                                severity="secondary" 
                                class="!text-[8px] !font-black !px-1.5 !py-0.5" 
                                title="E-Invoice IRN Not Generated"
                            />

                            <!-- E-Way Bill status tag -->
                            <Tag 
                                v-if="slotProps.data.eway_bill_no"
                                :value="`EWAY: ${slotProps.data.eway_bill_no}`" 
                                severity="info" 
                                class="!text-[8px] !font-black !px-1.5 !py-0.5" 
                                title="E-Way Bill Active"
                            />
                        </div>
                    </div>
                </template>
            </Column>

            <Column header="Actions" class="text-right">
                <template #body="slotProps">
                    <div class="flex justify-end gap-1">
                        <template v-if="isAdmin">
                            <Button 
                                icon="pi pi-file" 
                                text rounded severity="secondary" 
                                @click.stop="printOriginal(slotProps.data)"
                                title="Print Original"
                            />
                            <Button 
                                icon="pi pi-copy" 
                                text rounded severity="secondary" 
                                @click.stop="printDuplicate(slotProps.data)"
                                title="Print Duplicate"
                            />
                        </template>
                        <Button 
                            v-else
                            icon="pi pi-print" 
                            text rounded severity="secondary" 
                            @click.stop="slotProps.data.is_duplicate ? printDuplicate(slotProps.data) : printInvoice(slotProps.data)"
                            title="Print Invoice"
                        />
                        <Button 
                            icon="pi pi-share-alt" 
                            text rounded severity="info" 
                            @click.stop="openShareInvoice(slotProps.data)"
                            title="Share Invoice"
                        />
                        <Button 
                            icon="pi pi-pencil" 
                            text rounded severity="info" 
                            @click.stop="toggleEdit(slotProps.data)" 
                            :disabled="slotProps.data.status !== 'draft'"
                        />
                          <!-- :disabled="['approved', 'paid'].includes(slotProps.data.status)" -->
                        <Button 
                            icon="pi pi-trash" 
                            text rounded severity="danger" 
                            @click.stop="deleteInvoice(slotProps.data)"
                          
                        />
                    </div>
                </template>
            </Column>

            <template #expansion="slotProps">
                <div class="">
                    <div class="max-w-6xl mx-auto">
                        <div v-if="loadingRows[slotProps.data.id]" class="flex flex-col items-center justify-center py-12 gap-4">
                            <div class="w-12 h-12 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest animate-pulse">Retrieving Document Details...</p>
                        </div>
                        <InvoiceEditForm 
                            v-else-if="invoiceDetails[slotProps.data.id]"
                            :key="slotProps.data.id"
                            :invoice="invoiceDetails[slotProps.data.id]" 
                            :patrons="patrons"
                            :taxes="taxes"
                            :accounts="accounts"
                            :mixdesign="mixdesign"
                            :units="units"
                            :machines="machines"
                            @saved="expandedRows = {}"
                            @cancel="expandedRows = {}"
                        />
                    </div>
                </div>
            </template>
        </BaseDataTable>

        <!-- Share Invoice Dialog -->
        <Dialog v-model:visible="showShareModal" modal header="Share Invoice Link" :style="{ width: '90vw', maxWidth: '500px' }">
            <div class="space-y-6 pt-4">
                <div class="flex flex-col items-center justify-center space-y-4">
                    <div class="w-16 h-16 bg-indigo-50 rounded-full flex items-center justify-center">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                        </svg>
                    </div>
                    <div class="text-center">
                        <h3 class="text-lg font-bold text-slate-800">Share Public Link</h3>
                        <p class="text-sm text-slate-500 mt-1">Generate a secure link to share this invoice.</p>
                    </div>
                </div>

                <div v-if="!shareLink" class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-2 tracking-wider">Link Expiry</label>
                        <div class="grid grid-cols-4 gap-2">
                            <button 
                                v-for="opt in [
                                    { label: '1 Day', value: '1' },
                                    { label: '7 Days', value: '7' },
                                    { label: '30 Days', value: '30' },
                                    { label: 'Never', value: '0' }
                                ]" 
                                :key="opt.value"
                                type="button"
                                @click="shareExpiry = opt.value"
                                class="px-2 py-2 text-xs font-semibold rounded-lg border text-center transition-all"
                                :class="[
                                    shareExpiry === opt.value
                                    ? 'bg-indigo-50 border-indigo-500 text-indigo-700'
                                    : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'
                                ]"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>
                    <Button 
                        label="Generate Link" 
                        icon="pi pi-link" 
                        class="w-full"
                        severity="primary"
                        :loading="isGeneratingLink"
                        @click="generateShareLink"
                    />
                </div>

                <div v-else class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Secure Link Generated</label>
                        <div class="flex relative">
                            <input 
                                type="text" 
                                :value="shareLink" 
                                readonly 
                                class="w-full text-sm border-slate-200 rounded-l-xl bg-slate-50 text-slate-600 focus:ring-0 pr-10"
                            />
                            <Button 
                                icon="pi pi-copy" 
                                class="rounded-l-none"
                                @click="copyShareLink"
                            />
                        </div>
                    </div>
                    <!-- Social Share Action Buttons -->
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-700 flex gap-2">
                        <button 
                            @click="shareWhatsApp"
                            class="flex-1 py-2 px-3 bg-emerald-500 hover:bg-emerald-600 text-white font-bold rounded-lg text-xs flex items-center justify-center gap-1.5 transition-all shadow-sm"
                        >
                            <i class="pi pi-whatsapp text-sm"></i>
                            WhatsApp
                        </button>
                        <button 
                            @click="shareEmail"
                            class="flex-1 py-2 px-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-xs flex items-center justify-center gap-1.5 transition-all shadow-sm"
                        >
                            <i class="pi pi-envelope text-sm"></i>
                            Email
                        </button>
                    </div>
                </div>
            </div>
        </Dialog>
    </div>
</template>
