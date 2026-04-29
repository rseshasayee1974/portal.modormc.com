import { defineStore } from 'pinia';

export interface Ledger {
    id: number;
    entity_id: number;
    account_type_id: number;
    code: string | null;
    is_pnl: boolean | number;
    title: string;
    slug: string | null;
    notes: string | null;
    description: string | null;
    status: number;
    account_type?: { id: number; title: string };
}

export const useLedgerStore = defineStore('ledger', {
    state: () => ({
        ledgers: [] as Ledger[],
    }),
    actions: {
        setLedgers(items: Ledger[]) {
            this.ledgers = items;
        },
        addLedger(item: Ledger) {
            this.ledgers.push(item);
        },
        updateLedger(updated: Ledger) {
            const index = this.ledgers.findIndex(l => l.id === updated.id);
            if (index !== -1) {
                this.ledgers[index] = updated;
            }
        },
        removeLedger(id: number) {
            this.ledgers = this.ledgers.filter(l => l.id !== id);
        },
    },
});
