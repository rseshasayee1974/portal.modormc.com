<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    batch: any;
}>();

const formatDate = (date: string | null) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-IN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
};

const formatTime = (date: string | null) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleTimeString('en-IN', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    });
};

const formatDateTime = (date: string | null) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleString('en-IN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
};

// Material Grouping Logic
const groups = [
    { name: 'Aggregate', keywords: ['sand', 'mm', 'agg', 'dust', 'grit'] },
    { name: 'Cement', keywords: ['cement', 'fly', 'ggbs', 'opc', 'ppc', 'ash'] },
    { name: 'Water / Ice', keywords: ['water', 'wtr', 'ice'] },
    { name: 'Admixture', keywords: ['adm', 'admixture', 'retarder', 'chem'] },
    { name: 'Silica', keywords: ['sil', 'silica', 'fume'] }
];

const groupedMaterials = computed(() => {
    const result: Record<string, any[]> = {
        'Aggregate': [],
        'Cement': [],
        'Water / Ice': [],
        'Admixture': [],
        'Silica': []
    };

    props.batch.materials.forEach((mat: any) => {
        const name = (mat.material_name || mat.product?.title || '').toLowerCase();
        let matched = false;
        for (const group of groups) {
            if (group.keywords.some(k => name.includes(k))) {
                result[group.name].push(mat);
                matched = true;
                break;
            }
        }
        if (!matched) result['Aggregate'].push(mat); // Default
    });

    return result;
});

const totalSetWeight = computed(() => 
    props.batch.materials.reduce((acc: number, m: any) => acc + Number(m.target_qty || 0), 0)
);

const totalActualWeight = computed(() => 
    props.batch.materials.reduce((acc: number, m: any) => acc + Number(m.actual_qty || 0), 0)
);

const totalDifferencePercent = computed(() => {
    if (totalSetWeight.value === 0) return 0;
    return ((totalActualWeight.value - totalSetWeight.value) / totalSetWeight.value) * 100;
});

const print = () => {
    window.print();
};

</script>

<template>
    <div class="min-h-screen bg-gray-100 py-10 no-print print-bg-white">
        <Head title="Batch Sheet Report" />
        
        <!-- Controls -->
        <div class="max-w-[1000px] mx-auto mb-6 flex justify-between items-center px-4">
            <button @click="$window.history.back()" class="flex items-center gap-2 text-gray-600 hover:text-indigo-600 font-bold uppercase text-xs transition-all">
                <i class="pi pi-arrow-left"></i>
                Back to Registry
            </button>
            <button @click="print" class="bg-indigo-600 text-white px-6 py-2 rounded shadow-lg hover:bg-indigo-700 font-black uppercase text-xs tracking-widest flex items-center gap-2 transition-all">
                <i class="pi pi-print"></i>
                Print Report
            </button>
        </div>

        <!-- Report Sheet -->
        <div class="report-sheet mx-auto bg-white shadow-2xl p-[50px] font-serif text-[#1a1a1a]">
            
            <!-- Header -->
            <div class="flex justify-between items-center mb-8 border-b-2 border-black pb-4">
                <div class="w-16 h-16 bg-gray-100 flex items-center justify-center rounded border border-gray-200">
                    <img src="/images/logo.png" alt="Logo" class="max-h-full max-w-full grayscale" onerror="this.style.display='none'"/>
                </div>
                <div class="text-center flex-1 mx-8">
                    <h1 class="text-2xl font-black uppercase tracking-tight">{{ $page.props.auth.user.active_entity?.legal_name || 'V J MIX CONCRETE INDIA PVT LTD' }}</h1>
                    <h2 class="text-lg font-bold uppercase tracking-[0.3em] mt-1 border-t border-black inline-block px-8">Batch Sheet Report</h2>
                </div>
                <div class="w-16 h-16 bg-gray-100 flex items-center justify-center rounded border border-gray-200">
                    <img src="/images/logo.png" alt="Logo" class="max-h-full max-w-full grayscale" onerror="this.style.display='none'"/>
                </div>
            </div>

            <!-- Meta Section -->
            <div class="flex justify-between mb-6 text-sm font-bold uppercase">
                <div>Plant Type : <span class="font-normal">M1.5</span></div>
                <div>Plant Sl.No : <span class="font-normal">121</span></div>
            </div>

            <div class="grid grid-cols-3 gap-y-2 gap-x-12 mb-8 text-[11px] leading-tight border-y border-black py-6 uppercase">
                <div class="flex justify-between"><span>Batch Number</span> <span class="font-bold">: {{ batch.batch_no }}</span></div>
                <div class="flex justify-between"><span>Recipe Name</span> <span class="font-bold">: {{ batch.work_order?.mix_design?.concrete_grade?.name || 'M30' }}</span></div>
                <div class="flex justify-between"><span>Mixer Capacity</span> <span class="font-bold">: 1.25</span></div>
                
                <div class="flex justify-between"><span>Batch Date</span> <span class="font-bold">: {{ formatDate(batch.load_time || batch.created_at) }}</span></div>
                <div class="flex justify-between"><span>Recipe Code</span> <span class="font-bold">: {{ batch.work_order?.mix_design?.mix_id || 'M30(N)' }}</span></div>
                <div class="flex justify-between"><span>Batch Size</span> <span class="font-bold">: {{ Number(batch.batch_size).toFixed(4) }}</span></div>
                
                <div class="flex justify-between"><span>Batch Start Time</span> <span class="font-bold">: {{ formatTime(batch.start_time) }}</span></div>
                <div class="flex justify-between"><span>Truck No</span> <span class="font-bold">: {{ batch.truck?.registration || '-' }}</span></div>
                <div class="flex justify-between"><span>Production Qty</span> <span class="font-bold">: {{ Number(batch.work_order?.produced_qty || 0).toFixed(2) }}</span></div>
                
                <div class="flex justify-between"><span>Batch End Time</span> <span class="font-bold">: {{ formatTime(batch.end_time) }}</span></div>
                <div class="flex justify-between"><span>Truck Driver</span> <span class="font-bold">: {{ batch.driver?.name || 'GUNA' }}</span></div>
                <div class="flex justify-between"><span>Order No</span> <span class="font-bold">: {{ batch.work_order?.order_no }}</span></div>
                
                <div class="flex justify-between"><span>Customer</span> <span class="font-bold">: {{ batch.work_order?.customer?.legal_name || '-' }}</span></div>
                <div class="flex justify-between"><span>Adj / Manual Qty</span> <span class="font-bold">: 0.00</span></div>
                <div class="flex justify-between"><span>Ordered Qty</span> <span class="font-bold">: {{ Number(batch.work_order?.total_qty || 0).toFixed(2) }}</span></div>
                
                <div class="flex justify-between"><span>Site</span> <span class="font-bold">: {{ batch.work_order?.site?.name || '-' }}</span></div>
                <div class="flex justify-between"><span></span> <span></span></div>
                <div class="flex justify-between"><span>With This Load</span> <span class="font-bold">: {{ Number(batch.work_order?.produced_qty || 0).toFixed(2) }}</span></div>
            </div>

            <!-- Materials Table -->
            <div class="overflow-x-auto mb-8">
                <table class="w-full border-collapse text-[10px] border border-black">
                    <thead>
                        <tr class="divide-x divide-black border-b border-black">
                            <th v-for="group in groups" :key="group.name" :colspan="Math.max(1, groupedMaterials[group.name].length)" class="text-center py-1 uppercase font-bold bg-gray-50 border-r border-black last:border-r-0">
                                {{ group.name }}
                            </th>
                        </tr>
                        <tr class="divide-x divide-black border-b border-black">
                            <template v-for="group in groups" :key="'label-'+group.name">
                                <th v-if="groupedMaterials[group.name].length === 0" class="px-1 py-2 text-center">-</th>
                                <th v-for="mat in groupedMaterials[group.name]" :key="mat.id" class="px-1 py-2 text-center uppercase font-bold">
                                    {{ mat.material_name || mat.product?.title }}
                                </th>
                            </template>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-black font-mono">
                        <!-- Targets -->
                        <tr class="divide-x divide-black">
                            <template v-for="group in groups" :key="'target-'+group.name">
                                <td v-if="groupedMaterials[group.name].length === 0" class="px-1 py-2 text-center">0</td>
                                <td v-for="mat in groupedMaterials[group.name]" :key="'target-'+mat.id" class="px-1 py-2 text-center font-bold">
                                    {{ Math.round(mat.target_qty) }}
                                </td>
                            </template>
                        </tr>
                        
                        <!-- Actuals label -->
                        <tr class="bg-gray-50 border-t border-black">
                            <td :colspan="100" class="px-2 py-1 font-bold text-[10px] uppercase">Actual Values in kg</td>
                        </tr>

                        <!-- Actuals -->
                        <tr class="divide-x divide-black">
                            <template v-for="group in groups" :key="'actual-'+group.name">
                                <td v-if="groupedMaterials[group.name].length === 0" class="px-1 py-2 text-center">0</td>
                                <td v-for="mat in groupedMaterials[group.name]" :key="'actual-'+mat.id" class="px-1 py-2 text-center font-bold">
                                    {{ Math.round(mat.actual_qty) }}
                                </td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Stats -->
            <div class="grid grid-cols-2 gap-x-20 gap-y-6 text-[12px] font-bold mt-12 border-t-2 border-black pt-8">
                <div class="space-y-6">
                    <div class="flex justify-between border-b border-black pb-1">
                        <span class="uppercase tracking-tight">Total Set Weight in kg</span>
                        <span class="font-mono">{{ totalSetWeight.toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between border-b border-black pb-1">
                        <span class="uppercase tracking-tight">Total Actual Weight in kg</span>
                        <span class="font-mono">{{ totalActualWeight.toFixed(2) }}</span>
                    </div>
                </div>
                <div class="space-y-6 text-right uppercase">
                    <div>Mass of Recipe Targets in kg : <span class="font-mono ml-2">{{ totalSetWeight.toFixed(2) }}</span></div>
                    <div>Mass of Total Actual Weight in kg : <span class="font-mono ml-2">{{ totalActualWeight.toFixed(2) }}</span></div>
                    <div class="pt-6 text-sm font-black uppercase tracking-tighter border-t border-black border-dashed">
                        Total Mass Difference : <span :class="['font-mono ml-2', totalDifferencePercent < 0 ? 'text-red-700' : 'text-emerald-700']">{{ totalDifferencePercent.toFixed(2) }} %</span>
                    </div>
                </div>
            </div>

            <!-- Page Footer -->
            <div class="mt-24 flex justify-between items-end border-t border-black pt-10">
                <div class="flex items-center gap-3">
                    <div class="p-2 border border-black rounded uppercase font-black text-[10px]">Schwing Stetter</div>
                    <span class="text-[8px] font-bold text-gray-500 tracking-widest">CONTROL SYSTEM DOCUMENTATION</span>
                </div>
                <div class="text-right text-[10px] font-bold uppercase tracking-widest">
                    <div>Generation Date: {{ formatDateTime(new Date().toISOString()) }}</div>
                    <div class="mt-1">Page 1 of 1</div>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
.report-sheet {
    width: 210mm;
    min-height: 297mm;
}

@media print {
    .print-bg-white {
        background: white !important;
        padding: 0 !important;
    }
    .no-print {
        display: none !important;
    }
    .report-sheet {
        width: 100% !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 40px !important;
        border: none !important;
    }
    @page {
        size: A4;
        margin: 0;
    }
}

/* Industrial Matrix Printer Style Font */
.font-serif {
    font-family: 'Inter', system-ui, sans-serif;
}
.font-mono {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}
</style>
