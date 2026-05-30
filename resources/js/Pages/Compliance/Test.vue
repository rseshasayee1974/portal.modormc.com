<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import axios from 'axios';
import { 
    CpuChipIcon, 
    ArrowPathIcon, 
    CheckCircleIcon, 
    XCircleIcon,
    CommandLineIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    KeyIcon,
    DocumentDuplicateIcon
} from '@heroicons/vue/24/outline';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    invoices: any[];
    plants: any[];
    defaultCredentials: any;
}>();

const toast = useToast();

const selectedInvoiceId = ref(props.invoices[0]?.id || null);
const credentials = ref({ ...props.defaultCredentials });
const activeTab = ref<'einvoice' | 'ewaybill'>('einvoice');

const isRunning = ref(false);
const testResult = ref<any>(null);
const expandedSteps = ref<Record<number, boolean>>({});

const runTest = async (action: string) => {
    if (!selectedInvoiceId.value) {
        toast.add({ severity: 'warn', summary: 'Select Invoice', detail: 'Please select an invoice to test with.', life: 3000 });
        return;
    }

    isRunning.value = true;
    testResult.value = null;
    expandedSteps.value = {};

    try {
        const response = await axios.post(route('compliance.test-action'), {
            action,
            invoice_id: selectedInvoiceId.value,
            credentials: credentials.value
        });
        testResult.value = {
            success: true,
            message: response.data.message,
            trace: response.data.trace
        };
        toast.add({ severity: 'success', summary: 'Test Passed', detail: response.data.message, life: 3000 });
    } catch (error: any) {
        testResult.value = {
            success: false,
            message: error.response?.data?.message || 'A network error occurred.',
            trace: error.response?.data?.trace || []
        };
        toast.add({ severity: 'error', summary: 'Test Failed', detail: testResult.value.message, life: 4000 });
    } finally {
        isRunning.value = false;
    }
};

const toggleStep = (index: number) => {
    expandedSteps.value[index] = !expandedSteps.value[index];
};

const formatJson = (data: any) => {
    if (!data) return '';
    if (typeof data === 'string') {
        try {
            return JSON.stringify(JSON.parse(data), null, 2);
        } catch {
            return data;
        }
    }
    return JSON.stringify(data, null, 2);
};

const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    toast.add({ severity: 'info', summary: 'Copied', detail: 'Copied to clipboard', life: 1000 });
};
</script>

<template>
    <AppLayout title="Compliance Testing Center">
        <template #header>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-600/10 flex items-center justify-center shadow-inner">
                    <CpuChipIcon class="w-6 h-6 text-indigo-600" />
                </div>
                <div>
                    <h2 class="font-semibold text-xl text-slate-800 leading-tight">Compliance Testing Center</h2>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Sandbox & Production API Terminal</p>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Parameters Config Panel -->
                <div class="lg:col-span-5 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-6">
                    <div class="flex items-center gap-2 pb-4 border-b border-slate-100 dark:border-slate-800">
                        <KeyIcon class="w-5 h-5 text-indigo-600" />
                        <h3 class="text-sm font-black uppercase text-slate-700 dark:text-slate-300 tracking-wider">Test Configuration</h3>
                    </div>

                    <!-- Invoice Selector -->
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black uppercase text-slate-400 tracking-widest block">1. Select Target Invoice</label>
                        <select 
                            v-model="selectedInvoiceId"
                            class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500 bg-slate-50/50 p-3 font-semibold text-slate-700"
                        >
                            <option v-for="inv in invoices" :key="inv.id" :value="inv.id">
                                {{ inv.number }} - {{ inv.customer }} (₹{{ inv.amount.toLocaleString() }}) [{{ inv.status }}]
                            </option>
                        </select>
                    </div>

                    <!-- Config Tabs -->
                    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-900 rounded-lg p-1 w-full border border-slate-200 dark:border-slate-800">
                        <button
                            type="button"
                            @click="activeTab = 'einvoice'"
                            :class="[
                                'flex-grow py-2 text-[10px] font-black uppercase tracking-widest rounded-md transition-all duration-200',
                                activeTab === 'einvoice'
                                    ? 'bg-white dark:bg-slate-800 text-indigo-600 shadow-sm border border-slate-200 dark:border-slate-700'
                                    : 'text-slate-500 hover:text-slate-700'
                            ]"
                        >
                            E-Invoice Credentials
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'ewaybill'"
                            :class="[
                                'flex-grow py-2 text-[10px] font-black uppercase tracking-widest rounded-md transition-all duration-200',
                                activeTab === 'ewaybill'
                                    ? 'bg-white dark:bg-slate-800 text-indigo-600 shadow-sm border border-slate-200 dark:border-slate-700'
                                    : 'text-slate-500 hover:text-slate-700'
                            ]"
                        >
                            E-Way Bill Credentials
                        </button>
                    </div>

                    <!-- GSP E-Invoice parameters -->
                    <div v-show="activeTab === 'einvoice'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider block">GSP Username</label>
                                <input v-model="credentials.einv_username" type="text" class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500" />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider block">GSP Password</label>
                                <input v-model="credentials.einv_password" type="password" class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500" />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider block">GSP Subscription Key</label>
                            <input v-model="credentials.api_key" type="text" class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider block">Seller GSTIN</label>
                                <input v-model="credentials.gstin" type="text" class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500" />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider block">Environment Domain</label>
                                <input v-model="credentials.url" type="text" placeholder="e.g. modostores.local" class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Whitebooks E-Way parameters -->
                    <div v-show="activeTab === 'ewaybill'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider block">Client ID</label>
                                <input v-model="credentials.eway_client_id" type="text" class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500" />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider block">Client Secret</label>
                                <input v-model="credentials.eway_client_secret" type="password" class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500" />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider block">Portal GSTIN</label>
                            <input v-model="credentials.eway_gstin" type="text" class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider block">Portal Email</label>
                                <input v-model="credentials.eway_email" type="email" class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500" />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider block">IP Address</label>
                                <input v-model="credentials.eway_ip" type="text" class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-col gap-2">
                        <Button 
                            @click="runTest('einvoice_generate')"
                            :disabled="isRunning"
                            class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !font-black !text-[10px] !uppercase !tracking-widest !py-3 !rounded-xl !flex !items-center !justify-center !gap-2"
                        >
                            <ArrowPathIcon v-if="isRunning" class="w-4 h-4 animate-spin" />
                            <span>🚀 Generate E-Invoice</span>
                        </Button>
                        
                        <Button 
                            @click="runTest('einvoice_cancel')"
                            :disabled="isRunning"
                            class="!bg-rose-600 hover:!bg-rose-700 !text-white !font-black !text-[10px] !uppercase !tracking-widest !py-3 !rounded-xl !flex !items-center !justify-center !gap-2"
                        >
                            <ArrowPathIcon v-if="isRunning" class="w-4 h-4 animate-spin" />
                            <span>❌ Cancel E-Invoice</span>
                        </Button>

                        <Button 
                            @click="runTest('ewaybill_generate')"
                            :disabled="isRunning"
                            class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !font-black !text-[10px] !uppercase !tracking-widest !py-3 !rounded-xl !flex !items-center !justify-center !gap-2"
                        >
                            <ArrowPathIcon v-if="isRunning" class="w-4 h-4 animate-spin" />
                            <span>🚚 Generate E-Way Bill</span>
                        </Button>
                    </div>
                </div>

                <!-- Test Response & Trace Log Panel -->
                <div class="lg:col-span-7 bg-slate-950 text-slate-100 rounded-2xl p-6 shadow-sm min-h-[500px] border border-slate-900 flex flex-col space-y-6">
                    <div class="flex items-center gap-2 pb-4 border-b border-slate-900 justify-between">
                        <div class="flex items-center gap-2">
                            <CommandLineIcon class="w-5 h-5 text-indigo-400" />
                            <h3 class="text-sm font-black uppercase tracking-wider text-slate-300">Transaction Trace Log</h3>
                        </div>
                        <div v-if="isRunning" class="flex items-center gap-1.5 text-xs text-indigo-400 font-bold animate-pulse">
                            <ArrowPathIcon class="w-3.5 h-3.5 animate-spin" />
                            Running...
                        </div>
                    </div>

                    <!-- Welcome / Default State -->
                    <div v-if="!testResult && !isRunning" class="flex-grow flex flex-col items-center justify-center text-center p-8 space-y-3">
                        <CommandLineIcon class="w-12 h-12 text-slate-700" />
                        <h4 class="text-sm font-bold text-slate-400">Terminal Idle</h4>
                        <p class="text-xs text-slate-600 max-w-sm">Configure parameters and click an action to capture real GSP communication envelopes, signatures, and cryptographic processes.</p>
                    </div>

                    <!-- Loader -->
                    <div v-else-if="isRunning" class="flex-grow flex flex-col items-center justify-center space-y-4">
                        <div class="w-12 h-12 rounded-full border-4 border-indigo-600 border-t-transparent animate-spin"></div>
                        <span class="text-xs font-mono text-indigo-400">Securing tunnel, verifying SEK keys, and calling GSP API...</span>
                    </div>

                    <!-- Results Trace Log -->
                    <div v-else class="space-y-4 flex-grow overflow-y-auto">
                        <!-- Top status card -->
                        <div 
                            :class="[
                                'p-4 rounded-xl border flex items-center gap-3',
                                testResult.success 
                                    ? 'bg-emerald-950/20 border-emerald-900/50 text-emerald-300' 
                                    : 'bg-rose-950/20 border-rose-900/50 text-rose-300'
                            ]"
                        >
                            <component :is="testResult.success ? CheckCircleIcon : XCircleIcon" class="w-6 h-6 flex-shrink-0" />
                            <div>
                                <h4 class="text-xs font-black uppercase tracking-wide">Test {{ testResult.success ? 'Success' : 'Failed' }}</h4>
                                <p class="text-xs text-slate-400 mt-0.5">{{ testResult.message }}</p>
                            </div>
                        </div>

                        <!-- Trace List -->
                        <div class="space-y-3">
                            <div v-for="(step, idx) in testResult.trace" :key="idx" class="border border-slate-900 rounded-xl overflow-hidden bg-slate-900/30">
                                <button 
                                    @click="toggleStep(idx)"
                                    class="w-full flex items-center justify-between p-4 bg-slate-900/50 hover:bg-slate-900/80 text-left transition-colors"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-[10px] font-mono bg-indigo-900/40 text-indigo-300 px-2 py-0.5 rounded">{{ idx + 1 }}</span>
                                        <span class="text-xs font-bold text-slate-300">{{ step.step }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] text-slate-500 font-mono">{{ step.timestamp }}</span>
                                        <component :is="expandedSteps[idx] ? ChevronUpIcon : ChevronDownIcon" class="w-4 h-4 text-slate-500" />
                                    </div>
                                </button>
                                
                                <div v-show="expandedSteps[idx]" class="p-4 border-t border-slate-900 space-y-2">
                                    <div class="flex justify-end">
                                        <button 
                                            @click="copyToClipboard(formatJson(step.data))"
                                            class="text-[9px] font-black uppercase tracking-widest text-indigo-400 hover:text-indigo-300 flex items-center gap-1"
                                        >
                                            <DocumentDuplicateIcon class="w-3.5 h-3.5" />
                                            Copy JSON
                                        </button>
                                    </div>
                                    <pre class="text-[10px] font-mono bg-slate-950 p-4 rounded-xl border border-slate-900 overflow-x-auto max-h-[300px] text-indigo-300/90 leading-relaxed">{{ formatJson(step.data) }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
