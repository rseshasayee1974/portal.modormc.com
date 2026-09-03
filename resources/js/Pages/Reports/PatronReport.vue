<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import { 
    UsersIcon, 
    ArrowPathIcon,
    PrinterIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    patrons: Array,
    filters: Object,
    reportData: Object
});

const form = useForm({
    patron_id: props.filters.patron_id,
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
});

const submit = () => {
    form.get(route('reports.patron'), {
        preserveState: true,
        preserveScroll: true,
    });
};

const transactionsWithBalance = computed(() => {
    let balance = props.reportData.opening_balance;
    return props.reportData.transactions.map(trx => {
        balance += (trx.debit - trx.credit);
        return {
            ...trx,
            running_balance: balance
        };
    });
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
    }).format(val);
};

const printReport = () => {
    window.print();
};

</script>

<template>
    <AppLayout title="Patron Statement">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Filter Header -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8 no-print">
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-amber-600 rounded-xl text-white shadow-lg shadow-amber-100">
                                <UsersIcon class="h-6 w-6" />
                            </div>
                            <div>
                                <h1 class="text-xl font-black text-slate-800 tracking-tight">Patron Statement</h1>
                                <p class="text-sm text-slate-500 font-medium italic">Customer & Vendor Ledger Analysis</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <BaseButton variant="outlined" severity="secondary" @click="printReport">
                                <PrinterIcon class="h-4 w-4 mr-2" />
                                Print
                            </BaseButton>
                        </div>
                    </div>

                    <div class="p-6 bg-white">
                        <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                            <div class="col-span-1 md:col-span-2">
                                <BaseSelect 
                                    v-model="form.patron_id"
                                    :options="patrons"
                                    optionLabel="legal_name"
                                    optionValue="id"
                                    label="Select Patron"
                                    placeholder="Choose a customer or vendor..."
                                    filter
                                    showClear
                                />
                            </div>
                            <div>
                                <BaseDatePicker v-model="form.start_date" :showTime="true" hourFormat="12" label="From Date & Time" fluid />
                            </div>
                            <div>
                                <BaseDatePicker v-model="form.end_date" :showTime="true" hourFormat="12" label="To Date & Time" fluid />
                            </div>
                            <div class="col-span-full flex justify-end gap-3 mt-2 border-t pt-6">
                                <BaseButton type="submit" variant="filled" severity="warning" :loading="form.processing">
                                    <ArrowPathIcon class="h-4 w-4 mr-2" />
                                    Generate Statement
                                </BaseButton>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Report Content -->
                <div v-if="reportData.transactions.length > 0 || reportData.opening_balance != 0" class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden print:shadow-none print:border-none">
                    
                    <!-- Report Header (Visible in Print) -->
                    <div class="hidden print:block p-8 text-center border-b-2 border-slate-900 mb-8">
                        <h1 class="text-3xl font-black uppercase tracking-widest">Patron Statement</h1>
                        <p class="text-lg font-bold mt-2">{{ patrons.find(p => p.id == form.patron_id)?.legal_name }}</p>
                        <p class="text-sm mt-1 text-slate-600 italic">Period: {{ form.start_date }} to {{ form.end_date }}</p>
                    </div>

                    <div class="p-8">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 border-b border-slate-100">
                                    <th class="pb-4 font-black">Date</th>
                                    <th class="pb-4 font-black">Transaction Details</th>
                                    <th class="pb-4 font-black">Voucher</th>
                                    <th class="pb-4 text-right font-black">Debit</th>
                                    <th class="pb-4 text-right font-black">Credit</th>
                                    <th class="pb-4 text-right font-black">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-medium">
                                <!-- Opening Balance Row -->
                                <tr class="border-b border-slate-50 bg-slate-50/30">
                                    <td class="py-4 text-slate-400 italic">{{ form.start_date }}</td>
                                    <td class="py-4 font-black text-amber-600 uppercase tracking-tighter">Opening Balance</td>
                                    <td class="py-4">---</td>
                                    <td class="py-4 text-right">
                                        {{ reportData.opening_balance > 0 ? formatCurrency(reportData.opening_balance) : '---' }}
                                    </td>
                                    <td class="py-4 text-right">
                                        {{ reportData.opening_balance < 0 ? formatCurrency(Math.abs(reportData.opening_balance)) : '---' }}
                                    </td>
                                    <td class="py-4 text-right font-black" :class="reportData.opening_balance >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                        {{ formatCurrency(reportData.opening_balance) }}
                                        <span class="text-[10px] ml-1 uppercase">{{ reportData.opening_balance >= 0 ? 'Dr' : 'Cr' }}</span>
                                    </td>
                                </tr>

                                <!-- Transactions -->
                                <tr v-for="(trx, idx) in transactionsWithBalance" :key="idx" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                                    <td class="py-4 text-slate-600 whitespace-nowrap">{{ trx.date }}</td>
                                    <td class="py-4">
                                        <div class="font-bold text-slate-800">{{ trx.narration }}</div>
                                        <div class="text-[10px] text-slate-400 uppercase tracking-widest mt-0.5">{{ trx.voucher_type }}</div>
                                    </td>
                                    <td class="py-4 text-amber-600 font-black tracking-tighter">{{ trx.voucher_no }}</td>
                                    <td class="py-4 text-right text-slate-900 font-bold">
                                        {{ trx.debit > 0 ? formatCurrency(trx.debit) : '---' }}
                                    </td>
                                    <td class="py-4 text-right text-slate-900 font-bold">
                                        {{ trx.credit > 0 ? formatCurrency(trx.credit) : '---' }}
                                    </td>
                                    <td class="py-4 text-right font-black text-slate-800 bg-slate-50/20">
                                        {{ formatCurrency(trx.running_balance) }}
                                        <span class="text-[10px] ml-1 uppercase text-slate-400">{{ trx.running_balance >= 0 ? 'Dr' : 'Cr' }}</span>
                                    </td>
                                </tr>

                                <!-- Closing Balance Footer -->
                                <tr class="bg-slate-900 text-white shadow-xl shadow-slate-100">
                                    <td colspan="3" class="py-6 px-4 text-right font-black uppercase tracking-widest text-[11px]">Closing Balance</td>
                                    <td colspan="3" class="py-6 px-8 text-right font-black text-xl">
                                        {{ formatCurrency(transactionsWithBalance.length > 0 ? transactionsWithBalance[transactionsWithBalance.length - 1].running_balance : reportData.opening_balance) }}
                                        <span class="text-xs ml-2 uppercase opacity-60">
                                            {{ (transactionsWithBalance.length > 0 ? transactionsWithBalance[transactionsWithBalance.length - 1].running_balance : reportData.opening_balance) >= 0 ? 'Debit' : 'Credit' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else-if="form.patron_id" class="bg-white rounded-2xl p-20 text-center border border-dashed border-slate-300">
                    <div class="mx-auto w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                        <UsersIcon class="h-8 w-8 text-slate-300" />
                    </div>
                    <h3 class="text-lg font-black text-slate-800">No transactions found</h3>
                    <p class="text-slate-500 max-w-xs mx-auto mt-2">This patron has no transaction history in the selected date range.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
    body {
        background: white !important;
    }
}
</style>
