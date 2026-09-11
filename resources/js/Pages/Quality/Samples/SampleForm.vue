<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';

const props = defineProps<{
    sample?: any;
    isEditing?: boolean;
    concreteGrades?: any[];
    materials?: any[];
    customers?: any[];
    dispatches?: any[];
    batches?: any[];
    testTypes?: any[];
    personnels?: any[];
}>();

const emit = defineEmits<{
    (e: 'saved'): void;
    (e: 'cancel'): void;
}>();

const form = useForm({
    sample_date: props.sample?.sample_date ? props.sample.sample_date.substring(0, 10) : new Date().toISOString().substring(0, 10),
    tested_by: props.sample?.tested_by || props.sample?.sampled_by || null,
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
    schedule_milestones: [7, 28],
    test_type_ids: props.sample?.tests ? props.sample.tests.map((t: any) => t.test_type_id) : [],
    status: props.sample?.status || 'pending_test',
    remarks: props.sample?.remarks || '',
});

// Dropdown options
const personnelOptions = computed(() => {
    return (props.personnels || []).map((p: any) => {
        const name = p.label || `${p.first_name || ''} ${p.last_name || ''}`.trim();
        const code = p.employee_code ? ` (${p.employee_code})` : '';
        return {
            label: name.includes('(') ? name : `${name}${code}`,
            value: p.id || p.value,
        };
    });
});
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
        const truck = d.truck?.registration || '';
        const grade = d.mix_design?.concrete_grade?.name || '';
        const client = d.customer?.legal_name ? ` — ${d.customer.legal_name}` : '';
        return {
            label: `${d.dispatch_no || 'Dispatch #' + d.id} ${truck ? '[' + truck + ']' : ''} ${grade ? '(' + grade + ')' : ''}${client}`,
            value: d.id,
            raw: d,
        };
    });
});

const batchOptions = computed(() => {
    return (props.batches || []).map(b => {
        // All details come from the latest dispatch linked to this batch
        const dispatch = b.dispatches?.[0];

        const grade = dispatch?.mix_design?.concrete_grade?.name
            || dispatch?.mixDesign?.concreteGrade?.name
            || dispatch?.mix_design?.grade
            || dispatch?.mixDesign?.grade
            || '';

        const customer = dispatch?.customer?.legal_name || '';
        const truck = dispatch?.truck?.registration || '';

        const date = b.start_time
            ? new Date(b.start_time).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: '2-digit' })
            : '';

        const batchNum = b.batch_no
            ? (String(b.batch_no).startsWith('B') ? String(b.batch_no) : 'B/' + b.batch_no)
            : 'Batch #' + b.id;

        // Label: Batch No · Grade · Customer · Truck · Date
        const parts = [batchNum];
        if (grade)    parts.push(grade);
        if (customer) parts.push(customer);
        if (truck)    parts.push(truck);
        if (date)     parts.push(date);

        return { label: parts.join('  ·  '), value: b.id, raw: b };
    });
});



const mouldOptions = [
    { label: '150 × 150 × 150 mm — Standard Cube (IS 516)', value: '150x150x150 mm' },
    { label: '100 × 100 × 100 mm — Small Aggregate (<20mm)', value: '100x100x100 mm' },
    { label: '150 mm Ø × 300 mm — Cylinder (IS 516 / ASTM C39)', value: '150x300 mm Cylinder' },
    { label: '70.6 × 70.6 × 70.6 mm — Cement Mortar Cube', value: '70.6x70.6x70.6 mm' },
];

const samplingPointOptions = [
    { label: 'Batching Plant Discharge', value: 'Batching Plant Discharge' },
    { label: 'Transit Mixer (TM) Chute', value: 'Transit Mixer (TM) Chute' },
    { label: 'Pump Outlet at Site', value: 'Pump Outlet at Site' },
    { label: 'Formwork / Placing Point', value: 'Formwork / Placing Point' },
    { label: 'Other', value: 'Other' },
];

// Track selected batch object for batch details card
const selectedBatch = ref<any>(null);

// Status label helper
const batchStatusMap: Record<number, { label: string; color: string }> = {
    1: { label: 'Planned',    color: 'gray' },
    2: { label: 'Loading',    color: 'amber' },
    3: { label: 'Dispatched', color: 'blue' },
    4: { label: 'Completed',  color: 'emerald' },
    5: { label: 'Cancelled',  color: 'rose' },
};

// Auto-fill from selected batch (via its latest dispatch)
const applyBatchSelection = (batchVal: any) => {
    // Safely unwrap if event object { originalEvent, value } was emitted
    const id = (batchVal && typeof batchVal === 'object' && 'value' in batchVal)
        ? batchVal.value
        : batchVal;

    if (!id) {
        selectedBatch.value = null;
        return;
    }

    const found = (props.batches || []).find((b: any) => b.id == id);
    if (!found) {
        selectedBatch.value = null;
        return;
    }
    selectedBatch.value = found;

    // Auto-fill casting date if empty and batch has start_time
    if (!form.sample_date && found.start_time) {
        form.sample_date = String(found.start_time).substring(0, 10);
    }

    // All auto-fills come from the latest dispatch on this batch
    const dispatch = found.dispatches?.[0];
    if (!dispatch) return;

    // Grade from dispatch → mixDesign → concreteGrade
    const gradeId = dispatch.mix_design?.concrete_grade?.id
        || dispatch.mixDesign?.concreteGrade?.id
        || dispatch.mix_design?.concrete_grade_id
        || dispatch.mixDesign?.concrete_grade_id;
    if (gradeId) form.concrete_grade_id = gradeId;

    // RMC concrete samples are identified by concrete_grade & batch, so material_id is set to null
    form.material_id = null;

    // Customer
    if (dispatch.customer_id) form.customer_id = dispatch.customer_id;

    // Site
    const siteName = dispatch.unload_site?.name || dispatch.unloadSite?.name;
    if (siteName) form.site_name = siteName;

    // Truck registration
    const truck = dispatch.truck;
    if (truck && truck.registration) form.truck_no = truck.registration;

    // Store dispatch id
    form.dispatch_id = dispatch.id;
};

const onBatchChange = (ev: any) => {
    applyBatchSelection(ev);
};

// Watch form.batch_id so that initial load, programmatic updates, and v-model changes trigger card display
watch(
    () => form.batch_id,
    (newVal) => {
        applyBatchSelection(newVal);
    },
    { immediate: true }
);

// Schedule milestones configuration (7 days, 15 days, 28 days)
// Support customers requesting either 2 tests (7d, 28d) or 3 tests (7d, 15d, 28d)
const availableMilestones = [
    { days: 7,  label: '7 Day',  color: 'amber',   targetPct: '65 – 75%',  desc: 'Early Compressive Strength' },
    { days: 15, label: '15 Day', color: 'emerald', targetPct: '85 – 90%',  desc: 'Intermediate Quality Check' },
    { days: 28, label: '28 Day', color: 'indigo',  targetPct: '100%',      desc: 'Standard Characteristic Strength' },
];

const initialMilestones = (() => {
    if (props.sample?.tests && props.sample.tests.length > 0) {
        const ages = Array.from(new Set(props.sample.tests.map((t: any) => Number(t.age_days)).filter(Boolean))).sort((a: any, b: any) => a - b);
        if (ages.length > 0) return ages;
    }
    return [7, 28];
})();

const selectedMilestones = ref<number[]>(initialMilestones);

// Keep form.schedule_milestones in sync
watch(selectedMilestones, (newVal) => {
    form.schedule_milestones = [...newVal].sort((a, b) => a - b);
}, { immediate: true, deep: true });

// Schedule preset switcher (2 tests vs 3 tests)
const schedulePreset = computed<'2-test' | '3-test' | 'custom'>({
    get() {
        const sorted = [...selectedMilestones.value].sort((a, b) => a - b);
        if (sorted.length === 2 && sorted[0] === 7 && sorted[1] === 28) return '2-test';
        if (sorted.length === 3 && sorted[0] === 7 && sorted[1] === 15 && sorted[2] === 28) return '3-test';
        return 'custom';
    },
    set(preset) {
        if (preset === '2-test') {
            selectedMilestones.value = [7, 28];
            form.specimen_count = 6;
            form.sample_quantity = '6 Cubes (7D, 28D)';
        } else if (preset === '3-test') {
            selectedMilestones.value = [7, 15, 28];
            form.specimen_count = 9;
            form.sample_quantity = '9 Cubes (7D, 15D, 28D)';
        }
    }
});

const toggleMilestone = (days: number) => {
    const idx = selectedMilestones.value.indexOf(days);
    if (idx >= 0) {
        if (selectedMilestones.value.length <= 1) return; // Keep at least 1 milestone
        selectedMilestones.value.splice(idx, 1);
    } else {
        selectedMilestones.value.push(days);
        selectedMilestones.value.sort((a, b) => a - b);
    }
    // Auto adjust specimen count (3 cubes per test stage)
    form.specimen_count = selectedMilestones.value.length * 3;
    form.sample_quantity = `${form.specimen_count} Cubes (${selectedMilestones.value.map(d => d + 'D').join(', ')})`;
};

const isMilestoneSelected = (days: number) => {
    return selectedMilestones.value.includes(days);
};

// Auto-scheduled test dates preview
const testSchedulePreview = computed(() => {
    if (!form.sample_date) return [];
    const base = new Date(form.sample_date);
    if (isNaN(base.getTime())) return [];

    const addDays = (d: Date, days: number) => {
        const res = new Date(d);
        res.setDate(res.getDate() + days);
        return {
            formatted: res.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }),
            dayOfWeek: res.toLocaleDateString('en-IN', { weekday: 'short' }),
        };
    };

    const cubesPerTest = Math.max(1, Math.round(Number(form.specimen_count || 6) / Math.max(1, selectedMilestones.value.length)));

    return availableMilestones
        .filter(m => selectedMilestones.value.includes(m.days))
        .map(m => {
            const dateInfo = addDays(base, m.days);
            return {
                ...m,
                age: m.label,
                date: dateInfo.formatted,
                dayOfWeek: dateInfo.dayOfWeek,
                specimens: `${cubesPerTest} Cubes`,
            };
        });
});

// Slump classification helper
const slumpClass = computed(() => {
    const s = Number(form.slump_mm);
    if (s < 25) return { label: 'Very Low', color: 'rose' };
    if (s <= 50) return { label: 'Low', color: 'amber' };
    if (s <= 100) return { label: 'Medium', color: 'blue' };
    if (s <= 175) return { label: 'High', color: 'emerald' };
    return { label: 'Very High', color: 'purple' };
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
    <form @submit.prevent="submit" class="space-y-5">

        <!-- ══ PAGE HEADER BANNER ══════════════════════════════════════════ -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-600 via-indigo-700 to-purple-700 shadow-lg shadow-indigo-500/20 px-6 py-5">
            <!-- bg decorative circles -->
            <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full bg-white/5 blur-xl pointer-events-none"></div>
            <div class="absolute right-24 bottom-0 w-24 h-24 rounded-full bg-purple-400/10 blur-lg pointer-events-none"></div>

            <div class="relative flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-white/15 backdrop-blur-sm flex items-center justify-center border border-white/20 shadow-inner">
                        <i class="pi pi-box text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-black text-white tracking-tight">
                            {{ isEditing ? 'Edit Concrete Sample' : 'Concrete Cube Sampling &amp; Casting' }}
                        </h2>
                        <p class="text-xs text-indigo-200 mt-0.5">
                            IS 516 · IS 1199 — Log fresh concrete QC sample and auto-schedule crushing tests
                        </p>
                    </div>
                </div>

                <!-- Casting date badge -->
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/15 backdrop-blur border border-white/20 text-white text-xs font-bold">
                        <i class="pi pi-calendar text-indigo-200 text-[11px]"></i>
                        <span class="text-indigo-100">Casting:</span>
                        <span>{{ form.sample_date }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ STEP PILLS ════════════════════════════════════════════════ -->
        <div class="flex items-center gap-1.5 px-1 overflow-x-auto scrollbar-none">
            <div class="flex items-center gap-1 shrink-0 px-3 py-1.5 rounded-full bg-indigo-600 text-white text-[11px] font-bold shadow-sm">
                <i class="pi pi-truck text-[10px]"></i>
                <span>1. Batch &amp; Dispatch</span>
            </div>
            <div class="w-6 h-px bg-gray-300 dark:bg-gray-700 shrink-0"></div>
            <div class="flex items-center gap-1 shrink-0 px-3 py-1.5 rounded-full bg-emerald-600 text-white text-[11px] font-bold shadow-sm">
                <i class="pi pi-check-circle text-[10px]"></i>
                <span>2. Fresh Concrete</span>
            </div>
            <div class="w-6 h-px bg-gray-300 dark:bg-gray-700 shrink-0"></div>
            <div class="flex items-center gap-1 shrink-0 px-3 py-1.5 rounded-full bg-amber-500 text-white text-[11px] font-bold shadow-sm">
                <i class="pi pi-th-large text-[10px]"></i>
                <span>3. Specimen Casting</span>
            </div>
            <div class="w-6 h-px bg-gray-300 dark:bg-gray-700 shrink-0"></div>
            <div class="flex items-center gap-1 shrink-0 px-3 py-1.5 rounded-full bg-purple-600 text-white text-[11px] font-bold shadow-sm">
                <i class="pi pi-clock text-[10px]"></i>
                <span>4. Crushing Schedule ({{ selectedMilestones.length }} Tests)</span>
            </div>
        </div>

        <!-- ══ SECTION 1: BATCH & DISPATCH ═══════════════════════════════ -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200/80 dark:border-gray-800 shadow-xs overflow-hidden">
            <!-- Section Header -->
            <div class="flex items-center gap-3 px-5 py-3.5 bg-indigo-50/60 dark:bg-indigo-950/30 border-b border-indigo-100 dark:border-indigo-900/60">
                <div class="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center shadow-sm">
                    <i class="pi pi-truck text-xs"></i>
                </div>
                <div>
                    <span class="text-xs font-extrabold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">
                        1. Batch &amp; Transit Mixer Details
                    </span>
                    <p class="text-[10px] text-indigo-500 dark:text-indigo-400 mt-0.5">Select batch to auto-fill grade, site &amp; customer</p>
                </div>
            </div>

            <div class="p-5 space-y-4">
                <!-- Row 1: Batch Selector, Casting Date & Tested By -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Batch: primary selector -->
                    <div class="md:col-span-2">
                        <BaseSelect
                            v-model="form.batch_id"
                            label="Batch Number *"
                            required
                            :options="batchOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="— Select Batch No —"
                            :filter="true"
                            filterPlaceholder="Search batch no, grade, customer, truck..."
                            @change="onBatchChange"
                            :error="form.errors.batch_id"
                            panelWidth="28rem"
                            hint="Selecting a batch auto-fills grade, customer, site & truck"
                        />
                    </div>
                    <div>
                        <BaseDatePicker
                            v-model="form.sample_date"
                            label="Casting Date *"
                            required
                            dateFormat="yy-mm-dd"
                            placeholder="Select Casting Date"
                            :error="form.errors.sample_date"
                            iconDisplay="button"
                        />
                    </div>
                    <div>
                        <BaseSelect
                            v-model="form.tested_by"
                            label="Tested By (Personnel)"
                            :options="personnelOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="— Select Personnel —"
                            :filter="true"
                            filterPlaceholder="Search QC personnel..."
                            :error="form.errors.tested_by"
                            panelWidth="18rem"
                            hint="QC technician / sampler"
                        />
                    </div>
                </div>

                <!-- Batch Details Card (shown when a batch is selected) -->
                <transition
                    enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div
                        v-if="selectedBatch"
                        class="rounded-xl border border-indigo-200/80 dark:border-indigo-800/60 bg-gradient-to-r from-indigo-50/70 via-white to-indigo-50/30 dark:from-indigo-950/40 dark:via-gray-900 dark:to-indigo-950/20 overflow-hidden shadow-xs"
                    >
                        <!-- Card header -->
                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-indigo-100 dark:border-indigo-900/60">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-database text-indigo-600 dark:text-indigo-400 text-xs"></i>
                                <span class="text-[11px] font-extrabold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">
                                    Batch &amp; Transit Mixer Details
                                </span>
                                <span v-if="selectedBatch.batch_no" class="ml-1 text-[11px] font-bold text-gray-500 dark:text-gray-400">
                                    ({{ selectedBatch.batch_no }})
                                </span>
                            </div>
                            <!-- Status badge -->
                            <span
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                                :class="{
                                    'bg-gray-100 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700': !selectedBatch.status || batchStatusMap[selectedBatch.status]?.color === 'gray',
                                    'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800': batchStatusMap[selectedBatch.status]?.color === 'amber',
                                    'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800': batchStatusMap[selectedBatch.status]?.color === 'blue',
                                    'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800': batchStatusMap[selectedBatch.status]?.color === 'emerald',
                                    'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800': batchStatusMap[selectedBatch.status]?.color === 'rose',
                                }"
                            >
                                {{ batchStatusMap[selectedBatch.status]?.label || 'Active' }}
                            </span>
                        </div>

                        <!-- 5-field details grid: Mix Design/Grade | Unload Site | Customer | Truck | Batch Size -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 divide-y sm:divide-y-0 sm:divide-x divide-indigo-100/80 dark:divide-indigo-900/40">

                            <!-- 1. Mix Design / Grade -->
                            <div class="px-4 py-3.5">
                                <div class="text-[9px] font-black uppercase tracking-wider text-indigo-400 dark:text-indigo-500 mb-1.5">
                                    Mix Design / Grade
                                </div>
                                <div class="space-y-1">
                                    <span class="inline-block px-2 py-0.5 rounded-md bg-indigo-600 text-white font-black text-[11px]">
                                        {{ selectedBatch.dispatches?.[0]?.mix_design?.concrete_grade?.name
                                           || selectedBatch.dispatches?.[0]?.mixDesign?.concreteGrade?.name
                                           || selectedBatch.dispatches?.[0]?.mix_design?.grade
                                           || selectedBatch.dispatches?.[0]?.mixDesign?.grade
                                           || '—' }}
                                    </span>
                                    <div v-if="selectedBatch.dispatches?.[0]?.mix_design?.design_type || selectedBatch.dispatches?.[0]?.mixDesign?.design_type" class="text-[10px] text-gray-500 dark:text-gray-400 font-medium truncate">
                                        {{ selectedBatch.dispatches?.[0]?.mix_design?.design_type
                                           || selectedBatch.dispatches?.[0]?.mixDesign?.design_type }}
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Unload Site -->
                            <div class="px-4 py-3.5">
                                <div class="text-[9px] font-black uppercase tracking-wider text-indigo-400 dark:text-indigo-500 mb-1.5">
                                    <i class="pi pi-map-marker mr-0.5"></i> Unload Site
                                </div>
                                <div class="text-xs font-bold text-gray-900 dark:text-gray-100 truncate" :title="selectedBatch.dispatches?.[0]?.unload_site?.name || selectedBatch.dispatches?.[0]?.unloadSite?.name || '—'">
                                    {{ selectedBatch.dispatches?.[0]?.unload_site?.name
                                       || selectedBatch.dispatches?.[0]?.unloadSite?.name
                                       || '—' }}
                                </div>
                            </div>

                            <!-- 3. Customer -->
                            <div class="px-4 py-3.5">
                                <div class="text-[9px] font-black uppercase tracking-wider text-indigo-400 dark:text-indigo-500 mb-1.5">
                                    <i class="pi pi-user mr-0.5"></i> Customer
                                </div>
                                <div class="text-xs font-bold text-gray-900 dark:text-gray-100 truncate" :title="selectedBatch.dispatches?.[0]?.customer?.legal_name || '—'">
                                    {{ selectedBatch.dispatches?.[0]?.customer?.legal_name || '—' }}
                                </div>
                            </div>

                            <!-- 4. Truck Registration -->
                            <div class="px-4 py-3.5">
                                <div class="text-[9px] font-black uppercase tracking-wider text-indigo-400 dark:text-indigo-500 mb-1.5">
                                    <i class="pi pi-truck mr-0.5"></i> Truck Reg No
                                </div>
                                <div class="text-xs font-bold text-gray-900 dark:text-gray-100 font-mono">
                                    {{ selectedBatch.dispatches?.[0]?.truck?.registration || '—' }}
                                </div>
                            </div>

                            <!-- 5. Batch Size -->
                            <div class="px-4 py-3.5">
                                <div class="text-[9px] font-black uppercase tracking-wider text-indigo-400 dark:text-indigo-500 mb-1.5">
                                    <i class="pi pi-box mr-0.5"></i> Batch Size
                                </div>
                                <div class="text-xs font-black text-gray-900 dark:text-gray-100">
                                    {{ selectedBatch.batch_size ? selectedBatch.batch_size + ' m³' : '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </div>

        <!-- ══ SECTION 2: FRESH CONCRETE CHECKS ══════════════════════════ -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200/80 dark:border-gray-800 shadow-xs overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-3.5 bg-emerald-50/60 dark:bg-emerald-950/30 border-b border-emerald-100 dark:border-emerald-900/60">
                <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center shadow-sm">
                    <i class="pi pi-check-circle text-xs"></i>
                </div>
                <div>
                    <span class="text-xs font-extrabold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
                        2. Fresh Concrete Quality Checks (At Point of Casting)
                    </span>
                    <p class="text-[10px] text-emerald-500 dark:text-emerald-400 mt-0.5">Slump, temperature &amp; sampling point as per IS 1199</p>
                </div>
            </div>

            <div class="p-5">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Slump with live classification badge -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[10px] font-bold text-gray-700 dark:text-gray-200">
                                Slump (mm) <span class="text-rose-500">*</span>
                            </label>
                            <span
                                v-if="form.slump_mm"
                                :class="{
                                    'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300': slumpClass.color === 'rose',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300': slumpClass.color === 'amber',
                                    'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300': slumpClass.color === 'blue',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300': slumpClass.color === 'emerald',
                                    'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300': slumpClass.color === 'purple',
                                }"
                                class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                            >
                                {{ slumpClass.label }}
                            </span>
                        </div>
                        <BaseInput
                            v-model="form.slump_mm"
                            type="number"
                            required
                            placeholder="120"
                            hint="Slump cone test — IS 1199"
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
                            hint="Discharge temp ≤ 35°C (IS 7861)"
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
                            hint="Site / plant air temperature"
                            :error="form.errors.ambient_temp_c"
                        />
                    </div>

                    <!-- Sampling Point -->
                    <div>
                        <BaseSelect
                            v-model="form.source_location"
                            label="Sampling Point"
                            :options="samplingPointOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Sampling Point"
                            :error="form.errors.source_location"
                        />
                    </div>
                </div>

                <!-- Temp Warnings -->
                <div class="mt-3 flex flex-wrap gap-2">
                    <div
                        v-if="Number(form.concrete_temp_c) > 35"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-700 dark:text-rose-300 text-[11px] font-bold"
                    >
                        <i class="pi pi-exclamation-triangle text-rose-500 text-xs"></i>
                        Concrete temp exceeds 35°C limit (IS 7861)
                    </div>
                    <div
                        v-if="Number(form.slump_mm) > 200"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-700 dark:text-amber-300 text-[11px] font-bold"
                    >
                        <i class="pi pi-exclamation-circle text-amber-500 text-xs"></i>
                        Slump > 200mm — verify if self-compacting grade
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ SECTION 3: SPECIMEN CASTING & CURING ══════════════════════ -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200/80 dark:border-gray-800 shadow-xs overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-3.5 bg-amber-50/60 dark:bg-amber-950/30 border-b border-amber-100 dark:border-amber-900/60">
                <div class="w-7 h-7 rounded-lg bg-amber-500 text-white flex items-center justify-center shadow-sm">
                    <i class="pi pi-th-large text-xs"></i>
                </div>
                <div>
                    <span class="text-xs font-extrabold uppercase tracking-wider text-amber-700 dark:text-amber-300">
                        3. Specimen Casting &amp; Curing Tank Setup
                    </span>
                    <p class="text-[10px] text-amber-500 dark:text-amber-400 mt-0.5">Mould size, quantity &amp; water-curing details per IS 516</p>
                </div>
            </div>

            <div class="p-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Mould Size -->
                    <div>
                        <BaseSelect
                            v-model="form.specimen_size"
                            label="Cube Mould Dimension"
                            :options="mouldOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Mould Size"
                            :error="form.errors.specimen_size"
                            panelWidth="20rem"
                        />
                    </div>

                    <!-- Count -->
                    <div>
                        <BaseInput
                            v-model="form.specimen_count"
                            type="number"
                            label="Specimens Cast *"
                            required
                            placeholder="6"
                            hint="Typically 6 cubes — 3 for 7-Day, 3 for 28-Day"
                            :error="form.errors.specimen_count"
                        />
                    </div>

                    <!-- Curing -->
                    <div>
                        <BaseInput
                            v-model="form.curing_tank_id"
                            label="Curing Tank / Method"
                            placeholder="Tank-1 (Water 27±2°C)"
                            hint="Standard water curing per IS 516"
                            :error="form.errors.curing_tank_id"
                        />
                    </div>
                </div>

                <!-- Specimen count info strip -->
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 text-[11px] font-semibold">
                        <i class="pi pi-info-circle text-indigo-500 text-xs"></i>
                        {{ form.specimen_count }} cubes cast →
                        <span v-for="(m, i) in testSchedulePreview" :key="m.days" class="inline-flex items-center">
                            <strong>{{ Math.max(1, Math.round(Number(form.specimen_count || 6) / testSchedulePreview.length)) }}</strong>&nbsp;for {{ m.label }}
                            <span v-if="i < testSchedulePreview.length - 1" class="mx-1 text-gray-400">+</span>
                        </span>
                    </div>
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 text-[11px] font-semibold">
                        <i class="pi pi-th-large text-amber-500 text-xs"></i>
                        Mould: <strong>{{ form.specimen_size || '—' }}</strong>
                    </div>
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800/60 text-purple-700 dark:text-purple-300 text-[11px] font-semibold">
                        <i class="pi pi-calendar text-purple-500 text-xs"></i>
                        Crushing Plan: <strong>{{ selectedMilestones.length }} Tests ({{ selectedMilestones.map(d => d + 'D').join(', ') }})</strong>
                    </div>
                    <div v-if="form.tested_by" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300 text-[11px] font-semibold">
                        <i class="pi pi-user text-indigo-500 text-xs"></i>
                        Tested By: <strong>{{ personnelOptions.find((p: any) => p.value == form.tested_by)?.label || 'Assigned' }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ SECTION 4: SCHEDULE PREVIEW ════════════════════════════════ -->
        <div class="rounded-2xl border border-purple-200/80 dark:border-purple-800/60 overflow-hidden shadow-xs">
            <!-- Header with Preset Selector -->
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 bg-purple-50/70 dark:bg-purple-950/40 border-b border-purple-100 dark:border-purple-900/60">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-purple-600 text-white flex items-center justify-center shadow-sm">
                        <i class="pi pi-clock text-xs"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-extrabold uppercase tracking-wider text-purple-700 dark:text-purple-300">
                                4. Auto-Generated Crushing Schedule
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                {{ selectedMilestones.length }} Test Stages
                            </span>
                        </div>
                        <p class="text-[10px] text-purple-500 dark:text-purple-400 mt-0.5">
                            Based on casting date — 2 tests (7D, 28D) or 3 tests (7D, 15D, 28D) per client requirement
                        </p>
                    </div>
                </div>

                <!-- 2-Test vs 3-Test Preset Toggle -->
                <div class="flex items-center gap-1.5 p-1 bg-white dark:bg-gray-800 rounded-xl border border-purple-200 dark:border-purple-800/70 shadow-xs">
                    <button
                        type="button"
                        @click="schedulePreset = '2-test'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                        :class="schedulePreset === '2-test'
                            ? 'bg-purple-600 text-white shadow-xs'
                            : 'text-gray-600 dark:text-gray-300 hover:text-purple-600 dark:hover:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-950/40'"
                    >
                        <i class="pi pi-check text-[10px]" v-if="schedulePreset === '2-test'"></i>
                        <span>2 Tests (7 &amp; 28 Days)</span>
                        <span class="text-[10px] opacity-80 font-normal">6 Cubes</span>
                    </button>
                    <button
                        type="button"
                        @click="schedulePreset = '3-test'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                        :class="schedulePreset === '3-test'
                            ? 'bg-purple-600 text-white shadow-xs'
                            : 'text-gray-600 dark:text-gray-300 hover:text-purple-600 dark:hover:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-950/40'"
                    >
                        <i class="pi pi-check text-[10px]" v-if="schedulePreset === '3-test'"></i>
                        <span>3 Tests (7, 15 &amp; 28 Days)</span>
                        <span class="text-[10px] opacity-80 font-normal">9 Cubes</span>
                    </button>
                </div>
            </div>

            <div class="bg-gradient-to-br from-purple-50/40 via-white to-indigo-50/30 dark:from-purple-950/20 dark:via-gray-900 dark:to-indigo-950/20 p-5 space-y-4">
                <!-- Milestone Toggle Chips -->
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-purple-100 dark:border-purple-900/50">
                    <div class="text-[11px] font-bold text-gray-600 dark:text-gray-300 flex items-center gap-1.5">
                        <i class="pi pi-sliders-h text-purple-600 dark:text-purple-400 text-xs"></i>
                        <span>Active Testing Milestones:</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-for="m in availableMilestones"
                            :key="m.days"
                            type="button"
                            @click="toggleMilestone(m.days)"
                            class="px-3 py-1 rounded-full text-xs font-extrabold border transition-all cursor-pointer flex items-center gap-1.5 shadow-xs"
                            :class="isMilestoneSelected(m.days)
                                ? (m.days === 7
                                    ? 'bg-amber-500 border-amber-500 text-white shadow-amber-500/20'
                                    : m.days === 15
                                        ? 'bg-emerald-600 border-emerald-600 text-white shadow-emerald-600/20'
                                        : 'bg-indigo-600 border-indigo-600 text-white shadow-indigo-600/20')
                                : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:border-purple-300'"
                        >
                            <i :class="isMilestoneSelected(m.days) ? 'pi pi-check' : 'pi pi-plus'" class="text-[10px]"></i>
                            <span>{{ m.label }} (+{{ m.days }}d)</span>
                            <span class="text-[10px] font-normal opacity-90">({{ m.targetPct }})</span>
                        </button>
                    </div>
                </div>

                <!-- Schedule Cards Grid -->
                <div
                    class="grid gap-4"
                    :class="testSchedulePreview.length === 3
                        ? 'grid-cols-1 md:grid-cols-3'
                        : testSchedulePreview.length === 2
                            ? 'grid-cols-1 sm:grid-cols-2'
                            : 'grid-cols-1'"
                >
                    <div
                        v-for="sched in testSchedulePreview"
                        :key="sched.days"
                        class="relative group overflow-hidden rounded-xl p-4 bg-white dark:bg-gray-800 border shadow-sm transition-all hover:shadow-md"
                        :class="sched.days === 7
                            ? 'border-amber-200 dark:border-amber-800/60 hover:border-amber-300'
                            : sched.days === 15
                                ? 'border-emerald-200 dark:border-emerald-800/60 hover:border-emerald-300'
                                : 'border-indigo-200 dark:border-indigo-800/60 hover:border-indigo-300'"
                    >
                        <!-- accent stripe -->
                        <div
                            class="absolute left-0 inset-y-0 w-1.5 rounded-l-xl"
                            :class="sched.days === 7
                                ? 'bg-amber-500'
                                : sched.days === 15
                                    ? 'bg-emerald-600'
                                    : 'bg-indigo-600'"
                        ></div>

                        <div class="pl-3">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-xs font-black text-white shadow-xs"
                                        :class="sched.days === 7
                                            ? 'bg-amber-500'
                                            : sched.days === 15
                                                ? 'bg-emerald-600'
                                                : 'bg-indigo-600'"
                                    >
                                        {{ sched.label }}
                                    </span>
                                    <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500">+{{ sched.days }} days</span>
                                </div>
                                <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700/60 px-2 py-0.5 rounded-md">
                                    {{ sched.dayOfWeek }}
                                </span>
                            </div>

                            <div class="text-base font-black text-gray-900 dark:text-gray-100 tracking-tight mt-1">
                                {{ sched.date }}
                            </div>

                            <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-1">
                                {{ sched.desc }}
                            </div>

                            <div class="flex items-center justify-between pt-3 mt-3 border-t border-gray-100 dark:border-gray-700/60">
                                <span class="text-[11px] text-gray-600 dark:text-gray-300 font-semibold flex items-center gap-1">
                                    <i class="pi pi-box text-gray-400 text-xs"></i>
                                    Specimens: <strong class="text-gray-900 dark:text-white">{{ sched.specimens }}</strong>
                                </span>
                                <span
                                    class="text-xs font-black px-2 py-0.5 rounded-md"
                                    :class="sched.days === 7
                                        ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300'
                                        : sched.days === 15
                                            ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'
                                            : 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300'"
                                >
                                    Target: {{ sched.targetPct }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-3 flex items-center gap-1">
                    <i class="pi pi-info-circle text-[11px]"></i>
                    Crushing test events for {{ selectedMilestones.map(d => d + 'D').join(', ') }} will automatically be scheduled upon saving.
                </p>
            </div>
        </div>

        <!-- ══ SECTION 5: REMARKS ══════════════════════════════════════════ -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200/80 dark:border-gray-800 shadow-xs p-5">
            <div class="flex items-center gap-2 mb-3">
                <i class="pi pi-file-edit text-gray-400 text-sm"></i>
                <span class="text-xs font-extrabold uppercase tracking-wider text-gray-500 dark:text-gray-400">Sampling Notes / Remarks</span>
            </div>
            <BaseInput
                v-model="form.remarks"
                label=""
                placeholder="e.g. Normal workability, good cohesiveness, pumpable mix. Water spray at site. No segregation observed."
                :error="form.errors.remarks"
            />
        </div>

        <!-- ══ FOOTER ACTIONS ══════════════════════════════════════════════ -->
        <div class="flex items-center justify-between gap-4 pt-1">
            <Link
                :href="route('quality.samples.index')"
                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-700 transition-all"
            >
                <i class="pi pi-arrow-left text-[10px]"></i>
                Back to Samples
            </Link>

            <button
                type="submit"
                :disabled="form.processing"
                :class="[
                    'inline-flex items-center gap-2 px-6 py-2.5 text-sm font-bold rounded-xl shadow-lg transition-all disabled:opacity-50 text-white cursor-pointer',
                    isEditing
                        ? 'bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-amber-500/25'
                        : 'bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 shadow-indigo-600/25'
                ]"
            >
                <i v-if="form.processing" class="pi pi-spin pi-spinner text-xs"></i>
                <i v-else :class="isEditing ? 'pi pi-check' : 'pi pi-save'" class="text-xs"></i>
                <span>{{ isEditing ? 'Update Sample' : 'Log Sample &amp; Generate Schedule' }}</span>
            </button>
        </div>
    </form>
</template>
