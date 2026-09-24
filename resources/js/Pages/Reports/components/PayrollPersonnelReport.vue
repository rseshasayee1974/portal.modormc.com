<script setup>
import { ref, computed } from 'vue';
import {
    UsersIcon,
    BanknotesIcon,
    DocumentTextIcon,
    ArrowDownTrayIcon,
    EyeIcon,
    CheckCircleIcon,
    MagnifyingGlassIcon,
    ArrowTopRightOnSquareIcon,
    ClockIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    reportData: {
        type: Object,
        required: true
    }
});

const searchQuery = ref('');

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2
    }).format(val || 0);
};

const filteredTransactions = computed(() => {
    const list = props.reportData?.transactions || [];
    if (!searchQuery.value.trim()) return list;
    const q = searchQuery.value.toLowerCase().trim();
    return list.filter(row =>
        (row.name && row.name.toLowerCase().includes(q)) ||
        (row.employee_code && row.employee_code.toLowerCase().includes(q)) ||
        (row.department && row.department.toLowerCase().includes(q)) ||
        (row.designation && row.designation.toLowerCase().includes(q)) ||
        (row.payslip_no && row.payslip_no.toLowerCase().includes(q)) ||
        (row.payslip_status && row.payslip_status.toLowerCase().includes(q))
    );
});

const summary = computed(() => {
    const list = props.reportData?.transactions || [];
    const totalEmployees = list.length;
    const generatedSlips = list.filter(r => r.has_payslip).length;
    const totalEarnings = list.reduce((acc, r) => acc + (Number(r.total_earnings) || 0), 0);
    const totalDeductions = list.reduce((acc, r) => acc + (Number(r.total_deductions) || 0), 0);
    const totalNetSalary = list.reduce((acc, r) => acc + (Number(r.net_salary) || 0), 0);

    return {
        totalEmployees,
        generatedSlips,
        totalEarnings,
        totalDeductions,
        totalNetSalary
    };
});

const viewPayslip = (payslipId) => {
    if (!payslipId) return;
    window.open(route('payslips.show', payslipId) + '?action=view', '_blank');
};

const downloadPayslip = (payslipId) => {
    if (!payslipId) return;
    window.open(route('payslips.show', payslipId), '_blank');
};

const openPayrollEngine = () => {
    window.open(route('payslips.index'), '_blank');
};
</script>

<template>
    <div class="space-y-6">
        <!-- Top Executive KPI Summary Row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Total Employees -->
            <div
                class="border border-slate-200 rounded-lg p-4 bg-white shadow-2xs flex justify-between items-center transition-all hover:border-slate-300">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total
                        Personnel</span>
                    <span class="text-xl font-black text-[#1d2d3e] mt-1 block">{{ summary.totalEmployees }} Staff</span>
                </div>
                <div
                    class="w-10 h-10 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500">
                    <UsersIcon class="w-5 h-5" />
                </div>
            </div>

            <!-- Payslips Generated -->
            <div
                class="border border-slate-200 rounded-lg p-4 bg-white shadow-2xs flex justify-between items-center transition-all hover:border-slate-300">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Payslips
                        Status</span>
                    <div class="flex items-baseline gap-1.5 mt-1">
                        <span class="text-xl font-black text-blue-700">{{ summary.generatedSlips }}</span>
                        <span class="text-xs font-semibold text-slate-400">/ {{ summary.totalEmployees }} Built</span>
                    </div>
                </div>
                <div
                    class="w-10 h-10 rounded-full bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600">
                    <DocumentTextIcon class="w-5 h-5" />
                </div>
            </div>

            <!-- Total Gross Earnings -->
            <div
                class="border border-slate-200 rounded-lg p-4 bg-white shadow-2xs flex justify-between items-center transition-all hover:border-slate-300">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Gross
                        Earnings</span>
                    <span class="text-lg font-black text-slate-800 mt-1 block">{{ formatCurrency(summary.totalEarnings)
                    }}</span>
                </div>
                <div
                    class="w-10 h-10 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600">
                    <BanknotesIcon class="w-5 h-5" />
                </div>
            </div>

            <!-- Total Deductions -->
            <div
                class="border border-slate-200 rounded-lg p-4 bg-white shadow-2xs flex justify-between items-center transition-all hover:border-slate-300">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total
                        Deductions</span>
                    <span class="text-lg font-black text-rose-700 mt-1 block">{{ formatCurrency(summary.totalDeductions)
                    }}</span>
                </div>
                <div
                    class="w-10 h-10 rounded-full bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600">
                    <BanknotesIcon class="w-5 h-5" />
                </div>
            </div>

            <!-- Net Salary Outflow -->
            <div
                class="border border-slate-200 rounded-lg p-4 bg-[#f2f7fc] border-blue-200 shadow-2xs flex justify-between items-center transition-all hover:border-blue-300">
                <div>
                    <span class="text-[10px] font-bold text-[#0064d2] uppercase tracking-wider block">Net Salary
                        Payout</span>
                    <span class="text-xl font-black text-[#0064d2] mt-1 block">{{ formatCurrency(summary.totalNetSalary)
                    }}</span>
                </div>
                <div
                    class="w-10 h-10 rounded-full bg-white border border-blue-200 flex items-center justify-center text-[#0064d2]">
                    <BanknotesIcon class="w-5 h-5" />
                </div>
            </div>
        </div>

        <!-- Table Card Header & Filter Search -->
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-white p-3.5 border border-slate-200 rounded-t-lg border-b-0">
            <div class="flex items-center gap-3">
                <span class="text-xs font-bold text-[#1d2d3e] uppercase tracking-wider">
                    Staff Payslip & Personnel Register
                </span>
                <span
                    class="text-[11px] px-2.5 py-0.5 rounded-full font-bold bg-slate-100 text-slate-600 border border-slate-200">
                    {{ filteredTransactions.length }} Records
                </span>
            </div>

            <div class="flex items-center gap-2.5 w-full sm:w-auto">
                <!-- Search input -->
                <div class="relative w-full sm:w-64">
                    <!-- <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" /> -->
                    <input type="text" v-model="searchQuery" placeholder="Search employee, code, role..."
                        class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-[#0064d2] focus:bg-white text-slate-800 placeholder-slate-400" />
                </div>

                <!-- Go to Payroll Engine shortcut button -->
                <button type="button" @click="openPayrollEngine"
                    class="px-3 py-1.5 text-xs font-bold text-[#0064d2] bg-blue-50 border border-blue-200 hover:bg-blue-100 rounded-md transition-all flex items-center gap-1.5 whitespace-nowrap cursor-pointer shrink-0"
                    title="Open Payroll Generation & Processing in a new tab">
                    <ArrowTopRightOnSquareIcon class="w-3.5 h-3.5" />
                    <span>Payroll Engine</span>
                </button>
            </div>
        </div>

        <!-- Statement Table -->
        <div class="overflow-x-auto border border-slate-200 rounded-b-lg bg-white shadow-2xs">
            <table class="w-full text-left border-collapse min-w-[1050px]">
                <thead>
                    <tr
                        class="text-[10px] font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200 bg-[#f8fafc]">
                        <th class="py-3 px-3.5 text-center" width="4%">#</th>
                        <th class="py-3 px-3.5" width="12%">Emp Code</th>
                        <th class="py-3 px-3.5" width="18%">Employee Name</th>
                        <th class="py-3 px-3.5" width="14%">Department / Role</th>
                        <th class="py-3 px-3.5 text-center" width="12%">Month / Period</th>
                        <th class="py-3 px-3.5 text-center" width="10%">Attendance</th>
                        <th class="py-3 px-3.5 text-right" width="9%">Gross Pay</th>
                        <th class="py-3 px-3.5 text-right" width="9%">Deductions</th>
                        <th class="py-3 px-3.5 text-right" width="9%">Net Salary</th>
                        <th class="py-3 px-3.5 text-center" width="11%">Status</th>
                        <th class="py-3 px-3.5 text-center" width="12%">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-semibold text-slate-700 divide-y divide-slate-100">
                    <tr v-for="(row, idx) in filteredTransactions" :key="idx"
                        class="hover:bg-slate-50/80 transition-colors">
                        <td class="py-3 px-3.5 text-center text-slate-400 font-mono">{{ idx + 1 }}</td>

                        <!-- Employee Code -->
                        <td class="py-3 px-3.5 font-mono font-bold text-slate-700">
                            {{ row.employee_code }}
                        </td>

                        <!-- Name & Contact -->
                        <td class="py-3 px-3.5">
                            <div class="font-bold text-slate-900">{{ row.name }}</div>
                            <div class="text-[10px] text-slate-400 font-normal mt-0.5">
                                {{ row.phone || row.email }}
                            </div>
                        </td>

                        <!-- Dept / Role -->
                        <td class="py-3 px-3.5">
                            <div class="text-slate-800 font-medium">{{ row.designation }}</div>
                            <div class="text-[10px] text-slate-400 font-normal uppercase">{{ row.department }}</div>
                        </td>

                        <!-- Period / Month -->
                        <td class="py-3 px-3.5 text-center">
                            <span v-if="row.has_payslip" class="font-bold text-slate-800">
                                {{ row.period_name }}
                            </span>
                            <span v-else class="text-slate-400 italic text-[10px]">
                                {{ row.period_name }}
                            </span>
                        </td>

                        <!-- Attendance Days -->
                        <td class="py-3 px-3.5 text-center">
                            <div v-if="row.has_payslip" class="inline-flex items-center gap-1.5 text-[10px]">
                                <span
                                    class="text-emerald-700 font-bold bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100"
                                    title="Present Days">
                                    P: {{ row.present_days }}
                                </span>
                                <span v-if="row.paid_leave_days > 0"
                                    class="text-blue-700 font-bold bg-blue-50 px-1.5 py-0.5 rounded border border-blue-100"
                                    title="Paid Leave Days">
                                    L: {{ row.paid_leave_days }}
                                </span>
                                <span v-if="row.absent_days > 0"
                                    class="text-rose-700 font-bold bg-rose-50 px-1.5 py-0.5 rounded border border-rose-100"
                                    title="Absent Days">
                                    A: {{ row.absent_days }}
                                </span>
                            </div>
                            <span v-else class="text-slate-300 text-xs">—</span>
                        </td>

                        <!-- Gross Earnings -->
                        <td class="py-3 px-3.5 text-right font-mono text-slate-700">
                            {{ row.has_payslip ? formatCurrency(row.total_earnings) : '—' }}
                        </td>

                        <!-- Deductions -->
                        <td class="py-3 px-3.5 text-right font-mono text-rose-600">
                            {{ row.has_payslip && row.total_deductions > 0 ? ('- ' +
                                formatCurrency(row.total_deductions)) : (row.has_payslip ? '₹0.00' : '—') }}
                        </td>

                        <!-- Net Salary -->
                        <td class="py-3 px-3.5 text-right font-mono font-bold text-emerald-700">
                            {{ row.has_payslip ? formatCurrency(row.net_salary) : '—' }}
                        </td>

                        <!-- Status Badge -->
                        <td class="py-3 px-3.5 text-center">
                            <span v-if="row.has_payslip" :class="[
                                'px-2.5 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider',
                                row.payslip_status?.toLowerCase() === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                    row.payslip_status?.toLowerCase() === 'approved' ? 'bg-blue-50 text-blue-700 border-blue-200' :
                                        'bg-amber-50 text-amber-700 border-amber-200'
                            ]">
                                {{ row.payslip_status }}
                            </span>
                            <span v-else
                                class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-500 border border-slate-200">
                                Not Generated
                            </span>
                        </td>

                        <!-- Action Buttons -->
                        <td class="py-3 px-3.5 text-center">
                            <div v-if="row.has_payslip" class="flex items-center justify-center gap-1.5">
                                <!-- View PDF in Browser -->
                                <button type="button" @click="viewPayslip(row.payslip_id)"
                                    class="p-1.5 text-slate-600 hover:text-blue-600 hover:bg-blue-50 rounded border border-slate-200 hover:border-blue-200 transition-all cursor-pointer shadow-2xs"
                                    title="View Payslip PDF">
                                    <EyeIcon class="w-3.5 h-3.5" />
                                </button>
                                <!-- Download PDF -->
                                <button type="button" @click="downloadPayslip(row.payslip_id)"
                                    class="p-1.5 text-slate-600 hover:text-emerald-600 hover:bg-emerald-50 rounded border border-slate-200 hover:border-emerald-200 transition-all cursor-pointer shadow-2xs"
                                    title="Download Payslip PDF">
                                    <ArrowDownTrayIcon class="w-3.5 h-3.5" />
                                </button>
                            </div>
                            <div v-else>
                                <button type="button" @click="openPayrollEngine"
                                    class="text-[10px] text-[#0064d2] hover:underline font-bold">
                                    Generate
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="filteredTransactions.length === 0">
                        <td colspan="11" class="py-12 text-center text-slate-400">
                            <DocumentTextIcon class="w-8 h-8 mx-auto mb-2 text-slate-300" />
                            <p class="text-xs font-semibold text-slate-500">No personnel records found for the selected
                                query.</p>
                            <p class="text-[11px] text-slate-400 mt-1">Try selecting a different month, date range, or
                                clear employee filters.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
