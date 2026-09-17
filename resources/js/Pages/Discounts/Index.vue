<script setup lang="ts">
import { computed, ref, type PropType } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseExpansionPanel from '@/Components/Base/BaseExpansionPanel.vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { 
    TagIcon, 
    BanknotesIcon, 
    ReceiptPercentIcon, 
    CheckCircleIcon,
    FunnelIcon,
    PlusIcon,
    ChevronUpIcon
} from '@heroicons/vue/24/outline';
import { usePermissions } from '@/Composables/usePermissions';
import DiscountForm from './components/DiscountForm.vue';
import DiscountEditForm from './components/DiscountEditForm.vue';
import type { Discount, DiscountOptions } from './types';

const props = defineProps({
    journals: { type: Array as PropType<DiscountOptions['journals']>, required: true },
    accounts: { type: Array as PropType<DiscountOptions['accounts']>, required: true },
    partners: { type: Array as PropType<DiscountOptions['partners']>, required: true },
    discounts: { type: Array as PropType<Discount[]>, required: true },
});

const { can } = usePermissions();

const filters = ref({ global: { value: null, matchMode: 'contains' } });
const expandedRows = ref<Record<string | number, boolean>>({});
const showCreateForm = ref(false);

const typeFilter = ref('');
const statusFilter = ref('');

const typeFilterOptions = [
    { label: 'All Types (Sales & Purchase)', value: '' },
    { label: 'Sales Discounts', value: 'Sales' },
    { label: 'Purchase Discounts', value: 'Purchase' },
];

const statusFilterOptions = [
    { label: 'All Statuses', value: '' },
    { label: 'Active', value: '1' },
    { label: 'Inactive', value: '0' },
];

const rows = computed(() =>
    props.discounts.filter(
        d =>
            (!typeFilter.value || d.primary_type === typeFilter.value) &&
            (statusFilter.value === '' || String(d.status) === statusFilter.value)
    )
);

const money = (value: string | number) =>
    Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// KPI Metric Computations
const totalCount = computed(() => props.discounts.length);
const activeCount = computed(() => props.discounts.filter(d => Number(d.status) === 1).length);

const salesDiscounts = computed(() => props.discounts.filter(d => d.primary_type === 'Sales'));
const totalSalesAmount = computed(() =>
    salesDiscounts.value.reduce((sum, d) => sum + Number(d.amount || 0), 0)
);

const purchaseDiscounts = computed(() => props.discounts.filter(d => d.primary_type === 'Purchase'));
const totalPurchaseAmount = computed(() =>
    purchaseDiscounts.value.reduce((sum, d) => sum + Number(d.amount || 0), 0)
);

const totalDiscountAmount = computed(() =>
    props.discounts.reduce((sum, d) => sum + Number(d.amount || 0), 0)
);

const isExpanded = (id: number | string) => {
    if (!expandedRows.value) return false;
    if (Array.isArray(expandedRows.value)) {
        return expandedRows.value.some((r: any) => (r?.id !== undefined ? r.id === id : r === id));
    }
    return Boolean((expandedRows.value as any)[id]);
};

const toggleRow = (discount: Discount) => {
    const id = discount.id;
    if (isExpanded(id)) {
        expandedRows.value = {};
    } else {
        expandedRows.value = { [id]: true };
    }
};

const onRowExpand = (event: { data: Discount }) => {
    expandedRows.value = { [event.data.id]: true };
};

const onRowCollapse = () => {
    expandedRows.value = {};
};

const clearFilters = () => {
    typeFilter.value = '';
    statusFilter.value = '';
    filters.value.global.value = null;
};
</script>

<template>
    <AppLayout title="Discounts">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <!-- <main class="max-w-7xl mx-auto px-4 py-6 space-y-6"> -->
             
            <!-- <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                
                <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-sm flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 shrink-0">
                        <TagIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Total Discounts</span>
                        <div class="flex items-baseline gap-1.5 mt-0.5">
                            <span class="text-xl font-black text-slate-900">{{ totalCount }}</span>
                            <span class="text-[11px] text-slate-500 font-medium truncate">vouchers</span>
                        </div>
                    </div>
                </div>

                
                <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-sm flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl bg-violet-50 flex items-center justify-center text-violet-600 shrink-0">
                        <BanknotesIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Total Value</span>
                        <div class="flex items-baseline gap-1 mt-0.5">
                            <span class="text-lg font-black text-violet-700 truncate">₹ {{ money(totalDiscountAmount) }}</span>
                        </div>
                    </div>
                </div>

               
                <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-sm flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 shrink-0">
                        <ReceiptPercentIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Sales Discounts</span>
                        <div class="flex items-baseline gap-1.5 mt-0.5">
                            <span class="text-lg font-black text-blue-700 truncate">₹ {{ money(totalSalesAmount) }}</span>
                        </div>
                        <span class="text-[10px] text-slate-400 font-medium block mt-0.5">{{ salesDiscounts.length }} sales entries</span>
                    </div>
                </div>

                
                <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-sm flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0">
                        <CheckCircleIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Purchase Discounts</span>
                        <div class="flex items-baseline gap-1.5 mt-0.5">
                            <span class="text-lg font-black text-emerald-700 truncate">₹ {{ money(totalPurchaseAmount) }}</span>
                        </div>
                        <span class="text-[10px] text-slate-400 font-medium block mt-0.5">{{ purchaseDiscounts.length }} purchase entries</span>
                    </div>
                </div>
            </section> -->

            <!-- Create Form Card (Collapsible) -->
            <section   class="space-y-2 pb-4">
                <!-- <div class="flex items-center justify-between px-1">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Discount Voucher Entry</span>
                    <BaseButton
                        :label="showCreateForm ? 'Hide Create Form' : 'New Discount Voucher'"
                        :icon="showCreateForm ? 'pi pi-chevron-up' : 'pi pi-plus'"
                        :severity="showCreateForm ? 'secondary' : 'primary'"
                        :variant="showCreateForm ? 'text' : 'filled'"
                        size="small"
                        @click="showCreateForm = !showCreateForm"
                    />
                </div> -->
                <div  class="transition-all duration-200">
                    <DiscountForm :journals="journals" :accounts="accounts" :partners="partners" @saved="showCreateForm = false" />
                </div>
            </section>

            <!-- Discounts Register Table Card -->
            <section class="rounded-xl border border-slate-200/90 bg-white overflow-hidden shadow-sm">
                <!-- BaseSelect Filter Bar -->
                <div class="px-5 py-3.5 bg-slate-50/70 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-600 uppercase tracking-wider">
                            <FunnelIcon class="w-4 h-4 text-slate-400" />
                            <span>Filters:</span>
                        </div>
                        <div class="w-52">
                            <BaseSelect
                                v-model="typeFilter"
                                :options="typeFilterOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Transaction Type"
                                :filter="false"
                            />
                        </div>
                        <div class="w-40">
                            <BaseSelect
                                v-model="statusFilter"
                                :options="statusFilterOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Status"
                                :filter="false"
                            />
                        </div>
                        <button
                            v-if="typeFilter || statusFilter"
                            type="button"
                            class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold transition-colors px-2 py-1 rounded hover:bg-indigo-50 cursor-pointer"
                            @click="clearFilters"
                        >
                            Reset Filters
                        </button>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-xs text-slate-500 font-medium">
                            Showing <span class="font-bold text-slate-800">{{ rows.length }}</span> of {{ discounts.length }} vouchers
                        </div>
                    </div>
                </div>

                <!-- Main Data Table with Row Expander for Editing -->
                <BaseDataTable
                    :value="rows"
                    dataKey="id"
                    v-model:filters="filters"
                    v-model:expandedRows="expandedRows"
                    :globalFilterFields="['reference_number', 'partner.legal_name', 'journal.voucher_number', 'account.title', 'note', 'primary_type']"
                    showSearch
                    showSerial
                    stripedRows
                    heading="Discount Register"
                    :rows="15"
                    @rowExpand="onRowExpand"
                    @rowCollapse="onRowCollapse"
                >
                    <!-- Row Toggle Expander Column -->
                    <!-- <Column expander style="width: 3.5rem; text-align: center" /> -->

                    <Column field="reference_number" header="Ref Number" sortable style="min-width: 9.5rem">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                            <Tag
                                :value="data.primary_type"
                                :severity="data.primary_type === 'Sales' ? 'info' : 'warn'"
                                class="text-[11px] font-bold uppercase tracking-wider"
                            />
                            <button
                                type="button"
                                class="font-mono text-xs font-semibold text-indigo-700 hover:text-indigo-900 transition-colors cursor-pointer text-left"
                                title="Click to toggle edit form"
                                @click.stop="toggleRow(data)"
                            >
                                {{ data.reference_number }}
                            </button>
                            </div>
                        </template>
                    </Column>

                    <Column field="date" header="Date" sortable style="min-width: 7.5rem">
                        <template #body="{ data }">
                            <span class="font-mono text-xs text-slate-700 font-medium">
                                {{ data.date?.slice(0, 10) }}
                            </span>
                        </template>
                    </Column>

                    <!-- <Column field="primary_type" header="Type" sortable style="min-width: 6.5rem">
                        <template #body="{ data }">
                            <Tag
                                :value="data.primary_type"
                                :severity="data.primary_type === 'Sales' ? 'info' : 'warn'"
                                class="text-[11px] font-bold uppercase tracking-wider"
                            />
                        </template>
                    </Column> -->

                    <Column field="partner.legal_name" header="Partner" sortable style="min-width: 9rem">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <button
                                    type="button"
                                    class="font-semibold text-xs text-left text-slate-800 hover:text-indigo-600 transition-colors cursor-pointer"
                                    title="Click to toggle edit form"
                                    @click.stop="toggleRow(data)"
                                >
                                    {{ data.partner?.legal_name || `Partner #${data.partner_id}` }}
                                </button>
                                <!-- <span v-if="data.move_id" class="text-[10px] text-slate-400">
                                    Ref: #{{ data.move_id }}
                                </span> -->
                            </div>
                        </template>
                    </Column>

                    <Column field="journal.voucher_number" header="Voucher" style="min-width: 10.5rem">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="font-mono text-xs text-slate-700 font-semibold">
                                    {{ data.journal?.voucher_number || `Journal #${data.journal_id}` }}
                                </span>
                                <span
                                    v-if="data.journal && (Number(data.journal.total_debit) > 0 || Number(data.journal.total_credit) > 0)"
                                    class="text-[11px] text-slate-500 font-medium"
                                >
                                    {{ data.primary_type === 'Sales' ? 'Inv' : 'Bill' }} Val:
                                    <strong class="font-semibold text-indigo-900 font-mono">
                                        ₹ {{ money(Math.max(Number(data.journal.total_debit || 0), Number(data.journal.total_credit || 0))) }}
                                    </strong>
                                </span>
                                <!-- <span v-if="data.invoice_id" class="text-[10px] text-emerald-700 font-mono">
                                    Linked Inv: #{{ data.invoice?.invoice_number || data.invoice_id }}
                                </span>
                                <span v-else-if="data.billing_id" class="text-[10px] text-amber-700 font-mono">
                                    Linked Bill: #{{ data.bill?.invoice_number || data.billing_id }}
                                </span>
                                <span v-else-if="data.payment_id" class="text-[10px] text-sky-700 font-mono">
                                    Linked Pay: #{{ data.payment_id }}
                                </span> -->
                            </div>
                        </template>
                    </Column>

                    <Column field="account.title" header="Ledger" style="min-width: 9rem">
                        <template #body="{ data }">
                            <span class="text-xs text-slate-700">
                                {{ data.account?.title || '—' }}
                            </span>
                        </template>
                    </Column>

                    <!-- <Column field="value" header="Discount" style="min-width: 8rem">
                        <template #body="{ data }">
                            <span class="text-xs font-medium text-slate-800">
                                {{ data.value_type === 'percent' ? `${data.value}%` : `₹ ${money(data.value)}` }}
                            </span>
                        </template>
                    </Column> -->

                    <Column field="amount" header="Disc. Amount" sortable style="min-width: 8.5rem">
                        <template #body="{ data }">
                            <span class="font-bold text-xs text-indigo-700 font-mono">
                                ₹ {{ money(data.amount) }}
                            </span>
                        </template>
                    </Column>

                    <!-- <Column field="status" header="Status" style="min-width: 6rem">
                        <template #body="{ data }">
                            <Tag
                                :value="data.status ? 'Active' : 'Inactive'"
                                :severity="data.status ? 'success' : 'secondary'"
                                class="text-[11px]"
                            />
                        </template>
                    </Column> -->

                    <!-- Row Actions Column with Edit Toggle and Delete -->
                    <Column header="Actions" style="width: 6.5rem; text-align: center">
                        <template #body="{ data }">
                            <div class="flex items-center justify-center gap-1" @click.stop>
                                <!-- <Button
                                    v-if="can('DISCOUNT.UPDATE')"
                                    :icon="isExpanded(data.id) ? 'pi pi-chevron-up' : 'pi pi-pencil'"
                                    :severity="isExpanded(data.id) ? 'primary' : 'secondary'"
                                    :variant="isExpanded(data.id) ? 'filled' : 'text'"
                                    rounded
                                    size="small"
                                    class="!w-8 !h-8"
                                    :class="isExpanded(data.id) ? '!bg-indigo-100 !text-indigo-700' : 'text-slate-500 hover:text-indigo-600'"
                                    :title="isExpanded(data.id) ? 'Collapse Edit Form' : 'Expand & Edit Discount'"
                                    :aria-label="isExpanded(data.id) ? 'Collapse Edit Form' : 'Expand & Edit Discount'"
                                    @click="toggleRow(data)"
                                /> -->
                                <BaseDeleteButton
                                    v-if="can('DISCOUNT.DELETE')"
                                    :url="route('discounts.destroy', data.id)"
                                    :title="`Delete discount #${data.id}?`"
                                    text="This permanently removes the discount voucher record."
                                    confirmButtonText="Yes, Delete"
                                    successMessage="Discount deleted successfully"
                                    @success="expandedRows = {}"
                                />
                            </div>
                        </template>
                    </Column>

                    <!-- Expanded Row for Edit Form -->
                    <template #expansion="{ data }">
                        <BaseExpansionPanel :title="`Discount #${data.id} (${data.primary_type}) · ${data.partner?.legal_name || 'Partner #' + data.partner_id}`">
                            <div class="pt-2">
                                <DiscountForm
                                    :key="data.id"
                                    :discount="data"
                                    :journals="journals"
                                    :accounts="accounts"
                                    :partners="partners"
                                    :readonly="!can('DISCOUNT.UPDATE')"
                                    @saved="expandedRows = {}"
                                    @cancel="expandedRows = {}"
                                />
                            </div>
                        </BaseExpansionPanel>
                    </template>

                    <template #empty>
                        <div class="p-8 text-center space-y-2">
                            <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center">
                                <TagIcon class="w-6 h-6" />
                            </div>
                            <p class="text-sm font-semibold text-slate-700">No discounts found</p>
                            <p class="text-xs text-slate-400">There are no discount records matching the selected filters for this plant.</p>
                        </div>
                    </template>
                </BaseDataTable>
            </section>
        <!-- </main> -->
    </AppLayout>
</template>
