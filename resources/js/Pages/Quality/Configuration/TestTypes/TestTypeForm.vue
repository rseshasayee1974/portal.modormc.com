<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { watch } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import ToggleSwitch from 'primevue/toggleswitch';

const props = withDefaults(defineProps<{
    testType?: any;
    categories?: string[];
    isEditing?: boolean;
    isExpansion?: boolean;
}>(), {
    isEditing: false,
    isExpansion: false
});

const emit = defineEmits<{
    (e: 'saved'): void;
    (e: 'cancel'): void;
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
        desc: 'Direct single reading with immediate formula evaluation.',
        examples: ['Slump Cone', 'Fresh Temp', 'Air Content', 'AIV / ACV'],
        icon: 'pi pi-check-circle',
        colorClass: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/60 border-emerald-200 dark:border-emerald-800',
    },
    {
        value: 'MULTI_TRIAL',
        label: 'Multi-Specimen Matrix',
        tag: 'CTM Crushing',
        desc: 'Multi-specimen trials with loads, densities, and average strength.',
        examples: ['7-Day Cube', '28-Day Cube', 'Flexural Beams'],
        icon: 'pi pi-table',
        colorClass: 'text-blue-600 bg-blue-50 dark:bg-blue-950/60 border-blue-200 dark:border-blue-800',
    },
    {
        value: 'SIEVE_GRADATION',
        label: 'Sieve Gradation Grid',
        tag: 'Gradation & FM',
        desc: 'Progressive sieve analysis with IS 383 passing limits & FM.',
        examples: ['20mm Aggregate', '10mm Aggregate', 'M-Sand'],
        icon: 'pi pi-sliders-h',
        colorClass: 'text-amber-600 bg-amber-50 dark:bg-amber-950/60 border-amber-200 dark:border-amber-800',
    },
    {
        value: 'GAUGE_MATRIX',
        label: 'Gauge Matrix Breakdown',
        tag: 'Flakiness / Elongation',
        desc: 'Thickness & length gauges per size fraction with combined index.',
        examples: ['Flakiness Index', 'Elongation Index'],
        icon: 'pi pi-percentage',
        colorClass: 'text-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 border-indigo-200 dark:border-indigo-800',
    },
    {
        value: 'TIMED_OBSERVATION',
        label: 'Timed Chronology',
        tag: 'Vicat Setting Time',
        desc: 'Elapsed time observation tracking needle penetration depths.',
        examples: ['Initial Setting (IST)', 'Final Setting (FST)'],
        icon: 'pi pi-clock',
        colorClass: 'text-purple-600 bg-purple-50 dark:bg-purple-950/60 border-purple-200 dark:border-purple-800',
    },
    {
        value: 'BEFORE_AFTER',
        label: 'Before / After Delta',
        tag: 'Gravimetric Delta',
        desc: 'SSD vs Oven-dry weight delta calculation for absorption.',
        examples: ['Surface Moisture', 'Water Absorption'],
        icon: 'pi pi-arrows-alt',
        colorClass: 'text-teal-600 bg-teal-50 dark:bg-teal-950/60 border-teal-200 dark:border-teal-800',
    },
    {
        value: 'DENSITY_VOLUME',
        label: 'Mass / Volume / Density',
        tag: 'Volumetric Density',
        desc: 'Tare, gross weight, and volume ratio for loose/compacted bulk.',
        examples: ['Loose Bulk Density', 'Compacted Density'],
        icon: 'pi pi-box',
        colorClass: 'text-cyan-600 bg-cyan-50 dark:bg-cyan-950/60 border-cyan-200 dark:border-cyan-800',
    },
    {
        value: 'OBSERVATION_CLASSIFICATION',
        label: 'Visual Observation & QA',
        tag: 'Defects / Checklist',
        desc: 'Qualitative defect log, cohesion, segregation, bleeding checks.',
        examples: ['Mix Homogeneity', 'Surface Texture'],
        icon: 'pi pi-eye',
        colorClass: 'text-rose-600 bg-rose-50 dark:bg-rose-950/60 border-rose-200 dark:border-rose-800',
    }
];

const getDefaultGridConfig = () => ({
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
});

const form = useForm({
    name: props.testType?.name || '',
    code: props.testType?.code || '',
    category: props.testType?.category || 'Aggregate',
    material_type: props.testType?.material_type || '',
    standard_reference: props.testType?.standard_reference || '',
    calculation_type: props.testType?.calculation_type || 'formula',
    layout_type: props.testType?.layout_type || 'SINGLE_TRIAL',
    grid_config: props.testType?.grid_config ? JSON.parse(JSON.stringify(props.testType.grid_config)) : getDefaultGridConfig(),
    is_active: props.testType?.is_active !== undefined ? Boolean(props.testType.is_active) : true,
});

const resetForm = () => {
    form.reset();
    form.name = '';
    form.code = '';
    form.category = 'Aggregate';
    form.material_type = '';
    form.standard_reference = '';
    form.calculation_type = 'formula';
    form.layout_type = 'SINGLE_TRIAL';
    form.grid_config = getDefaultGridConfig();
    form.is_active = true;
    form.clearErrors();
};

watch(() => props.testType, (newVal) => {
    if (newVal) {
        form.name = newVal.name || '';
        form.code = newVal.code || '';
        form.category = newVal.category || 'Aggregate';
        form.material_type = newVal.material_type || '';
        form.standard_reference = newVal.standard_reference || '';
        form.calculation_type = newVal.calculation_type || 'formula';
        form.layout_type = newVal.layout_type || 'SINGLE_TRIAL';
        form.grid_config = newVal.grid_config ? JSON.parse(JSON.stringify(newVal.grid_config)) : getDefaultGridConfig();
        if (!form.grid_config.fractions) form.grid_config.fractions = getDefaultGridConfig().fractions;
        if (!form.grid_config.sieves) form.grid_config.sieves = getDefaultGridConfig().sieves;
        form.is_active = newVal.is_active !== undefined ? Boolean(newVal.is_active) : true;
        form.clearErrors();
    } else {
        resetForm();
    }
}, { deep: true, immediate: true });

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

const onCancel = () => {
    resetForm();
    emit('cancel');
};

const handleCancel = () => {
    if (props.isEditing) {
        onCancel();
    } else {
        resetForm();
    }
};

const submit = () => {
    form.code = (form.code || '').toUpperCase().trim();
    if (props.isEditing && props.testType?.id) {
        form.put(route('quality.config.test-types.update', props.testType.id), {
            preserveScroll: true,
            onSuccess: () => {
                resetForm();
                emit('saved');
            }
        });
    } else {
        form.post(route('quality.config.test-types.store'), {
            preserveScroll: true,
            onSuccess: () => {
                resetForm();
                emit('saved');
            }
        });
    }
};
</script>

<template>
    <form
        @submit.prevent="submit"
        :class="[
            'transition-all',
            isExpansion
                ? 'bg-white dark:bg-gray-900 border border-amber-300/80 dark:border-amber-800/80 border-l-4 border-l-amber-500 dark:border-l-amber-400 rounded-2xl shadow-md overflow-hidden'
                : 'bg-white dark:bg-gray-900 border border-gray-200/90 dark:border-gray-800 rounded-2xl shadow-xs overflow-hidden'
        ]"
    >
        <!-- Sleek Card Header -->
        <div
            :class="[
                'px-5 py-3.5 border-b flex flex-col sm:flex-row sm:items-center justify-between gap-3',
                isExpansion
                    ? 'bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-white dark:from-amber-950/40 dark:via-gray-900 dark:to-gray-900 border-amber-200/70 dark:border-amber-900/50'
                    : 'bg-gradient-to-r from-gray-50/80 to-white dark:from-gray-800/60 dark:to-gray-900 border-gray-100 dark:border-gray-800'
            ]"
        >
            <div class="flex items-center gap-3">
                <div
                    class="w-8 h-8 rounded-xl flex items-center justify-center border shrink-0 transition-transform shadow-xs"
                    :class="isEditing
                        ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800/80'
                        : 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800/80'"
                >
                    <i :class="isEditing ? 'pi pi-pencil' : 'pi pi-sliders-h'" class="text-xs font-bold"></i>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 tracking-tight">
                            {{ isExpansion ? 'Edit QC Test Type Inline' : (isEditing ? 'Edit QC Test Definition' : 'Define New QC Test Type') }}
                        </h3>
                        <span v-if="isEditing && props.testType?.name" class="text-xs font-semibold text-gray-600 dark:text-gray-300">
                            &bull; {{ props.testType.name }}
                        </span>
                        <span v-if="isEditing && props.testType?.code" class="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                            {{ props.testType.code }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Header Right: Parameters link, Active Switch & Close/Cancel -->
            <div class="flex items-center gap-2 sm:gap-3 self-end sm:self-auto flex-wrap">
                <!-- Parameters & Criteria shortcut button (for existing test types) -->
                <!-- <Link
                    v-if="isEditing && props.testType?.id"
                    :href="route('quality.config.test-parameters.index', { test_type_id: props.testType.id })"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800/80 shadow-2xs transition-all"
                    title="Configure test parameters & formulas"
                >
                    <i class="pi pi-sliders-h text-[11px]"></i>
                    <span>Parameters & Criteria ({{ props.testType?.parameters?.length || 0 }})</span>
                </Link> -->

                <div class="flex items-center gap-2 px-3 py-1 bg-white dark:bg-gray-800 rounded-xl border border-gray-200/80 dark:border-gray-700/80 shadow-2xs">
                    <ToggleSwitch v-model="form.is_active" class="scale-80 origin-left" />
                    <span class="text-xs font-bold" :class="form.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400'">
                        {{ form.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

               
            </div>
        </div>

        <div class="p-5 sm:p-6 space-y-5">
            <!-- Main Inputs in Balanced Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3.5">
                <!-- Test Name (Span 4) -->
                <div class="lg:col-span-4">
                    <BaseInput
                        v-model="form.name"
                        @input="onNameInput"
                        label="Test Name"
                        required
                        :error="form.errors.name"
                        placeholder="e.g. Sieve Analysis & Gradation"
                    />
                </div>

                <!-- Test Code (Span 2) -->
                <div class="lg:col-span-2">
                    <BaseInput
                        v-model="form.code"
                        label="Test Code"
                        required
                        :error="form.errors.code"
                        placeholder="e.g. SIEVE_AGG"
                    />
                </div>

                <!-- Category (Span 2) -->
                <div class="lg:col-span-2">
                    <BaseSelect
                        v-model="form.category"
                        label="Category"
                        :options="categoryOptions"
                        optionLabel="label"
                        optionValue="value"
                        required
                        :error="form.errors.category"
                    />
                </div>

                <!-- Material Classification (Span 2) -->
                <div class="lg:col-span-2">
                    <BaseInput
                        v-model="form.material_type"
                        label="Material (Optional)"
                        :error="form.errors.material_type"
                        placeholder="e.g. 20mm Coarse Aggregate"
                    />
                </div>

                <!-- Standard Specification (Span 2) -->
                <div class="lg:col-span-2">
                    <BaseInput
                        v-model="form.standard_reference"
                        label="IS / ASTM Standard"
                        :error="form.errors.standard_reference"
                        placeholder="e.g. IS 2386 (Part 1)"
                    />
                </div>
            </div>

            <!-- Standard Reference Quick Presets Micro-Bar -->
            <div class="flex flex-wrap items-center gap-1.5 -mt-2">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Quick Presets:</span>
                <button
                    v-for="preset in isStandardPresets"
                    :key="preset"
                    type="button"
                    @click="form.standard_reference = preset"
                    :class="[
                        'px-2 py-0.5 rounded-md text-[11px] font-mono transition-all cursor-pointer select-none',
                        form.standard_reference === preset
                            ? 'bg-indigo-600 text-white font-bold shadow-xs'
                            : 'bg-gray-100 dark:bg-gray-800 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300'
                    ]"
                >
                    {{ preset }}
                </button>
            </div>
        </div>

        <!-- Form Actions Footer Bar -->
        <div class="px-5 py-3.5 bg-gray-50/80 dark:bg-gray-800/60 border-t border-gray-100 dark:border-gray-800 flex items-end justify-end gap-3">
            <!-- <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5"> -->
                <!-- <i :class="isEditing ? 'pi pi-info-circle text-amber-500' : 'pi pi-info-circle text-indigo-500'" class="text-xs"></i> -->
                <!-- <span v-if="isEditing">Updates apply immediately to test specifications.</span>
                <span v-else>Provide standard test configuration details above.</span> -->
            <!-- </div> -->

            <div class="flex items-end gap-2">
                <button
                    v-if="isExpansion"
                    type="button"
                    @click="onCancel"
                    class="px-4 py-2 text-xs font-semibold rounded-xl text-gray-600 dark:text-gray-300 hover:bg-gray-200/60 dark:hover:bg-gray-700 transition-colors cursor-pointer"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    :disabled="form.processing"
                    :class="[
                        'px-5 py-2 text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-2 cursor-pointer disabled:opacity-50',
                        isEditing
                            ? 'bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white shadow-amber-500/20'
                            : 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-600/20'
                    ]"
                >
                    <i v-if="form.processing" class="pi pi-spin pi-spinner text-xs"></i>
                    <i v-else :class="isEditing ? 'pi pi-check' : 'pi pi-plus'" class="text-xs"></i>
                    <span>{{ isEditing ? 'Update QC Test Type' : 'Save QC Test Type' }}</span>
                </button>
            </div>
        </div>
    </form>
</template>
