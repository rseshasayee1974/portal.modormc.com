<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';

const props = defineProps<{
    parameter?: any;
    testTypes: any[];
    selectedTestTypeId?: number;
    units: any[];
    ruleTypes: string[];
    ruleTypeLabels: Record<string, string>;
    isEditing?: boolean;
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

const ruleTypeSelectOptions = computed(() => {
    const list = [
        { label: 'None (No Criteria)', value: '' },
        { label: 'Min / Max Range (Acceptable Interval)', value: 'MIN_MAX' },
        { label: 'Target Value ± Tolerance', value: 'TARGET_TOLERANCE' },
        { label: 'Minimum Threshold (≥ Value)', value: 'MIN_ONLY' },
        { label: 'Maximum Limit (≤ Value)', value: 'MAX_ONLY' },
        { label: 'Pass / Fail Condition (Boolean)', value: 'PASS_FAIL' },
        { label: 'Exact Nominal Value', value: 'EXACT_VALUE' }
    ];
    return list;
});

const form = useForm({
    test_type_id: props.parameter?.test_type_id || props.selectedTestTypeId || props.testTypes?.[0]?.id,
    code: props.parameter?.code || '',
    name: props.parameter?.name || '',
    data_type: props.parameter?.data_type || 'numeric',
    unit: props.parameter?.unit || '',
    is_required: props.parameter !== undefined ? Boolean(props.parameter.is_required) : true,
    is_calculated: props.parameter !== undefined ? Boolean(props.parameter.is_calculated) : false,
    formula: props.parameter?.formula || '',
    default_value: props.parameter?.default_value || '',
    display_order: props.parameter?.display_order !== undefined ? Number(props.parameter.display_order) : 10,
    rule_type: props.parameter?.rule_type || '',
    min_value: props.parameter?.min_value !== undefined && props.parameter?.min_value !== null ? Number(props.parameter.min_value) : null,
    max_value: props.parameter?.max_value !== undefined && props.parameter?.max_value !== null ? Number(props.parameter.max_value) : null,
    target_value: props.parameter?.target_value !== undefined && props.parameter?.target_value !== null ? Number(props.parameter.target_value) : null,
    tolerance: props.parameter?.tolerance !== undefined && props.parameter?.tolerance !== null ? Number(props.parameter.tolerance) : null,
    standard_reference: props.parameter?.standard_reference || '',
});

const onNameInput = () => {
    if (!props.isEditing && !form.code && form.name) {
        form.code = form.name.toUpperCase().replace(/[^A-Z0-9]/g, '_').replace(/_+/g, '_').slice(0, 15);
    }
};

const submit = () => {
    form.code = (form.code || '').toUpperCase().trim();
    if (props.isEditing && props.parameter?.id) {
        form.put(route('quality.config.test-parameters.update', props.parameter.id));
    } else {
        form.post(route('quality.config.test-parameters.store'));
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <!-- Card 1: Parameter Definition -->
        <BaseCard class="p-5 sm:p-6 space-y-4">
            <div class="border-b border-gray-100 dark:border-gray-800 pb-3">
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Parameter Basic Identification</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Define variable name, code, and parent test definition.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <BaseSelect
                    v-model="form.test_type_id"
                    label="Parent Test Definition"
                    :options="testTypeOptions"
                    optionLabel="label"
                    optionValue="value"
                    required
                />
                <div class="grid grid-cols-2 gap-3">
                    <BaseInput
                        v-model="form.code"
                        label="Param Code"
                        required
                        placeholder="e.g. SLUMP"
                    />
                    <BaseInput
                        v-model.number="form.display_order"
                        type="number"
                        label="Display Order"
                        placeholder="10"
                    />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <BaseInput
                    v-model="form.name"
                    @input="onNameInput"
                    label="Parameter Display Name"
                    required
                    placeholder="e.g. Slump Value"
                />
                <BaseSelect
                    v-model="form.unit"
                    label="Measurement Unit"
                    :options="unitOptions"
                    optionLabel="label"
                    optionValue="value"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <BaseInput
                    v-model="form.default_value"
                    label="Default / Pre-filled Value (Optional)"
                    placeholder="e.g. 100"
                />
                <div class="flex items-center gap-6 pt-5">
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            v-model="form.is_required"
                            class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span>Mandatory Field</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            v-model="form.is_calculated"
                            class="rounded border-gray-300 dark:border-gray-700 text-purple-600 focus:ring-purple-500"
                        />
                        <span>Calculated via Formula</span>
                    </label>
                </div>
            </div>

            <!-- Formula Engine Box (if is_calculated) -->
            <div v-if="form.is_calculated" class="p-4 bg-purple-50/70 dark:bg-purple-950/40 rounded-xl border border-purple-200 dark:border-purple-800 space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-bold text-purple-900 dark:text-purple-200">
                        Calculation Math Formula <span class="text-rose-500">*</span>
                    </label>
                    <span class="text-[10px] font-mono text-purple-600 dark:text-purple-400">
                        Use parameter codes like (LOAD * 1000) / AREA
                    </span>
                </div>
                <input
                    v-model="form.formula"
                    type="text"
                    placeholder="e.g. (W1 - W2) / W2 * 100"
                    class="w-full px-3 py-2 bg-white dark:bg-gray-900 border border-purple-300 dark:border-purple-700 rounded-lg text-xs font-mono font-bold text-purple-900 dark:text-purple-200 focus:ring-2 focus:ring-purple-500"
                />
            </div>
        </BaseCard>

        <!-- Card 2: Acceptance Criteria & Compliance Rule -->
        <BaseCard class="p-5 sm:p-6 space-y-4">
            <div class="border-b border-gray-100 dark:border-gray-800 pb-3">
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Quality Acceptance Criteria & Standards</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Define passing thresholds for automatic pass/fail evaluation upon test execution.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <BaseSelect
                    v-model="form.rule_type"
                    label="Compliance Rule Type"
                    :options="ruleTypeSelectOptions"
                    optionLabel="label"
                    optionValue="value"
                />
                <BaseInput
                    v-model="form.standard_reference"
                    label="Standard Reference (IS / ASTM Spec)"
                    placeholder="e.g. IS 456 Table 2"
                />
            </div>

            <!-- Dynamic Threshold Inputs based on Rule Type -->
            <div v-if="form.rule_type === 'MIN_MAX'" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
                <BaseInput
                    v-model.number="form.min_value"
                    type="number"
                    step="any"
                    label="Minimum Acceptable Limit (≥)"
                    required
                    placeholder="e.g. 75"
                />
                <BaseInput
                    v-model.number="form.max_value"
                    type="number"
                    step="any"
                    label="Maximum Acceptable Limit (≤)"
                    required
                    placeholder="e.g. 125"
                />
            </div>

            <div v-else-if="form.rule_type === 'TARGET_TOLERANCE'" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
                <BaseInput
                    v-model.number="form.target_value"
                    type="number"
                    step="any"
                    label="Target Design Value"
                    required
                    placeholder="e.g. 100"
                />
                <BaseInput
                    v-model.number="form.tolerance"
                    type="number"
                    step="any"
                    label="Permissible Tolerance (±)"
                    required
                    placeholder="e.g. 25"
                />
            </div>

            <div v-else-if="form.rule_type === 'MIN_ONLY'" class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
                <BaseInput
                    v-model.number="form.min_value"
                    type="number"
                    step="any"
                    label="Minimum Threshold (≥ Value)"
                    required
                    placeholder="e.g. 30"
                />
            </div>

            <div v-else-if="form.rule_type === 'MAX_ONLY'" class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
                <BaseInput
                    v-model.number="form.max_value"
                    type="number"
                    step="any"
                    label="Maximum Limit (≤ Value)"
                    required
                    placeholder="e.g. 40"
                />
            </div>

            <div v-else-if="form.rule_type === 'EXACT_VALUE'" class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
                <BaseInput
                    v-model.number="form.target_value"
                    type="number"
                    step="any"
                    label="Exact Required Nominal Value"
                    required
                    placeholder="e.g. 0"
                />
            </div>
        </BaseCard>

        <!-- Actions -->
        <div class="flex items-center justify-between pt-2">
            <Link
                :href="route('quality.config.test-parameters.index', { test_type_id: form.test_type_id })"
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
                <span>{{ isEditing ? 'Update Parameter' : 'Save Parameter' }}</span>
            </button>
        </div>
    </form>
</template>
