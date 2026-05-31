<script setup>
import { ref, computed } from 'vue';
import { BanknotesIcon, UsersIcon, ScaleIcon, CalculatorIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    reportData: {
        type: Object,
        required: true
    }
});

const activeTab = ref('pf');

const formatCurrency = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '₹ 0.00';
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
    }).format(val);
};
</script>

<template>
    <div>
        <!-- Tab Selector -->
        <div class="flex border-b border-slate-200 mb-6 gap-2">
            <button
                @click="activeTab = 'pf'"
                class="px-5 py-2.5 text-xs font-bold transition-all border-b-2 -mb-px flex items-center gap-1.5"
                :class="[
                    activeTab === 'pf'
                    ? 'border-[#0064d2] text-[#0064d2]'
                    : 'border-transparent text-slate-500 hover:text-slate-800'
                ]"
            >
                <CalculatorIcon class="w-4 h-4" />
                Provident Fund (PF) Challan
            </button>
            <button
                @click="activeTab = 'esi'"
                class="px-5 py-2.5 text-xs font-bold transition-all border-b-2 -mb-px flex items-center gap-1.5"
                :class="[
                    activeTab === 'esi'
                    ? 'border-[#0064d2] text-[#0064d2]'
                    : 'border-transparent text-slate-500 hover:text-slate-800'
                ]"
            >
                <ScaleIcon class="w-4 h-4" />
                Employee State Insurance (ESI) Challan
            </button>
        </div>

        <!-- 1. Provident Fund (PF) Tab -->
        <div v-if="activeTab === 'pf'" class="animate-in fade-in duration-200">
            <!-- PF KPI Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
                <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">EPF Wages Total</span>
                        <span class="text-md font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(reportData.pf_totals?.epf_wages) }}</span>
                    </div>
                    <UsersIcon class="w-5 h-5 text-slate-400" />
                </div>
                <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Employee PF Share (12%)</span>
                        <span class="text-md font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(reportData.pf_totals?.employee_contribution) }}</span>
                    </div>
                    <BanknotesIcon class="w-5 h-5 text-slate-400" />
                </div>
                <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Employer Share (12%)</span>
                        <span class="text-md font-black text-[#1d2d3e] mt-1 block">
                            {{ formatCurrency((reportData.pf_totals?.employer_eps_share || 0) + (reportData.pf_totals?.employer_epf_share || 0)) }}
                        </span>
                    </div>
                    <BanknotesIcon class="w-5 h-5 text-slate-400" />
                </div>
                <div class="border border-slate-200 rounded p-4 bg-[#f2f7fc] border-[#d2e4f9] flex justify-between items-center">
                    <div>
                        <span class="text-[9px] font-bold text-[#0064d2] uppercase tracking-wider block">Net PF Payable Challan</span>
                        <span class="text-md font-black text-[#0064d2] mt-1 block">{{ formatCurrency(reportData.pf_totals?.total_contribution) }}</span>
                    </div>
                    <ScaleIcon class="w-5 h-5 text-[#0064d2]" />
                </div>
            </div>

            <!-- PF Table -->
            <div class="overflow-x-auto border border-slate-200 rounded">
                <table class="w-full text-left border-collapse min-w-[1000px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-4 text-center" style="width: 100px;">Emp Code</th>
                            <th class="py-3 px-4">Employee Name</th>
                            <th class="py-3 px-4 text-center">UAN</th>
                            <th class="py-3 px-4 text-right">Gross Wages</th>
                            <th class="py-3 px-4 text-right">EPF Wages</th>
                            <th class="py-3 px-4 text-right">EPS Wages</th>
                            <th class="py-3 px-4 text-right">Employee PF (12%)</th>
                            <th class="py-3 px-4 text-right">Employer EPS (8.33%)</th>
                            <th class="py-3 px-4 text-right">Employer EPF (3.67%)</th>
                            <th class="py-3 px-4 text-right">Total PF Payable</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr v-if="(reportData.pf || []).length === 0">
                            <td colspan="10" class="py-8 text-center text-slate-400 bg-slate-50/50">
                                No PF transactions found for the selected date range.
                            </td>
                        </tr>
                        <tr v-for="(row, idx) in reportData.pf" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 text-center text-slate-500 whitespace-nowrap">{{ row.employee_code }}</td>
                            <td class="py-3 px-4 font-bold text-slate-900">{{ row.name }}</td>
                            <td class="py-3 px-4 text-center font-mono">{{ row.uan }}</td>
                            <td class="py-3 px-4 text-right text-slate-500">{{ formatCurrency(row.gross_wages) }}</td>
                            <td class="py-3 px-4 text-right text-slate-500">{{ formatCurrency(row.epf_wages) }}</td>
                            <td class="py-3 px-4 text-right text-slate-500">{{ formatCurrency(row.eps_wages) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.employee_contribution) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.employer_eps_share) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.employer_epf_share) }}</td>
                            <td class="py-3 px-4 text-right font-black text-indigo-600 bg-indigo-50/20">{{ formatCurrency(row.total_contribution) }}</td>
                        </tr>
                        <tr v-if="(reportData.pf || []).length > 0" class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs text-slate-800">
                            <td colspan="3" class="py-3.5 px-4 uppercase text-center font-bold">Total PF Summary</td>
                            <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(reportData.pf_totals?.gross_wages) }}</td>
                            <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(reportData.pf_totals?.epf_wages) }}</td>
                            <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(reportData.pf_totals?.eps_wages) }}</td>
                            <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(reportData.pf_totals?.employee_contribution) }}</td>
                            <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(reportData.pf_totals?.employer_eps_share) }}</td>
                            <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(reportData.pf_totals?.employer_epf_share) }}</td>
                            <td class="py-3.5 px-4 text-right font-black bg-indigo-50 text-indigo-600">{{ formatCurrency(reportData.pf_totals?.total_contribution) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. Employee State Insurance (ESI) Tab -->
        <div v-if="activeTab === 'esi'" class="animate-in fade-in duration-200">
            <!-- ESI KPI Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
                <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">ESI Gross Wages</span>
                        <span class="text-md font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(reportData.esi_totals?.gross_wages) }}</span>
                    </div>
                    <UsersIcon class="w-5 h-5 text-slate-400" />
                </div>
                <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Employee ESI Share (0.75%)</span>
                        <span class="text-md font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(reportData.esi_totals?.employee_contribution) }}</span>
                    </div>
                    <BanknotesIcon class="w-5 h-5 text-slate-400" />
                </div>
                <div class="border border-slate-200 rounded p-4 bg-slate-50/30 flex justify-between items-center">
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Employer ESI Share (3.25%)</span>
                        <span class="text-md font-black text-[#1d2d3e] mt-1 block">{{ formatCurrency(reportData.esi_totals?.employer_contribution) }}</span>
                    </div>
                    <BanknotesIcon class="w-5 h-5 text-slate-400" />
                </div>
                <div class="border border-slate-200 rounded p-4 bg-[#f2f7fc] border-[#d2e4f9] flex justify-between items-center">
                    <div>
                        <span class="text-[9px] font-bold text-[#0064d2] uppercase tracking-wider block">Net ESI Payable Challan</span>
                        <span class="text-md font-black text-[#0064d2] mt-1 block">{{ formatCurrency(reportData.esi_totals?.total_contribution) }}</span>
                    </div>
                    <ScaleIcon class="w-5 h-5 text-[#0064d2]" />
                </div>
            </div>

            <!-- ESI Table -->
            <div class="overflow-x-auto border border-slate-200 rounded">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f2f4f7]">
                            <th class="py-3 px-4 text-center" style="width: 100px;">Emp Code</th>
                            <th class="py-3 px-4">Employee Name</th>
                            <th class="py-3 px-4 text-center">ESI Number</th>
                            <th class="py-3 px-4 text-center" style="width: 120px;">Days Worked</th>
                            <th class="py-3 px-4 text-right">Gross Wages</th>
                            <th class="py-3 px-4 text-right">Employee ESI (0.75%)</th>
                            <th class="py-3 px-4 text-right">Employer ESI (3.25%)</th>
                            <th class="py-3 px-4 text-right">Total ESI Payable</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px] font-semibold text-slate-700">
                        <tr v-if="(reportData.esi || []).length === 0">
                            <td colspan="8" class="py-8 text-center text-slate-400 bg-slate-50/50">
                                No ESI transactions found for the selected date range.
                            </td>
                        </tr>
                        <tr v-for="(row, idx) in reportData.esi" :key="idx" class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                            <td class="py-3 px-4 text-center text-slate-500 whitespace-nowrap">{{ row.employee_code }}</td>
                            <td class="py-3 px-4 font-bold text-slate-900">{{ row.name }}</td>
                            <td class="py-3 px-4 text-center font-mono">{{ row.esi_number }}</td>
                            <td class="py-3 px-4 text-center">{{ row.days_worked }}</td>
                            <td class="py-3 px-4 text-right text-slate-500">{{ formatCurrency(row.gross_wages) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.employee_contribution) }}</td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ formatCurrency(row.employer_contribution) }}</td>
                            <td class="py-3 px-4 text-right font-black text-indigo-600 bg-indigo-50/20">{{ formatCurrency(row.total_contribution) }}</td>
                        </tr>
                        <tr v-if="(reportData.esi || []).length > 0" class="bg-[#f2f4f7] font-bold border-t border-slate-300 text-xs text-slate-800">
                            <td colspan="3" class="py-3.5 px-4 uppercase text-center font-bold">Total ESI Summary</td>
                            <td class="py-3.5 px-4 text-center font-black">{{ reportData.esi_totals?.days_worked }}</td>
                            <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(reportData.esi_totals?.gross_wages) }}</td>
                            <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(reportData.esi_totals?.employee_contribution) }}</td>
                            <td class="py-3.5 px-4 text-right font-black">{{ formatCurrency(reportData.esi_totals?.employer_contribution) }}</td>
                            <td class="py-3.5 px-4 text-right font-black bg-indigo-50 text-indigo-600">{{ formatCurrency(reportData.esi_totals?.total_contribution) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
