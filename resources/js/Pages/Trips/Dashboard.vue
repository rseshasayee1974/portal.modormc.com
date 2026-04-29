<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { ref, h } from 'vue';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import { BanknotesIcon, ArrowTrendingUpIcon, ShieldExclamationIcon, CurrencyRupeeIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    stats: any;
    outstanding: number;
}>();

const columns = [
    { title: 'Payment Method', key: 'method' },
    { title: 'Today\'s Collection', key: 'total' },
];

const totalCollected = Object.values(props.stats).reduce((acc: any, val: any) => acc + parseFloat(val.total), 0);
</script>

<template>
    <AppLayout title="Fleet Performance Dashboard">
        <template #header><ModuleSubTopNav /></template>

        <div class="py-12 bg-slate-50/50 min-h-screen font-sans">
            <div class="max-w-[1400px] mx-auto sm:px-6 lg:px-8 space-y-10">

                <!-- Primary Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-slate-100/50 flex flex-col justify-between h-48 group hover:shadow-indigo-100 transition-all cursor-default">
                        <div class="flex justify-between items-center">
                            <div class="bg-indigo-50 p-3 rounded-2xl text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-all"><BanknotesIcon class="w-6 h-6"/></div>
                            <span class="bg-green-100 text-green-600 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-tighter">Live</span>
                        </div>
                        <div>
                            <h4 class="text-xs uppercase font-black text-gray-400 tracking-widest mb-1">Today's Collections</h4>
                            <p class="text-3xl font-black text-gray-800 tracking-tighter font-mono leading-none">₹ {{ totalCollected.toLocaleString() }}</p>
                        </div>
                    </div>

                    <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-slate-100/50 flex flex-col justify-between h-48 group hover:shadow-red-100 transition-all cursor-default">
                        <div class="flex justify-between items-center">
                            <div class="bg-red-50 p-3 rounded-2xl text-red-600 group-hover:bg-red-600 group-hover:text-white transition-all"><ShieldExclamationIcon class="w-6 h-6"/></div>
                            <span class="bg-red-100 text-red-600 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-tighter">Action Needed</span>
                        </div>
                        <div>
                            <h4 class="text-xs uppercase font-black text-gray-400 tracking-widest mb-1">Pending Receivables</h4>
                            <p class="text-3xl font-black text-red-600 tracking-tighter font-mono leading-none">₹ {{ outstanding.toLocaleString() }}</p>
                        </div>
                    </div>

                    <div class="lg:col-span-2 bg-indigo-600 p-8 rounded-[2rem] shadow-xl flex flex-col justify-between h-48 relative overflow-hidden">
                        <ArrowTrendingUpIcon class="absolute right-[-40px] bottom-[-40px] w-64 h-64 text-white opacity-[0.05] stroke-[0.5px] rotate-[-15deg]"/>
                        <div class="relative z-10 flex flex-col justify-between h-full">
                            <h4 class="text-xs uppercase font-black text-indigo-200 tracking-widest mb-1 opacity-80">Operational Target</h4>
                            <div class="flex items-end justify-between">
                                <p class="text-4xl font-black text-white tracking-tighter uppercase leading-none">Fleet Running</p>
                                <Button label="Optimize Fleet" class="rounded-full !bg-white/15 !text-white font-black text-[9px] uppercase tracking-widest h-10 px-8 border-none shadow-none hover:!bg-white/25 transition-colors" @click="() => $inertia.visit(route('trips.index'))" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Detailed Table -->
                <div class="grid grid-cols-12 gap-8">
                    <div class="col-span-12">
                        <div class="bg-white shadow-2xl rounded-[2.5rem] p-10 border border-slate-100">
                             <div class="flex items-center gap-4 mb-8">
                                <div class="p-3 bg-green-50 text-green-600 rounded-[1.2rem] shadow-sm"><CurrencyRupeeIcon class="w-8 h-8 stroke-[2.5px]" /></div>
                                <div>
                                    <h3 class="text-2xl font-black text-gray-800 tracking-tighter uppercase leading-none">Daily Reconciliation</h3>
                                    <p class="text-[10px] text-gray-400 font-extrabold mt-1 uppercase tracking-widest">Cross-verification of cash flows across methods</p>
                                </div>
                            </div>

                            <DataTable :value="Object.values(stats)" stripedRows class="p-datatable-sm">
                                <Column field="method" header="Payment Method">
                                    <template #body="slotProps">
                                        <div class="font-black uppercase text-xs text-gray-500 tracking-widest">{{ slotProps.data.method }}</div>
                                    </template>
                                </Column>
                                <Column field="total" header="Today's Collection">
                                    <template #body="slotProps">
                                        <div class="font-mono text-lg font-black text-green-600">₹{{ Number(slotProps.data.total).toLocaleString('en-IN') }}</div>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

