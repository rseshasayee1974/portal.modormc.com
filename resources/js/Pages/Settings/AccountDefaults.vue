<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dropdown from 'primevue/dropdown';
import Card from 'primevue/card';
import Message from 'primevue/message';
import Swal from 'sweetalert2';
import { 
    CreditCardIcon, 
    BanknotesIcon, 
    ShoppingCartIcon, 
    DocumentTextIcon,
    ArrowPathIcon,
    ShieldCheckIcon,
    UsersIcon
} from '@heroicons/vue/24/outline';
import { ref, onMounted } from 'vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

const props = defineProps<{
    modules: any[];
    ledgers: any[];
    settings: any[];
}>();

// Structure for the UI
const moduleConfigs = [
    { 
        name: 'Invoice', 
        icon: DocumentTextIcon, 
        color: 'indigo',
        keys: [
            { key: 'sales_account', label: 'Sales Revenue Account', description: 'Main ledger for credit sales revenue' },
            { key: 'cgst_output', label: 'CGST Output Account', description: 'Central GST liability for intra-state sales' },
            { key: 'sgst_output', label: 'SGST Output Account', description: 'State GST liability for intra-state sales' },
            { key: 'igst_output', label: 'IGST Output Account', description: 'Integrated GST liability for inter-state sales' },
            { key: 'shipping_account', label: 'Shipping/Freight Account', description: 'Ledger for recovery of freight charges' },
            { key: 'round_off_account', label: 'Round Off Account', description: 'Ledger for rounding adjustments' },
            { key: 'adjustment_account', label: 'Other Adjustment Account', description: 'Ledger for manual price adjustments' },
            { key: 'tds_receivable', label: 'TDS Receivable Account', description: 'Ledger for TDS deducted by customers' },
        ]
    },
    { 
        name: 'Purchase', 
        icon: ShoppingCartIcon, 
        color: 'emerald',
        keys: [
            { key: 'purchase_account', label: 'Purchase Expense Account', description: 'Main ledger for raw material purchases' },
            { key: 'cgst_input', label: 'CGST Input Account', description: 'Central GST credit for intra-state purchases' },
            { key: 'sgst_input', label: 'SGST Input Account', description: 'State GST credit for intra-state purchases' },
            { key: 'igst_input', label: 'IGST Input Account', description: 'Integrated GST credit for inter-state purchases' },
            { key: 'shipping_account', label: 'Shipping/Freight Account', description: 'Ledger for freight expenses paid' },
            { key: 'round_off_account', label: 'Round Off Account', description: 'Ledger for rounding adjustments' },
            { key: 'tds_payable', label: 'TDS Payable Account', description: 'Ledger for TDS deducted from vendors' },
        ]
    },
    { 
        name: 'Payment', 
        icon: BanknotesIcon, 
        color: 'rose',
        keys: [
            { key: 'cash_account', label: 'Default Cash Account', description: 'Primary cash-in-hand ledger' },
            { key: 'bank_account', label: 'Default Bank Account', description: 'Primary business bank ledger' },
            { key: 'discount_allowed', label: 'Discount Allowed', description: 'Expense ledger for cash discounts given' },
        ]
    },
    { 
        name: 'Receipt', 
        icon: CreditCardIcon, 
        color: 'amber',
        keys: [
            { key: 'cash_account', label: 'Default Cash Account', description: 'Primary cash-in-hand ledger' },
            { key: 'bank_account', label: 'Default Bank Account', description: 'Primary business bank ledger' },
            { key: 'discount_received', label: 'Discount Received', description: 'Income ledger for cash discounts received' },
        ]
    },
    { 
        name: 'Patron', 
        icon: UsersIcon, 
        color: 'cyan',
        keys: [
            { key: 'debit_ledger', label: 'Default Debit Ledger (Customers)', description: 'Default group for new customers (Sundry Debtors)' },
            { key: 'credit_ledger', label: 'Default Credit Ledger (Vendors)', description: 'Default group for new vendors (Sundry Creditors)' },
        ]
    }
];

// Initialize form data
const initialSettings = [];
moduleConfigs.forEach(mc => {
    const moduleObj = props.modules.find(m => m.module_name.toLowerCase() === mc.name.toLowerCase());
    if (moduleObj) {
        mc.keys.forEach(k => {
            const existing = props.settings.find(s => s.module_id === moduleObj.id && s.setting_key === k.key);
            initialSettings.push({
                module_id: moduleObj.id,
                setting_key: k.key,
                ledger_id: existing ? existing.ledger_id : null
            });
        });
    }
});

const form = useForm({
    settings: initialSettings
});

const getLedgerId = (moduleId, key) => {
    const item = form.settings.find(s => s.module_id === moduleId && s.setting_key === key);
    return item ? item.ledger_id : null;
};

const setLedgerId = (moduleId, key, value) => {
    const index = form.settings.findIndex(s => s.module_id === moduleId && s.setting_key === key);
    if (index !== -1) {
        form.settings[index].ledger_id = value;
    }
};

const submit = () => {
    form.post(route('settings.account-defaults.store'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Accounting mappings updated',
                showConfirmButton: false,
                timer: 1500
            });
        }
    });
};

const getModuleId = (name) => {
    return props.modules.find(m => m.module_name.toLowerCase() === name.toLowerCase())?.id;
};

</script>

<template>
    <AppLayout title="Default Ledger Settings">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Action Bar -->
                <div class="flex items-center justify-between mb-8 bg-white dark:bg-slate-900 p-6 rounded-[1.5rem] shadow-sm border border-slate-100 dark:border-slate-800">
                    <div>
                        <h2 class="font-black text-2xl text-gray-800 dark:text-gray-100 uppercase tracking-tighter">
                            Accounting Mappings
                        </h2>
                        <p class="text-xs text-slate-500 font-medium uppercase tracking-widest mt-1">Configure default ledgers for automated journal entries</p>
                    </div>
                    <Button 
                        @click="submit" 
                        icon="pi pi-save" 
                        label="Sync All Mappings" 
                        class="p-button-raised p-button-indigo shadow-lg shadow-indigo-500/20 uppercase tracking-widest font-black text-xs px-6 h-12" 
                        :loading="form.processing"
                    />
                </div>

                <Message severity="info" class="mb-8 shadow-sm" :closable="false">
                    <div class="flex items-center gap-2">
                        <ShieldCheckIcon class="w-5 h-5" />
                        <span>These settings are used by the system to automatically post transactions to the General Ledger.</span>
                    </div>
                </Message>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    
                    <template v-for="mc in moduleConfigs" :key="mc.name">
                        <Card v-if="getModuleId(mc.name)" class="shadow-xl border-t-4 transition-all duration-300 hover:shadow-2xl" :class="`border-${mc.color}-500`">
                            <template #title>
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg" :class="`bg-${mc.color}-50 text-${mc.color}-600`">
                                        <component :is="mc.icon" class="w-6 h-6" />
                                    </div>
                                    <span class="text-xl font-bold text-slate-800">{{ mc.name }} Module Defaults</span>
                                </div>
                            </template>
                            <template #content>
                                <div class="space-y-6 mt-4">
                                    <div v-for="k in mc.keys" :key="k.key" class="flex flex-col gap-2 p-4 rounded-xl bg-slate-50 border border-slate-100 hover:border-slate-200 transition-colors">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <label class="text-sm font-bold text-slate-700 block">{{ k.label }}</label>
                                                <p class="text-[11px] text-slate-400 font-medium">{{ k.description }}</p>
                                            </div>
                                            <span class="text-[10px] bg-white border border-slate-200 text-slate-400 px-2 py-0.5 rounded font-mono uppercase">{{ k.key }}</span>
                                        </div>
                                        
                                        <Dropdown 
                                            :modelValue="getLedgerId(getModuleId(mc.name), k.key)"
                                            @update:modelValue="(val) => setLedgerId(getModuleId(mc.name), k.key, val)"
                                            :options="ledgers" 
                                            optionLabel="title" 
                                            optionValue="id" 
                                            placeholder="Select Ledger Account" 
                                            class="w-full text-sm border-slate-200"
                                            filter
                                            showClear
                                        >
                                            <template #option="slotProps">
                                                <div class="flex flex-col">
                                                    <span class="font-semibold">{{ slotProps.option.title }}</span>
                                                    <span class="text-[10px] text-slate-400 font-mono">{{ slotProps.option.code }}</span>
                                                </div>
                                            </template>
                                        </Dropdown>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </template>

                </div>

                <div class="mt-12 flex justify-center pb-12">
                     <Button 
                        @click="submit" 
                        icon="pi pi-check-circle" 
                        label="Save All Mappings" 
                        class="p-button-lg p-button-raised p-button-indigo px-8 shadow-xl" 
                        :loading="form.processing"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.p-card {
    border-radius: 1.5rem;
    overflow: hidden;
}

:deep(.p-dropdown) {
    border-radius: 0.75rem;
}

:deep(.p-dropdown-panel) {
    border-radius: 0.75rem;
}

/* Tailwind safelist-like patterns since we are using dynamic classes */
.border-indigo-500 { border-color: rgb(99 102 241); }
.bg-indigo-50 { background-color: rgb(238 242 255); }
.text-indigo-600 { color: rgb(79 70 229); }

.border-emerald-500 { border-color: rgb(16 185 129); }
.bg-emerald-50 { background-color: rgb(236 253 245); }
.text-emerald-600 { color: rgb(5 150 105); }

.border-rose-500 { border-color: rgb(244 63 94); }
.bg-rose-50 { background-color: rgb(255 241 242); }
.text-rose-600 { color: rgb(225 29 72); }

.border-amber-500 { border-color: rgb(245 158 11); }
.bg-amber-50 { background-color: rgb(255 251 235); }
.text-amber-600 { color: rgb(217 119 6); }

.border-cyan-500 { border-color: rgb(6 182 212); }
.bg-cyan-50 { background-color: rgb(236 254 255); }
.text-cyan-600 { color: rgb(8 145 178); }
</style>
