import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface PaymentMethod {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
}

export const usePaymentMethodStore = defineStore('paymentMethod', () => {
    const paymentMethods = ref<PaymentMethod[]>([]);

    function setPaymentMethods(data: PaymentMethod[]) {
        paymentMethods.value = data;
    }

    function addPaymentMethod(item: PaymentMethod) {
        paymentMethods.value.push(item);
    }

    function updatePaymentMethod(updatedItem: PaymentMethod) {
        const index = paymentMethods.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            paymentMethods.value[index] = updatedItem;
        }
    }

    function removePaymentMethod(id: number) {
        paymentMethods.value = paymentMethods.value.filter(a => a.id !== id);
    }

    return {
        paymentMethods,
        setPaymentMethods,
        addPaymentMethod,
        updatePaymentMethod,
        removePaymentMethod
    };
});
