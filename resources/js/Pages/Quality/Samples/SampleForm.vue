<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';

const props = defineProps<{
    sample?: any;
    isEditing?: boolean;
    concreteGrades?: any[];
    materials?: any[];
    customers?: any[];
    dispatches?: any[];
    batches?: any[];
    testTypes?: any[];
}>();

const emit = defineEmits<{
    (e: 'saved'): void;
    (e: 'cancel'): void;
}>();

const form = useForm({
    sample_date: props.sample?.sample_date ? props.sample.sample_date.substring(0, 10) : new Date().toISOString().substring(0, 10),
    concrete_grade_id: props.sample?.concrete_grade_id || null,
    material_id: props.sample?.material_id || null,
    dispatch_id: props.sample?.dispatch_id || null,
    batch_id: props.sample?.batch_id || null,
    customer_id: props.sample?.customer_id || null,
    truck_no: props.sample?.truck_no || '',
    site_name: props.sample?.site_name || '',
    source_location: props.sample?.source_location || 'Batching Plant Discharge',
    slump_mm: props.sample?.slump_mm || 120,
    concrete_temp_c: props.sample?.concrete_temp_c || 28.0,
    ambient_temp_c: props.sample?.ambient_temp_c || 32.0,
    specimen_size: props.sample?.specimen_size || '150x150x150 mm',
    specimen_count: props.sample?.specimen_count || 6,
    curing_tank_id: props.sample?.curing_tank_id || 'Tank-1 (27±2°C)',
    sample_quantity: props.sample?.sample_quantity || '6 Cubes',
    test_type_ids: props.sample?.tests ? props.sample.tests.map((t: any) => t.test_type_id) : [],
    status: props.sample?.status || 'pending_test',
    remarks: props.sample?.remarks || '',
});

// Dropdown options
const concreteGradeOptions = computed(() => {
    return (props.concreteGrades || []).map(g => ({
        label: g.name ? `${g.name} (${g.design_type || g.code || 'Standard'})` : g.name,
        value: g.id,
    }));
});

const customerOptions = computed(() => {
    return (props.customers || []).map(c => ({
        label: c.legal_name || c.code || `Customer #${c.id}`,
        value: c.id,
    }));
});

const dispatchOptions = computed(() => {
    return (props.dispatches || []).map(d => {
        const truck = d.truck?.reg_number || d.truck?.machine_name || '';
        const grade = d.mix_design?.concrete_grade?.name || '';
        const client = d.customer?.legal_name ? ` - ${d.customer.legal_name}` : '';
        return {
            label: `${d.dispatch_no || 'Dispatch #' + d.id} ${truck ? '[' + truck + ']' : ''} ${grade ? '(' + grade + ')' : ''}${client}`,
            value: d.id,
            raw: d,
        };
    });
});

const batchOptions = computed(() => {
    return (props.batches || []).map(b => ({
        label: b.batch_no || `Batch #${b.id}`,
        value: b.id,
    }));
});

// Auto-fill from selected dispatch
const onDispatchChange = (dispatchId: number) => {
    if (!dispatchId) return;
    const found = (props.dispatches || []).find(d => d.id === dispatchId);
    if (!found) return;

    if (found.truck) {
        form.truck_no = found.truck.reg_number || found.truck.machine_name || form.truck_no;
    }
    if (found.customer_id) {
        form.customer_id = found.customer_id;
    }
    if (found.unload_site?.name) {
        form.site_name = found.unload_site.name;
    }
    if (found.batch_id) {
        form.batch_id = found.batch_id;
    }
    const mixGradeId = found.mix_design?.concrete_grade?.id;
    if (mixGradeId) {
        form.concrete_grade_id = mixGradeId;
    }
};

// Auto-scheduled test dates preview
const testSchedulePreview = computed(() => {
    if (!form.sample_date) return [];
    const base = new Date(form.sample_date);
    if (isNaN(base.getTime())) return [];

    const addDays = (d: Date, days: number) => {
        const res = new Date(d);
        res.setDate(res.getDate() + days);
        return res.toISOString().substring(0, 10);
    };

    return [
        { age: '7-Day', date: addDays(base, 7), specimens: '3 Cubes', targetPct: '65-75%' },
        { age: '28-Day', date: addDays(base, 28), specimens: '3 Cubes', targetPct: '100%' },
    ];
});

const submit = () => {
    if (props.isEditing && props.sample?.id) {
        form.put(route('quality.samples.update', props.sample.id), {
            onSuccess: () => emit('saved'),
        });
    } else {
        form.post(route('quality.samples.store'), {
            onSuccess: () => emit('saved'),
        });
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <!-- Main Form Card -->
        <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200/80 dark:border-gray-800 shadow-xs overflow-hidden">
            <!-- Header -->
            <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800 bg-gradient-to-r from-gray-50/90 via-white to-gray-50/50 dark:from-gray-800/60 dark:via-gray-900 dark:to-gray-800/40 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white flex items-center justify-center shadow-md shadow-indigo-500/20">
                        <i class="pi pi-box text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-gray-900 dark:text-gray-100 tracking-tight">
                            {{ isEditing ? 'Edit Concrete Cube Sample' : 'Concrete Grade Sampling & Cube Casting' }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Log concrete cube casting from batching/transit mixer with fresh concrete slump and curing setup (IS 516 / IS 1199).
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60">
                        <i class="pi pi-calendar text-[11px]"></i>
                        <span>Casting Date: {{ form.sample_date }}</span>
                    </span>
                </div>
            </div>

            <div class="p-6 space-y-6">
                <!-- SECTION 1: BATCH & DISPATCH IDENTIFICATION -->
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xs font-extrabold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                            1. Batch & Transit Mixer Details
                        </span>
                        <div class="h-px flex-1 bg-gray-100 dark:bg-gray-800"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Dispatch Docket / TM -->
                        <div>
                            <BaseSelect
                                v-model="form.dispatch_id"
                                label="Dispatch Docket / Transit Mixer"
                                :options="dispatchOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select Dispatch Docket"
                                filter
                                @change="onDispatchChange"
                                :error="form.errors.dispatch_id"
                                panelWidth="22rem"
                                hint="Auto-fills client, site, truck, and grade"
                            />
                        </div>

                        <!-- Concrete Grade * -->
                        <div>
                            <BaseSelect
                                v-model="form.concrete_grade_id"
                                label="Concrete Grade *"
                                required
                                :options="concreteGradeOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select Grade (e.g. M30)"
                                filter
                                :error="form.errors.concrete_grade_id"
                                panelWidth="16rem"
                            />
                        </div>

                        <!-- Casting Date -->
                        <div>
                            <BaseInput
                                v-model="form.sample_date"
                                type="date"
                                label="Casting Date *"
                                required
                                :error="form.errors.sample_date"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                        <!-- Customer -->
                        <div>
                            <BaseSelect
                                v-model="form.customer_id"
                                label="Customer / Client"
                                :options="customerOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select Client"
                                filter
                                :error="form.errors.customer_id"
                                panelWidth="18rem"
                            />
                        </div>

                        <!-- Site Name -->
                        <div>
                            <BaseInput
                                v-model="form.site_name"
                                label="Project / Site Location"
                                placeholder="e.g. Metro Pillar P-42"
                                :error="form.errors.site_name"
                            />
                        </div>

                        <!-- Truck Reg No -->
                        <div>
                            <BaseInput
                                v-model="form.truck_no"
                                label="Transit Mixer (TM No)"
                                placeholder="e.g. KA-04-AB-1234"
                                :error="form.errors.truck_no"
                            />
                        </div>

                        <!-- Batch No -->
                        <div>
                            <BaseSelect
                                v-model="form.batch_id"
                                label="Batch Number"
                                :options="batchOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select Batch No"
                                filter
                                :error="form.errors.batch_id"
                            />
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: FRESH CONCRETE ON-SITE CHECKS -->
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xs font-extrabold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                            2. Fresh Concrete Quality Checks (At Casting)
                        </span>
                        <div class="h-px flex-1 bg-gray-100 dark:bg-gray-800"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Slump (mm) -->
                        <div>
                            <BaseInput
                                v-model="form.slump_mm"
                                type="number"
                                label="Slump (mm) *"
                                required
                                placeholder="120"
                                hint="Standard slump cone test (IS 1199)"
                                :error="form.errors.slump_mm"
                            />
                        </div>

                        <!-- Concrete Temp -->
                        <div>
                            <BaseInput
                                v-model="form.concrete_temp_c"
                                type="number"
                                step="0.5"
                                label="Concrete Temp (°C)"
                                placeholder="28.0"
                                hint="Temperature at discharge (≤ 35°C)"
                                :error="form.errors.concrete_temp_c"
                            />
                        </div>

                        <!-- Ambient Temp -->
                        <div>
                            <BaseInput
                                v-model="form.ambient_temp_c"
                                type="number"
                                step="0.5"
                                label="Ambient Temp (°C)"
                                placeholder="32.0"
                                hint="Site / Plant air temperature"
                                :error="form.errors.ambient_temp_c"
                            />
                        </div>

                        <!-- Sampling Location -->
                        <div>
                            <BaseInput
                                v-model="form.source_location"
                                label="Sampling Point"
                                placeholder="e.g. TM Chute Discharge"
                                :error="form.errors.source_location"
                            />
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: SPECIMEN CASTING & CURING -->
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xs font-extrabold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                            3. Specimen Casting & Curing Tank Setup
                        </span>
                        <div class="h-px flex-1 bg-gray-100 dark:bg-gray-800"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Mould Size -->
                        <div>
                            <BaseSelect
                                v-model="form.specimen_size"
                                label="Cube Mould Dimension"
                                :options="[
                                    { label: '150 x 150 x 150 mm (Standard)', value: '150x150x150 mm' },
                                    { label: '100 x 100 x 100 mm (Small Aggregate)', value: '100x100x100 mm' },
                                    { label: '150 mm dia x 300 mm (Cylinder)', value: '150x300 mm Cylinder' }
                                ]"
                                optionLabel="label"
                                optionValue="value"
                            />
                        </div>

                        <!-- Number of specimens -->
                        <div>
                            <BaseInput
                                v-model="form.specimen_count"
                                type="number"
                                label="Specimens Cast (Quantity) *"
                                required
                                placeholder="6"
                                hint="Typically 6 cubes (3 for 7-Day, 3 for 28-Day)"
                                :error="form.errors.specimen_count"
                            />
                        </div>

                        <!-- Curing Tank -->
                        <div>
                            <BaseInput
                                v-model="form.curing_tank_id"
                                label="Curing Tank / Method"
                                placeholder="Tank-1 (Water 27±2°C)"
                                hint="Standard curing tank as per IS 516"
                                :error="form.errors.curing_tank_id"
                            />
                        </div>
                    </div>
                </div>

                <!-- AUTOMATED TESTING SCHEDULE PREVIEW CARD -->
                <div class="rounded-2xl border border-indigo-200/80 dark:border-indigo-800/80 bg-gradient-to-r from-indigo-50/60 via-purple-50/40 to-white dark:from-indigo-950/40 dark:via-purple-950/20 dark:to-gray-900 p-4.5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-extrabold uppercase tracking-wider text-indigo-700 dark:text-indigo-300 flex items-center gap-1.5">
                            <i class="pi pi-clock text-xs"></i>
                            Automated Lab Crushing Schedule (Auto-Generated on Save)
                        </span>
                        <span class="text-[11px] font-bold text-gray-500 dark:text-gray-400">
                            Based on Product Grade QC Master
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div
                            v-for="sched in testSchedulePreview"
                            :key="sched.age"
                            class="bg-white/80 dark:bg-gray-800/80 rounded-xl p-3.5 border border-indigo-100 dark:border-indigo-900/60 shadow-2xs flex items-center justify-between"
                        >
                            <div class="space-y-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-md font-mono font-black text-xs bg-indigo-600 text-white">
                                        {{ sched.age }}
                                    </span>
                                    <span class="text-xs font-bold text-gray-900 dark:text-gray-100">
                                        Due Date: {{ sched.date }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                    Specimens to test: <strong class="text-gray-700 dark:text-gray-200">{{ sched.specimens }}</strong>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-[10px] uppercase font-bold text-gray-400">Target Level</div>
                                <div class="text-xs font-mono font-extrabold text-indigo-600 dark:text-indigo-400">{{ sched.targetPct }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 4: REMARKS -->
                <div>
                    <BaseInput
                        v-model="form.remarks"
                        label="Sampling Notes / Remarks"
                        placeholder="e.g. Normal workability, good cohesiveness, pumpable mix."
                        :error="form.errors.remarks"
                    />
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="px-6 py-4 bg-gray-50/80 dark:bg-gray-800/60 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <Link
                    :href="route('quality.samples.index')"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/60 dark:hover:bg-gray-700 transition-colors inline-block"
                >
                    <i class="pi pi-arrow-left text-[10px] mr-1"></i> Back to Samples
                </Link>

                <div class="flex items-center gap-2.5">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        :class="[
                            'px-6 py-2.5 text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-2 cursor-pointer disabled:opacity-50 text-white',
                            isEditing
                                ? 'bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-amber-500/20'
                                : 'bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 shadow-indigo-600/20'
                        ]"
                    >
                        <i v-if="form.processing" class="pi pi-spin pi-spinner text-xs"></i>
                        <i v-else :class="isEditing ? 'pi pi-check' : 'pi pi-save'" class="text-xs"></i>
                        <span>{{ isEditing ? 'Update Sample' : 'Log Sample & Generate Schedule' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</template>
