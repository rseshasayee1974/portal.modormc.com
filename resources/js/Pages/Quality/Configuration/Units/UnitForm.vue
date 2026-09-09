<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import ToggleSwitch from 'primevue/toggleswitch';

const props = withDefaults(
    defineProps<{
        unit?: any;
        dimensions?: string[];
        isEditing?: boolean;
        isModal?: boolean;
    }>(),
    {
        isEditing: false,
        isModal: false,
    }
);

const emit = defineEmits<{
    (e: 'saved', unit?: any): void;
    (e: 'cancel'): void;
}>();

const dimensionList = [
    { value: 'pressure', label: 'Pressure / Stress (MPa, N/mm²)', icon: 'pi pi-chart-line', color: 'emerald' },
    { value: 'force', label: 'Force / Load (kN, N)', icon: 'pi pi-bolt', color: 'indigo' },
    { value: 'mass', label: 'Mass / Weight (kg, g, ton)', icon: 'pi pi-box', color: 'blue' },
    { value: 'length', label: 'Length / Slump (mm, cm, m)', icon: 'pi pi-arrows-h', color: 'amber' },
    { value: 'area', label: 'Area (mm², cm²)', icon: 'pi pi-table', color: 'orange' },
    { value: 'volume', label: 'Volume (m³, L, mL)', icon: 'pi pi-database', color: 'violet' },
    { value: 'density', label: 'Density (kg/m³)', icon: 'pi pi-filter', color: 'cyan' },
    { value: 'temperature', label: 'Temperature (°C, °F)', icon: 'pi pi-sun', color: 'rose' },
    { value: 'time', label: 'Time / Age (days, hours)', icon: 'pi pi-clock', color: 'slate' },
    { value: 'ratio', label: 'Ratio / Percentage (%)', icon: 'pi pi-percentage', color: 'purple' },
    { value: 'other', label: 'Other Dimension', icon: 'pi pi-tag', color: 'gray' },
];

const dimensionOptions = computed(() => {
    return dimensionList.map(d => ({ label: d.label, value: d.value }));
});

// Common presets for quick setup
const quickPresets = [
    { name: 'Megapascal', code: 'MPA', symbol: 'MPa', dimension: 'pressure' },
    { name: 'Kilonewton', code: 'KN', symbol: 'kN', dimension: 'force' },
    { name: 'Millimeter', code: 'MM', symbol: 'mm', dimension: 'length' },
    { name: 'Kilogram per Cubic Meter', code: 'KG_M3', symbol: 'kg/m³', dimension: 'density' },
    { name: 'Degree Celsius', code: 'C', symbol: '°C', dimension: 'temperature' },
    { name: 'Percentage', code: 'PERCENT', symbol: '%', dimension: 'ratio' },
];

const form = useForm({
    name: props.unit?.name || '',
    code: props.unit?.code || '',
    symbol: props.unit?.symbol || '',
    dimension: props.unit?.dimension || 'pressure',
    is_active: props.unit !== undefined ? Boolean(props.unit.is_active) : true,
});

const onNameInput = () => {
    if (!props.isEditing && !form.code && form.name) {
        form.code = form.name.toUpperCase().replace(/[^A-Z0-9]/g, '_').replace(/_+/g, '_').slice(0, 20);
    }
};

const applyPreset = (preset: typeof quickPresets[0]) => {
    form.name = preset.name;
    form.code = preset.code;
    form.symbol = preset.symbol;
    form.dimension = preset.dimension;
};

// Compute dynamic sample preview value based on dimension
const previewReading = computed(() => {
    const s = form.symbol || 'unit';
    switch (form.dimension) {
        case 'pressure':
            return `30.00 ${s}`;
        case 'force':
            return `450.5 ${s}`;
        case 'length':
            return `125 ${s}`;
        case 'density':
            return `2410 ${s}`;
        case 'temperature':
            return `28.5 ${s}`;
        case 'time':
            return `28 ${s}`;
        case 'ratio':
            return `95.0 ${s}`;
        case 'mass':
            return `8.35 ${s}`;
        case 'volume':
            return `0.003375 ${s}`;
        default:
            return `100.0 ${s}`;
    }
});

const submit = () => {
    form.code = (form.code || '').toUpperCase().trim();
    if (props.isEditing && props.unit?.id) {
        form.put(route('quality.config.units.update', props.unit.id), {
            preserveScroll: true,
            onSuccess: () => emit('saved', form.data()),
        });
    } else {
        form.post(route('quality.config.units.store'), {
            preserveScroll: true,
            onSuccess: () => {
                if (!props.isModal) {
                    form.reset();
                }
                emit('saved', form.data());
            },
        });
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <!-- Main Form Card -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200/80 dark:border-gray-800 shadow-xs overflow-hidden">
            <!-- Card Header -->
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 bg-gradient-to-r from-gray-50/90 via-white to-gray-50/50 dark:from-gray-800/60 dark:via-gray-900 dark:to-gray-800/40 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white flex items-center justify-center shadow-md shadow-indigo-500/20">
                        <i class="pi pi-compass text-base"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-gray-900 dark:text-gray-100 tracking-tight">
                            {{ isEditing ? 'Edit Measurement Unit' : 'Define New QC Unit' }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Configure standard physical dimensions, display symbols, and reporting formats.
                        </p>
                    </div>
                </div>

                <!-- Active Switch -->
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-gray-100/80 dark:bg-gray-800 border border-gray-200/60 dark:border-gray-700">
                    <ToggleSwitch v-model="form.is_active" class="scale-90" />
                    <span class="text-xs font-bold" :class="form.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400'">
                        {{ form.is_active ? 'Active Unit' : 'Inactive' }}
                    </span>
                </div>
            </div>

            <!-- Quick Presets Bar (when creating new unit) -->
            <div v-if="!isEditing" class="px-6 py-2.5 bg-indigo-50/50 dark:bg-indigo-950/20 border-b border-indigo-100/60 dark:border-indigo-900/40 flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-bold text-indigo-700 dark:text-indigo-300 flex items-center gap-1">
                    <i class="pi pi-bolt text-[10px]"></i> Quick Presets:
                </span>
                <button
                    v-for="preset in quickPresets"
                    :key="preset.code"
                    type="button"
                    @click="applyPreset(preset)"
                    class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-700 hover:text-indigo-600 dark:hover:text-indigo-400 hover:shadow-2xs transition-all cursor-pointer"
                >
                    {{ preset.name }} ({{ preset.symbol }})
                </button>
            </div>

            <!-- Fields Container -->
            <div class="p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Unit Name -->
                    <BaseInput
                        v-model="form.name"
                        @input="onNameInput"
                        label="Unit Full Name"
                        required
                        placeholder="e.g. Megapascal, Kilonewton"
                        hint="Official descriptive name of the measurement unit"
                        :error="form.errors.name"
                    />

                    <!-- Unit Code -->
                    <BaseInput
                        v-model="form.code"
                        label="System Code (Unique Identifier)"
                        required
                        placeholder="e.g. MPA, KN, MM"
                        hint="Uppercase programmatic code for formula engine & API"
                        :error="form.errors.code"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Display Symbol -->
                    <BaseInput
                        v-model="form.symbol"
                        label="Display Symbol / Unit Text"
                        required
                        placeholder="e.g. MPa, N/mm², kN, mm"
                        hint="Displayed on lab entry forms, graphs, and customer certificates"
                        :error="form.errors.symbol"
                    />

                    <!-- Dimension -->
                    <BaseSelect
                        v-model="form.dimension"
                        label="Physical Dimension Category"
                        required
                        :options="dimensionOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select dimension category"
                        hint="Categorizes parameters for validation and conversion"
                        :error="form.errors.dimension"
                    />
                </div>

                <!-- LIVE REPORT PREVIEW BOX -->
                <div class="mt-4 rounded-xl border border-dashed border-indigo-200 dark:border-indigo-800/80 bg-indigo-50/30 dark:bg-indigo-950/20 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-indigo-700 dark:text-indigo-300 flex items-center gap-1.5">
                            <i class="pi pi-eye text-xs"></i> Live Certificate & Report Preview
                        </span>
                        <span class="text-[10px] text-gray-500 dark:text-gray-400">
                            Real-time rendering in QC Master & Test Certificates
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <!-- Preview Card 1: Symbol Badge -->
                        <div class="bg-white dark:bg-gray-800/80 rounded-lg p-3 border border-gray-100 dark:border-gray-700/60 shadow-2xs">
                            <div class="text-[10px] font-medium text-gray-500 dark:text-gray-400 mb-1">Unit Symbol Badge</div>
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-1 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-mono font-black text-xs rounded-lg border border-indigo-200 dark:border-indigo-800">
                                    {{ form.symbol || '—' }}
                                </span>
                                <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 truncate">
                                    {{ form.name || 'Untitled Unit' }}
                                </span>
                            </div>
                        </div>

                        <!-- Preview Card 2: Sample Test Reading -->
                        <div class="bg-white dark:bg-gray-800/80 rounded-lg p-3 border border-gray-100 dark:border-gray-700/60 shadow-2xs">
                            <div class="text-[10px] font-medium text-gray-500 dark:text-gray-400 mb-1">Sample Lab Reading</div>
                            <div class="text-sm font-extrabold font-mono text-emerald-600 dark:text-emerald-400">
                                {{ previewReading }}
                            </div>
                        </div>

                        <!-- Preview Card 3: Target Rule -->
                        <div class="bg-white dark:bg-gray-800/80 rounded-lg p-3 border border-gray-100 dark:border-gray-700/60 shadow-2xs">
                            <div class="text-[10px] font-medium text-gray-500 dark:text-gray-400 mb-1">QC Parameter Rule Format</div>
                            <div class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                                Target: <span class="font-bold text-indigo-600 dark:text-indigo-400 font-mono">{{ previewReading }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="px-6 py-4 bg-gray-50/80 dark:bg-gray-800/60 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <div>
                    <button
                        v-if="isModal"
                        type="button"
                        @click="emit('cancel')"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/60 dark:hover:bg-gray-700 transition-colors cursor-pointer"
                    >
                        Cancel
                    </button>
                    <Link
                        v-else
                        :href="route('quality.config.units.index')"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/60 dark:hover:bg-gray-700 transition-colors inline-block"
                    >
                        <i class="pi pi-arrow-left text-[10px] mr-1"></i> Back to Units
                    </Link>
                </div>

                <div class="flex items-center gap-2">
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
                        <i v-else :class="isEditing ? 'pi pi-check' : 'pi pi-plus'" class="text-xs"></i>
                        <span>{{ isEditing ? 'Update QC Unit' : 'Save QC Unit' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</template>
