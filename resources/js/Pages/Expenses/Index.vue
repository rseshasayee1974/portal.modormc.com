<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import DatePicker from 'primevue/datepicker';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import ExpenseCreateForm from './components/ExpenseCreateForm.vue';
import Swal from 'sweetalert2';

const props = defineProps<{
    expenses: any[];
    expenseTypes: any[];
    ledgers: any[];
    machines: any[];
    patrons: any[];
}>();

const toast = useToast();

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(val);
};

</script>

<template>
    <AppLayout title="Operational Expenses">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Toast />

        <div class="min-h-screen py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 space-y-8">
                
                <!-- Creation Form Section -->
                <section>
                    <ExpenseCreateForm 
                        :expense-types="expenseTypes"
                        :ledgers="ledgers"
                        :machines="machines"
                        :patrons="patrons"
                    />
                </section>

                <section class="space-y-4">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <BaseDataTable
                            :value="expenses"
                            v-model:filters="filters"
                            :globalFilterFields="['ref_no', 'note', 'expense_type.name', 'vendor.legal_name']"
                            showSearch
                            showSerial
                            heading="Expense History"
                            headingIcon="CreditCardIcon"
                            :rows="15"
                        >
                            
                            <Column field="date" header="Date" sortable>
                                <template #body="slotProps">
                                    <span class="text-xs font-bold text-slate-600">{{ new Date(slotProps.data.date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) }}</span>
                                </template>
                            </Column>

                            <Column field="ref_no" header="Reference" sortable>
                                <template #body="slotProps">
                                    <span class="text-[11px] font-mono font-black text-indigo-600">{{ slotProps.data.ref_no }}</span>
                                </template>
                            </Column>

                            <Column field="expense_type.name" header="Category" sortable>
                                <template #body="slotProps">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-700 text-sm">{{ slotProps.data.expense_type?.name }}</span>
                                        <span class="text-[10px] text-slate-400 uppercase font-black">{{ slotProps.data.machine?.registration || 'General Plant' }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="vendor.legal_name" header="Payee">
                                <template #body="slotProps">
                                    <span class="text-xs text-slate-500 font-medium">{{ slotProps.data.vendor?.legal_name || slotProps.data.customer?.legal_name || '-' }}</span>
                                </template>
                            </Column>

                            <Column field="amount" header="Amount" sortable class="text-right">
                                <template #body="slotProps">
                                    <span class="font-black text-slate-800">{{ formatCurrency(slotProps.data.amount) }}</span>
                                </template>
                            </Column>

                            <Column header="Status">
                                <template #body>
                                    <Tag value="Recorded" severity="success" pt:root:style="font-size: 8px" />
                                </template>
                            </Column>
                        </BaseDataTable>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

