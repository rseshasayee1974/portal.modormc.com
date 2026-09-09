<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';

const props = withDefaults(defineProps<{
    parameter?: any;
    testTypes?: any[];
    selectedTestTypeId?: number;
    units?: any[];
    ruleTypes?: string[];
    ruleTypeLabels?: Record<string, string>;
    availableParameters?: any[];
    isEditing?: boolean;
    isExpansion?: boolean;
}>(), {
    testTypes: () => [],
    units: () => [],
    ruleTypes: () => [],
    ruleTypeLabels: () => ({}),
    availableParameters: () => [],
    isEditing: false,
    isExpansion: false,
});

const emit = defineEmits<{
    (e: 'saved'): void;
    (e: 'cancel'): void;
}>();

const testTypeOptions = computed(() => {
    return (props.testTypes || []).map(t => ({
        label: `${t.name} (${t.code})`,
        value: t.id
    }));
});

const unitOptions = computed(() => {
    return [
        { label: 'None / Unitless', value: '' },
        ...(props.units || []).map(u => ({
            label: `${u.name} (${u.symbol || u.code})`,
            value: u.symbol || u.code
        }))
    ];
});

const ruleTypeSelectOptions = [
    { label: 'None (Informational Only)', value: '' },
    { label: 'Min / Max Range (Acceptable Interval)', value: 'MIN_MAX' },
    { label: 'Target Value ± Tolerance', value: 'TARGET_TOLERANCE' },
    { label: 'Minimum Threshold (≥ Value)', value: 'MIN_ONLY' },
    { label: 'Maximum Limit (≤ Value)', value: 'MAX_ONLY' },
    { label: 'Pass / Fail Condition (Boolean)', value: 'PASS_FAIL' },
    { label: 'Exact Nominal Value', value: 'EXACT_VALUE' }
];

const isStandardPresets = [
    'IS 456',
    'IS 516',
    'IS 2386',
    'IS 383',
    'IS 10262',
    'IS 4031',
    'IS 9103',
    'ASTM C39'
];

const form = useForm({
    test_type_id: props.parameter?.test_type_id || props.selectedTestTypeId || props.testTypes?.[0]?.id,
    code: props.parameter?.code || '',
    name: props.parameter?.name || '',
    data_type: props.parameter?.data_type || 'numeric',
    unit: props.parameter?.unit || '',
    is_required: props.parameter !== undefined ? Boolean(props.parameter.is_required) : true,
    is_calculated: props.parameter !== undefined ? Boolean(props.parameter.is_calculated) : false,
    formula: props.parameter?.formula || '',
    default_value: props.parameter?.default_value ?? '',
    display_order: props.parameter?.display_order !== undefined ? Number(props.parameter.display_order) : 10,
    rule_type: props.parameter?.rule_type || '',
    min_value: props.parameter?.min_value !== undefined && props.parameter?.min_value !== null ? Number(props.parameter.min_value) : null,
    max_value: props.parameter?.max_value !== undefined && props.parameter?.max_value !== null ? Number(props.parameter.max_value) : null,
    target_value: props.parameter?.target_value !== undefined && props.parameter?.target_value !== null ? Number(props.parameter.target_value) : null,
    tolerance: props.parameter?.tolerance !== undefined && props.parameter?.tolerance !== null ? Number(props.parameter.tolerance) : null,
    standard_reference: props.parameter?.standard_reference || '',
});

watch(() => props.selectedTestTypeId, (newVal) => {
    if (!props.isEditing && newVal) {
        form.test_type_id = newVal;
    }
});

const onNameInput = () => {
    if (!props.isEditing && !form.code && form.name) {
        form.code = form.name.toUpperCase().replace(/[^A-Z0-9]/g, '_').replace(/_+/g, '_').slice(0, 20);
    }
};

const insertParamCode = (code: string) => {
    if (!form.formula) {
        form.formula = code;
    } else {
        form.formula = `${form.formula.trim()} ${code}`;
    }
};

const otherParameters = computed(() => {
    return (props.availableParameters || []).filter(p => !props.parameter?.id || p.id !== props.parameter.id);
});

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.test_type_id = props.selectedTestTypeId || props.testTypes?.[0]?.id;
    form.is_required = true;
    form.is_calculated = false;
    form.display_order = 10;
};

const onCancel = () => {
    emit('cancel');
};

const submit = () => {
    form.code = (form.code || '').toUpperCase().trim();

    if (props.isEditing && props.parameter?.id) {
        form.put(route('quality.config.test-parameters.update', props.parameter.id), {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
            }
        });
    } else {
        form.post(route('quality.config.test-parameters.store'), {
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
            'transition-all duration-200',
            isExpansion
                ? 'p-5 bg-amber-50/20 dark:bg-amber-950/10 border-l-4 border-l-amber-500 rounded-r-2xl space-y-5'
                : 'bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 rounded-2xl shadow-xs overflow-hidden'
        ]"
    >
        <!-- Header for Top Mode -->
        <div
            v-if="!isExpansion"
            class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 bg-gradient-to-r from-gray-50/80 via-white to-indigo-50/20 dark:from-gray-800/60 dark:via-gray-900 dark:to-indigo-950/20 flex flex-col sm:flex-row sm:items-center justify-between gap-2"
        >
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60 flex items-center justify-center shrink-0">
                    <i class="pi pi-sliders-h text-sm font-bold"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                        {{ isEditing ? 'Edit Parameter Definition' : 'Define New Test Parameter' }}
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Configure measured variables, formula calculations, and automatic pass/fail criteria.
                    </p>
                </div>
            </div>

            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200/50 dark:border-indigo-800/50 self-start sm:self-center">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                Parameter Builder
            </span>
        </div>

        <!-- Header for Row Expansion Inline Edit Mode -->
        <div
            v-else
            class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-amber-200/60 dark:border-amber-800/60"
        >
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 flex items-center justify-center shrink-0">
                    <i class="pi pi-pencil text-xs"></i>
                </div>
                <div>
                    <span class="text-xs font-bold text-amber-900 dark:text-amber-200 uppercase tracking-wider">
                        Inline Parameter Editor:
                    </span>
                    <span class="ml-1.5 text-sm font-bold text-gray-900 dark:text-gray-100">
                        {{ parameter?.name || form.name }}
                    </span>
                    <span class="ml-2 font-mono text-[11px] font-bold px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200">
                        {{ parameter?.code || form.code }}
                    </span>
                </div>
            </div>

            <button
                type="button"
                @click="onCancel"
                class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex items-center gap-1 self-end sm:self-center cursor-pointer"
            >
                <i class="pi pi-times text-[10px]"></i> Close Editor
            </button>
        </div>

        <!-- Form Body Content -->
        <div :class="isExpansion ? 'space-y-4' : 'p-5 sm:p-6 space-y-5'">
            <!-- Section 1: Basic Variable Identification -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                        <i class="pi pi-tag text-[11px] text-indigo-500"></i> Variable Identification
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3.5">
                    <!-- Parent Test Definition (if multiple types available) -->
                    <div class="sm:col-span-2" v-if="!isExpansion">
                        <BaseSelect
                            v-model="form.test_type_id"
                            label="Parent Test Definition"
                            :options="testTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            required
                            :error="form.errors.test_type_id"
                        />
                    </div>

                    <!-- Parameter Display Name -->
                    <div :class="isExpansion ? 'sm:col-span-2' : 'sm:col-span-2'">
                        <BaseInput
                            v-model="form.name"
                            @input="onNameInput"
                            label="Parameter Name"
                            required
                            placeholder="e.g. Slump Value, 28-Day Strength"
                            :error="form.errors.name"
                        />
                    </div>

                    <!-- Param Code -->
                    <div :class="isExpansion ? 'sm:col-span-2' : 'sm:col-span-2'">
                        <BaseInput
                            v-model="form.code"
                            label="Variable Code"
                            required
                            placeholder="e.g. SLUMP, CTM_LOAD"
                            :error="form.errors.code"
                        />
                    </div>

                    <!-- Display Order -->
                    <div class="sm:col-span-2">
                        <BaseInput
                            v-model.number="form.display_order"
                            type="number"
                            label="Display #"
                            placeholder="10"
                            :error="form.errors.display_order"
                        />
                    </div>
                    <!-- Unit -->
                    <div class="sm:col-span-2">
                        <BaseSelect
                            v-model="form.unit"
                            label="Measurement Unit"
                            :options="unitOptions"
                            optionLabel="label"
                            optionValue="value"
                            :error="form.errors.unit"
                        />
                    </div>

                    <!-- Default Value -->
                    <div class="sm:col-span-2">
                        <BaseInput
                            v-model="form.default_value"
                            label="Default / Pre-filled Value"
                            placeholder="e.g. 100, 0, or leave blank"
                            :error="form.errors.default_value"
                        />
                    </div>

                    <!-- Mandatory & Formula Toggles -->
                    <div class="sm:col-span-4 flex items-center gap-5 pt-4">
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                v-model="form.is_required"
                                class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer"
                            />
                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">Mandatory</span>
                        </label>

                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                v-model="form.is_calculated"
                                class="rounded border-gray-300 dark:border-gray-700 text-purple-600 focus:ring-purple-500 w-4 h-4 cursor-pointer"
                            />
                            <span class="text-xs font-semibold text-purple-700 dark:text-purple-300">Calculated</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Formula Engine Box (Conditional on is_calculated) -->
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-if="form.is_calculated"
                    class="p-4 bg-purple-50/70 dark:bg-purple-950/40 rounded-xl border border-purple-200 dark:border-purple-800/80 space-y-3"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                        <label class="text-xs font-bold text-purple-900 dark:text-purple-200 flex items-center gap-1.5">
                            <i class="pi pi-calculator text-xs"></i>
                            Formula Math Expression <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-[11px] font-mono text-purple-600 dark:text-purple-400">
                            Math operators: + - * / ( )
                        </span>
                    </div>

                    <div class="relative">
                        <input
                            v-model="form.formula"
                            type="text"
                            placeholder="e.g. (LOAD * 1000) / AREA"
                            class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-900 border border-purple-300 dark:border-purple-700 rounded-xl text-xs font-mono font-bold text-purple-900 dark:text-purple-200 focus:ring-2 focus:ring-purple-500 focus:outline-none shadow-2xs"
                        />
                    </div>

                    <!-- Clickable Parameter Variable Chips -->
                    <div v-if="otherParameters.length > 0" class="flex flex-wrap items-center gap-1.5 pt-1">
                        <span class="text-[10px] font-bold text-purple-700 dark:text-purple-300 uppercase tracking-wider">
                            Insert Variables:
                        </span>
                        <button
                            v-for="p in otherParameters"
                            :key="p.id"
                            type="button"
                            @click="insertParamCode(p.code)"
                            class="px-2 py-0.5 rounded-md text-[11px] font-mono font-bold bg-white dark:bg-gray-900 hover:bg-purple-100 dark:hover:bg-purple-900/60 text-purple-800 dark:text-purple-200 border border-purple-200 dark:border-purple-700/80 shadow-2xs transition-all cursor-pointer select-none"
                            :title="`Click to insert ${p.code} (${p.name})`"
                        >
                            + {{ p.code }}
                        </button>
                    </div>
                </div>
            </transition>

            <!-- Section 2: Quality Acceptance Criteria & Compliance -->
            <div class="pt-3 border-t border-gray-100 dark:border-gray-800 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                        <i class="pi pi-check-circle text-[11px] text-emerald-500"></i> Acceptance Criteria & Standard Limits
                    </span>
                    <span class="text-[11px] text-gray-400 italic">Automated Pass / Fail</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3.5">
                    <!-- Compliance Rule Type -->
                    <div class="sm:col-span-6">
                        <BaseSelect
                            v-model="form.rule_type"
                            label="Compliance Rule Type"
                            :options="ruleTypeSelectOptions"
                            optionLabel="label"
                            optionValue="value"
                            :error="form.errors.rule_type"
                        />
                    </div>

                    <!-- Standard Reference -->
                    <div class="sm:col-span-6">
                        <BaseInput
                            v-model="form.standard_reference"
                            label="Standard Reference (IS / ASTM Spec)"
                            placeholder="e.g. IS 456 Table 2, IS 516"
                            :error="form.errors.standard_reference"
                        />
                    </div>
                </div>

                <!-- Standard Reference Quick Presets Micro-Bar -->
                <div class="flex flex-wrap items-center gap-1.5 -mt-1">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Quick Presets:</span>
                    <button
                        v-for="preset in isStandardPresets"
                        :key="preset"
                        type="button"
                        @click="form.standard_reference = preset"
                        :class="[
                            'px-2 py-0.5 rounded-md text-[11px] font-mono transition-all cursor-pointer select-none',
                            form.standard_reference === preset
                                ? 'bg-indigo-600 text-white font-bold shadow-2xs'
                                : 'bg-gray-100 dark:bg-gray-800 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300'
                        ]"
                    >
                        {{ preset }}
                    </button>
                </div>

                <!-- Dynamic Threshold Inputs based on Rule Type -->
                <div v-if="form.rule_type === 'MIN_MAX'" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 p-3.5 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200/70 dark:border-gray-700/70">
                    <BaseInput
                        v-model.number="form.min_value"
                        type="number"
                        step="any"
                        label="Minimum Acceptable Limit (≥)"
                        required
                        placeholder="e.g. 75"
                        :error="form.errors.min_value"
                    />
                    <BaseInput
                        v-model.number="form.max_value"
                        type="number"
                        step="any"
                        label="Maximum Acceptable Limit (≤)"
                        required
                        placeholder="e.g. 125"
                        :error="form.errors.max_value"
                    />
                </div>

                <div v-else-if="form.rule_type === 'TARGET_TOLERANCE'" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 p-3.5 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200/70 dark:border-gray-700/70">
                    <BaseInput
                        v-model.number="form.target_value"
                        type="number"
                        step="any"
                        label="Target Design Value"
                        required
                        placeholder="e.g. 100"
                        :error="form.errors.target_value"
                    />
                    <BaseInput
                        v-model.number="form.tolerance"
                        type="number"
                        step="any"
                        label="Permissible Tolerance (±)"
                        required
                        placeholder="e.g. 25"
                        :error="form.errors.tolerance"
                    />
                </div>

                <div v-else-if="form.rule_type === 'MIN_ONLY'" class="p-3.5 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200/70 dark:border-gray-700/70">
                    <BaseInput
                        v-model.number="form.min_value"
                        type="number"
                        step="any"
                        label="Minimum Threshold (≥ Value)"
                        required
                        placeholder="e.g. 30"
                        :error="form.errors.min_value"
                    />
                </div>

                <div v-else-if="form.rule_type === 'MAX_ONLY'" class="p-3.5 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200/70 dark:border-gray-700/70">
                    <BaseInput
                        v-model.number="form.max_value"
                        type="number"
                        step="any"
                        label="Maximum Limit (≤ Value)"
                        required
                        placeholder="e.g. 40"
                        :error="form.errors.max_value"
                    />
                </div>

                <div v-else-if="form.rule_type === 'EXACT_VALUE'" class="p-3.5 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200/70 dark:border-gray-700/70">
                    <BaseInput
                        v-model.number="form.target_value"
                        type="number"
                        step="any"
                        label="Exact Required Nominal Value"
                        required
                        placeholder="e.g. 0"
                        :error="form.errors.target_value"
                    />
                </div>
            </div>
        </div>

        <!-- Form Actions Footer Bar -->
        <div
            :class="[
                'flex items-center justify-between',
                isExpansion
                    ? 'pt-3 border-t border-amber-200/60 dark:border-amber-800/60'
                    : 'px-5 py-3.5 bg-gray-50/80 dark:bg-gray-800/60 border-t border-gray-100 dark:border-gray-800'
            ]"
        >
            <div>
                <button
                    v-if="!isExpansion"
                    type="button"
                    @click="resetForm"
                    class="text-xs font-semibold text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 flex items-center gap-1 cursor-pointer transition-colors"
                >
                    <i class="pi pi-refresh text-[10px]"></i> Reset Form
                </button>
            </div>

            <div class="flex items-center gap-2">
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
                    <span>{{ isEditing ? 'Update Parameter' : 'Save Parameter' }}</span>
                </button>
            </div>
        </div>
    </form>
</template>
