<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';

const props = defineProps<{
    unit?: any;
    dimensions?: string[];
    isEditing?: boolean;
}>();

const dimensionOptions = (props.dimensions || ['mass', 'length', 'volume', 'pressure', 'force', 'ratio', 'temperature', 'density', 'time', 'other'])
    .map(d => ({ label: d.charAt(0).toUpperCase() + d.slice(1), value: d }));

const form = useForm({
    name: props.unit?.name || '',
    code: props.unit?.code || '',
    symbol: props.unit?.symbol || '',
    dimension: props.unit?.dimension || 'other',
    is_active: props.unit !== undefined ? Boolean(props.unit.is_active) : true,
});

const onNameInput = () => {
    if (!props.isEditing && !form.code && form.name) {
        form.code = form.name.toUpperCase().replace(/[^A-Z0-9]/g, '_').replace(/_+/g, '_').slice(0, 15);
    }
};

const submit = () => {
    form.code = (form.code || '').toUpperCase().trim();
    if (props.isEditing && props.unit?.id) {
        form.put(route('quality.config.units.update', props.unit.id));
    } else {
        form.post(route('quality.config.units.store'));
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <BaseCard class="p-5 sm:p-6 space-y-4">
            <div class="border-b border-gray-100 dark:border-gray-800 pb-3">
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Measurement Unit Properties</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Define unit symbol, code, and physical dimension.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <BaseInput
                    v-model="form.name"
                    @input="onNameInput"
                    label="Unit Name"
                    required
                    placeholder="e.g. Megapascal"
                />
                <BaseInput
                    v-model="form.code"
                    label="Unit Code"
                    required
                    placeholder="e.g. MPA"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <BaseInput
                    v-model="form.symbol"
                    label="Display Symbol"
                    required
                    placeholder="e.g. N/mm² or MPa"
                />
                <BaseSelect
                    v-model="form.dimension"
                    label="Physical Dimension"
                    :options="dimensionOptions"
                    optionLabel="label"
                    optionValue="value"
                />
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input
                    type="checkbox"
                    id="unit_active"
                    v-model="form.is_active"
                    class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                />
                <label for="unit_active" class="text-xs font-semibold text-gray-700 dark:text-gray-300 cursor-pointer">
                    Active (Available across all parameter definitions)
                </label>
            </div>
        </BaseCard>

        <!-- Actions -->
        <div class="flex items-center justify-between pt-2">
            <Link
                :href="route('quality.config.units.index')"
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
                <span>{{ isEditing ? 'Update Unit' : 'Save Unit' }}</span>
            </button>
        </div>
    </form>
</template>
