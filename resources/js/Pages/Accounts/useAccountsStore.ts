import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface Account {
    id: number;
    entity_id: number | null;
    code: string | null;
    title: string;
    status: number;
}

export const useAccountsStore = defineStore('accounts', () => {
    const accounts = ref<Account[]>([]);

    function setAccounts(data: Account[]) {
        accounts.value = data;
    }

    function addAccount(item: Account) {
        accounts.value.push(item);
    }

    function updateAccount(updatedItem: Account) {
        const index = accounts.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            accounts.value[index] = updatedItem;
        }
    }

    function removeAccount(id: number) {
        accounts.value = accounts.value.filter(a => a.id !== id);
    }

    return {
        accounts,
        setAccounts,
        addAccount,
        updateAccount,
        removeAccount,
    };
});
