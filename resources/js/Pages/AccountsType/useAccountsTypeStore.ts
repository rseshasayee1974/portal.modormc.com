import { defineStore } from 'pinia';

export interface AccountsType {
    id: number;
    entity_id: number;
    account_id: number;
    parent_id: number | null;
    title: string | null;
    code: string | null;
    status: number;
    account?: { id: number; title: string };
    parent?: { id: number; title: string };
}

export const useAccountsTypeStore = defineStore('accountsType', {
    state: () => ({
        accountTypes: [] as AccountsType[],
    }),
    actions: {
        setAccountTypes(types: AccountsType[]) {
            this.accountTypes = types;
        },
        addAccountType(type: AccountsType) {
            this.accountTypes.push(type);
        },
        updateAccountType(type: AccountsType) {
            const index = this.accountTypes.findIndex(t => t.id === type.id);
            if (index !== -1) {
                this.accountTypes[index] = type;
            }
        },
        removeAccountType(id: number) {
            this.accountTypes = this.accountTypes.filter(t => t.id !== id);
        },
    },
});
