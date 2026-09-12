<script setup lang="ts">
import { computed, ref, type PropType } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Swal from 'sweetalert2';
import { usePermissions } from '@/Composables/usePermissions';
import DiscountForm from './components/DiscountForm.vue';
import type { Discount, DiscountOptions } from './types';

const props = defineProps({
    journals: { type: Array as PropType<DiscountOptions['journals']>, required: true },
    accounts: { type: Array as PropType<DiscountOptions['accounts']>, required: true },
    partners: { type: Array as PropType<DiscountOptions['partners']>, required: true },
    discounts: { type: Array as PropType<Discount[]>, required: true },
});
const { can } = usePermissions();
const filters = ref({ global: { value: null, matchMode: 'contains' } });
const expandedRows = ref<Record<string, boolean>>({});
const type = ref('');
const status = ref('');
const rows = computed(() => props.discounts.filter(d => (!type.value || d.primary_type === type.value) && (status.value === '' || String(d.status) === status.value)));
const money = (value: string) => Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const remove = async (discount: Discount) => {
    const result = await Swal.fire({ title: `Delete discount #${discount.id}?`, text: 'This permanently removes the discount record.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#dc2626' });
    if (!result.isConfirmed) return;
    router.delete(route('discounts.destroy', discount.id), {
        preserveScroll: true,
        onSuccess: () => { expandedRows.value = {}; },
        onError: errors => Swal.fire('Could not delete discount', Object.values(errors).join('\n'), 'error'),
    });
};
</script>

<template>
    <AppLayout title="Discounts">
        <template #header><ModuleSubTopNav /></template>
        <main class="max-w-7xl mx-auto px-4 py-6 space-y-6">
            <DiscountForm v-if="can('DISCOUNT.CREATE')" :journals="journals" :accounts="accounts" :partners="partners" />
            <section class="rounded-lg border border-slate-200 bg-white overflow-hidden">
                <div class="flex flex-wrap gap-3 px-5 pt-4">
                    <select v-model="type" aria-label="Filter transaction type" class="rounded border-slate-300 text-sm"><option value="">Sales and Purchase</option><option>Sales</option><option>Purchase</option></select>
                    <select v-model="status" aria-label="Filter status" class="rounded border-slate-300 text-sm"><option value="">All statuses</option><option value="1">Active</option><option value="0">Inactive</option></select>
                </div>
                <BaseDataTable :value="rows" dataKey="id" v-model:filters="filters" v-model:expandedRows="expandedRows" :globalFilterFields="['partner.legal_name', 'journal.voucher_number', 'account.title', 'note', 'primary_type']" showSearch showSerial stripedRows heading="Discount Register" :rows="15">
                    <Column expander style="width: 3rem" />
                    <Column field="date" header="Date" sortable><template #body="{ data }">{{ data.date?.slice(0, 10) }}</template></Column>
                    <Column field="primary_type" header="Type" sortable />
                    <Column field="partner.legal_name" header="Partner" sortable><template #body="{ data }">{{ data.partner?.legal_name || `Partner #${data.partner_id}` }}</template></Column>
                    <Column field="journal.voucher_number" header="Journal"><template #body="{ data }">{{ data.journal?.voucher_number || `Journal #${data.journal_id}` }}</template></Column>
                    <Column field="account.title" header="Account"><template #body="{ data }">{{ data.account?.title || '—' }}</template></Column>
                    <Column field="value" header="Discount"><template #body="{ data }">{{ data.value_type === 'percent' ? `${data.value}%` : money(data.value) }}</template></Column>
                    <Column field="amount" header="Amount" sortable><template #body="{ data }"><span class="font-semibold text-indigo-700">{{ money(data.amount) }}</span></template></Column>
                    <Column field="status" header="Status"><template #body="{ data }"><span :class="data.status ? 'text-emerald-700' : 'text-slate-400'">{{ data.status ? 'Active' : 'Inactive' }}</span></template></Column>
                    <Column header="Actions"><template #body="{ data }"><Button v-if="can('DISCOUNT.DELETE')" icon="pi pi-trash" severity="danger" variant="text" rounded aria-label="Delete discount" @click.stop="remove(data)" /></template></Column>
                    <template #expansion="{ data }"><div class="p-4 bg-slate-50"><DiscountForm :key="data.id" :discount="data" :journals="journals" :accounts="accounts" :partners="partners" :readonly="!can('DISCOUNT.UPDATE')" @saved="expandedRows = {}" @cancel="expandedRows = {}" /></div></template>
                    <template #empty><p class="p-8 text-center text-slate-500">No discounts found for this plant.</p></template>
                </BaseDataTable>
            </section>
        </main>
    </AppLayout>
</template>
