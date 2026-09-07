<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';

const props = defineProps<{
    testType?: any;
    categories?: string[];
    isEditing?: boolean;
}>();

const categoryOptions = (props.categories || ['Aggregate', 'Cement', 'Concrete', 'Admixture', 'Water', 'General'])
    .map(c => ({ label: c, value: c }));

const isStandardPresets = [
    'IS 383',
    'IS 516',
    'IS 2386',
    'IS 456',
    'IS 10262',
    'IS 4031',
    'IS 9103',
    'ASTM C39'
];

const layoutTypeOptions = [
    {
        value: 'SINGLE_TRIAL',
        label: 'Single Reading / Trial',
        tag: 'Standard Direct',
        desc: 'Direct single-set parameter entry and immediate formula evaluation.',
        examples: ['Slump Cone', 'Fresh Temp', 'Air Content', 'AIV / ACV', 'Water Quality'],
        icon: 'pi pi-check-circle',
        colorClass: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/60 border-emerald-200 dark:border-emerald-800',
    },
    {
        value: 'MULTI_TRIAL',
        label: 'Multi-Specimen Trials Matrix',
        tag: 'Multi-Specimen CTM',
        desc: 'Multi-row specimen matrix with individual crushing loads, densities, and average strength.',
        examples: ['7-Day Cube Strength', '28-Day Cube Strength', 'Flexural Beams'],
        icon: 'pi pi-table',
        colorClass: 'text-blue-600 bg-blue-50 dark:bg-blue-950/60 border-blue-200 dark:border-blue-800',
    },
    {
        value: 'SIEVE_GRADATION',
        label: 'Sieve Gradation Grid',
        tag: 'Gradation & FM',
        desc: 'Progressive sieve analysis with IS 383 passing limits, retained masses, and Fineness Modulus.',
        examples: ['20mm Aggregate', '10mm Aggregate', 'M-Sand Gradation'],
        icon: 'pi pi-sliders-h',
        colorClass: 'text-amber-600 bg-amber-50 dark:bg-amber-950/60 border-amber-200 dark:border-amber-800',
    },
    {
        value: 'GAUGE_MATRIX',
        label: 'Gauge Matrix Breakdown',
        tag: 'Flakiness & Elongation',
        desc: 'Thickness & Length gauge matrix per size fraction with combined index calculation.',
        examples: ['Combined Flakiness Index', 'Elongation Index'],
        icon: 'pi pi-percentage',
        colorClass: 'text-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 border-indigo-200 dark:border-indigo-800',
    },
    {
        value: 'TIMED_OBSERVATION',
        label: 'Timed Chronology',
        tag: 'Vicat Setting Time',
        desc: 'Time-elapsed observation log tracking needle penetration depths for initial and final setting.',
        examples: ['Cement Initial Setting (IST)', 'Final Setting Time (FST)'],
        icon: 'pi pi-clock',
        colorClass: 'text-purple-600 bg-purple-50 dark:bg-purple-950/60 border-purple-200 dark:border-purple-800',
    },
    {
        value: 'BEFORE_AFTER',
        label: 'Before / After Mass Delta',
        tag: 'Gravimetric Delta',
        desc: 'SSD vs Oven-dry weight delta calculation for moisture and water absorption.',
        examples: ['Surface Moisture Content', 'Water Absorption (SSD)'],
        icon: 'pi pi-arrows-alt',
        colorClass: 'text-teal-600 bg-teal-50 dark:bg-teal-950/60 border-teal-200 dark:border-teal-800',
    },
    {
        value: 'DENSITY_VOLUME',
        label: 'Mass / Volume / Density',
        tag: 'Volumetric Density',
        desc: 'Cylinder tare, gross weight, and volume ratio for loose/compacted bulk density and voids.',
        examples: ['Loose Bulk Density', 'Compacted Bulk Density', 'Fresh Concrete Density'],
        icon: 'pi pi-box',
        colorClass: 'text-cyan-600 bg-cyan-50 dark:bg-cyan-950/60 border-cyan-200 dark:border-cyan-800',
    },
    {
        value: 'OBSERVATION_CLASSIFICATION',
        label: 'Visual Observation & Defects',
        tag: 'Checklist / QA',
        desc: 'Qualitative defect log, cohesion, segregation, bleeding, and visual compliance checks.',
        examples: ['Fresh Mix Homogeneity', 'Surface Texture Inspection'],
        icon: 'pi pi-eye',
        colorClass: 'text-rose-600 bg-rose-50 dark:bg-rose-950/60 border-rose-200 dark:border-rose-800',
    }
];

const form = useForm({
    name: props.testType?.name || '',
    code: props.testType?.code || '',
    category: props.testType?.category || 'Aggregate',
    material_type: props.testType?.material_type || '',
    standard_reference: props.testType?.standard_reference || '',
    calculation_type: props.testType?.calculation_type || 'formula',
    layout_type: props.testType?.layout_type || 'SINGLE_TRIAL',
    grid_config: props.testType?.grid_config || {
        fractions: [
            { label: '25-20 mm', thickness_mm: 13.50, length_mm: 40.50 },
            { label: '20-16 mm', thickness_mm: 10.80, length_mm: 32.40 },
            { label: '16-12.5 mm', thickness_mm: 8.55, length_mm: 25.60 },
            { label: '12.5-10 mm', thickness_mm: 6.75, length_mm: 20.20 },
            { label: '10-6.3 mm', thickness_mm: 4.89, length_mm: 14.70 }
        ],
        sieves: [
            { label: '40.00 mm', is_limit: '100' },
            { label: '20.00 mm', is_limit: '85-100' },
            { label: '10.00 mm', is_limit: '0-20' },
            { label: '4.75 mm', is_limit: '0-5' },
            { label: 'Pan', is_limit: '-' }
        ]
    },
    description: props.testType?.description || '',
    is_active: props.testType !== undefined ? Boolean(props.testType.is_active) : true,
});

if (!form.grid_config.fractions) {
    form.grid_config.fractions = [
        { label: '25-20 mm', thickness_mm: 13.50, length_mm: 40.50 },
        { label: '20-16 mm', thickness_mm: 10.80, length_mm: 32.40 },
        { label: '16-12.5 mm', thickness_mm: 8.55, length_mm: 25.60 },
        { label: '12.5-10 mm', thickness_mm: 6.75, length_mm: 20.20 },
        { label: '10-6.3 mm', thickness_mm: 4.89, length_mm: 14.70 }
    ];
}
if (!form.grid_config.sieves) {
    form.grid_config.sieves = [
        { label: '40.00 mm', is_limit: '100' },
        { label: '20.00 mm', is_limit: '85-100' },
        { label: '10.00 mm', is_limit: '0-20' },
        { label: '4.75 mm', is_limit: '0-5' },
        { label: 'Pan', is_limit: '-' }
    ];
}

const onNameInput = () => {
    if (!props.isEditing && !form.code && form.name) {
        form.code = form.name.toUpperCase().replace(/[^A-Z0-9]/g, '_').replace(/_+/g, '_').slice(0, 20);
    }
};

const loadConfigGaugePreset = (type: '20mm' | '40mm' | '10mm' | 'clear') => {
    if (!form.grid_config) form.grid_config = {};
    if (type === '20mm') {
        form.grid_config.fractions = [
            { label: '25-20 mm', thickness_mm: 13.50, length_mm: 40.50 },
            { label: '20-16 mm', thickness_mm: 10.80, length_mm: 32.40 },
            { label: '16-12.5 mm', thickness_mm: 8.55, length_mm: 25.60 },
            { label: '12.5-10 mm', thickness_mm: 6.75, length_mm: 20.20 },
            { label: '10-6.3 mm', thickness_mm: 4.89, length_mm: 14.70 }
        ];
    } else if (type === '40mm') {
        form.grid_config.fractions = [
            { label: '50-40 mm', thickness_mm: 27.00, length_mm: 81.00 },
            { label: '40-25 mm', thickness_mm: 19.50, length_mm: 58.50 },
            { label: '25-20 mm', thickness_mm: 13.50, length_mm: 40.50 },
            { label: '20-16 mm', thickness_mm: 10.80, length_mm: 32.40 },
            { label: '16-12.5 mm', thickness_mm: 8.55, length_mm: 25.60 }
        ];
    } else if (type === '10mm') {
        form.grid_config.fractions = [
            { label: '12.5-10 mm', thickness_mm: 6.75, length_mm: 20.20 },
            { label: '10-6.3 mm', thickness_mm: 4.89, length_mm: 14.70 },
            { label: '6.3-4.75 mm', thickness_mm: 3.32, length_mm: 9.95 }
        ];
    } else if (type === 'clear') {
        form.grid_config.fractions = [
            { label: '', thickness_mm: 0, length_mm: 0 }
        ];
    }
};

const loadConfigSievePreset = (type: 'coarse' | 'fine' | 'clear') => {
    if (!form.grid_config) form.grid_config = {};
    if (type === 'coarse') {
        form.grid_config.sieves = [
            { label: '40.00 mm', is_limit: '100' },
            { label: '20.00 mm', is_limit: '85-100' },
            { label: '10.00 mm', is_limit: '0-20' },
            { label: '4.75 mm', is_limit: '0-5' },
            { label: '2.36 mm', is_limit: '0-2' },
            { label: 'Pan', is_limit: '-' }
        ];
    } else if (type === 'fine') {
        form.grid_config.sieves = [
            { label: '10.00 mm', is_limit: '100' },
            { label: '4.75 mm', is_limit: '90-100' },
            { label: '2.36 mm', is_limit: '75-100' },
            { label: '1.18 mm', is_limit: '55-90' },
            { label: '600 µm', is_limit: '35-59' },
            { label: '300 µm', is_limit: '8-30' },
            { label: '150 µm', is_limit: '0-10' },
            { label: 'Pan', is_limit: '-' }
        ];
    } else if (type === 'clear') {
        form.grid_config.sieves = [
            { label: '', is_limit: '-' }
        ];
    }
};

const formatLayoutLabel = (typeValue: string) => {
    if (!typeValue) return 'Single Reading';
    const found = layoutTypeOptions.find(o => o.value === typeValue || o.value === typeValue.toUpperCase());
    return found ? found.label : typeValue;
};

const submit = () => {
    form.code = (form.code || '').toUpperCase().trim();
    if (props.isEditing && props.testType?.id) {
        form.put(route('quality.config.test-types.update', props.testType.id));
    } else {
        form.post(route('quality.config.test-types.store'));
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <!-- General Information Card -->
        <BaseCard class="p-5 sm:p-6 space-y-4">
            <div class="border-b border-gray-100 dark:border-gray-800 pb-3">
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Test Definition Details</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Specify basic identification, category, and IS standard codes.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <BaseInput
                    v-model="form.name"
                    @input="onNameInput"
                    label="Test Name"
                    required
                    placeholder="e.g. Sieve Analysis & Gradation"
                />
                <BaseInput
                    v-model="form.code"
                    label="Test Code"
                    required
                    placeholder="e.g. SIEVE_AGG"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <BaseSelect
                    v-model="form.category"
                    label="Category"
                    :options="categoryOptions"
                    optionLabel="label"
                    optionValue="value"
                    required
                />
                <BaseInput
                    v-model="form.material_type"
                    label="Material Classification (Optional)"
                    placeholder="e.g. 20mm Coarse Aggregate"
                />
            </div>

            <!-- Standard Reference with Presets -->
            <div class="space-y-1.5">
                <BaseInput
                    v-model="form.standard_reference"
                    label="Standard Specification Reference (IS / ASTM)"
                    placeholder="e.g. IS 2386 (Part 1)"
                />
                <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                    <span class="text-[10px] font-bold text-gray-400 uppercase">Quick Presets:</span>
                    <button
                        v-for="preset in isStandardPresets"
                        :key="preset"
                        type="button"
                        @click="form.standard_reference = preset"
                        class="px-2 py-0.5 rounded text-[11px] font-mono font-medium bg-gray-100 dark:bg-gray-800 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition-colors cursor-pointer"
                    >
                        {{ preset }}
                    </button>
                </div>
            </div>

            <BaseInput
                v-model="form.description"
                label="Description & Scope"
                placeholder="Technical description or standard test methodology notes..."
            />

            <div class="flex items-center gap-2 pt-2">
                <input
                    type="checkbox"
                    id="is_active_check"
                    v-model="form.is_active"
                    class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                />
                <label for="is_active_check" class="text-xs font-semibold text-gray-700 dark:text-gray-300 cursor-pointer">
                    Active (Available for sampling & testing execution)
                </label>
            </div>
        </BaseCard>

        <!-- Visual Layout & Template Card -->
        <BaseCard class="p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Execution Layout & Data Entry Format</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Choose the interactive user interface and calculation engine structure for this test.</p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                    <i class="pi pi-check text-[10px]"></i>
                    {{ formatLayoutLabel(form.layout_type) }}
                </span>
            </div>

            <!-- Visual Grid of Layout Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div
                    v-for="layout in layoutTypeOptions"
                    :key="layout.value"
                    @click="form.layout_type = layout.value"
                    class="relative flex flex-col justify-between p-3.5 rounded-xl border cursor-pointer select-none transition-all duration-150 shadow-xs"
                    :class="form.layout_type === layout.value
                        ? 'bg-indigo-50/80 dark:bg-indigo-950/50 border-indigo-400 dark:border-indigo-600 ring-2 ring-indigo-500/80 dark:ring-indigo-600'
                        : 'bg-white dark:bg-gray-800/90 border-gray-200 dark:border-gray-700/80 hover:border-indigo-300 hover:bg-gray-50/80 dark:hover:bg-gray-800'"
                >
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center border shrink-0" :class="layout.colorClass">
                                    <i :class="layout.icon" class="text-xs"></i>
                                </div>
                                <div>
                                    <!-- <h4 class="text-xs font-bold text-gray-900 dark:text-gray-100 leading-tight">
                                        {{ layout.label }}
                                    </h4> -->
                                    <span class="text-[9px] font-bold text-gray-500 uppercase tracking-wider">
                                        {{ layout.tag }}
                                    </span>
                                </div>
                            </div>
                            <!-- Custom Radio Indicator -->
                            <div class="pt-0.5">
                                <div
                                    class="w-4 h-4 rounded-full border flex items-center justify-center transition-colors"
                                    :class="form.layout_type === layout.value
                                        ? 'border-indigo-600 bg-indigo-600 dark:border-indigo-500 dark:bg-indigo-500'
                                        : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900'"
                                >
                                    <div v-if="form.layout_type === layout.value" class="w-1.5 h-1.5 rounded-full bg-white"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                            {{ layout.desc }}
                        </p>
                    </div>

                    <!-- Examples -->
                    <div class="flex flex-wrap items-center gap-1 mt-2.5 pt-2 border-t border-gray-100 dark:border-gray-800/60">
                        <span
                            v-for="(ex, exIdx) in layout.examples"
                            :key="exIdx"
                            class="text-[9px] font-medium px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300"
                        >
                            {{ ex }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Specialized Configuration (Gauge Matrix or Sieve Gradation) -->
            <div v-if="form.layout_type === 'GAUGE_MATRIX'" class="space-y-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h4 class="text-xs font-bold text-gray-800 dark:text-gray-200">Pre-configured Size Fractions (Gauge Matrix)</h4>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            @click="loadConfigGaugePreset('20mm')"
                            class="px-2 py-1 text-xs font-bold bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-indigo-50 cursor-pointer"
                        >
                            20mm
                        </button>
                        <button
                            type="button"
                            @click="loadConfigGaugePreset('40mm')"
                            class="px-2 py-1 text-xs font-bold bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-indigo-50 cursor-pointer"
                        >
                            40mm
                        </button>
                        <button
                            type="button"
                            @click="loadConfigGaugePreset('10mm')"
                            class="px-2 py-1 text-xs font-bold bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-indigo-50 cursor-pointer"
                        >
                            10mm
                        </button>
                        <button
                            type="button"
                            @click="loadConfigGaugePreset('clear')"
                            class="px-2 py-1 text-xs font-bold bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-rose-600 hover:bg-rose-50 cursor-pointer"
                        >
                            Clear
                        </button>
                        <button
                            type="button"
                            @click="form.grid_config.fractions.push({ label: '', thickness_mm: 0, length_mm: 0 })"
                            class="ml-2 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer"
                        >
                            + Add Fraction
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <div v-for="(item, idx) in (form.grid_config.fractions || [])" :key="idx" class="flex items-center gap-2">
                        <input
                            v-model="item.label"
                            type="text"
                            placeholder="Fraction Label (e.g. 25-20 mm)"
                            class="w-1/3 px-3 py-1.5 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-xs font-bold"
                        />
                        <input
                            v-model.number="item.thickness_mm"
                            type="number"
                            step="0.01"
                            placeholder="Thickness (mm)"
                            class="w-1/4 px-3 py-1.5 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-xs font-mono"
                        />
                        <input
                            v-model.number="item.length_mm"
                            type="number"
                            step="0.01"
                            placeholder="Length (mm)"
                            class="w-1/4 px-3 py-1.5 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-xs font-mono"
                        />
                        <button
                            type="button"
                            @click="form.grid_config.fractions.splice(idx, 1)"
                            class="text-rose-500 hover:text-rose-700 p-1.5 text-xs cursor-pointer"
                        >
                            <i class="pi pi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div v-else-if="form.layout_type === 'SIEVE_GRADATION'" class="space-y-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h4 class="text-xs font-bold text-gray-800 dark:text-gray-200">Standard Sieve Sizes & Specification Limits</h4>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            @click="loadConfigSievePreset('coarse')"
                            class="px-2 py-1 text-xs font-bold bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-indigo-50 cursor-pointer"
                        >
                            Coarse (40/20/10)
                        </button>
                        <button
                            type="button"
                            @click="loadConfigSievePreset('fine')"
                            class="px-2 py-1 text-xs font-bold bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-indigo-50 cursor-pointer"
                        >
                            Fine / M-Sand
                        </button>
                        <button
                            type="button"
                            @click="loadConfigSievePreset('clear')"
                            class="px-2 py-1 text-xs font-bold bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-rose-600 hover:bg-rose-50 cursor-pointer"
                        >
                            Clear
                        </button>
                        <button
                            type="button"
                            @click="form.grid_config.sieves.push({ label: '', is_limit: '-' })"
                            class="ml-2 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer"
                        >
                            + Add Sieve
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <div v-for="(item, idx) in (form.grid_config.sieves || [])" :key="idx" class="flex items-center gap-2">
                        <input
                            v-model="item.label"
                            type="text"
                            placeholder="Sieve Size (e.g. 20.00 mm)"
                            class="w-1/2 px-3 py-1.5 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-xs font-bold"
                        />
                        <input
                            v-model="item.is_limit"
                            type="text"
                            placeholder="IS 383 Limit (e.g. 85-100)"
                            class="w-1/3 px-3 py-1.5 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-xs font-mono"
                        />
                        <button
                            type="button"
                            @click="form.grid_config.sieves.splice(idx, 1)"
                            class="text-rose-500 hover:text-rose-700 p-1.5 text-xs cursor-pointer"
                        >
                            <i class="pi pi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </BaseCard>

        <!-- Form Actions Bar -->
        <div class="flex items-center justify-between pt-2">
            <Link
                :href="route('quality.config.test-types.index')"
                class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 transition-colors"
            >
                Cancel & Back
            </Link>

            <button
                type="submit"
                :disabled="form.processing"
                class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
                <i v-if="form.processing" class="pi pi-spin pi-spinner text-xs"></i>
                <i v-else class="pi pi-check text-xs"></i>
                <span>{{ isEditing ? 'Update Test Type' : 'Create Test Type' }}</span>
            </button>
        </div>
    </form>
</template>
