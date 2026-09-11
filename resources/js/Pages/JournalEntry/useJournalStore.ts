import { defineStore } from 'pinia';

export interface JournalLine {
    id?: number;
    account_id: number | null;
    debit_amount: number;
    credit_amount: number;
    partner_id: number | null;
    line_narration?: string;
    ledger?: {
        id: number;
        code: string;
        title: string;
    };
    partner?: {
        id: number;
        code: string;
        legal_name: string;
    };
}

export interface JournalEntry {
    id: number;
    plant_id?: number;
    voucher_type: string;
    voucher_number: string;
    voucher_date: string;
    posting_date: string;
    narration: string;
    narration_label?: string;
    total_debit: number | string;
    total_credit: number | string;
    is_status: string;
    lines: JournalLine[];
    creator?: {
        id: number;
        name: string;
        username?: string;
    };
    plant?: {
        id: number;
        name: string;
        code?: string;
    };
    created_at?: string;
}

export const useJournalStore = defineStore('journal', {
    state: () => ({
        entries: [] as JournalEntry[],
        ledgers: [] as any[],
        voucherTypes: [] as any[],
        partners: [] as any[],
        nextVoucherNumbers: {} as Record<string, string>,
        currentVoucherNumber: '',
        selectedEntry: null as JournalEntry | null,
        loading: false,
    }),
    actions: {
        setInitialData(data: {
            entries?: JournalEntry[];
            ledgers?: any[];
            voucherTypes?: any[];
            partners?: any[];
            nextVoucherNumbers?: Record<string, string>;
            initialVoucherNumber?: string;
        }) {
            this.entries = data.entries || [];
            this.ledgers = data.ledgers || [];
            this.voucherTypes = data.voucherTypes || [];
            this.partners = data.partners || [];
            this.nextVoucherNumbers = data.nextVoucherNumbers || {};
            if (data.initialVoucherNumber) {
                this.currentVoucherNumber = data.initialVoucherNumber;
            }
        },
        computeNextVoucherNumber(voucherType: string): string {
            if (!voucherType) return '';

            const vType = this.voucherTypes.find(
                (v: any) => v.short_code?.toUpperCase() === voucherType.toUpperCase()
            );
            const prefix = vType?.prefix || `${voucherType.toUpperCase()}-`;

            const usedInts = new Set<number>();
            for (const entry of this.entries) {
                if (entry.voucher_type?.toUpperCase() === voucherType.toUpperCase() && entry.voucher_number) {
                    const numStr = entry.voucher_number;
                    if (prefix && numStr.startsWith(prefix)) {
                        const part = parseInt(numStr.slice(prefix.length), 10);
                        if (!isNaN(part)) usedInts.add(part);
                    } else {
                        const digits = numStr.replace(/\D/g, '');
                        if (digits) usedInts.add(parseInt(digits, 10));
                    }
                }
            }

            let nextSeq = 1;
            while (usedInts.has(nextSeq)) {
                nextSeq++;
            }

            const calculated = `${prefix}${String(nextSeq).padStart(5, '0')}`;
            this.currentVoucherNumber = calculated;
            return calculated;
        },
        addEntry(entry: JournalEntry) {
            this.entries.unshift(entry);
        },
        updateEntry(entry: JournalEntry) {
            const index = this.entries.findIndex(e => e.id === entry.id);
            if (index !== -1) {
                this.entries[index] = entry;
            }
        },
        removeEntry(id: number) {
            this.entries = this.entries.filter(e => e.id !== id);
        },
        setSelectedEntry(entry: JournalEntry | null) {
            this.selectedEntry = entry;
        }
    }
});
