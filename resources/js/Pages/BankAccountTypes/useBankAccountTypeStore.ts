import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface BankAccountType {
    id: number;
    type: string;
}

export const useBankAccountTypeStore = defineStore('bankAccountType', () => {
    const bankAccountTypes = ref<BankAccountType[]>([]);

    function setBankAccountTypes(data: BankAccountType[]) {
        bankAccountTypes.value = data;
    }

    function addBankAccountType(item: BankAccountType) {
        bankAccountTypes.value.push(item);
    }

    function updateBankAccountType(updatedItem: BankAccountType) {
        const index = bankAccountTypes.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            bankAccountTypes.value[index] = updatedItem;
        }
    }

    function removeBankAccountType(id: number) {
        bankAccountTypes.value = bankAccountTypes.value.filter(a => a.id !== id);
    }

    return {
        bankAccountTypes,
        setBankAccountTypes,
        addBankAccountType,
        updateBankAccountType,
        removeBankAccountType
    };
});
