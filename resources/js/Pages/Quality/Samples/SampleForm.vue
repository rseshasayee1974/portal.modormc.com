<script setup lang="ts">
import { APP_LOCALE } from '@/Utils/locale';
import { entityToday } from '@/Utils/entityDateTime';
import { useForm, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import Column from 'primevue/column';
import Checkbox from 'primevue/checkbox';
import Tag from 'primevue/tag';

const props = defineProps<{
    sample?: any;
    isEditing?: boolean;
    embedded?: boolean;
    concreteGrades?: any[];
    materials?: any[];
    customers?: any[];
    dispatches?: any[];
    batches?: any[];
    salesOrders?: any[];
    usedBatchIds?: number[];
    usedDispatchIds?: number[];
    testTypes?: any[];
    personnels?: any[];
}>();

const emit = defineEmits<{
    (e: 'saved'): void;
    (e: 'cancel'): void;
}>();

const initialOrder = (props.salesOrders || []).find(o => o.batches?.some((b: any) => b.id == props.sample?.batch_id) || o.dispatches?.some((d: any) => d.id == props.sample?.dispatch_id));
const initialKey = props.sample?.batch_id ? `batch:${props.sample.batch_id}` : props.sample?.dispatch_id ? `dispatch:${props.sample.dispatch_id}` : null;
const form = useForm({
    sales_order_id: initialOrder?.id || null,
    source_keys: initialKey ? [initialKey] : [] as string[],
    sample_date: props.sample?.sample_date ? props.sample.sample_date.substring(0, 10) : entityToday(),
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

const salesOrderOptions = computed(() => (props.salesOrders || []).map(o => ({ value: o.id, label: `${o.prefix || 'SO'}/${o.order_no} · ${o.customer?.legal_name || ''} · ${o.site?.name || ''}` })));
const selectedOrder = computed(() => (props.salesOrders || []).find(o => o.id == form.sales_order_id));
const productionRows = computed(() => {
    const order = selectedOrder.value;
    if (!order) return [];
    const batches = order.batches || [];
    const rows = batches.map((batch: any) => ({ key: `batch:${batch.id}`, batch, dispatch: [...(batch.dispatches || [])].sort((a: any, b: any) => b.id - a.id)[0] }));
    for (const dispatch of order.dispatches || []) {
        if (!batches.some((b: any) => b.id == dispatch.batch_id)) rows.push({ key: `dispatch:${dispatch.id}`, batch: dispatch.batch, dispatch });
    }
    return rows.map((row: any) => ({
        ...row,
        unavailable: row.key !== initialKey && ((props.usedBatchIds || []).includes(row.batch?.id) || (props.usedDispatchIds || []).includes(row.dispatch?.id)),
        cancelled: Number(row.batch?.status) === 5 || row.dispatch?.dispatch_status === 'Cancelled',
    }));
});
const toggleSource = (key: string) => {
    form.source_keys = props.isEditing ? [key] : form.source_keys.includes(key) ? form.source_keys.filter(k => k !== key) : [...form.source_keys, key];
};
const availableRows = computed(() => productionRows.value.filter((row: any) => !row.unavailable && !row.cancelled));
const selectedRows = computed(() => productionRows.value.filter((row: any) => form.source_keys.includes(row.key)));
const selectedQuantity = computed(() => selectedRows.value.reduce((total: number, row: any) => total + Number(row.dispatch?.delivered_qty ?? row.batch?.batch_size ?? 0), 0));
const selectAllAvailable = () => { form.source_keys = availableRows.value.map((row: any) => row.key); };
const areAllAvailableSelected = computed(() => availableRows.value.length > 0 && availableRows.value.every((row: any) => form.source_keys.includes(row.key)));
const toggleSelectAll = (checked: boolean) => { form.source_keys = checked ? availableRows.value.map((row: any) => row.key) : []; };
const productionRowClass = (row: any) => form.source_keys.includes(row.key) ? '!bg-indigo-50/70 dark:!bg-indigo-950/30' : row.unavailable || row.cancelled ? 'opacity-60' : '';
const testingLabel = (row: any) => row.cancelled ? 'Cancelled' : row.unavailable ? 'Already sampled' : form.source_keys.includes(row.key) ? 'Selected' : 'Available';
const testingSeverity = (row: any) => row.cancelled ? 'danger' : row.unavailable ? 'secondary' : form.source_keys.includes(row.key) ? 'info' : 'success';

watch(() => form.sales_order_id, () => { form.source_keys = []; form.batch_id = null; form.dispatch_id = null; });
watch(() => form.source_keys, keys => {
    const row = productionRows.value.find((r: any) => r.key === keys[0]);
    const order = selectedOrder.value;
    form.batch_id = row?.batch?.id || null;
    form.dispatch_id = row?.dispatch?.id || null;
    form.customer_id = row?.dispatch?.customer_id || order?.customer_id || null;
    form.concrete_grade_id = row?.dispatch?.mix_design?.concrete_grade_id || order?.mix_design?.concrete_grade_id || null;
    form.site_name = row?.dispatch?.unload_site?.name || order?.site?.name || '';
    form.truck_no = row?.dispatch?.truck?.registration || '';
}, { deep: true });

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

// Schedule milestones configuration (7 days, 15 days, 28 days)
// Support customers requesting either 2 tests (7d, 28d) or 3 tests (7d, 15d, 28d)
const availableMilestones = [
    { days: 7, label: '7 Day', color: 'amber', targetPct: '65 – 75%', desc: 'Early Compressive Strength' },
    { days: 15, label: '15 Day', color: 'emerald', targetPct: '85 – 90%', desc: 'Intermediate Quality Check' },
    { days: 28, label: '28 Day', color: 'indigo', targetPct: '100%', desc: 'Standard Characteristic Strength' },
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
            formatted: res.toLocaleDateString(APP_LOCALE, { day: '2-digit', month: 'short', year: 'numeric' }),
            dayOfWeek: res.toLocaleDateString(APP_LOCALE, { weekday: 'short' }),
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

const submit = () => {
    if (props.isEditing && props.sample?.id) {
        form.put(route('quality.samples.update', props.sample.id), {
            preserveScroll: true,
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
    <form @submit.prevent="submit" class="sample-form min-w-0 space-y-3">
        <div class="flex items-center justify-between gap-3">

            <Link v-if="!embedded" :href="route('quality.samples.index')"
                class="shrink-0 text-xs font-medium text-slate-500 hover:text-indigo-600">Back to samples</Link>
        </div>

        <BaseCard bodyClass="!p-0">
            <!-- Form Header -->
            <div
                class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div
                        class="rounded-lg bg-indigo-100 dark:bg-indigo-950/50 p-1.5 text-indigo-700 dark:text-indigo-400 ring-1 ring-indigo-200 dark:ring-indigo-900/30 flex items-center justify-center h-7 w-7">
                        <i :class="isEditing ? 'pi pi-pencil' : 'pi pi-plus-circle'" class="text-xs"></i>
                    </div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        {{ isEditing ? 'Edit Concrete Sample' : embedded ? 'New Concrete Sample' : 'Concrete Sampling'
                        }}
                    </h2>
                </div>
            </div>

            <!-- Production Section -->
            <div class="p-3">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
                    <!-- Left Column: Inputs -->
                    <div class="space-y-4 lg:col-span-5 xl:col-span-4">
                        <BaseSelect v-model="form.sales_order_id" label="Sales order" required
                            :options="salesOrderOptions" optionLabel="label" optionValue="value"
                            placeholder="Select sales order" :filter="true" :error="form.errors.sales_order_id" />

                        <div class="grid grid-cols-2 gap-3">
                            <BaseDatePicker v-model="form.sample_date" label="Casting date" required
                                dateFormat="yy-mm-dd" :error="form.errors.sample_date" iconDisplay="button" />
                            <BaseSelect v-model="form.tested_by" label="Tested by" :options="personnelOptions"
                                optionLabel="label" optionValue="value" placeholder="Select personnel" :filter="true"
                                :error="form.errors.tested_by" />
                        </div>

                        <div class="space-y-1">
                            <p v-if="form.errors.source_keys" role="alert" class="text-xs text-rose-600">{{
                                form.errors.source_keys }}</p>
                            <!-- <p class="text-[0.8rem] text-slate-500">Each selected load gets a separate sample.
                                Unselected
                                loads
                                remain
                                available for later.</p> -->
                        </div>
                    </div>

                    <!-- Right Column: Data Table -->
                    <div class="space-y-2 lg:col-span-7 xl:col-span-8 min-w-0">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs text-slate-500"><span
                                    class="font-semibold text-indigo-600 dark:text-indigo-400">{{
                                        form.source_keys.length }} selected</span> / {{ productionRows.length }} loads <span
                                    class="mx-2 text-slate-300">·</span>{{ selectedQuantity.toLocaleString(APP_LOCALE, {
                                        maximumFractionDigits: 3
                                    }) }} m³</p>
                        </div>

                        <div class="rounded-lg border border-slate-200 dark:border-slate-700">
                            <BaseDataTable :value="productionRows" dataKey="key" :paginator="false" :stripedRows="false"
                                :rowClass="productionRowClass" :showAdvancedFilter="false"
                                class="p-datatable-sm qc-production-table">
                                <Column style="width: 4rem">
                                    <template #header>
                                        <Checkbox :modelValue="areAllAvailableSelected" binary
                                            :disabled="!availableRows.length && !form.source_keys.length"
                                            @update:modelValue="toggleSelectAll" aria-label="Select all available" />
                                    </template>
                                    <template #body="{ data }">
                                        <Checkbox :modelValue="form.source_keys.includes(data.key)" binary
                                            :disabled="data.unavailable || data.cancelled"
                                            :aria-label="`Select batch ${data.batch?.batch_no || data.dispatch?.dispatch_no}`"
                                            @update:modelValue="toggleSource(data.key)" />
                                    </template>
                                </Column>
                                <Column header="Batch">
                                    <template #body="{ data }">
                                        <div class="flex items-center gap-2"><i
                                                class="pi pi-box text-indigo-400 text-xs"></i><span
                                                class="font-bold text-indigo-700 dark:text-indigo-300">{{
                                                    data.batch?.batch_no
                                                    ||
                                                    '—' }}</span></div>
                                    </template>
                                </Column>
                                <Column header="Invoice">
                                    <template #body="{ data }">
                                        <Tag :value="data.dispatch?.invoice_status?.invoice?.invoice_number || data.dispatch?.invoice_status?.invoice_number || 'Not invoiced'"
                                            :severity="data.dispatch?.invoice_status?.invoice_id ? 'success' : 'secondary'"
                                            class="!text-[10px] !font-bold" />
                                    </template>
                                </Column>
                                <Column header="Grade">
                                    <template #body="{ data }">
                                        <div class="flex flex-wrap items-center gap-2 min-w-0">
                                            <Tag :value="data.dispatch?.mix_design?.concrete_grade?.name || selectedOrder?.mix_design?.concrete_grade?.name || 'No grade'"
                                                severity="info" class="!text-[10px] !font-bold" />

                                        </div>
                                    </template>
                                </Column>
                                <Column header="Truck">
                                    <template #body="{ data }">
                                        <div class="flex flex-wrap items-center gap-2 min-w-0">
                                            <p
                                                class="flex items-center gap-1.5 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                                                <!-- <i class="pi pi-truck text-[10px]"></i> -->
                                                {{ data.dispatch?.truck?.registration
                                                    ||
                                                    `No
                                                vehicle assigned` }}
                                            </p>
                                        </div>
                                    </template>
                                </Column>
                                <Column header="Volume">
                                    <template #body="{ data }"><span
                                            class="font-bold tabular-nums text-slate-800 dark:text-slate-100">{{
                                                Number(data.dispatch?.delivered_qty ?? data.batch?.batch_size ??
                                                    0).toLocaleString(APP_LOCALE, { maximumFractionDigits: 3 }) }}</span><span
                                            class="ml-1 text-[10px] text-slate-400">m³</span></template>
                                </Column>
                                <Column header="Testing status">
                                    <template #body="{ data }">
                                        <Tag :value="testingLabel(data)" :severity="testingSeverity(data)"
                                            :icon="form.source_keys.includes(data.key) ? 'pi pi-check-circle' : undefined"
                                            rounded class="!text-[10px]" />
                                    </template>
                                </Column>
                                <template #empty>
                                    <p class="py-5 text-center text-sm text-slate-500">{{ selectedOrder ? `No production
                                        loads
                                        for
                                        this order.` : `Select a sales order to view production.` }}</p>
                                </template>
                            </BaseDataTable>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sample Details Section -->
            <div class="p-3">
                <h2 class="text-sm font-black uppercase tracking-tight text-gray-800 dark:text-gray-100 mb-4">Sample
                    details</h2>
                <div class="grid grid-cols-1 gap-x-3 gap-y-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">
                    <BaseInput v-model="form.slump_mm" type="number" label="Slump (mm)" required
                        :error="form.errors.slump_mm" />
                    <BaseSelect v-model="form.source_location" label="Sampling point" :options="samplingPointOptions"
                        optionLabel="label" optionValue="value" :error="form.errors.source_location" />
                    <BaseInput v-model="form.concrete_temp_c" type="number" step="0.5" label="Concrete temp (°C)"
                        :error="form.errors.concrete_temp_c" />
                    <BaseInput v-model="form.ambient_temp_c" type="number" step="0.5" label="Ambient temp (°C)"
                        :error="form.errors.ambient_temp_c" />
                    <BaseSelect v-model="form.specimen_size" label="Mould size" :options="mouldOptions"
                        optionLabel="label" optionValue="value" :error="form.errors.specimen_size" />
                    <BaseInput v-model="form.curing_tank_id" label="Curing tank" :error="form.errors.curing_tank_id" />
                    <BaseInput v-model="form.specimen_count" type="number" label="Specimens per load"
                        :error="form.errors.specimen_count" />
                    <BaseInput v-model="form.sample_quantity" label="Sample quantity"
                        :error="form.errors.sample_quantity" />
                    <div class="sm:col-span-2 md:col-span-1 lg:col-span-2 min-w-0">
                        <BaseInput v-model="form.remarks" label="Remarks" placeholder="Optional sampling notes"
                            :error="form.errors.remarks" />
                    </div>
                </div>
                <p v-if="Number(form.concrete_temp_c) > 35" class="text-xs text-rose-600 mt-2">Concrete temperature
                    exceeds
                    35°C (IS
                    7861).</p>
                <p v-if="Number(form.slump_mm) > 200" class="text-xs text-amber-600 mt-2">Slump exceeds 200 mm. Verify
                    the
                    concrete
                    grade.</p>
            </div>

            <!-- Testing Schedule Section -->
            <div class="p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <h2 class="text-sm font-black uppercase tracking-tight text-gray-800 dark:text-gray-100">Testing
                        schedule</h2>
                    <span class="text-xs text-slate-500">{{ selectedMilestones.length }} stages · {{
                        form.specimen_count }} specimens per load</span>
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <label v-for="milestone in availableMilestones" :key="milestone.days"
                        class="flex cursor-pointer items-start gap-2 rounded-lg border px-3 py-2 transition-colors"
                        :class="isMilestoneSelected(milestone.days) ? 'border-indigo-200 bg-indigo-50/50 dark:border-indigo-800 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-700'">
                        <Checkbox :modelValue="isMilestoneSelected(milestone.days)" binary
                            :aria-label="`Schedule ${milestone.days} day test`"
                            :disabled="isMilestoneSelected(milestone.days) && selectedMilestones.length === 1"
                            @update:modelValue="toggleMilestone(milestone.days)" class="mt-0.5" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ milestone.label }}
                                <span class="ml-1 font-normal text-slate-500">{{ milestone.targetPct }}</span>
                            </p>
                            <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">{{testSchedulePreview.find(s =>
                                s.days
                                === milestone.days)?.date || (isMilestoneSelected(milestone.days) ? `Choose casting
                                date` : `Not
                                scheduled`)}}</p>
                            <p v-if="isMilestoneSelected(milestone.days)" class="mt-0.5 text-[11px] text-slate-500">{{
                                testSchedulePreview.find(s => s.days === milestone.days)?.specimens}}</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Footer Actions -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 p-4 sm:p-5 bg-slate-50 dark:bg-slate-800/50 dark:border-slate-700 rounded-b-lg">
                <p class="text-xs text-slate-500">{{ form.source_keys.length }} load{{ form.source_keys.length === 1 ?
                    '' :
                    's' }}
                    selected · {{ selectedMilestones.length }} test stages per sample</p>
                <div class="flex flex-wrap items-center gap-2">
                    <BaseButton v-if="isEditing && embedded" label="Cancel" severity="secondary" variant="text"
                        @click="emit('cancel')" />
                    <BaseButton type="submit" :loading="form.processing"
                        :disabled="form.processing || !form.source_keys.length" variant="filled"
                        :icon="isEditing ? 'pi pi-check' : 'pi pi-plus'"
                        :label="isEditing ? 'Save changes' : 'Create samples & tests'" />
                </div>
            </div>
        </BaseCard>
    </form>
</template>

<style scoped>
.sample-form .grid>* {
    min-width: 0;
}

.sample-form :deep(.p-datatable-table) {
    width: 100%;
    table-layout: fixed;
}

.sample-form :deep(.p-datatable-table-container:not(.qc-production-table .p-datatable-table-container)) {
    overflow: visible !important;
    max-height: none !important;
}

.sample-form :deep(.qc-production-table .p-datatable-table-container) {
    overflow-y: auto !important;
    max-height: 280px !important;
}

.sample-form :deep(.p-datatable .p-datatable-thead > tr > th),
.sample-form :deep(.p-datatable .p-datatable-tbody > tr > td) {
    padding: 8px !important;
    min-width: 0;
    white-space: normal;
    overflow-wrap: anywhere;
}

.sample-form :deep(.p-column-title),
.sample-form :deep(.p-tag-label) {
    white-space: normal !important;
}
</style>
