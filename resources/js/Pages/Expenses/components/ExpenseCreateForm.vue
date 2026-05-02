<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { 
    BanknotesIcon, 
    CalendarIcon, 
    DocumentTextIcon, 
    TruckIcon,
    UserIcon,
    BuildingOfficeIcon,
    IdentificationIcon,
    ChevronDownIcon,
    ChevronUpIcon
} from '@heroicons/vue/24/outline';

// Components
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    expenseTypes: any[];
    ledgers: any[];
    machines: any[];
    patrons: any[];
}>();

const toast = useToast();
const isOpen = ref(true);

const form = useForm({
    expense_type_id: null,
    paid_through: null,
    amount: 0,
    date: new Date().toISOString().split('T')[0],
    vendor_id: null,
    customer_id: null,
    machine_id: null,
    note: '',
});

const toggle = () => {
    isOpen.value = !isOpen.value;
};

const submit = () => {
    form.post(route('expenses.store'), {
        onSuccess: () => {
            form.reset();
            toast.add({ severity: 'success', summary: 'Recorded', detail: 'Expense synchronized successfully', life: 3000 });
        },
    });
};

</script>

<template>
    <div class="create-panel" :class="{ 'create-panel--open': isOpen }">
        <button class="create-panel__header" @click="toggle" type="button">
            <div class="flex items-center gap-3">
                <div class="create-panel__icon">
                    <BanknotesIcon class="w-5 h-5 text-emerald-600" />
                </div>
                <div class="text-left">
                    <p class="text-xs font-bold text-gray-700 uppercase tracking-widest">Record Expense</p>
                    <p class="text-[11px] text-gray-400 font-medium mt-0.5">Log operational costs, maintenance, and miscellaneous outflows</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <component :is="isOpen ? ChevronUpIcon : ChevronDownIcon" class="w-4 h-4 text-gray-400" />
            </div>
        </button>

        <Transition name="panel-slide">
            <div v-if="isOpen" class="create-panel__body">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                        
                        <!-- Primary Info Section -->
                        <div class="md:col-span-8 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <BaseSelect 
                                    v-model="form.expense_type_id" 
                                    label="Expense Category"
                                    :options="expenseTypes"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="Select Category"
                                    :error="form.errors.expense_type_id"
                                    filter
                                    required
                                >
                                    <template #option="slotProps">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full bg-indigo-400"></div>
                                            <span>{{ slotProps.option.label }}</span>
                                        </div>
                                    </template>
                                </BaseSelect>

                                <BaseSelect 
                                    v-model="form.paid_through" 
                                    label="Payment Source / Ledger"
                                    :options="ledgers"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="Select Ledger"
                                    :error="form.errors.paid_through"
                                    filter
                                    required
                                />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <BaseDatePicker
                                    v-model="form.date"
                                    label="Expense Date"
                                    required
                                    :error="form.errors.date"
                                />
                                
                                <div class="md:col-span-2">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1 px-1">Amount (INR)</label>
                                    <BaseInputNumber 
                                        v-model="form.amount" 
                                        mode="currency" 
                                        currency="INR" 
                                        locale="en-IN"
                                        inputClass="text-lg font-black text-slate-800 !bg-emerald-50/30 !border-emerald-100"
                                        class="w-full"
                                        :error="form.errors.amount"
                                    />
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="text-[10px] uppercase font-black text-slate-400 tracking-widest block mb-2 px-1">Narration / Notes</label>
                                <Textarea 
                                    v-model="form.note" 
                                    rows="3" 
                                    placeholder="Provide details about this transaction..." 
                                    class="w-full text-xs rounded-xl border-slate-200 focus:ring-emerald-500 shadow-sm" 
                                />
                            </div>
                        </div>

                        <!-- Sidebar / Optional Refs -->
                        <div class="md:col-span-4 bg-slate-50/50 rounded-2xl p-6 border border-slate-100 space-y-5">
                            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Entity Association</h3>
                            
                            <BaseSelect 
                                v-model="form.machine_id" 
                                label="Machine / Asset"
                                :options="machines"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="None"
                                :error="form.errors.machine_id"
                                filter
                                showClear
                            >
                                <template #option="slotProps">
                                    <div class="flex items-center gap-2">
                                        <TruckIcon class="w-4 h-4 text-slate-400" />
                                        <span class="text-xs font-bold">{{ slotProps.option.label }}</span>
                                    </div>
                                </template>
                            </BaseSelect>

                            <BaseSelect 
                                v-model="form.vendor_id" 
                                label="Vendor / Payee"
                                :options="patrons"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="None"
                                :error="form.errors.vendor_id"
                                filter
                                showClear
                            />

                            <BaseSelect 
                                v-model="form.customer_id" 
                                label="Rebill to Customer"
                                :options="patrons"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="None"
                                :error="form.errors.customer_id"
                                filter
                                showClear
                            />

                            <div class="pt-4 border-t border-slate-200 mt-4">
                                <BaseFormActions
                                    label="Post Expense"
                                    :loading="form.processing"
                                    @submit="submit"
                                    @reset="form.reset()"
                                    submit-class="w-full !bg-emerald-600 hover:!bg-emerald-700 shadow-lg shadow-emerald-900/10"
                                />
                            </div>
                        </div>

                    </div>
                </form>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.create-panel {
    @apply bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-2xl shadow-indigo-900/5 overflow-hidden transition-all duration-500 ease-in-out;
}
 
.create-panel__header {
    @apply w-full p-4 px-8 bg-gradient-to-r from-slate-50 to-white dark:from-slate-900 dark:to-slate-800 flex justify-between items-center border-b border-slate-100 dark:border-slate-700 hover:bg-slate-100/50 transition-colors;
}
.create-panel__icon {
    @apply w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center shadow-inner;
}
.create-panel__body {
    @apply p-8;
}

/* Panel Slide Animation */
.panel-slide-enter-active, .panel-slide-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.panel-slide-enter-from, .panel-slide-leave-to {
    opacity: 0;
    transform: translateY(-20px);
    max-height: 0;
}
</style>
