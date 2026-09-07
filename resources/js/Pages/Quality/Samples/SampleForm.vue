<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

const props = defineProps<{
    sample?: any;
    isEditing?: boolean;
    materials: any[];
    suppliers: any[];
    customers: any[];
    inwards: any[];
    batches: any[];
    dispatches: any[];
    testTypes?: any[];
}>();

const form = useForm({
    sample_date: props.sample?.sample_date ? props.sample.sample_date.substring(0, 10) : new Date().toISOString().substring(0, 10),
    material_id: props.sample?.material_id || null,
    supplier_id: props.sample?.supplier_id || null,
    customer_id: props.sample?.customer_id || null,
    inward_id: props.sample?.inward_id || null,
    batch_id: props.sample?.batch_id || null,
    dispatch_id: props.sample?.dispatch_id || null,
    source_location: props.sample?.source_location || '',
    sample_quantity: props.sample?.sample_quantity || '',
    test_type_ids: props.sample?.tests ? props.sample.tests.map((t: any) => t.test_type_id) : (props.testTypes || []).map(t => t.id),
    status: props.sample?.status || 'pending_test',
    remarks: props.sample?.remarks || '',
});

const materialOptions = computed(() => {
    return (props.materials || []).map(m => ({
        label: `${m.title} (${m.code || m.material_code || 'N/A'})`,
        value: m.id
    }));
});

const supplierOptions = computed(() => {
    return (props.suppliers || []).map(s => ({
        label: s.legal_name || s.code || `Supplier #${s.id}`,
        value: s.id
    }));
});

const inwardOptions = computed(() => {
    return (props.inwards || []).map(i => ({
        label: i.inward_no || `Inward #${i.id}`,
        value: i.id
    }));
});

const batchOptions = computed(() => {
    return (props.batches || []).map(b => ({
        label: b.batch_no || `Batch #${b.id}`,
        value: b.id
    }));
});

const statusOptions = [
    { label: 'Pending Test', value: 'pending_test' },
    { label: 'Completed', value: 'completed' },
    { label: 'Rejected', value: 'rejected' }
];

const testSearchQuery = ref('');
const selectedCategory = ref('All');

const availableCategories = computed(() => {
    const cats = new Set<string>();
    (props.testTypes || []).forEach((t: any) => {
        if (t.category) cats.add(t.category);
    });
    return ['All', ...Array.from(cats)];
});

const filteredTestTypes = computed(() => {
    return (props.testTypes || []).filter((tt: any) => {
        const matchesCategory = selectedCategory.value === 'All' || tt.category === selectedCategory.value;
        const matchesSearch = !testSearchQuery.value || 
            tt.name.toLowerCase().includes(testSearchQuery.value.toLowerCase()) || 
            tt.code.toLowerCase().includes(testSearchQuery.value.toLowerCase());
        return matchesCategory && matchesSearch;
    });
});

const toggleTestTypeSelection = (id: number) => {
    const idx = form.test_type_ids.indexOf(id);
    if (idx > -1) {
        form.test_type_ids.splice(idx, 1);
    } else {
        form.test_type_ids.push(id);
    }
};

const selectAllTestTypes = () => {
    const idsToAdd = filteredTestTypes.value.map((t: any) => t.id);
    const newIds = new Set([...form.test_type_ids, ...idsToAdd]);
    form.test_type_ids = Array.from(newIds);
};

const deselectAllTestTypes = () => {
    if (selectedCategory.value === 'All' && !testSearchQuery.value) {
        form.test_type_ids = [];
    } else {
        const idsToRemove = new Set(filteredTestTypes.value.map((t: any) => t.id));
        form.test_type_ids = form.test_type_ids.filter(id => !idsToRemove.has(id));
    }
};

const getCategoryColor = (cat: string) => {
    switch (cat) {
        case 'Concrete': return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/50 dark:text-blue-300 dark:border-blue-800';
        case 'Aggregate': return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800';
        case 'Cement': return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800';
        case 'Water': return 'bg-cyan-50 text-cyan-700 border-cyan-200 dark:bg-cyan-950/50 dark:text-cyan-300 dark:border-cyan-800';
        case 'Admixture': return 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/50 dark:text-purple-300 dark:border-purple-800';
        default: return 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700';
    }
};

const submit = () => {
    if (props.isEditing && props.sample?.id) {
        form.put(route('quality.samples.update', props.sample.id));
    } else {
        form.post(route('quality.samples.store'));
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <BaseCard>
            <div class="space-y-6">
                <div class="border-b border-gray-100 dark:border-gray-800 pb-4">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
                        {{ isEditing ? `Edit Sample: ${sample?.sample_no || ''}` : 'Log New Quality Sample' }}
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Capture sampling point details, traceability references, and laboratory test assignments.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <BaseInput
                        v-model="form.sample_date"
                        type="date"
                        label="Sample Date"
                        required
                        :error="form.errors.sample_date"
                    />
                    <BaseSelect
                        v-model="form.material_id"
                        label="Material"
                        :options="materialOptions"
                        optionLabel="label"
                        optionValue="value"
                        required
                        :error="form.errors.material_id"
                        placeholder="Select Material..."
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <BaseSelect
                        v-model="form.supplier_id"
                        label="Supplier / Vendor"
                        :options="supplierOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="N/A (Internal)"
                        :error="form.errors.supplier_id"
                    />
                    <BaseSelect
                        v-model="form.inward_id"
                        label="Inward Receipt"
                        :options="inwardOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="N/A"
                        :error="form.errors.inward_id"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <BaseSelect
                        v-model="form.batch_id"
                        label="Batch Code"
                        :options="batchOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="N/A"
                        :error="form.errors.batch_id"
                    />
                    <BaseInput
                        v-model="form.sample_quantity"
                        label="Sample Quantity"
                        placeholder="e.g. 5 kg / 3 Cubes"
                        :error="form.errors.sample_quantity"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <BaseInput
                        v-model="form.source_location"
                        label="Source Location / Stockpile"
                        placeholder="e.g. Quarry / Silo 2 / Plant Hopper"
                        :error="form.errors.source_location"
                    />
                    <BaseSelect
                        v-model="form.status"
                        label="Sample Status"
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        :error="form.errors.status"
                    />
                </div>

                <!-- Assigned Tests (Only during create or when configuring) -->
                <div v-if="!isEditing" class="space-y-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">
                                Assigned Laboratory Tests
                            </label>
                            <span class="text-[11px] text-gray-500 dark:text-gray-400">
                                Select tests to automatically generate when logging this sample
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                {{ form.test_type_ids.length }} of {{ (props.testTypes || []).length }} selected
                            </span>
                            <button
                                type="button"
                                @click="selectAllTestTypes"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/50 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60 transition shadow-sm cursor-pointer"
                            >
                                <i class="pi pi-check text-[10px]"></i>
                                Select All
                            </button>
                            <button
                                type="button"
                                @click="deselectAllTestTypes"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 transition shadow-sm cursor-pointer"
                            >
                                <i class="pi pi-times text-[10px]"></i>
                                Clear
                            </button>
                        </div>
                    </div>

                    <!-- Search & Category Filter Pills -->
                    <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                        <div class="relative flex-1">
                            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
                            <input
                                v-model="testSearchQuery"
                                type="text"
                                placeholder="Search test name or code..."
                                class="w-full pl-8 pr-8 py-1.5 text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition shadow-inner"
                            />
                            <button
                                v-if="testSearchQuery"
                                type="button"
                                @click="testSearchQuery = ''"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs"
                            >
                                <i class="pi pi-times-circle"></i>
                            </button>
                        </div>

                        <div class="flex items-center gap-1 overflow-x-auto pb-1 sm:pb-0">
                            <button
                                v-for="cat in availableCategories"
                                :key="cat"
                                type="button"
                                @click="selectedCategory = cat"
                                class="px-2.5 py-1 text-[11px] font-bold rounded-lg transition whitespace-nowrap cursor-pointer"
                                :class="selectedCategory === cat 
                                    ? 'bg-indigo-600 text-white shadow-sm' 
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'"
                            >
                                {{ cat }}
                            </button>
                        </div>
                    </div>

                    <!-- Interactive Grid Tiles -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 max-h-64 overflow-y-auto p-2 bg-gray-50/70 dark:bg-gray-950/40 rounded-xl border border-gray-200/80 dark:border-gray-800">
                        <div
                            v-for="tt in filteredTestTypes"
                            :key="tt.id"
                            @click="toggleTestTypeSelection(tt.id)"
                            class="group relative flex items-start gap-2.5 p-2.5 rounded-xl border cursor-pointer select-none transition-all duration-150 shadow-sm"
                            :class="form.test_type_ids.includes(tt.id)
                                ? 'bg-indigo-50/70 dark:bg-indigo-950/40 border-indigo-300 dark:border-indigo-700 ring-1 ring-indigo-400 dark:ring-indigo-600'
                                : 'bg-white dark:bg-gray-800/90 border-gray-200 dark:border-gray-700/80 hover:border-indigo-200 hover:bg-gray-50/80 dark:hover:bg-gray-800'"
                        >
                            <div class="pt-0.5 flex items-center justify-center">
                                <input
                                    type="checkbox"
                                    :value="tt.id"
                                    :checked="form.test_type_ids.includes(tt.id)"
                                    @click.stop="toggleTestTypeSelection(tt.id)"
                                    class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-1.5">
                                    <span class="text-xs font-bold text-gray-900 dark:text-gray-100 truncate" :title="tt.name">
                                        {{ tt.name }}
                                    </span>
                                    <span
                                        v-if="tt.category"
                                        class="text-[9px] font-bold px-1.5 py-0.5 rounded border uppercase shrink-0"
                                        :class="getCategoryColor(tt.category)"
                                    >
                                        {{ tt.category }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span class="text-[10px] font-mono font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-1.5 py-0.5 rounded border border-indigo-200/50 dark:border-indigo-800/50">
                                        {{ tt.code }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-if="filteredTestTypes.length === 0" class="col-span-full py-6 text-center">
                            <i class="pi pi-filter-slash text-xl text-gray-400 dark:text-gray-500 mb-1"></i>
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                                No laboratory test types match your search or filter
                            </p>
                        </div>
                    </div>
                </div>

                <BaseInput
                    v-model="form.remarks"
                    label="Remarks & Notes"
                    placeholder="Sampling field observations..."
                    :error="form.errors.remarks"
                />

                <div class="pt-4 border-t border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <Link :href="route('quality.samples.index')">
                            <BaseButton type="button" variant="secondary" size="small">
                                <i class="pi pi-arrow-left mr-1.5 text-xs"></i> Back to Samples
                            </BaseButton>
                        </Link>
                        <BaseFormActions
                            :processing="form.processing"
                            :cancelHref="route('quality.samples.index')"
                            :submitText="isEditing ? 'Update Sample' : 'Log Sample & Assign Tests'"
                        />
                    </div>
                </div>
            </div>
        </BaseCard>
    </form>
</template>
