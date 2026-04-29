<script setup lang="ts">
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { 
    XMarkIcon, 
    PrinterIcon, 
    ArrowDownTrayIcon,
    AdjustmentsVerticalIcon,
    ChevronDownIcon,
    CheckIcon
} from '@heroicons/vue/24/outline';

// Import Separated Template Components
import Standard from './components/Standard.vue';
import Elite from './components/Elite.vue';
import Modern from './components/Modern.vue';
import Spreadsheet from './components/Spreadsheet.vue';
import TallySheet from './components/TallySheet.vue';
import Compact from './components/Compact.vue';
import IndianGst from './components/IndianGst.vue';

const props = defineProps<{
    template: any;
    dummyData: any;
}>();

const printTest = () => {
    // Open the printable view in a new tab
    const url = `/settings/print/purchase_orders/1/view?template=${selectedDesignId.value}`;
    window.open(url, '_blank');
};

const downloadSample = () => {
    // Trigger a download
    const url = `/settings/print/purchase_orders/1/download?template=${selectedDesignId.value}`;
    window.location.href = url;
};

const designs = [
    {
        id: 'standard',
        name: 'Standard',
        component: Standard,
        description: 'Classic 3-column bordered invoice layout',
        category: 'invoice'
    },
    {
        id: 'elite',
        name: 'Elite',
        component: Elite,
        description: 'Two-section layout — details bar + Bill To/Ship To block',
        category: 'invoice'
    },
    {
        id: 'modern',
        name: 'Modern',
        component: Modern,
        description: 'Borderless design — dark header bars, right-only totals',
        category: 'invoice'
    },
    {
        id: 'spreadsheet',
        name: 'Spreadsheet',
        component: Spreadsheet,
        description: 'Grid-based inventory disbursement sheet',
        category: 'inventory'
    },
    {
        id: 'tallysheet',
        name: 'Tally Sheet',
        component: TallySheet,
        description: 'Ledger-style debit/credit account statement',
        category: 'statement'
    },
    {
        id: 'compact',
        name: 'Compact',
        component: Compact,
        description: 'Ultra-dense minimal invoice format',
        category: 'invoice'
    },
    {
        id: 'indian_gst',
        name: 'Indian GST',
        component: IndianGst,
        description: 'GST-compliant with HSN codes, CGST/SGST columns',
        category: 'gst'
    }
];

const currentZoom = ref(100);

// Auto-select the component matching the DB template key
const selectedDesignId = ref(
    designs.some(d => d.id === props.template?.key)
        ? props.template.key
        : 'standard'
);

const activeDesign = computed(() => {
    return designs.find(d => d.id === selectedDesignId.value) || designs[0];
});

const showDesignSelector = ref(false);
</script>

<template>
    <AppLayout :title="'Preview: ' + template.name">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('templates.index')" class="p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
                        <XMarkIcon class="w-5 h-5 text-slate-500" />
                    </Link>
                    <div>
                        <h2 class="text-xl font-black text-slate-800 uppercase tracking-tight">Design Preview</h2>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Template: {{ template.name }} • {{ activeDesign.name }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Design Selector Dropdown -->
                    <div class="relative">
                        <button @click="showDesignSelector = !showDesignSelector" 
                                class="action-btn flex items-center gap-2 border-indigo-200 shadow-sm shadow-indigo-100">
                            <AdjustmentsVerticalIcon class="w-4 h-4 text-indigo-500" />
                            <span class="text-[10px] font-black uppercase text-indigo-600">Switch Layout</span>
                            <ChevronDownIcon class="w-3 h-3 text-indigo-400 ml-1" />
                        </button>
                        
                        <div v-if="showDesignSelector" 
                             class="absolute right-0 mt-2 w-80 bg-white rounded-2xl shadow-2xl border border-slate-100 z-50 p-2 animate-in fade-in slide-in-from-top-2">
                             <div class="p-3 border-b border-slate-50 mb-1">
                                 <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Library Components</h3>
                             </div>
                             <div class="max-h-[400px] overflow-y-auto custom-scrollbar">
                                <button v-for="d in designs" :key="d.id"
                                        @click="selectedDesignId = d.id; showDesignSelector = false"
                                        class="w-full text-left p-3 rounded-xl hover:bg-slate-50 transition-colors group relative mb-1">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-black uppercase tracking-tight" :class="selectedDesignId === d.id ? 'text-indigo-600' : 'text-slate-700'">{{ d.name }}</span>
                                        <div v-if="selectedDesignId === d.id" class="w-2 h-2 rounded-full bg-indigo-600"></div>
                                    </div>
                                    <p class="text-[10px] text-slate-400 mt-0.5 leading-tight">{{ d.description }}</p>
                                </button>
                             </div>
                        </div>
                    </div>

                    <button @click="printTest" class="action-btn">
                        <PrinterIcon class="w-4 h-4" />
                        <span>Print Test</span>
                    </button>
                    <button @click="downloadSample" class="action-btn action-btn--primary">
                        <ArrowDownTrayIcon class="w-4 h-4" />
                        <span>Download Sample</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="min-h-screen bg-slate-100/50 py-12">
            <div class="max-w-5xl mx-auto px-4">
                <!-- Toolbar -->
                <div class="flex items-center justify-between mb-8 bg-white/80 backdrop-blur-md p-4 rounded-2xl border border-white shadow-sm">
                    <div class="flex items-center gap-6">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Active Scale</span>
                            <div class="flex items-center gap-3 bg-slate-100 rounded-lg p-1">
                                <button @click="currentZoom -= 5" class="zoom-btn">-</button>
                                <span class="text-xs font-black text-slate-700 min-w-[40px] text-center">{{ currentZoom }}%</span>
                                <button @click="currentZoom += 5" class="zoom-btn">+</button>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                            Vite HMR Active
                        </div>
                        <div class="h-4 w-px bg-slate-200"></div>
                        <div class="p-2 bg-indigo-50 border border-indigo-100 text-indigo-600 rounded-lg flex items-center gap-2">
                            <AdjustmentsVerticalIcon class="w-4 h-4" />
                            <span class="text-[10px] font-black uppercase tracking-widest">Formal GST Configuration</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center transition-all duration-300 pb-24" :style="{ transform: `scale(${currentZoom / 100})`, transformOrigin: 'top center' }">
                    <div class="a4-canvas">
                        <component :is="activeDesign.component" :dummy-data="dummyData" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.a4-canvas {
    width: 210mm;
    min-height: 297mm;
    background: white;
    box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.15);
    border: 1px solid #e2e8f0;
    transition: transform 0.3s ease;
    overflow: hidden;
}

:deep(.design-wrap) {
    padding: 25mm;
    min-height: inherit;
}

.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

.action-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    @apply text-[10px] font-black uppercase tracking-widest text-slate-600 transition-all;
}

.action-btn:hover { @apply bg-slate-50 border-slate-300 -translate-y-0.5 shadow-sm; }
.action-btn--primary { @apply bg-indigo-600 border-indigo-700 text-white shadow-indigo-200 shadow-lg; }
.action-btn--primary:hover { @apply bg-indigo-700 shadow-indigo-300; }

.zoom-btn {
    width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: bold; color: #64748b; border-radius: 4px; transition: background 0.2s;
}
.zoom-btn:hover { background: #e2e8f0; color: #1e293b; }

/* Animations */
.animate-in { animation-duration: 0.3s; animation-fill-mode: forwards; }
@keyframes slide-in-from-top { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.fade-in { animation-name: fadeIn; }
.slide-in-from-top-2 { animation-name: slide-in-from-top; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>

