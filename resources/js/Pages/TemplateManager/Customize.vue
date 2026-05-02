<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { 
    AdjustmentsHorizontalIcon,
    ArrowLeftIcon,
    CheckCircleIcon,
    DocumentTextIcon,
    EyeIcon,
    PaintBrushIcon
} from '@heroicons/vue/24/outline';
import InputSwitch from 'primevue/inputswitch';
import InputText from 'primevue/inputtext';

const props = defineProps<{
    moduleKey: string;
    moduleName: string;
    initialSettings: any;
}>();

const form = useForm({
    settings: props.initialSettings
});

const activeSection = ref('header'); // header, body, totals, footer

const sections = [
    { id: 'header', name: 'Header & Company', icon: DocumentTextIcon },
    { id: 'body', name: 'Items & Table', icon: AdjustmentsHorizontalIcon },
    { id: 'totals', name: 'Pricing & Taxes', icon: PaintBrushIcon },
    { id: 'footer', name: 'Footer & Legal', icon: PaintBrushIcon },
];

const submit = () => {
    form.post(route('templates.save-customization', props.moduleKey), {
        preserveScroll: true,
        onSuccess: () => {
            // Success notification handled by session flash if configured
        }
    });
};
</script>

<template>
    <AppLayout :title="'Customize ' + moduleName">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('templates.index')" class="p-2 hover:bg-slate-100 rounded-xl transition-colors">
                        <ArrowLeftIcon class="w-5 h-5 text-slate-500" />
                    </Link>
                    <div>
                        <h2 class="text-xl font-black text-slate-800 uppercase tracking-tight">Smart Customization</h2>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Configuring {{ moduleName }} Output</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button 
                        @click="submit"
                        :disabled="form.processing"
                        class="flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-xl shadow-lg shadow-indigo-100 transition-all active:scale-[0.98] group"
                    >
                        <span class="text-xs font-black uppercase tracking-widest">Save Configuration</span>
                        <CheckCircleIcon class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-12 gap-8">
                
                <!-- Sidebar Nav -->
                <div class="col-span-12 lg:col-span-3 space-y-2">
                    <button 
                        v-for="section in sections" 
                        :key="section.id"
                        @click="activeSection = section.id"
                        :class="['w-full flex items-center gap-3 px-4 py-4 rounded-2xl transition-all border', 
                            activeSection === section.id 
                            ? 'bg-white border-indigo-100 shadow-sm text-indigo-600' 
                            : 'bg-transparent border-transparent text-slate-500 hover:bg-slate-50']"
                    >
                        <component :is="section.icon" class="w-5 h-5" />
                        <span class="text-xs font-black uppercase tracking-widest">{{ section.name }}</span>
                    </button>
                </div>

                <!-- Main Content -->
                <div class="col-span-12 lg:col-span-9">
                    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                        
                        <!-- Header Section -->
                        <div v-if="activeSection === 'header'" class="p-8 space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
                                <div class="col-span-2 pb-2 border-bottom border-slate-50">
                                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Issuer Details</h3>
                                </div>
                                
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Company Name</label>
                                        <p class="setting-desc">Show your entity name in header</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.company_name" />
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Logo</label>
                                        <p class="setting-desc">Show branding logo on document</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.logo" />
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Address & Contact</label>
                                        <p class="setting-desc">Full plant address, phone & email</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.address" />
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">GSTIN</label>
                                        <p class="setting-desc">Show Tax Registration Number</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.gstin" />
                                </div>

                                <div class="col-span-2 pt-4 pb-2 border-bottom border-slate-50">
                                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Labels & Titles</h3>
                                </div>

                                <div class="col-span-2 md:col-span-1">
                                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block">Document Title</label>
                                    <InputText v-model="form.settings.pdf.labels.invoice_title" class="w-full" placeholder="e.g. TAX INVOICE" />
                                </div>
                            </div>
                        </div>

                        <!-- Body Section -->
                        <div v-if="activeSection === 'body'" class="p-8 space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
                                <div class="col-span-2 pb-2 border-bottom border-slate-50">
                                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Party Details</h3>
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Bill To Section</label>
                                        <p class="setting-desc">Show Billing Address details</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.bill_to" />
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Ship To Section</label>
                                        <p class="setting-desc">Show Delivery Address details</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.ship_to" />
                                </div>

                                <div class="col-span-2 pt-4 pb-2 border-bottom border-slate-50">
                                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Item Table Columns</h3>
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">HSN Code</label>
                                        <p class="setting-desc">Show HSN/SAC classification</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.hsn_code" />
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Description</label>
                                        <p class="setting-desc">Show line-item description</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.description" />
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Unit of Measure</label>
                                        <p class="setting-desc">Show UOM column (m3, MT, etc)</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.unit" />
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Discount</label>
                                        <p class="setting-desc">Show item-level discount</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.discount" />
                                </div>
                            </div>
                        </div>

                        <!-- Totals Section -->
                        <div v-if="activeSection === 'totals'" class="p-8 space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                             <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
                                <div class="col-span-2 pb-2 border-bottom border-slate-50">
                                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Tax Display</h3>
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Show CGST</label>
                                        <p class="setting-desc">Toggle Central GST split</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.cgst" />
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Show SGST</label>
                                        <p class="setting-desc">Toggle State GST split</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.sgst" />
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Show IGST</label>
                                        <p class="setting-desc">Toggle Integrated GST split</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.igst" />
                                </div>

                                <div class="col-span-2 pt-4 pb-2 border-bottom border-slate-50">
                                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Charges & Totals</h3>
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Shipping Charges</label>
                                        <p class="setting-desc">Show freight/shipping line</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.shipping" />
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Amount in Words</label>
                                        <p class="setting-desc">Convert grand total to words</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.total_words" />
                                </div>
                             </div>
                        </div>

                        <!-- Footer Section -->
                        <div v-if="activeSection === 'footer'" class="p-8 space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                             <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
                                <div class="col-span-2 pb-2 border-bottom border-slate-50">
                                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Legal & Signature</h3>
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Notes</label>
                                        <p class="setting-desc">Show document-specific notes</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.notes" />
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Terms & Conditions</label>
                                        <p class="setting-desc">Show legal terms at bottom</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.terms" />
                                </div>

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <label class="setting-label">Signature Block</label>
                                        <p class="setting-desc">Show Authorized Signatory line</p>
                                    </div>
                                    <InputSwitch v-model="form.settings.pdf.signature" />
                                </div>
                             </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.setting-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 4px;
}

.setting-label {
    display: block;
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #334155;
    margin-bottom: 2px;
}

.setting-desc {
    font-size: 11px;
    color: #94a3b8;
    font-weight: 500;
}

:deep(.p-inputswitch.p-focus .p-inputswitch-slider) {
    box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px #e0e7ff;
}

:deep(.p-inputswitch .p-inputswitch-slider) {
    background: #f1f5f9;
}

:deep(.p-inputswitch.p-inputswitch-checked .p-inputswitch-slider) {
    background: #4f46e5;
}

:deep(.p-inputtext) {
    border-radius: 12px;
    border-color: #f1f5f9;
    font-size: 13px;
    padding: 10px 14px;
    font-weight: 600;
}

:deep(.p-inputtext:focus) {
    border-color: #c7d2fe;
    box-shadow: 0 0 0 4px #f5f3ff;
}
</style>
