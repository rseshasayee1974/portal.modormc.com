<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import ToggleSwitch from 'primevue/toggleswitch';
import Dialog from 'primevue/dialog';

interface ParameterItem {
    id?: number;
    name: string;
    age: string;
    target: string | number;
    min: string | number;
}

const toast = useToast();

const props = withDefaults(defineProps<{
    testType?: any;
    products?: any[];
    concrete_grade?: any[];
    units?: any[];
    isEditing?: boolean;
    isExpansion?: boolean;
}>(), {
    products: () => [],
    concrete_grade: () => [],
    units: () => [],
    isEditing: false,
    isExpansion: false
});

const emit = defineEmits<{
    (e: 'saved'): void;
    (e: 'cancel'): void;
}>();

// Category presets
const categoryOptions = [
    { label: 'Concrete', value: 'Concrete' },
    { label: 'Raw Material', value: 'Raw Material' },
    { label: 'Other', value: 'Other' }
];

const isConcrete = computed(() => (form.category || '').toLowerCase() === 'concrete');
const isRawMaterial = computed(() => (form.category || '').toLowerCase().replace(/\s+/g, '') === 'rawmaterial');

// Concrete QC Test Types
const concreteQcTestTypes = [
    { label: 'Compressive Strength', value: 'COMPRESSIVE' },
    { label: 'Flexural Strength', value: 'FLEXURAL' },
    { label: 'Split Tensile Strength', value: 'SPLIT_TENSILE' },
    { label: 'Slump', value: 'SLUMP' },
    { label: 'Rapid Chloride Permeability (RCPT)', value: 'RCPT' },
    { label: 'Water Permeability', value: 'WATER_PERMEABILITY' },
    { label: 'Density', value: 'DENSITY' },
    { label: 'Temperature', value: 'TEMPERATURE' }
];

// Aggregate / Raw Material QC Test Types
const aggregateQcTestTypes = [
    { label: 'Sieve Analysis & Grading', value: 'SIEVE_GRADING' },
    { label: 'Specific Gravity', value: 'SPECIFIC_GRAVITY' },
    { label: 'Water Absorption', value: 'WATER_ABSORPTION' },
    { label: 'Moisture Content', value: 'MOISTURE_CONTENT' },
    { label: 'Aggregate Crushing Value', value: 'ACV' },
    { label: 'Aggregate Impact Value', value: 'AIV' },
    { label: 'Flakiness Index', value: 'FLAKINESS_INDEX' },
    { label: 'Elongation Index', value: 'ELONGATION_INDEX' },
    { label: 'Los Angeles Abrasion', value: 'LOS_ANGELES_ABRASION' },
    { label: 'Soundness', value: 'SOUNDNESS' }
];

// Cement QC Test Types
const cementQcTestTypes = [
    { label: 'Fineness', value: 'FINENESS' },
    { label: 'Standard Consistency', value: 'CONSISTENCY' },
    { label: 'Initial Setting Time', value: 'INITIAL_SETTING_TIME' },
    { label: 'Final Setting Time', value: 'FINAL_SETTING_TIME' },
    { label: 'Soundness', value: 'SOUNDNESS' },
    { label: 'Compressive Strength', value: 'COMPRESSIVE' }
];

// Raw Material Subtype state (Aggregate / Cement)
const rawMaterialType = ref<'Aggregate' | 'Cement'>('Aggregate');

// QC Test Type dynamic options based on Category & Subtype
const qcTestTypeOptions = computed(() => {
    if (isConcrete.value) {
        return concreteQcTestTypes;
    }
    if (isRawMaterial.value) {
        return rawMaterialType.value === 'Cement' ? cementQcTestTypes : aggregateQcTestTypes;
    }
    return [
        ...concreteQcTestTypes,
        ...aggregateQcTestTypes,
        ...cementQcTestTypes
    ].filter((v, i, a) => a.findIndex(t => t.value === v.value) === i);
});

// Standard and Unit defaults mapped to test types
const testTypeDefaults: Record<string, { standard: string; unit: string }> = {
    // Concrete
    COMPRESSIVE: { standard: 'IS 516', unit: 'MPa' },
    FLEXURAL: { standard: 'IS 516', unit: 'MPa' },
    SPLIT_TENSILE: { standard: 'IS 5816', unit: 'MPa' },
    SLUMP: { standard: 'IS 1199', unit: 'mm' },
    RCPT: { standard: 'ASTM C1202', unit: 'Coulombs' },
    WATER_PERMEABILITY: { standard: 'DIN 1048', unit: 'mm' },
    DENSITY: { standard: 'IS 516', unit: 'kg/m³' },
    TEMPERATURE: { standard: 'IS 1199', unit: '°C' },

    // Aggregate
    SIEVE_GRADING: { standard: 'IS 2386 (Part 1)', unit: '%' },
    SPECIFIC_GRAVITY: { standard: 'IS 2386 (Part 3)', unit: 'ratio' },
    WATER_ABSORPTION: { standard: 'IS 2386 (Part 3)', unit: '%' },
    MOISTURE_CONTENT: { standard: 'IS 2386 (Part 3)', unit: '%' },
    ACV: { standard: 'IS 2386 (Part 4)', unit: '%' },
    AIV: { standard: 'IS 2386 (Part 4)', unit: '%' },
    FLAKINESS_INDEX: { standard: 'IS 2386 (Part 1)', unit: '%' },
    ELONGATION_INDEX: { standard: 'IS 2386 (Part 1)', unit: '%' },
    LOS_ANGELES_ABRASION: { standard: 'IS 2386 (Part 4)', unit: '%' },
    SOUNDNESS: { standard: 'IS 2386 (Part 5)', unit: '%' },

    // Cement
    FINENESS: { standard: 'IS 4031 (Part 1)', unit: 'm²/kg' },
    CONSISTENCY: { standard: 'IS 4031 (Part 4)', unit: '%' },
    INITIAL_SETTING_TIME: { standard: 'IS 4031 (Part 5)', unit: 'minutes' },
    FINAL_SETTING_TIME: { standard: 'IS 4031 (Part 5)', unit: 'minutes' }
};

const onQcTestTypeChange = () => {
    const d = testTypeDefaults[form.qc_test_type];
    if (d) {
        if (d.standard) form.standard = d.standard;
        if (d.unit) form.unit = d.unit;
    }
};

// Concrete Grade Options
const concreteGradeOptions = computed(() => {
    return (props.concrete_grade || []).map((g: any) => ({
        label: g.design_type || g.name,
        value: g.design_type || g.name,
        raw: g
    }));
});

// Product Options
const productOptions = computed(() => {
    return (props.products || []).map((p: any) => ({
        label: p.title ? `${p.title}${p.code ? ' (' + p.code + ')' : ''}` : (p.name || p.code),
        value: p.id,
        raw: p
    }));
});

// Standard presets
const standardPresets = ['IS 516', 'IS 456', 'IS 10262', 'IS 1199', 'ASTM C39'];

// Unit Master Options & Management
const localUnits = ref<any[]>([]);

watch(() => props.units, (newUnits) => {
    if (newUnits && newUnits.length) {
        localUnits.value = [...newUnits];
    }
}, { immediate: true });

const unitOptions = computed(() => {
    const list = localUnits.value.map((u: any) => ({
        label: `${u.symbol} — ${u.name}`,
        value: u.symbol,
        code: u.code,
        name: u.name,
        dimension: u.dimension
    }));

    if (form.unit && !list.some((u: any) => u.value === form.unit)) {
        list.unshift({
            label: form.unit,
            value: form.unit,
            code: form.unit,
            name: form.unit,
            dimension: 'pressure'
        });
    }

    return list;
});

// Add QC Unit Dialog State
const showAddUnitModal = ref(false);
const addingUnit = ref(false);
const unitForm = ref({
    name: '',
    symbol: '',
    code: '',
    dimension: 'pressure'
});
const unitErrors = ref<Record<string, string>>({});

const dimensionOptions = [
    { label: 'Pressure (e.g. MPa, N/mm²)', value: 'pressure' },
    { label: 'Force (e.g. kN, N)', value: 'force' },
    { label: 'Length (e.g. mm, cm, m)', value: 'length' },
    { label: 'Mass (e.g. kg, g, t)', value: 'mass' },
    { label: 'Volume (e.g. m³, L)', value: 'volume' },
    { label: 'Density (e.g. kg/m³)', value: 'density' },
    { label: 'Ratio / Percent (e.g. %)', value: 'ratio' },
    { label: 'Time (e.g. days, hr, s)', value: 'time' },
    { label: 'Other', value: 'other' }
];

const openAddUnitModal = () => {
    unitForm.value = {
        name: '',
        symbol: '',
        code: '',
        dimension: 'pressure'
    };
    unitErrors.value = {};
    showAddUnitModal.value = true;
};

const onUnitSymbolOrNameInput = () => {
    if (!unitForm.value.code && (unitForm.value.symbol || unitForm.value.name)) {
        const text = unitForm.value.symbol || unitForm.value.name;
        unitForm.value.code = text.toUpperCase().replace(/[^A-Z0-9]/g, '_').slice(0, 30);
    }
};

const saveUnit = async () => {
    unitErrors.value = {};
    if (!unitForm.value.name.trim()) {
        unitErrors.value.name = 'Unit name is required';
    }
    if (!unitForm.value.symbol.trim()) {
        unitErrors.value.symbol = 'Unit symbol is required';
    }
    if (!unitForm.value.code.trim()) {
        unitForm.value.code = (unitForm.value.symbol || unitForm.value.name).toUpperCase().replace(/[^A-Z0-9]/g, '_').slice(0, 30);
    }

    if (Object.keys(unitErrors.value).length > 0) {
        return;
    }

    addingUnit.value = true;
    try {
        const res = await axios.post(route('quality.config.units.store'), {
            name: unitForm.value.name.trim(),
            symbol: unitForm.value.symbol.trim(),
            code: unitForm.value.code.trim(),
            dimension: unitForm.value.dimension,
            is_active: true
        });

        const created = res.data?.unit;
        if (created) {
            localUnits.value.push(created);
            form.unit = created.symbol;
        } else {
            const fallback = {
                name: unitForm.value.name.trim(),
                symbol: unitForm.value.symbol.trim(),
                code: unitForm.value.code.trim(),
                dimension: unitForm.value.dimension
            };
            localUnits.value.push(fallback);
            form.unit = fallback.symbol;
        }

        showAddUnitModal.value = false;
        toast.add({
            severity: 'success',
            summary: 'Unit Added',
            detail: `Unit "${unitForm.value.symbol}" added to qc_units successfully.`,
            life: 2500
        });
    } catch (err: any) {
        if (err.response?.data?.errors) {
            unitErrors.value = Object.entries(err.response.data.errors).reduce((acc: any, [k, v]: any) => {
                acc[k] = Array.isArray(v) ? v[0] : v;
                return acc;
            }, {});
        } else {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: err.response?.data?.message || 'Failed to add unit to qc_units.',
                life: 3000
            });
        }
    } finally {
        addingUnit.value = false;
    }
};

// Parameters state
const parameters = ref<ParameterItem[]>([]);

// Form Definition
const form = useForm({
    category: 'Concrete',
    concrete_grade: '',
    qc_test_type: 'Compressive',
    product_id: null as any,
    grade_strength: '30.00',
    standard: 'IS 516',
    unit: 'MPa',
    is_active: true,
});

// Helper to auto-calculate default 7, 15, 28-day parameters
const generateDefaultParameters = (strengthValue: number) => {
    return [
        {
            name: '7-Day',
            age: '7 d',
            target: (strengthValue * 0.65).toFixed(2),
            min: (strengthValue * 0.60).toFixed(2)
        },
        {
            name: '15-Day',
            age: '15 d',
            target: (strengthValue * 0.90).toFixed(2),
            min: (strengthValue * 0.85).toFixed(2)
        },
        {
            name: '28-Day',
            age: '28 d',
            target: (strengthValue * 1.00).toFixed(2),
            min: (strengthValue * 1.00).toFixed(2)
        }
    ];
};

// Handle Concrete Grade Selection
const onConcreteGradeChange = () => {
    if (!form.concrete_grade) return;

    // Extract numeric strength e.g. M30 -> 30, M25 -> 25
    const match = form.concrete_grade.match(/\d+/);
    const strengthNum = match ? parseFloat(match[0]) : 30.0;
    form.grade_strength = strengthNum.toFixed(2);

    if (!form.standard) {
        form.standard = 'IS 516';
    }
    if (!form.unit) {
        form.unit = 'MPa';
    }

    // Auto-select corresponding product if available and not already set
    if (!form.product_id && props.products?.length) {
        const found = props.products.find((p: any) => 
            p.title?.toLowerCase().includes(form.concrete_grade.toLowerCase()) ||
            p.code?.toLowerCase().includes(form.concrete_grade.toLowerCase())
        );
        if (found) {
            form.product_id = found.id;
        } else {
            // Pick first RMC product
            const rmc = props.products.find((p: any) => 
                p.title?.toLowerCase().includes('rmc') || 
                p.title?.toLowerCase().includes('ready mix')
            );
            if (rmc) form.product_id = rmc.id;
        }
    }

    // Populate default parameters if currently empty
    if (!props.isEditing || parameters.value.length === 0) {
        parameters.value = generateDefaultParameters(strengthNum);
    }
};

// Handle Grade Strength manual input change
const onGradeStrengthChange = () => {
    const val = parseFloat(form.grade_strength);
    if (!isNaN(val) && val > 0 && parameters.value.length === 0) {
        parameters.value = generateDefaultParameters(val);
    }
};

// Handle Product selection for Raw Material / Other
const onProductChange = () => {
    const selected = productOptions.value.find(p => p.value === form.product_id);
    if (selected && isRawMaterial.value) {
        form.concrete_grade = selected.raw?.title || selected.label;
        const text = `${selected.raw?.title || ''} ${selected.label || ''} ${selected.raw?.category?.name || ''}`.toLowerCase();
        if (text.includes('cement')) {
            rawMaterialType.value = 'Cement';
        } else {
            rawMaterialType.value = 'Aggregate';
        }
        const first = qcTestTypeOptions.value[0];
        if (first) {
            form.qc_test_type = first.value;
            onQcTestTypeChange();
        }
    }
};

// Watch Raw Material Type toggle (Aggregate / Cement)
watch(rawMaterialType, () => {
    if (isRawMaterial.value) {
        const first = qcTestTypeOptions.value[0];
        if (first) {
            form.qc_test_type = first.value;
            onQcTestTypeChange();
        }
    }
});

// Watch Category change to toggle fields and reset defaults
watch(() => form.category, (newCategory) => {
    const cat = (newCategory || '').toLowerCase().replace(/\s+/g, '');
    if (cat === 'concrete') {
        form.product_id = null;
        form.qc_test_type = 'COMPRESSIVE';
        form.standard = 'IS 516';
        form.unit = 'MPa';
        if (form.concrete_grade) onConcreteGradeChange();
    } else if (cat === 'rawmaterial') {
        form.concrete_grade = '';
        form.grade_strength = '';
        const first = qcTestTypeOptions.value[0];
        if (first) {
            form.qc_test_type = first.value;
            onQcTestTypeChange();
        }
    } else {
        form.grade_strength = '';
    }
});

// Modal for adding / editing a parameter
const showParamModal = ref(false);
const editingParamIndex = ref<number | null>(null);
const paramForm = ref<ParameterItem>({
    name: '',
    age: '',
    target: '',
    min: ''
});

const openAddParameter = () => {
    editingParamIndex.value = null;
    paramForm.value = {
        name: '',
        age: '',
        target: '',
        min: ''
    };
    showParamModal.value = true;
};

const openEditParameter = (index: number) => {
    editingParamIndex.value = index;
    const item = parameters.value[index];
    paramForm.value = {
        ...item
    };
    showParamModal.value = true;
};

const saveParameter = () => {
    if (!paramForm.value.name.trim()) {
        return;
    }

    let formattedAge = paramForm.value.age.trim();
    if (formattedAge && !formattedAge.toLowerCase().endsWith('d') && !isNaN(Number(formattedAge))) {
        formattedAge = `${formattedAge} d`;
    }

    const itemToSave: ParameterItem = {
        name: paramForm.value.name.trim(),
        age: formattedAge,
        target: paramForm.value.target !== '' ? parseFloat(String(paramForm.value.target)).toFixed(2) : '',
        min: paramForm.value.min !== '' ? parseFloat(String(paramForm.value.min)).toFixed(2) : ''
    };

    if (editingParamIndex.value !== null && editingParamIndex.value >= 0) {
        parameters.value[editingParamIndex.value] = {
            ...parameters.value[editingParamIndex.value],
            ...itemToSave
        };
    } else {
        parameters.value.push(itemToSave);
    }

    showParamModal.value = false;
};

const deleteParameter = (index: number) => {
    parameters.value.splice(index, 1);
};

// Reset Form to Clean / Default State
const resetForm = () => {
    form.reset();
    form.category = 'Concrete';
    rawMaterialType.value = 'Aggregate';
    form.concrete_grade = '';
    form.qc_test_type = 'COMPRESSIVE';
    form.product_id = null;
    form.grade_strength = '30.00';
    form.standard = 'IS 516';
    form.unit = 'MPa';
    form.is_active = true;
    parameters.value = [];
    form.clearErrors();
};

// Initialize / watch for props.testType changes
watch(() => props.testType, (newVal) => {
    if (newVal) {
        const gridConfig = newVal.grid_config || {};
        form.category = newVal.category || gridConfig.category || 'Concrete';
        form.concrete_grade = gridConfig.concrete_grade || newVal.material_type || '';
        form.product_id = gridConfig.product_id || null;

        if (gridConfig.raw_material_type) {
            rawMaterialType.value = gridConfig.raw_material_type;
        } else if ((form.category || '').toLowerCase().includes('raw')) {
            const rawQc = String(gridConfig.qc_test_type || newVal.qc_test_type || '').toUpperCase();
            if (cementQcTestTypes.some(c => c.value === rawQc)) {
                rawMaterialType.value = 'Cement';
            } else {
                rawMaterialType.value = 'Aggregate';
            }
        }

        const rawType = gridConfig.qc_test_type || newVal.qc_test_type || 'COMPRESSIVE';
        const upperType = String(rawType).toUpperCase();
        form.qc_test_type = upperType === 'COMPRESSIVE' ? 'COMPRESSIVE' : (gridConfig.qc_test_type || upperType);

        form.grade_strength = gridConfig.grade_strength || (form.concrete_grade.match(/\d+/) ? parseFloat(form.concrete_grade.match(/\d+/)[0]).toFixed(2) : '30.00');
        form.standard = newVal.standard_reference || gridConfig.standard || 'IS 516';
        form.unit = gridConfig.unit || 'MPa';
        form.is_active = newVal.is_active !== undefined ? Boolean(newVal.is_active) : true;

        if (newVal.parameters && newVal.parameters.length > 0) {
            parameters.value = newVal.parameters.map((p: any) => ({
                id: p.id,
                name: p.name,
                age: p.default_value ? `${p.default_value} d` : (p.name.includes('Day') ? p.name.replace('-Day', ' d') : ''),
                target: p.target_value !== null ? parseFloat(p.target_value).toFixed(2) : '',
                min: p.min_value !== null ? parseFloat(p.min_value).toFixed(2) : ''
            }));
        } else {
            const match = form.concrete_grade.match(/\d+/);
            const num = match ? parseFloat(match[0]) : 30.0;
            parameters.value = generateDefaultParameters(num);
        }
    } else {
        resetForm();
    }
}, { deep: true, immediate: true });

const onCancel = () => {
    resetForm();
    emit('cancel');
};

const submit = () => {
    let gradeName = '';
    if (isConcrete.value) {
        gradeName = form.concrete_grade || 'Concrete';
    } else if (isRawMaterial.value) {
        const prod = productOptions.value.find(p => p.value === form.product_id);
        gradeName = prod?.raw?.title || prod?.label || 'Raw Material';
    } else {
        gradeName = form.concrete_grade || 'Other';
    }

    const allTypes = [...concreteQcTestTypes, ...aggregateQcTestTypes, ...cementQcTestTypes];
    const testTypeObj = allTypes.find(
        t => t.value === form.qc_test_type || t.value.toLowerCase() === (form.qc_test_type || '').toLowerCase()
    );
    const testTypeName = testTypeObj?.label || form.qc_test_type || 'Test';
    const name = `${gradeName} ${testTypeName}`;
    const code = `${gradeName}_${form.qc_test_type || 'TEST'}`.toUpperCase().replace(/[^A-Z0-9]/g, '_').slice(0, 30);

    const payload = {
        name,
        code,
        category: form.category || 'Concrete',
        material_type: gradeName,
        standard_reference: form.standard || 'IS 516',
        calculation_type: 'formula',
        layout_type: 'SINGLE_TRIAL',
        is_active: form.is_active,
        grid_config: {
            category: form.category,
            raw_material_type: isRawMaterial.value ? rawMaterialType.value : null,
            concrete_grade: form.concrete_grade,
            qc_test_type: form.qc_test_type,
            product_id: form.product_id,
            grade_strength: form.grade_strength,
            standard: form.standard,
            unit: form.unit
        },
        parameters: parameters.value.map(p => ({
            name: p.name,
            age: p.age,
            target: p.target !== '' && p.target !== null ? parseFloat(String(p.target)) : null,
            min: p.min !== '' && p.min !== null ? parseFloat(String(p.min)) : null,
            unit: form.unit || 'MPa'
        }))
    };

    if (props.isEditing && props.testType?.id) {
        form.transform(() => payload).put(route('quality.config.test-types.update', props.testType.id), {
            preserveScroll: true,
            onSuccess: () => {
                resetForm();
                emit('saved');
            }
        });
    } else {
        form.transform(() => payload).post(route('quality.config.test-types.store'), {
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
    <div
        :class="[
            'transition-all bg-white dark:bg-gray-900 border rounded-2xl shadow-xs overflow-hidden',
            isExpansion
                ? 'border-amber-300/80 dark:border-amber-800/80 border-l-4 border-l-amber-500 dark:border-l-amber-400'
                : 'border-gray-200/90 dark:border-gray-800'
        ]"
    >
        <!-- CARD HEADER -->
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gradient-to-r from-gray-50/70 via-white to-white dark:from-gray-800/60 dark:via-gray-900 dark:to-gray-900">
            <div>
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 tracking-tight">
                    {{ isEditing ? 'Edit Product Grade QC Master' : 'Define Product Grade QC Master' }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Configure grade-specific QC parameters and acceptance rules.
                </p>
            </div>

            <!-- Active Switch -->
            <div class="flex items-center gap-2.5 px-3 py-1 bg-white dark:bg-gray-800 rounded-xl border border-gray-200/80 dark:border-gray-700/80 shadow-2xs self-start sm:self-auto">
                <ToggleSwitch v-model="form.is_active" class="scale-80 origin-left" />
                <span
                    class="text-xs font-bold select-none cursor-pointer"
                    :class="form.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400'"
                    @click="form.is_active = !form.is_active"
                >
                    {{ form.is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>

        <form @submit.prevent="submit">
            <!-- SECTION 1: MASTER INFORMATION -->
            <div class="p-6 border-b border-gray-100 dark:border-gray-800 space-y-4">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-extrabold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Master Information
                    </span>
                    <div class="h-px flex-1 bg-gray-100 dark:bg-gray-800"></div>
                </div>

                <!-- Validation Error Alert Banner -->
                <div v-if="form.errors.name" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 flex items-center gap-2.5 text-rose-700 dark:text-rose-300 text-xs font-semibold">
                    <i class="pi pi-exclamation-circle text-rose-500 text-sm"></i>
                    <span>{{ form.errors.name }}</span>
                </div>

                <!-- Responsive Grid Layout -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <!-- Category * -->
                    <div>
                        <BaseSelect
                            v-model="form.category"
                            label="Category"
                            required
                            :options="categoryOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Category"
                            :error="form.errors.category"
                            panelWidth="13rem"
                        />
                    </div>

                    <!-- If Category is Concrete: Show material_type (Concrete Grade) -->
                    <div v-if="isConcrete">
                        <BaseSelect
                            v-model="form.concrete_grade"
                            label="Material Type"
                            required
                            :options="concreteGradeOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Material Type (e.g. M30)"
                            @change="onConcreteGradeChange"
                            :error="form.errors.concrete_grade || form.errors.material_type"
                            panelWidth="14rem"
                        />
                    </div>

                    <!-- If Category is Raw Material: Show Product Dropdown & Raw Material Type Switcher -->
                    <template v-else-if="isRawMaterial">
                        <div>
                            <BaseSelect
                                v-model="form.product_id"
                                label="Product"
                                required
                                :options="productOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select Product"
                                @change="onProductChange"
                                :error="form.errors.product_id || form.errors.material_type"
                                panelWidth="15rem"
                            />
                        </div>

                        <!-- Raw Material Subtype (Aggregate / Cement) -->
                        <div>
                            <label class="block text-[10px] font-bold text-gray-700 dark:text-gray-200 mb-1">
                                Raw Material Type
                            </label>
                            <div class="flex items-center p-0.5 bg-gray-100 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 h-[38px]">
                                <button
                                    type="button"
                                    @click="rawMaterialType = 'Aggregate'"
                                    :class="rawMaterialType === 'Aggregate' ? 'bg-white dark:bg-gray-700 text-amber-700 dark:text-amber-300 font-bold shadow-2xs' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 font-medium'"
                                    class="flex-1 py-1.5 px-2 text-xs rounded-lg transition-all text-center cursor-pointer flex items-center justify-center gap-1.5"
                                >
                                    <!-- <i class="pi pi-box text-[10px]"></i> -->
                                    <span>Aggregate</span>
                                </button>
                                <button
                                    type="button"
                                    @click="rawMaterialType = 'Cement'"
                                    :class="rawMaterialType === 'Cement' ? 'bg-white dark:bg-gray-700 text-amber-700 dark:text-amber-300 font-bold shadow-2xs' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 font-medium'"
                                    class="flex-1 py-1.5 px-2 text-xs rounded-lg transition-all text-center cursor-pointer flex items-center justify-center gap-1.5"
                                >
                                    <!-- <i class="pi pi-filter text-[10px]"></i> -->
                                    <span>Cement</span>
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- If Category is Other: Show Material / Item Name Input -->
                    <div v-else>
                        <BaseInput
                            v-model="form.concrete_grade"
                            label="Material / Item Name"
                            required
                            placeholder="Enter Material Name"
                            :error="form.errors.concrete_grade || form.errors.material_type"
                        />
                    </div>

                    <!-- QC Test Type * -->
                    <div>
                        <BaseSelect
                            v-model="form.qc_test_type"
                            label="QC Test Type"
                            required
                            :options="qcTestTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Test Type"
                            @change="onQcTestTypeChange"
                            :error="form.errors.qc_test_type || form.errors.name"
                            panelWidth="19rem"
                        />
                    </div>

                    <!-- Grade Strength (Only for Concrete) -->
                    <div v-if="isConcrete">
                        <BaseInput
                            v-model="form.grade_strength"
                            label="Grade Strength"
                            placeholder="30.00"
                            @blur="onGradeStrengthChange"
                            :error="form.errors.grade_strength"
                        />
                    </div>

                    <!-- Standard -->
                    <div>
                        <BaseInput
                            v-model="form.standard"
                            label="Standard"
                            placeholder="IS 516"
                            :error="form.errors.standard || form.errors.standard_reference"
                        />
                    </div>

                    <!-- Unit -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[10px] font-bold text-gray-700 dark:text-gray-200">
                                Unit
                            </label>
                            <button  v-if="$page.props.auth.user.roles[0].id<=3"
                                type="button"
                                @click="openAddUnitModal"
                                class="inline-flex items-center gap-1 text-[10px] font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 hover:underline cursor-pointer"
                                title="Add new unit to qc_units"
                            >
                                <i class="pi pi-plus text-[9px]"></i>
                                <span>Add Unit</span>
                            </button>
                        </div>
                        <BaseSelect
                            v-model="form.unit"
                            :options="unitOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Unit (e.g. MPa)"
                            :filter="true"
                            :error="form.errors.unit"
                            panelWidth="14rem"
                        />
                    </div>
                </div>
            </div>
            <!-- SECTION 2: QC PARAMETERS -->
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-extrabold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            QC Parameters
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60">
                            {{ parameters.length }}
                        </span>
                    </div>

                    <!-- Add Parameter Button -->
                    <button
                        type="button"
                        @click="openAddParameter"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-xl text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/50 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 border border-indigo-200/80 dark:border-indigo-800/80 transition-all cursor-pointer shadow-2xs"
                    >
                        <i class="pi pi-plus text-[10px]"></i>
                        <span>Add Parameter</span>
                    </button>
                </div>

                <!-- Parameters Table Box -->
                <div class="border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden shadow-2xs">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-gray-50/80 dark:bg-gray-800/60 border-b border-gray-200/80 dark:border-gray-800 text-gray-600 dark:text-gray-300 font-bold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-4 w-12 text-center">#</th>
                                <th class="py-3 px-4">Parameter</th>
                                <th class="py-3 px-4">Age</th>
                                <th class="py-3 px-4">Target</th>
                                <th class="py-3 px-4">Min</th>
                                <th class="py-3 px-4 text-right w-36">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                            <tr
                                v-for="(param, idx) in parameters"
                                :key="idx"
                                class="hover:bg-gray-50/60 dark:hover:bg-gray-800/40 transition-colors"
                            >
                                <td class="py-3 px-4 text-center font-mono text-gray-500 font-semibold">
                                    {{ idx + 1 }}
                                </td>
                                <td class="py-3 px-4 font-bold text-gray-900 dark:text-gray-100">
                                    {{ param.name }}
                                </td>
                                <td class="py-3 px-4 font-semibold text-gray-600 dark:text-gray-300">
                                    <span class="inline-block px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 font-mono text-[11px]">
                                        {{ param.age || '—' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-bold text-indigo-600 dark:text-indigo-400 font-mono">
                                    {{ param.target || '—' }}
                                </td>
                                <td class="py-3 px-4 font-bold text-gray-700 dark:text-gray-300 font-mono">
                                    {{ param.min || '—' }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="openEditParameter(idx)"
                                            class="px-2.5 py-1 text-xs font-semibold rounded-lg text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 transition-colors cursor-pointer"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteParameter(idx)"
                                            class="px-2.5 py-1 text-xs font-semibold rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/60 transition-colors cursor-pointer"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty Parameters Notice -->
                            <tr v-if="parameters.length === 0">
                                <td colspan="6" class="py-8 text-center text-gray-400 text-xs">
                                    <i class="pi pi-inbox text-lg mb-1 block"></i>
                                    No parameters added yet. Click <span class="font-bold text-indigo-600 dark:text-indigo-400">[+ Add Parameter]</span> or select a Concrete Grade to populate standard values.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- FOOTER ACTIONS -->
            <div class="px-6 py-4 bg-gray-50/80 dark:bg-gray-800/60 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <!-- Left: Reset -->
                <button
                    type="button"
                    @click="resetForm"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold rounded-xl text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/60 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 transition-colors cursor-pointer shadow-2xs"
                >
                    <i class="pi pi-refresh text-xs"></i>
                    <span>Reset</span>
                </button>

                <!-- Right: Cancel & Save Master -->
                <div class="flex items-center gap-2.5">
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
                            'px-5 py-2 text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-2 cursor-pointer disabled:opacity-50 text-white',
                            isEditing
                                ? 'bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-amber-500/20'
                                : 'bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 shadow-indigo-600/20'
                        ]"
                    >
                        <i v-if="form.processing" class="pi pi-spin pi-spinner text-xs"></i>
                        <i v-else :class="isEditing ? 'pi pi-check' : 'pi pi-save'" class="text-xs"></i>
                        <span>{{ isEditing ? 'Update Master' : 'Save Master' }}</span>
                    </button>
                </div>
            </div>
        </form>

        <!-- ADD / EDIT PARAMETER MODAL -->
        <Dialog
            v-model:visible="showParamModal"
            modal
            :header="editingParamIndex !== null ? 'Edit QC Parameter' : 'Add QC Parameter'"
            :style="{ width: '420px' }"
            class="rounded-2xl"
        >
            <div class="space-y-3.5 py-2">
                <div>
                    <BaseInput
                        v-model="paramForm.name"
                        label="Parameter Name"
                        required
                        placeholder="e.g. 7-Day, 15-Day, 28-Day"
                    />
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <BaseInput
                            v-model="paramForm.age"
                            label="Age (Days)"
                            placeholder="e.g. 7 d"
                        />
                    </div>
                    <div>
                        <BaseInput
                            v-model="paramForm.target"
                            label="Target"
                            placeholder="19.50"
                        />
                    </div>
                    <div>
                        <BaseInput
                            v-model="paramForm.min"
                            label="Min"
                            placeholder="18.00"
                        />
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end gap-2 pt-3">
                    <button
                        type="button"
                        @click="showParamModal = false"
                        class="px-3.5 py-1.5 text-xs font-semibold rounded-xl text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="saveParameter"
                        class="px-4 py-1.5 text-xs font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-xs cursor-pointer"
                    >
                        {{ editingParamIndex !== null ? 'Update' : 'Add' }}
                    </button>
                </div>
            </template>
        </Dialog>

        <!-- ADD QC UNIT MODAL (qc_units) -->
        <Dialog
            v-model:visible="showAddUnitModal"
            modal
            header="Add QC Unit (qc_units)"
            :style="{ width: '420px' }"
            class="rounded-2xl"
        >
            <form @submit.prevent="saveUnit" class="space-y-3.5 py-2">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <BaseInput
                            v-model="unitForm.name"
                            label="Unit Name"
                            required
                            placeholder="e.g. Megapascal"
                            :error="unitErrors.name"
                            @input="onUnitSymbolOrNameInput"
                        />
                    </div>
                    <div>
                        <BaseInput
                            v-model="unitForm.symbol"
                            label="Symbol"
                            required
                            placeholder="e.g. MPa"
                            :error="unitErrors.symbol"
                            @input="onUnitSymbolOrNameInput"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <BaseInput
                            v-model="unitForm.code"
                            label="Code"
                            required
                            placeholder="e.g. MPA"
                            :error="unitErrors.code"
                        />
                    </div>
                    <div>
                        <BaseSelect
                            v-model="unitForm.dimension"
                            label="Dimension"
                            :options="dimensionOptions"
                            optionLabel="label"
                            optionValue="value"
                        />
                    </div>
                </div>
            </form>

            <template #footer>
                <div class="flex items-center justify-end gap-2 pt-3">
                    <button
                        type="button"
                        @click="showAddUnitModal = false"
                        class="px-3.5 py-1.5 text-xs font-semibold rounded-xl text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="addingUnit"
                        @click="saveUnit"
                        class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-xs cursor-pointer disabled:opacity-50"
                    >
                        <i v-if="addingUnit" class="pi pi-spin pi-spinner text-xs"></i>
                        <i v-else class="pi pi-plus text-xs"></i>
                        <span>Save to qc_units</span>
                    </button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
