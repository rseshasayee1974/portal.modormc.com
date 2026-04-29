export interface Patron {
    id: number;
    code: string | null;
    legal_name: string;
    patron_type: string | string[];
    operational_status: string;
    pan_no: string | null;
    gstin: string | null;
    status: boolean;
    displayed?: boolean;
    contacts?: any[];
    bank_accounts?: any[];
    ledger?: any;
    ledger_id: number | null;
}
