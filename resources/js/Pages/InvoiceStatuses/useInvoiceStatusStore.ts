import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface InvoiceStatus {
    id: number;
    status_name: string;
}

export const useInvoiceStatusStore = defineStore('invoiceStatus', () => {
    const invoiceStatuses = ref<InvoiceStatus[]>([]);

    function setInvoiceStatuses(data: InvoiceStatus[]) {
        invoiceStatuses.value = data;
    }

    function addInvoiceStatus(item: InvoiceStatus) {
        invoiceStatuses.value.push(item);
    }

    function updateInvoiceStatus(updatedItem: InvoiceStatus) {
        const index = invoiceStatuses.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            invoiceStatuses.value[index] = updatedItem;
        }
    }

    function removeInvoiceStatus(id: number) {
        invoiceStatuses.value = invoiceStatuses.value.filter(a => a.id !== id);
    }

    return {
        invoiceStatuses,
        setInvoiceStatuses,
        addInvoiceStatus,
        updateInvoiceStatus,
        removeInvoiceStatus
    };
});
