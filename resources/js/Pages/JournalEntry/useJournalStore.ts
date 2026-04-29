import { defineStore } from 'pinia';

export interface JournalLine {
    account_id: number | null;
    debit_amount: number;
    credit_amount: number;
    partner_id: number | null;
    line_narration: string;
}

export interface JournalEntry {
    id: number;
    voucher_type: string;
    voucher_number: string;
    voucher_date: string;
    posting_date: string;
    narration: string;
    total_debit: number;
    total_credit: number;
    is_status: string;
    lines: any[];
    creator?: any;
}

export const useJournalStore = defineStore('journal', {
    state: () => ({
        entries: [] as JournalEntry[],
        ledgers: [] as any[],
        voucherTypes: [] as any[],
        partners: [] as any[],
        loading: false,
    }),
    actions: {
        setInitialData(data: any) {
            this.entries = data.entries;
            this.ledgers = data.ledgers;
            this.voucherTypes = data.voucherTypes;
            this.partners = data.partners;
        },
        addEntry(entry: JournalEntry) {
            this.entries.unshift(entry);
        },
        removeEntry(id: number) {
            this.entries = this.entries.filter(e => e.id !== id);
        }
    }
});
