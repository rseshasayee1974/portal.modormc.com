export interface Discount {
    id: number;
    primary_type: 'Sales' | 'Purchase';
    value_type: 'percent' | 'amount';
    value: string;
    amount: string;
    journal_id: number;
    account_id: number | null;
    partner_id: number;
    invoice_id?: number | null;
    billing_id?: number | null;
    payment_id?: number | null;
    reference_number?: string | null;
    move_id: number | null;
    date: string;
    note: string | null;
    status: number;
    created_at: string;
    modified_at: string;
    journal?: {
        id?: number;
        voucher_number: string;
        voucher_type?: string;
        ref_module?: string | null;
        total_debit?: string | number | null;
        total_credit?: string | number | null;
    };
    invoice?: { id: number; invoice_number: string; prefix?: string | null };
    bill?: { id: number; invoice_number: string; prefix?: string | null };
    account?: { title: string };
    partner?: { legal_name: string };
}

export interface DiscountOptions {
    journals: {
        id: number;
        voucher_number: string;
        voucher_type: string;
        ref_module?: string | null;
        ref_id?: number | null;
        invoice_doc_id?: number | null;
        total_debit?: string | number | null;
        total_credit?: string | number | null;
        partner_id?: number | null;
        partner_name?: string | null;
        invoice_number?: string | null;
        applied_discount_id?: number | null;
    }[];
    accounts: { id: number; title: string }[];
    partners: { id: number; legal_name: string; patron_type?: string | null }[];
}
