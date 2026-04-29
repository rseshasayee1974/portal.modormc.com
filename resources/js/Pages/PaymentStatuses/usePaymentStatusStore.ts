import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface PaymentStatus {
    id: number;
    status_name: string;
}

export const usePaymentStatusStore = defineStore('paymentStatus', () => {
    const paymentStatuses = ref<PaymentStatus[]>([]);

    function setPaymentStatuses(data: PaymentStatus[]) {
        paymentStatuses.value = data;
    }

    function addPaymentStatus(item: PaymentStatus) {
        paymentStatuses.value.push(item);
    }

    function updatePaymentStatus(updatedItem: PaymentStatus) {
        const index = paymentStatuses.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            paymentStatuses.value[index] = updatedItem;
        }
    }

    function removePaymentStatus(id: number) {
        paymentStatuses.value = paymentStatuses.value.filter(a => a.id !== id);
    }

    return {
        paymentStatuses,
        setPaymentStatuses,
        addPaymentStatus,
        updatePaymentStatus,
        removePaymentStatus
    };
});
