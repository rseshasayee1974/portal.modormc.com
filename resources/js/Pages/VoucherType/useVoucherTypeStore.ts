import { defineStore } from 'pinia';

export interface VoucherType {
    id: number;
    journal_name: string;
    short_code: string;
    is_system_generated: boolean | number;
    prefix: string | null;
    voucher_group: string;
    created_at?: string;
    updated_at?: string;
}

export const useVoucherTypeStore = defineStore('voucherType', {
    state: () => ({
        voucherTypes: [] as VoucherType[],
        loading: false,
    }),
    actions: {
        setVoucherTypes(types: VoucherType[]) {
            this.voucherTypes = types;
        },
        addVoucherType(type: VoucherType) {
            this.voucherTypes.unshift(type);
        },
        updateVoucherType(updated: VoucherType) {
            const index = this.voucherTypes.findIndex(t => t.id === updated.id);
            if (index !== -1) {
                this.voucherTypes[index] = updated;
            }
        },
        removeVoucherType(id: number) {
            this.voucherTypes = this.voucherTypes.filter(t => t.id !== id);
        },
    },
});
