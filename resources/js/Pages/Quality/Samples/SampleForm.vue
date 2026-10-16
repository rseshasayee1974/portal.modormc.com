<script setup lang="ts">
import { usePermissions } from '@/Composables/usePermissions';
import { entityToday, entityLocaleDate } from '@/Utils/entityDateTime';
import { useForm, Link } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseField from '@/Components/Base/BaseField.vue';
import MultiSelect from 'primevue/multiselect';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import Column from 'primevue/column';
import Popover from 'primevue/popover';
import Button from 'primevue/button';

const props = defineProps<{
    supportedTestAges?: number[];
    sample?: any;
    additionalSamples?: any[];
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

const { can } = usePermissions();
const canCreateTests = computed(() => can('QC_TEST.CREATE'));
const canCreateSamples = computed(() => can('QC_SAMPLE.CREATE') && canCreateTests.value);

const emit = defineEmits<{
    (e: 'saved'): void;
    (e: 'cancel'): void;
}>();

const initialOrder = (props.salesOrders || []).find(o => o.batches?.some((b: any) => b.id == props.sample?.batch_id) || o.dispatches?.some((d: any) => d.id == props.sample?.dispatch_id));
const initialDispatch = initialOrder?.dispatches?.find((dispatch: any) => dispatch.id == props.sample?.dispatch_id)
    || initialOrder?.dispatches?.find((dispatch: any) => dispatch.batch_id == props.sample?.batch_id);
const initialKey = initialDispatch ? `dispatch:${initialDispatch.id}` : null;
const form = useForm({
    execute_after_create: false,
    test_type_id: null as number | null,
    sales_order_id: initialOrder?.id || null,
    source_keys: initialKey ? [initialKey] : [] as string[],
    sample_date: props.sample?.sample_date ? props.sample.sample_date.substring(0, 10) : entityToday(),
    tested_by: props.sample?.tested_by || null,
    concrete_grade_id: props.sample?.concrete_grade_id || null,
    material_id: props.sample?.material_id || null,
    dispatch_id: props.sample?.dispatch_id || null,
    batch_id: props.sample?.batch_id || null,
    customer_id: props.sample?.customer_id || null,
    truck_no: props.sample?.truck_no || '',
    site_name: props.sample?.site_name || '',
    source_location: props.sample?.source_location || 'Batching Plant Discharge',
    slump_mm: props.sample?.slump_mm ?? 120,
    concrete_temp_c: props.sample?.concrete_temp_c ?? 28.0,
    ambient_temp_c: props.sample?.ambient_temp_c ?? 32.0,
    specimen_size: props.sample?.specimen_size || '150x150x150 mm',
    specimen_count: props.sample?.specimen_count || 3,
    curing_tank_id: props.sample?.curing_tank_id || 'Tank-1 (27±2°C)',
    sample_quantity: props.sample?.sample_quantity || '3 Cubes',
    schedule_milestones: [7],
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
const supportedTestAges = computed(() => props.supportedTestAges || [7, 15, 28]);
const ageFilter = ref<number | null>(null);
const statusFilter = ref('All');
const statusOptions = [
    { label: 'All statuses', value: 'All' },
    { label: 'Not sampled', value: 'not_sampled' },
    { label: 'Pending', value: 'pending' },
    { label: 'Retest', value: 'retest' },
];
const loadSearch = ref('');
const tableFirst = ref(0);
watch([ageFilter, statusFilter, loadSearch], () => { tableFirst.value = 0; });
const ageOptions = [
    { label: 'All ages', value: null },
    { label: '7 days', value: 7 },
    { label: '15 days', value: 15 },
    { label: '28 days', value: 28 },
];
const currentAge = (value?: string) => {
    if (!value) return null;
    const calendarDay = (date: string) => {
        const [year, month, day] = date.split('-').map(Number);
        return Date.UTC(year, month - 1, day);
    };
    const days = Math.floor((calendarDay(entityToday()) - calendarDay(entityToday(value))) / 86400000);
    return Number.isFinite(days) ? days : null;
};
const isDispatched = (dispatch: any) => dispatch?.dispatch_time && dispatch?.dispatch_no
    && !['Draft', 'Loading', 'Cancelled'].includes(dispatch.dispatch_status);
const productionRows = computed(() => [...(props.salesOrders || []).flatMap(order =>
    (order.dispatches || []).filter(isDispatched).map((dispatch: any) => {
        const batch = (order.batches || []).find((batch: any) => batch.id == dispatch.batch_id) || dispatch.batch;
        const sample = dispatch.quality_sample || batch?.quality_sample;
        const key = `dispatch:${dispatch.id}`;
        return {
            key, order, batch, dispatch, sample,
            age: dispatch.qc_due_age ?? currentAge(dispatch.dispatch_time),
            cancelled: Number(batch?.status) === 5,
        };
    })
), ...(props.additionalSamples || []).map(sample => ({
    key: `sample:${sample.id}`, sample, batch: sample.batch,
    dispatch: sample.dispatch || {},
    order: { customer: sample.customer, site: { name: sample.site_name } },
    age: currentAge(sample.dispatch?.dispatch_time),
    cancelled: Number(sample.batch?.status) === 5 || sample.dispatch?.dispatch_status === 'Cancelled',
}))]);
const filteredSources = computed(() => productionRows.value.filter((row: any) => {
    const age = ageFilter.value;
    if (row.cancelled || !isDispatched(row.dispatch) || !supportedTestAges.value.includes(row.age)) return false;
    if (age !== null && row.age !== age) return false;
    return true;
}));
const filteredRows = computed(() => filteredSources.value.flatMap((row: any) => {
    const matchingTests = (row.sample?.tests || []).filter((test: any) => Number(test.age_days) === row.age);
    const tests = matchingTests.filter((test: any) => ['pending', 'retest'].includes(test.overall_status) && test.approval_status !== 'approved');
    if (matchingTests.length && !tests.length) return [];
    return tests.length ? tests.map((test: any) => ({ ...row, sourceKey: row.key, key: `${row.key}:test:${test.id}`, test })) : [{ ...row, sourceKey: row.key, test: null }];
}).filter((row: any) => {
    if (statusFilter.value !== 'All' && (row.test?.overall_status || (row.sample ? 'pending' : 'not_sampled')) !== statusFilter.value) return false;
    const search = loadSearch.value.trim().toLowerCase();
    return !search || [row.order.order_no, row.order.customer?.legal_name, row.order.site?.name,
    row.batch?.batch_no, row.dispatch.dispatch_no, invoiceNumber(row), row.sample?.sample_no,
    row.sample?.truck_no, row.sample?.source_location, row.sample?.concrete_grade?.name,
    row.sample?.material?.title, row.test?.test_no, row.test?.tester?.username]
        .some(value => String(value || '').toLowerCase().includes(search));
}));
const invoiceNumber = (row: any) => row.dispatch?.invoice_status?.invoice?.invoice_number || row.dispatch?.invoice_status?.invoice_number || 'Not invoiced';
const fieldTechnician = (row: any) => personnelOptions.value.find(person => person.value == row.sample?.tested_by)?.label
    || [row.sample?.tester?.first_name, row.sample?.tester?.last_name].filter(Boolean).join(' ') || '-';
const formMode = ref<'create' | 'edit'>(props.isEditing ? 'edit' : 'create');
const editing = computed(() => formMode.value === 'edit');
const editorSource = ref<any>(null);
const editingSample = ref<any>(props.sample || null);
const sourceKey = ref<string | null>(initialKey);
const formTarget = ref<HTMLElement | null>(null);
const createTarget = ref<HTMLElement | null>(null);
const editorElement = ref<HTMLElement | null>(null);
const editorTarget = computed(() => editing.value && !props.isEditing ? formTarget.value : createTarget.value);
const activeRowKey = computed(() => editing.value ? editorSource.value?.key : null);
const expandedRows = computed(() => activeRowKey.value ? { [activeRowKey.value]: true } : {});
const tableRows = ref(30);
const defaults = form.data();
const preferredTestType = () => (props.testTypes || []).find(type => /compressive/i.test(type.name))?.id || props.testTypes?.[0]?.id || null;
form.test_type_id = preferredTestType();
const testTypeOptions = computed(() => (props.testTypes || []).map(type => ({ label: type.name, value: type.id })));
const ageOptionsForTest = [{ label: '7 days', value: 7 }, { label: '15 days', value: 15 }, { label: '28 days', value: 28 }];
const sourceOptions = computed(() => productionRows.value.filter(row => !row.cancelled).map(row => ({
    value: row.key, label: (row.dispatch.dispatch_no || row.sample?.sample_no) + ' - ' + (row.order.customer?.legal_name || ''),
})));
const existingSample = computed(() => editorSource.value?.sample);
const metadataReadOnly = computed(() => !editing.value && !!existingSample.value);
const readOnlySampleFields = computed(() => [
    { label: 'Casting date', value: form.sample_date ? entityLocaleDate(form.sample_date) : '', icon: 'pi pi-calendar' },
    { label: 'Field technician', value: personnelOptions.value.find(person => person.value == form.tested_by)?.label || '', icon: 'pi pi-user' },
    { label: 'Project / Site', value: form.site_name, icon: 'pi pi-map-marker' },
    { label: 'Truck number', value: form.truck_no, icon: 'pi pi-truck' },
    { label: 'Slump (mm)', value: form.slump_mm, icon: 'pi pi-chart-bar' },
    { label: 'Sampling point', value: form.source_location, icon: 'pi pi-map-marker' },
    { label: 'Concrete temp (C)', value: form.concrete_temp_c, icon: 'pi pi-sun' },
    { label: 'Ambient temp (C)', value: form.ambient_temp_c, icon: 'pi pi-sun' },
    { label: 'Mould size', value: form.specimen_size, icon: 'pi pi-box' },
    { label: 'Curing tank', value: form.curing_tank_id, icon: 'pi pi-inbox' },
    { label: 'Specimens per load', value: form.specimen_count, icon: 'pi pi-th-large' },
    { label: 'Sample quantity', value: form.sample_quantity, icon: 'pi pi-box' },
]);
const existingTest = computed(() => !editing.value && existingSample.value?.tests?.find((test: any) => test.test_type_id == form.test_type_id && form.schedule_milestones.includes(Number(test.age_days))));
const laboratoryTests = computed(() => (editingSample.value?.tests || []).filter((test: any) => supportedTestAges.value.includes(Number(test.age_days))));
const milestoneError = computed(() => Object.entries(form.errors).find(([key]) => key === 'schedule_milestones' || key.startsWith('schedule_milestones.') || key === 'age_days')?.[1]);
const scheduledDate = computed(() => {
    const origin = editorSource.value?.dispatch.dispatch_time || editingSample.value?.dispatch?.dispatch_time || form.sample_date;
    if (!origin) return '';
    return [...form.schedule_milestones].sort((a, b) => a - b).map(age => {
        const date = new Date(entityToday(origin) + 'T12:00:00');
        date.setDate(date.getDate() + Number(age));
        return `${age}D: ${entityLocaleDate(date.toISOString())}`;
    }).join(' / ');
});
const sourceDetails = computed(() => [
    { label: 'Load / Sample', value: editorSource.value?.dispatch?.dispatch_no || editingSample.value?.sample_no || '-', icon: 'pi pi-truck', accent: false },
    { label: 'Account name', value: editorSource.value?.order?.customer?.legal_name || editingSample.value?.customer?.legal_name || '-', icon: 'pi pi-building', accent: false },
    { label: 'Invoice', value: editorSource.value ? invoiceNumber(editorSource.value) : '-', icon: 'pi pi-file', accent: false },
    { label: 'Grade / Material', value: (props.concreteGrades || []).find(grade => grade.id == form.concrete_grade_id)?.name || editingSample.value?.material?.title || '-', icon: 'pi pi-box', accent: true },
    { label: 'Project / Site', value: form.site_name || '-', icon: 'pi pi-map-marker', accent: false },
    { label: 'Truck number', value: form.truck_no || '-', icon: 'pi pi-truck', accent: false },
    { label: 'Sample number', value: editingSample.value?.sample_no || 'Generated on save', icon: 'pi pi-hashtag', accent: true },
    ...(!editing.value ? [{ label: 'Scheduled testing dates', value: scheduledDate.value || '-', icon: 'pi pi-calendar', accent: true }] : []),
]);
const loadSource = (row?: any) => {
    form.reset();
    form.clearErrors();
    form.test_type_id = preferredTestType();
    form.schedule_milestones = [row?.test?.age_days || ageFilter.value || (supportedTestAges.value.includes(row?.age) ? row.age : 7)];
    editorSource.value = row || null;
    editingSample.value = row?.sample || null;
    if (!row) return;
    for (const key of Object.keys(defaults)) {
        if (row.sample?.[key] != null) (form as any)[key] = row.sample[key];
    }
    form.sales_order_id = row.order.id || null;
    form.source_keys = [row.sample?.dispatch_id ? 'dispatch:' + row.sample.dispatch_id : row.sourceKey || row.key];
    form.sample_date = entityToday(row.sample?.sample_date || row.dispatch.dispatch_time);
    form.batch_id = row.sample?.batch_id || row.batch?.id || null;
    form.dispatch_id = row.sample?.dispatch_id || row.dispatch.id || null;
    form.customer_id = row.sample?.customer_id || row.order.customer_id || null;
    form.concrete_grade_id = row.sample?.concrete_grade_id || row.dispatch.mix_design?.concrete_grade_id || row.order.mix_design?.concrete_grade_id || null;
    form.site_name = row.sample?.site_name || row.dispatch.unload_site?.name || row.order.site?.name || '';
    form.truck_no = row.sample?.truck_no || row.dispatch.truck?.registration || '';
};
watch(sourceKey, key => {
    if (!editing.value) loadSource(productionRows.value.find(row => row.key === key));
});
const scrollToForm = async () => {
    await nextTick();
    editorElement.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};
const openCreateTest = (row?: any) => {
    formMode.value = 'create';
    sourceKey.value = row?.sourceKey || row?.key || null;
    loadSource(row);
    void scrollToForm();
};
const openEditor = (row: any) => {
    formMode.value = 'edit';
    loadSource(row);
    if (!filteredRows.value.some((entry: any) => entry.key === row.key)) {
        ageFilter.value = null;
        statusFilter.value = 'All';
        loadSearch.value = '';
    }
    const index = filteredRows.value.findIndex((entry: any) => entry.key === row.key);
    if (index >= 0) tableFirst.value = Math.floor(index / tableRows.value) * tableRows.value;
    void scrollToForm();
};
const closeEditor = () => {
    if (props.isEditing) emit('cancel');
    else {
        formMode.value = 'create';
        sourceKey.value = null;
        loadSource();
    }
};
const mouldOptions = [
    { label: '150 x 150 x 150 mm', value: '150x150x150 mm' },
    { label: '100 x 100 x 100 mm', value: '100x100x100 mm' },
    { label: '70.6 x 70.6 x 70.6 mm', value: '70.6x70.6x70.6 mm' },
];
const samplingPointOptions = ['Batching Plant Discharge', 'Transit Mixer (TM) Chute', 'Pump Outlet at Site', 'Formwork / Placing Point', 'Other'].map(value => ({ label: value, value }));
const rowStatus = (row: any) => row.cancelled ? 'Cancelled' : row.test?.overall_status || (row.sample ? 'No test' : 'Not sampled');
const canDownloadReport = (row: any) => {
    const type = (props.testTypes || []).find(type => type.id == row.test?.test_type_id);
    const name = `${type?.name || ''} ${type?.code || ''}`.toLowerCase();
    return ['pass', 'fail', 'retest'].includes(row.test?.overall_status)
        && !/flexur|slump|tensile|beam|cement|aggregate/.test(name)
        && (type?.category === 'Concrete' || /cube|compressive/.test(name));
};
const actionPopover = ref<InstanceType<typeof Popover> | null>(null);
const actionRow = ref<any>(null);
const openActions = (event: Event, row: any) => {
    actionRow.value = row;
    actionPopover.value?.toggle(event);
};
const editAction = () => {
    if (actionRow.value?.sample) openEditor(actionRow.value);
    actionPopover.value?.hide();
};
const executeAction = () => {
    if (actionRow.value && !actionRow.value.test) openCreateTest(actionRow.value);
    actionPopover.value?.hide();
};
const statusClass = (row: any) => ({
    pass: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    fail: 'bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    retest: 'bg-tangerine-50 text-tangerine-700 dark:bg-tangerine-900/40 dark:text-tangerine-300',
} as Record<string, string>)[row.test?.overall_status as string] || 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
const submit = () => {
    const success = () => emit('saved');
    if (editing.value) {
        form.transform(data => {
            const { schedule_milestones, test_type_ids, test_type_id, execute_after_create, ...metadata } = data;
            if (metadata.sales_order_id) return metadata;
            const { sales_order_id, source_keys, ...details } = metadata;
            return details;
        }).put(route('quality.samples.update', editingSample.value.id), { preserveScroll: true, onSuccess: success });
    } else if (existingSample.value) {
        form.transform(data => ({ sample_id: existingSample.value.id, test_type_id: data.test_type_id, schedule_milestones: data.schedule_milestones, remarks: data.remarks }))
            .post(route('quality.tests.store'), { onSuccess: success });
    } else {
        if (!sourceKey.value) return;
        form.transform(data => ({ ...data, test_type_ids: data.test_type_id ? [data.test_type_id] : [], schedule_milestones: data.schedule_milestones, execute_after_create: true }))
            .post(route('quality.samples.store'), { onSuccess: success });
    }
};
</script>

<template>
    <div class="sample-form min-w-0 space-y-4 text-sm text-slate-700 dark:text-slate-200">
        <div ref="createTarget" class="min-w-0"></div>

        <div v-if="!isEditing" class="min-w-0">
            <BaseDataTable :value="filteredRows" dataKey="key" :paginator="true" :rows="tableRows"
                :rowsPerPageOptions="[30, 50, 100]" :first="tableFirst"
                @page="tableFirst = $event.first; tableRows = $event.rows" :stripedRows="true"
                :expandedRows="expandedRows" :expandOnRowClick="false"
                :rowClass="(row: any) => [row.cancelled ? 'opacity-60' : '', row.key === activeRowKey ? 'qc-active-row' : ''].join(' ')"
                :showAdvancedFilter="false" heading="Concrete Test Directory" headingIcon="ListBulletIcon" showSerial
                class="qc-production-table">
                <template #toolbar>
                    <div class="flex flex-wrap items-center gap-3">
                        <BaseInput v-model="loadSearch" placeholder="Search dispatch, sample or customer"
                            aria-label="Search concrete tests" class="w-full sm:w-64" />
                        <BaseSelect v-model="ageFilter" :options="ageOptions" optionLabel="label" optionValue="value"
                            placeholder="Current age" aria-label="Filter current age" class="w-36" />
                        <BaseSelect v-model="statusFilter" :options="statusOptions" optionLabel="label"
                            optionValue="value" aria-label="Filter status" class="w-36" />
                        <!-- <BaseButton type="button" v-if="canCreateTests" label="New test" icon="pi pi-plus" @click="openCreateTest()" /> -->
                    </div>
                </template>
                <Column header="Test / Sample" style="min-width: 190px">
                    <template #body="{ data }">
                        <Link v-if="data.test" :href="route('quality.tests.execute', data.test.id)"
                            class="block font-medium text-indigo-600 hover:text-indigo-700 hover:underline dark:text-indigo-300 dark:hover:text-indigo-200">
                            {{ data.test.test_no }}</Link>
                        <span v-else class="text-slate-500">{{ data.sample?.sample_no || data.dispatch.dispatch_no
                            }}</span>
                        <span class="mt-2 inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold capitalize"
                            :class="statusClass(data)">{{ rowStatus(data) }}</span>

                    </template>
                </Column>
                <Column header="Account / Invoice" style="min-width: 200px">
                    <template #body="{ data }">
                        <p class="font-medium text-slate-800 dark:text-slate-100">{{ data.order.customer?.legal_name ||
                            '-' }}</p>
                        <p class="mt-1 text-xxs text-slate-500">{{ data.dispatch.id ? invoiceNumber(data) : '-' }}</p>
                    </template>
                </Column>
                <Column header="Grade / Age" style="min-width: 120px">
                    <template #body="{ data }">
                        <p class="font-medium">{{ data.sample?.concrete_grade?.name ||
                            data.dispatch.mix_design?.concrete_grade?.name ||
                            data.order.mix_design?.concrete_grade?.name || data.sample?.material?.title || '-' }}</p>
                        <p class="mt-1 text-xxs text-slate-500">{{ data.test ? data.test.age_days + 'D test' : data.age
                            === null ? '-' : data.age + 'd old' }}</p>
                    </template>
                </Column>
                <Column header="Dispatch / Due date" style="min-width: 180px">
                    <template #body="{ data }">
                        <p>{{ entityLocaleDate(data.dispatch.dispatch_time) }}</p>
                        <p class="mt-1 text-xxs text-slate-500">Due today <span
                                class="text-indigo-600 font-bold text-xs">{{
                                    data.age
                                }}D</span></p>
                    </template>
                </Column>
                <Column header="Field / Lab technician" style="min-width: 180px">
                    <template #body="{ data }">
                        <p>{{ fieldTechnician(data) }}</p>
                        <p class="mt-1 text-xxs text-slate-500">{{ data.test && (data.test.evaluated_at ||
                            data.test.overall_status !== 'pending') ? data.test.tester?.username || '-' : 'Not tested'
                            }}</p>
                    </template>
                </Column>
                <Column header="Actions" style="width: 60px">
                    <template #body="{ data }">
                        <Button type="button" icon="pi pi-ellipsis-v" text rounded severity="secondary"
                            aria-label="Test actions" aria-haspopup="true" aria-controls="sample-test-actions"
                            @click.stop="openActions($event, data)" />
                    </template>
                </Column>
                <template #expansion>
                    <div ref="formTarget" class="min-w-0 p-3"></div>
                </template>
                <template #empty>
                    <p class="py-6 text-center text-sm text-slate-500">No 7-day, 15-day or 28-day loads or samples match
                        your filters.</p>
                </template>
            </BaseDataTable>
            <p class="mt-2 text-xxs text-slate-500">Only tests to perform exactly 7, 15 or 28 days after dispatch are
                listed.
                Completed tests and other ages are excluded.</p>
        </div>

        <Popover ref="actionPopover" id="sample-test-actions">
            <div v-if="actionRow" class="flex min-w-48 flex-col gap-1 text-sm text-slate-700 dark:text-slate-200">
                <p
                    class="border-b border-slate-100 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    {{
                        actionRow.test?.test_no || actionRow.sample?.sample_no || actionRow.dispatch.dispatch_no }}</p>
                <button type="button" class="qc-menu-item" :disabled="!actionRow.sample || !can('QC_SAMPLE.UPDATE')"
                    @click="editAction"><i class="pi pi-pencil" aria-hidden="true"></i>Edit</button>
                <Link v-if="actionRow.test && can('QC_TEST.VIEW')"
                    :href="route('quality.tests.execute', { test: actionRow.test.id, view: 1 })" class="qc-menu-item"
                    @click="actionPopover?.hide()"><i class="pi pi-eye" aria-hidden="true"></i>View
                </Link>
                <button v-else type="button" class="qc-menu-item" disabled><i class="pi pi-eye"
                        aria-hidden="true"></i>View</button>
                <Link v-if="actionRow.test && can('QC_TEST.UPDATE')"
                    :href="route('quality.tests.execute', actionRow.test.id)" class="qc-menu-item"
                    @click="actionPopover?.hide()"><i class="pi pi-play" aria-hidden="true"></i>Execute
                    test</Link>
                <button v-else type="button"
                    :disabled="!!actionRow.test || !canCreateTests || (!actionRow.sample && !canCreateSamples)"
                    class="qc-menu-item" @click="executeAction"><i class="pi pi-play" aria-hidden="true"></i>Execute
                    test</button>
                <a v-if="canDownloadReport(actionRow) && can('QC_TEST.PDF')"
                    :href="route('quality.tests.cube-report-pdf', { test: actionRow.test.id, inline: 1 })"
                    target="_blank" rel="noopener" class="qc-menu-item" @click="actionPopover?.hide()"><i
                        class="pi pi-print" aria-hidden="true"></i>Print results</a>
                <button v-else type="button" class="qc-menu-item" disabled><i class="pi pi-print"
                        aria-hidden="true"></i>Print
                    results</button>
                <div class="border-t border-slate-100 pt-1">
                    <BaseDeleteButton v-if="actionRow.sample"
                        :disabled="!can(actionRow.test ? 'QC_TEST.DELETE' : 'QC_SAMPLE.DELETE')"
                        :url="actionRow.test ? route('quality.tests.destroy', actionRow.test.id) : route('quality.samples.destroy', actionRow.sample.id)"
                        :title="actionRow.test ? 'Delete test?' : 'Delete sample?'"
                        :text="actionRow.test ? 'Delete this test and retain its sample?' : 'Delete this sample?'"
                        :iconOnly="false" buttonText="Delete" class="!w-full !justify-start !border-0 !text-rose-600"
                        @success="actionPopover?.hide(); emit('saved')" />
                    <button v-else type="button" class="qc-menu-item text-rose-600" disabled><i class="pi pi-trash"
                            aria-hidden="true"></i>Delete</button>
                </div>
            </div>
        </Popover>

        <Teleport :to="editorTarget || 'body'" :disabled="!editorTarget">
            <section v-if="editing || canCreateTests" id="sample-create-editor" ref="editorElement"
                class="min-w-0 rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3 p-5">
                    <div class="flex items-center gap-4">
                        <div class="rounded-xl bg-indigo-50 p-2.5 dark:bg-indigo-900/40">
                            <i class="pi pi-clipboard text-indigo-600 dark:text-indigo-300" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800 dark:text-slate-100">{{
                                editing
                                    ? 'Edit sample' : 'Create sample & test' }}</h2>
                            <p class="text-[10px] font-medium tracking-tight text-slate-400">Record concrete samples and
                                manage
                                cube testing</p>
                        </div>
                    </div>
                    <span v-if="editingSample"
                        class="rounded-md border border-indigo-100 bg-indigo-50 px-2.5 py-1 text-xxs font-medium text-indigo-700 dark:border-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">{{
                            editingSample.sample_no }}</span>
                </div>
                <form @submit.prevent="submit"
                    class="space-y-6 border-t border-slate-100 p-4 sm:p-6 dark:border-slate-800">

                    <div class="grid grid-cols-1 items-stretch gap-6 lg:grid-cols-12">
                        <!-- Left Column: Stacked vertically to match the height of Load Details & prevent text cutoff -->
                        <div v-if="!editing"
                            class="flex flex-col justify-between gap-4 sm:grid sm:grid-cols-3 lg:flex lg:col-span-4 lg:flex-col">
                            <BaseSelect v-model="sourceKey" label="Load / Sample" required :options="sourceOptions"
                                optionLabel="label" optionValue="value" filter placeholder="Select a load or sample"
                                :error="form.errors.source_keys || form.errors.sample_id" />
                            <BaseSelect v-model="form.test_type_id" label="Test type" required
                                :options="testTypeOptions" optionLabel="label" optionValue="value"
                                :error="form.errors.test_type_id || form.errors.test_type_ids" />
                            <BaseField label="Test ages" required :error="milestoneError"
                                hint="Select one or more: 7, 15 and 28 days.">
                                <template #default="{ invalid, inputId }">
                                    <MultiSelect :inputId="inputId" v-model="form.schedule_milestones"
                                        :options="ageOptionsForTest" optionLabel="label" optionValue="value"
                                        display="chip" placeholder="Select test ages" :invalid="invalid"
                                        :maxSelectedLabels="3" class="w-full" />
                                </template>
                            </BaseField>
                        </div>

                        <!-- Right Column: Clean Load Details without field icons -->
                        <section :class="editing ? 'lg:col-span-12' : 'lg:col-span-8'"
                            class="flex flex-col rounded-xl border border-indigo-100 bg-indigo-50/40 p-5 dark:border-indigo-800/80 dark:bg-indigo-950/20"
                            aria-label="Read-only load details">
                            <!-- Clean Header (No truck/lock icons) -->
                            <div
                                class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-indigo-100/80 pb-3 dark:border-indigo-800/60">
                                <h3
                                    class="text-xs font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">
                                    Load &amp; Batch Details
                                </h3>
                                <span
                                    class="rounded-md bg-indigo-100/70 px-2.5 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                    Read only
                                </span>
                            </div>

                            <div v-if="!sourceKey && !editing" class="flex flex-1 items-center justify-center py-8">
                                <p class="text-xs italic text-slate-500 dark:text-slate-400">
                                    Select a load or sample on the left to populate these details.
                                </p>
                            </div>

                            <!-- Clean 4-Column Data Grid with Typographic Headers instead of Icons -->
                            <dl v-else class="grid flex-1 grid-cols-2 content-between gap-x-6 gap-y-5 sm:grid-cols-4">
                                <div v-for="field in sourceDetails" :key="field.label"
                                    class="min-w-0 pl-3 dark:border-indigo-800/70">
                                    <dt
                                        class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                                        {{ field.label }}
                                    </dt>
                                    <dd class="mt-1 break-words text-sm font-semibold leading-snug"
                                        :class="field.accent ? 'text-indigo-700 dark:text-indigo-300' : 'text-slate-800 dark:text-slate-100'">
                                        {{ field.value || '—' }}
                                    </dd>
                                </div>
                            </dl>
                        </section>
                    </div>
                    <div class="grid min-w-0 grid-cols-1 items-end gap-6 sm:grid-cols-2 lg:grid-cols-5">
                        <fieldset v-if="!metadataReadOnly" class="contents">
                            <BaseDatePicker v-model="form.sample_date" label="Casting date" required
                                dateFormat="yy-mm-dd" :disabled="metadataReadOnly" :error="form.errors.sample_date" />
                            <BaseSelect v-model="form.tested_by" label="Field technician" :options="personnelOptions"
                                optionLabel="label" optionValue="value" filter :disabled="metadataReadOnly"
                                :error="form.errors.tested_by" />
                            <BaseInput v-model="form.slump_mm" type="number" label="Slump (mm)" min="0" max="500"
                                :error="form.errors.slump_mm" />
                            <BaseSelect v-model="form.source_location" label="Sampling point"
                                :options="samplingPointOptions" optionLabel="label" optionValue="value"
                                :disabled="metadataReadOnly" :error="form.errors.source_location" />
                            <BaseInput v-model="form.concrete_temp_c" type="number" step="0.5" label="Concrete temp (C)"
                                :error="form.errors.concrete_temp_c" />
                            <BaseInput v-model="form.ambient_temp_c" type="number" step="0.5" label="Ambient temp (C)"
                                :error="form.errors.ambient_temp_c" />
                            <BaseSelect v-model="form.specimen_size" label="Mould size" :options="mouldOptions"
                                optionLabel="label" optionValue="value" :disabled="metadataReadOnly"
                                :error="form.errors.specimen_size" />
                            <BaseInput v-model="form.curing_tank_id" label="Curing tank"
                                :error="form.errors.curing_tank_id" />
                            <BaseInput v-model="form.specimen_count" type="number" label="Specimens per load" min="1"
                                max="30" step="1" :error="form.errors.specimen_count" />
                            <BaseInput v-model="form.sample_quantity" label="Sample quantity"
                                :error="form.errors.sample_quantity" />
                        </fieldset>
                        <section v-else
                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2 lg:col-span-5 dark:border-slate-700 dark:bg-slate-800/50"
                            aria-label="Existing sample details">
                            <h3
                                class="mb-4 flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <i class="pi pi-lock" aria-hidden="true"></i>Existing sample details
                            </h3>
                            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-5">
                                <div v-for="field in readOnlySampleFields.filter(field => !['Project / Site', 'Truck number'].includes(field.label))"
                                    :key="field.label" class="min-w-0">
                                    <dt class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">{{
                                        field.label }}
                                    </dt>
                                    <dd class="mt-1 break-words text-sm font-medium text-slate-800 dark:text-slate-100">
                                        {{
                                            field.value === '' || field.value == null ? '-' : field.value }}</dd>
                                </div>
                            </dl>
                        </section>
                        <BaseInput v-model="form.remarks" label="Description / Remarks"
                            class="sm:col-span-2 lg:col-span-5" :error="form.errors.remarks" />
                    </div>
                    <div class="space-y-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Laboratory test
                            information
                        </h3>
                        <div v-if="laboratoryTests.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <div v-for="test in laboratoryTests" :key="test.id"
                                class="flex flex-wrap items-center justify-between gap-3 py-2 text-sm">
                                <div><span class="font-medium">{{ test.age_days }}D · {{ test.test_no }}</span>
                                    <p class="text-xs text-slate-500">{{ test.test_date ? 'Tested ' +
                                        entityLocaleDate(test.test_date) : 'Due ' +
                                        entityLocaleDate(test.scheduled_date) }} ·
                                        {{ test.overall_status }}</p>
                                </div>
                                <Link :href="route('quality.tests.execute', test.id)"
                                    class="text-indigo-600 hover:text-indigo-700 hover:underline dark:text-indigo-300 dark:hover:text-indigo-200">
                                    {{ ['pending', 'retest'].includes(test.overall_status) ? `Enter cube results` :
                                        `View cube
                                    results` }}</Link>
                            </div>
                        </div>
                        <p v-else class="text-xs text-slate-500">Save the sample to enter cube weights and loads,
                            calculate
                            compressive strength and record the lab technician in the results editor.</p>
                    </div>
                    <p v-if="metadataReadOnly" class="text-xs text-slate-500">Existing sample details are shown for
                        reference.
                        Use the row's Edit sample action to change them.</p>
                    <p v-else-if="!editing && sourceKey" class="text-xs text-slate-500">Scheduled testing dates: {{
                        scheduledDate
                        }}. After saving, enter the specimen weights and loads to execute the test.</p>
                    <p v-if="existingTest" role="alert" class="text-xs text-tangerine-700 dark:text-tangerine-300">This
                        test
                        already exists at one of the selected ages. Remove that age to schedule the others.
                        <Link :href="route('quality.tests.execute', existingTest.id)" class="font-medium underline">Open
                            {{
                                existingTest.test_no }}</Link>
                    </p>
                    <p v-if="form.errors.sales_order_id" role="alert" class="text-xs text-rose-600 dark:text-rose-400">
                        {{
                            form.errors.sales_order_id }}</p>
                    <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                        <BaseButton type="button" :label="editing ? 'Cancel' : 'Reset'" severity="secondary"
                            @click="closeEditor" />
                        <BaseButton type="submit" :label="editing ? 'Save sample' : 'Save & enter results'"
                            :loading="form.processing" />
                    </div>
                </form>
            </section>
        </Teleport>
    </div>
</template>

<style scoped>
.qc-menu-item {
    @apply flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition-colors hover:bg-indigo-50 hover:text-indigo-700 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-indigo-900/40 dark:hover:text-indigo-300;
}

.qc-menu-item i {
    @apply text-sm text-indigo-500;
}

.sample-form .grid>*,
.sample-form fieldset>* {
    min-width: 0;
}

.sample-form :deep(.qc-production-table .p-datatable-table-container) {
    overflow-x: auto;
}

.sample-form :deep(.qc-production-table .p-datatable-table) {
    min-width: 1100px;
}
</style>
