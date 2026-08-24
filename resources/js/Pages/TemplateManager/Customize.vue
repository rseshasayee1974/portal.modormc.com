<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    ArrowLeftIcon,
    CheckCircleIcon,
    PrinterIcon,
    ArrowDownTrayIcon,
    SwatchIcon,
    BuildingOfficeIcon,
    TableCellsIcon,
    BanknotesIcon,
    DocumentTextIcon,
    SparklesIcon,
    CheckIcon,
    MagnifyingGlassPlusIcon,
    MagnifyingGlassMinusIcon
} from '@heroicons/vue/24/outline';
import InputSwitch from 'primevue/inputswitch';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';

const props = defineProps<{
    moduleKey: string;
    moduleName: string;
    initialSettings: any;
    templateSettingsMap?: Record<string, any>;
    assignedTemplateKey?: string;
    availableTemplates?: string[];
    dummyData?: any;
}>();

const selectedTemplateKey = ref(props.assignedTemplateKey || 'standard');

const designs = [
    { id: 'standard', name: 'Standard', description: 'Classic bordered grid layout with comprehensive details' },
    { id: 'box_layout', name: '3-Block Box Layout', description: 'Exact 3-block bordered tax invoice format with plant & company details' },
    { id: 'elite', name: 'Elite', description: 'Two-section modern corporate format with split metadata' },
    { id: 'modern', name: 'Modern', description: 'Clean borderless layout with subtle slate accents' },
    { id: 'compact', name: 'Compact', description: 'Ultra-dense minimal invoice format for quick receipts' },
    { id: 'indian_gst', name: 'Indian GST', description: 'Full GST-compliant layout with HSN & tax column splits' },
    { id: 'spreadsheet', name: 'Spreadsheet', description: 'Grid-based inventory disbursement & dispatch layout' },
    { id: 'tallysheet', name: 'Tally Sheet', description: 'Ledger-style statement layout for debit/credit tracking' },
];

const availableDesignList = computed(() => {
    if (props.availableTemplates && props.availableTemplates.length > 0) {
        return designs.filter(d => props.availableTemplates?.includes(d.id));
    }
    return designs;
});

// Helper to ensure nested pdf settings exist
const ensurePdfStructure = (rawSettings: any) => {
    const s = JSON.parse(JSON.stringify(rawSettings || {}));
    if (!s.pdf) s.pdf = {};
    if (!s.pdf.labels) s.pdf.labels = {};
    if (s.pdf.terms_text === undefined) {
        s.pdf.terms_text = "1. Payment within 15 days of invoice date.\n2. Goods once sold will not be returned.\n3. All disputes subject to local jurisdiction.";
    }
    if (s.pdf.show_pump_charges === undefined || s.pdf.show_pump_charges === null) {
        s.pdf.show_pump_charges = true;
    } else {
        s.pdf.show_pump_charges = s.pdf.show_pump_charges === 1 || s.pdf.show_pump_charges === true || s.pdf.show_pump_charges === '1';
    }
    if (s.pdf.show_bank_details === undefined) s.pdf.show_bank_details = true;
    if (s.pdf.show_einvoice_details === undefined) s.pdf.show_einvoice_details = true;
    if (s.pdf.show_customer_ref === undefined) s.pdf.show_customer_ref = true;
    if (s.pdf.show_carrier_driver === undefined) s.pdf.show_carrier_driver = true;
    if (s.pdf.show_seal_signature === undefined) s.pdf.show_seal_signature = true;
    return s;
};

// Initialize shared settings object
const sharedSettings = ref(ensurePdfStructure(props.initialSettings));
const templateSettingsMap = ref<Record<string, any>>({});

availableDesignList.value.forEach(d => {
    templateSettingsMap.value[d.id] = sharedSettings.value;
});

// Current active settings object (shared across all designs)
const activeSettings = computed({
    get: () => sharedSettings.value,
    set: (val) => {
        sharedSettings.value = val;
        availableDesignList.value.forEach(d => {
            templateSettingsMap.value[d.id] = val;
        });
    }
});

const form = useForm({
    settings: sharedSettings.value,
    template_key: selectedTemplateKey.value,
    template_settings_map: templateSettingsMap.value
});

const activeSection = ref('layout'); // layout, header, body, totals, footer
const currentZoom = ref(85);
const previewHtml = ref('');
const isLoadingPreview = ref(false);

let fetchTimer: any = null;


const refreshPreview = () => {
    if (fetchTimer) clearTimeout(fetchTimer);
    fetchTimer = setTimeout(() => {
        isLoadingPreview.value = true;
        axios.post(route('templates.preview-render', props.moduleKey), {
            settings: activeSettings.value,
            template_key: selectedTemplateKey.value
        }).then(res => {
            previewHtml.value = res.data;
        }).catch(err => {
            console.error('Preview render error', err);
        }).finally(() => {
            isLoadingPreview.value = false;
        });
    }, 120);
};

watch([activeSettings, selectedTemplateKey], () => {
    refreshPreview();
}, { deep: true });

onMounted(() => {
    refreshPreview();
});

const sections = [
    { id: 'layout', name: 'Layout', icon: SwatchIcon },
    { id: 'header', name: 'Header', icon: BuildingOfficeIcon },
    { id: 'body', name: 'Columns', icon: TableCellsIcon },
    { id: 'totals', name: 'Totals', icon: BanknotesIcon },
    { id: 'footer', name: 'Terms', icon: DocumentTextIcon },
];

const submit = () => {
    form.template_key = selectedTemplateKey.value;
    form.settings = activeSettings.value;
    form.template_settings_map = templateSettingsMap.value;
    console.log('asdsd',);
    
    form.post(route('templates.save-customization', props.moduleKey), {
        preserveScroll: true,
        onSuccess: () => {
            refreshPreview();
        }
    });
};

const printTest = () => {
    const url = `/print/${props.moduleKey}/dummy/view?template=${selectedTemplateKey.value}`;
    window.open(url, '_blank');
};

const downloadSample = () => {
    const url = `/print/${props.moduleKey}/dummy/download?template=${selectedTemplateKey.value}`;
    window.location.href = url;
};
</script>

<template>
    <AppLayout :title="'Live Customizer - ' + moduleName">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Link :href="route('templates.index')" class="p-2 bg-white border border-slate-200 hover:bg-slate-50 rounded-xl transition-all shadow-sm">
                        <ArrowLeftIcon class="w-5 h-5 text-slate-600" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight">Template Builder</h2>
                            <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-widest bg-indigo-100 text-indigo-700 rounded-full flex items-center gap-1">
                                <SparklesIcon class="w-3 h-3" />
                                Real-Time Engine
                            </span>
                        </div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Customizing {{ moduleName }} Output • Design: {{ selectedTemplateKey.toUpperCase() }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <button @click="printTest" type="button" class="action-btn">
                        <PrinterIcon class="w-4 h-4 text-slate-600" />
                        <span>Print Test</span>
                    </button>
                    <button @click="downloadSample" type="button" class="action-btn">
                        <ArrowDownTrayIcon class="w-4 h-4 text-slate-600" />
                        <span>Download PDF</span>
                    </button>
                    <button 
                        @click="submit"
                        :disabled="form.processing"
                        class="flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-xl shadow-md transition-all active:scale-[0.98]"
                    >
                        <span class="text-xs font-black uppercase tracking-widest">Save Configuration</span>
                        <CheckCircleIcon class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-[1650px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-12 gap-8 items-start">
                
                <!-- ═══ LEFT PANEL: CONTROLS (5 cols) ═══ -->
                <div class="col-span-12 lg:col-span-5 space-y-6">
                    
                    <!-- Sleek Category Tabs -->
                    <div class="bg-slate-200/60 p-1.5 rounded-2xl flex items-center gap-1 overflow-x-auto custom-scrollbar">
                        <button 
                            v-for="section in sections" 
                            :key="section.id"
                            @click="activeSection = section.id"
                            :class="['flex-1 flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl transition-all whitespace-nowrap', 
                                activeSection === section.id 
                                ? 'bg-white shadow-sm font-black text-indigo-600' 
                                : 'text-slate-600 hover:text-slate-900 font-bold']"
                        >
                            <component :is="section.icon" class="w-4 h-4" />
                            <span class="text-[11px] uppercase tracking-wider">{{ section.name }}</span>
                        </button>
                    </div>

                    <!-- Main Settings Form Container -->
                    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 space-y-6">
                        
                        <!-- 1. LAYOUT SECTION -->
                        <div v-if="activeSection === 'layout'" class="space-y-4 animate-in fade-in duration-300">
                            <div class="border-b border-slate-100 pb-3">
                                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Select Active Design Template</h3>
                                <p class="text-[11px] text-slate-400 font-medium">All design templates share the same custom print settings</p>
                            </div>

                            <div class="grid grid-cols-1 gap-3">
                                <button 
                                    v-for="d in availableDesignList" 
                                    :key="d.id"
                                    type="button"
                                    @click="selectedTemplateKey = d.id"
                                    :class="['w-full text-left p-4 rounded-2xl border transition-all relative',
                                        selectedTemplateKey === d.id 
                                        ? 'bg-indigo-50/50 border-indigo-500 ring-2 ring-indigo-500/20 shadow-sm' 
                                        : 'bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50/50']"
                                >
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs font-black uppercase tracking-tight" :class="selectedTemplateKey === d.id ? 'text-indigo-700' : 'text-slate-800'">
                                            {{ d.name }}
                                        </span>
                                        <div v-if="selectedTemplateKey === d.id" class="flex items-center gap-1 bg-indigo-600 text-white px-2 py-0.5 rounded-full text-[9px] font-black uppercase">
                                            <CheckIcon class="w-3 h-3 stroke-[3]" /> Active
                                        </div>
                                    </div>
                                    <p class="text-[11px] text-slate-500 font-medium leading-relaxed">{{ d.description }}</p>
                                </button>
                            </div>
                        </div>

                        <!-- 2. HEADER & BRANDING SECTION -->
                        <div v-if="activeSection === 'header'" class="space-y-6 animate-in fade-in duration-300">
                            <div class="border-b border-slate-100 pb-3">
                                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Header & Company Branding</h3>
                                <p class="text-[11px] text-slate-400 font-medium">Configuring settings for <strong class="text-indigo-600 uppercase">{{ selectedTemplateKey }}</strong> design</p>
                            </div>

                            <div class="space-y-4">
                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Company Name</label>
                                        <p class="setting-desc">Display entity name in template header</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.company_name" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Company Logo</label>
                                        <p class="setting-desc">Display brand logo on printable header</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.logo" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Full Address</label>
                                        <p class="setting-desc">Show registered address & plant location</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.address" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Company GSTIN</label>
                                        <p class="setting-desc">Show tax registration number in header</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.gstin" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Phone & Email</label>
                                        <p class="setting-desc">Include contact phone and support email</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.phone" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Bank Details Block</label>
                                        <p class="setting-desc">Show plant bank account details in invoice</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.show_bank_details" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">e-Invoice & IRN Meta</label>
                                        <p class="setting-desc">Show IRN, Ack No/Date & E-Way bill info</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.show_einvoice_details" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Document Reference #</label>
                                        <p class="setting-desc">Show document number e.g. INV-2026-001</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.invoice_number" />
                                </div>

                                <div class="pt-2">
                                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1.5 block">Custom Document Title ({{ selectedTemplateKey.toUpperCase() }})</label>
                                    <InputText v-model="activeSettings.pdf.labels.invoice_title" class="w-full" placeholder="e.g. TAX INVOICE, QUOTATION" />
                                </div>
                            </div>
                        </div>

                        <!-- 3. ITEMS & COLUMNS SECTION -->
                        <div v-if="activeSection === 'body'" class="space-y-6 animate-in fade-in duration-300">
                            <div class="border-b border-slate-100 pb-3">
                                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Party Details & Table Columns</h3>
                                <p class="text-[11px] text-slate-400 font-medium">Configuring settings for <strong class="text-indigo-600 uppercase">{{ selectedTemplateKey }}</strong> design</p>
                            </div>

                            <div class="space-y-4">
                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Bill To Address Block</label>
                                        <p class="setting-desc">Show billing party name, address & GSTIN</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.bill_to" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Ship To Address Block</label>
                                        <p class="setting-desc">Show delivery site & shipping address</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.ship_to" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">HSN / SAC Code Column</label>
                                        <p class="setting-desc">Display HSN classification column</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.hsn_code" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Item Description Column</label>
                                        <p class="setting-desc">Show detailed item description text</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.description" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Unit of Measure (UOM)</label>
                                        <p class="setting-desc">Display unit column (m³, MT, Nos)</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.unit" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Item Discount Column</label>
                                        <p class="setting-desc">Show item-level discount column</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.discount" />
                                </div>

                                <!-- <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Pump Rates Breakdown</label>
                                        <p class="setting-desc">Display pumping & piping rate breakdown</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.pump_rates" />
                                </div> -->

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Customer Ref & Metadata</label>
                                        <p class="setting-desc">Show Acc No, PO, Sales Person, Pump, Design Mix Ref</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.show_customer_ref" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Carrier & Driver Info</label>
                                        <p class="setting-desc">Show transport, vehicle registration & driver details</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.show_carrier_driver" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Operation & Pump Columns</label>
                                        <p class="setting-desc">Display Concrete Type and Pump Charges columns in items table</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.show_pump_charges" />
                                </div>
                            </div>
                        </div>

                        <!-- 4. TAXES & TOTALS SECTION -->
                        <div v-if="activeSection === 'totals'" class="space-y-6 animate-in fade-in duration-300">
                            <div class="border-b border-slate-100 pb-3">
                                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Taxes & Pricing Breakdown</h3>
                                <p class="text-[11px] text-slate-400 font-medium">Configuring settings for <strong class="text-indigo-600 uppercase">{{ selectedTemplateKey }}</strong> design</p>
                            </div>

                            <div class="space-y-4">
                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Central GST (CGST)</label>
                                        <p class="setting-desc">Show CGST tax split line in totals</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.cgst" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">State GST (SGST)</label>
                                        <p class="setting-desc">Show SGST tax split line in totals</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.sgst" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Integrated GST (IGST)</label>
                                        <p class="setting-desc">Show IGST tax line for interstate sales</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.igst" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Freight / Shipping Charges</label>
                                        <p class="setting-desc">Show shipping & delivery line item</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.shipping" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Amount in Words</label>
                                        <p class="setting-desc">Convert grand total into words format</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.total_words" />
                                </div>
                            </div>
                        </div>

                        <!-- 5. FOOTER & TERMS SECTION -->
                        <div v-if="activeSection === 'footer'" class="space-y-6 animate-in fade-in duration-300">
                            <div class="border-b border-slate-100 pb-3">
                                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Legal Terms & Signatures</h3>
                                <p class="text-[11px] text-slate-400 font-medium">Configuring settings for <strong class="text-indigo-600 uppercase">{{ selectedTemplateKey }}</strong> design</p>
                            </div>

                            <div class="space-y-4">
                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Document Notes</label>
                                        <p class="setting-desc">Show document-specific special notes</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.notes" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Terms & Conditions Block</label>
                                        <p class="setting-desc">Show terms & conditions section in footer</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.terms" />
                                </div>

                                <div v-if="activeSettings.pdf.terms !== false" class="pt-2">
                                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1.5 block">Custom Terms & Conditions Text ({{ selectedTemplateKey.toUpperCase() }})</label>
                                    <Textarea v-model="activeSettings.pdf.terms_text" rows="4" class="w-full text-xs font-medium" placeholder="Enter terms & conditions line by line..." />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Authorized Signatory Line</label>
                                        <p class="setting-desc">Show company signature placeholder</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.signature" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">Plant Seal & Digital Signature</label>
                                        <p class="setting-desc">Display uploaded seal and signature image on footer</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.show_seal_signature" />
                                </div>

                                <div class="setting-row">
                                    <div>
                                        <label class="setting-title">UPI Payment QR Code</label>
                                        <p class="setting-desc">Show payment QR code on print template</p>
                                    </div>
                                    <InputSwitch v-model="activeSettings.pdf.upi_qr" />
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- ═══ RIGHT PANEL: EXACT BLADE HTML IFRAME PREVIEW (7 cols) ═══ -->
                <div class="col-span-12 lg:col-span-7">
                    <div class="bg-slate-900/5 backdrop-blur-md rounded-3xl border border-slate-200/80 p-6 shadow-inner flex flex-col items-center sticky top-24">
                        
                        <!-- Toolbar -->
                        <div class="w-full flex items-center justify-between mb-6 bg-white p-3 rounded-2xl border border-slate-100 shadow-sm">
                            <div class="flex items-center gap-2">
                                <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></div>
                                <span class="text-[10px] font-black text-slate-700 uppercase tracking-widest">Blade Print Engine ({{ selectedTemplateKey.toUpperCase() }})</span>
                                <span v-if="isLoadingPreview" class="text-[10px] text-slate-400 font-bold animate-pulse">(Updating...)</span>
                            </div>

                            <!-- Scale Zoom -->
                            <div class="flex items-center gap-2 bg-slate-100 px-3 py-1 rounded-xl">
                                <button type="button" @click="currentZoom = Math.max(50, currentZoom - 5)" class="text-slate-600 hover:text-slate-900 font-bold p-1">
                                    <MagnifyingGlassMinusIcon class="w-3.5 h-3.5" />
                                </button>
                                <span class="text-[10px] font-black text-slate-700 w-10 text-center">{{ currentZoom }}%</span>
                                <button type="button" @click="currentZoom = Math.min(130, currentZoom + 5)" class="text-slate-600 hover:text-slate-900 font-bold p-1">
                                    <MagnifyingGlassPlusIcon class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Scalable Frame Canvas -->
                        <div class="w-full overflow-auto max-h-[820px] flex justify-center py-4 custom-scrollbar">
                            <div 
                                class="a4-canvas transition-all duration-300 origin-top shadow-2xl rounded-sm bg-white"
                                :style="{ transform: `scale(${currentZoom / 100})`, marginBottom: `${(100 - currentZoom) * -2.5}px` }"
                            >
                                <iframe 
                                    :srcdoc="previewHtml" 
                                    sandbox="allow-same-origin allow-forms"
                                    class="w-full h-full border-0 min-h-[297mm]"
                                ></iframe>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.setting-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 0;
}

.setting-title {
    display: block;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #1e293b;
}

.setting-desc {
    font-size: 10.5px;
    color: #94a3b8;
    font-weight: 500;
}

.a4-canvas {
    width: 210mm;
    min-height: 297mm;
    background: white;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 9px 16px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #334155;
    transition: all 0.2s;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.action-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    transform: translateY(-1px);
}

:deep(.p-inputswitch .p-inputswitch-slider) {
    background: #e2e8f0;
}

:deep(.p-inputswitch.p-inputswitch-checked .p-inputswitch-slider) {
    background: #4f46e5;
}

:deep(.p-inputtext), :deep(.p-inputtextarea) {
    border-radius: 12px;
    border-color: #e2e8f0;
    font-size: 12px;
    padding: 10px 14px;
    font-weight: 600;
}

:deep(.p-inputtext:focus), :deep(.p-inputtextarea:focus) {
    border-color: #818cf8;
    box-shadow: 0 0 0 4px #e0e7ff;
}

.custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>
