export interface Discount {
    id: number;
    primary_type: 'Sales' | 'Purchase';
    value_type: 'percent' | 'amount';
    value: string;
    amount: string;
    journal_id: number;
    account_id: number | null;
    partner_id: number;
    move_id: number | null;
    date: string;
    note: string | null;
    status: number;
    created_at: string;
    modified_at: string;
    journal?: { voucher_number: string };
    account?: { title: string };
    partner?: { legal_name: string };
}

export interface DiscountOptions {
    journals: { id: number; voucher_number: string; voucher_type: string }[];
    accounts: { id: number; title: string }[];
    partners: { id: number; legal_name: string }[];
}
