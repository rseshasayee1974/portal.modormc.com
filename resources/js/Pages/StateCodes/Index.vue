<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useStateCodeStore } from '@/Pages/StateCodes/useStateCodeStore';
import Toast from 'primevue/toast';
import Column from 'primevue/column';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import { useToast } from 'primevue/usetoast';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';

const store = useStateCodeStore();
const toast = useToast();

interface StateCode {
    id: number;
    country_id: number;
    state_code: string;
    state_name: string;
    zipcode?: string | null;
    area?: string | null;
    district?: string | null;
}

interface CountryOption {
    id: number;
    country_name: string;
}

const props = defineProps<{
    stateCodes: StateCode[];
    countries: CountryOption[];
}>();

onMounted(() => {
    store.setStateCodes(props.stateCodes);
    store.setCountries(props.countries);
});

const createOpen = ref(false);
const filterCountry = ref<number | null>(null);
const expandedRows = ref<Record<number, boolean>>({});
const editingId = ref<number | null>(null);

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const countryFilterOptions = computed(() => [
    { label: 'All countries', value: null },
    ...store.countries.map((c) => ({ label: c.country_name, value: c.id })),
]);

const createForm = ref({
    country_id: null as number | null,
    state_code: '',
    state_name: '',
    zipcode: '',
    area: '',
    district: '',
    processing: false,
    errors: { country_id: '', state_code: '', state_name: '', zipcode: '', area: '', district: '' },
});

const editForm = ref({
    country_id: null as number | null,
    state_code: '',
    state_name: '',
    zipcode: '',
    area: '',
    district: '',
    processing: false,
    errors: { country_id: '', state_code: '', state_name: '', zipcode: '', area: '', district: '' },
});

const resetCreateForm = () => {
    createForm.value.country_id = null;
    createForm.value.state_code = '';
    createForm.value.state_name = '';
    createForm.value.zipcode = '';
    createForm.value.area = '';
    createForm.value.district = '';
    createForm.value.errors = { country_id: '', state_code: '', state_name: '', zipcode: '', area: '', district: '' };
};

const resetEditForm = () => {
    editingId.value = null;
    expandedRows.value = {};
    editForm.value.country_id = null;
    editForm.value.state_code = '';
    editForm.value.state_name = '';
    editForm.value.zipcode = '';
    editForm.value.area = '';
    editForm.value.district = '';
    editForm.value.errors = { country_id: '', state_code: '', state_name: '', zipcode: '', area: '', district: '' };
};

const getCountryName = (id: number) => {
    return store.countries.find((c) => c.id === id)?.country_name || 'Unknown';
};

const filteredStateCodes = computed(() => {
    return store.stateCodes
        .filter((item) => !filterCountry.value || item.country_id === filterCountry.value)
        .map((item) => ({
            ...item,
            country_name: getCountryName(item.country_id),
        }));
});

const submitCreate = async () => {
    createForm.value.processing = true;
    createForm.value.errors = { country_id: '', state_code: '', state_name: '', zipcode: '', area: '', district: '' };
    try {
        const response = await axios.post(route('statecodes.store'), {
            country_id: createForm.value.country_id,
            state_code: createForm.value.state_code,
            state_name: createForm.value.state_name,
            zipcode: createForm.value.zipcode,
            area: createForm.value.area,
            district: createForm.value.district,
        });
        store.addStateCode(response.data.stateCode);
        toast.add({ severity: 'success', summary: 'Created', detail: 'State code created successfully.', life: 1500 });
        createOpen.value = false;
        resetCreateForm();
    } catch (error: any) {
        const errs = error?.response?.data?.errors || {};
        createForm.value.errors.country_id = errs.country_id?.[0] || '';
        createForm.value.errors.state_code = errs.state_code?.[0] || '';
        createForm.value.errors.state_name = errs.state_name?.[0] || '';
        createForm.value.errors.zipcode = errs.zipcode?.[0] || '';
        createForm.value.errors.area = errs.area?.[0] || '';
        createForm.value.errors.district = errs.district?.[0] || '';
        if (!Object.keys(errs).length) {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Unable to create state code.', life: 1500 });
        }
    } finally {
        createForm.value.processing = false;
    }
};

const onRowExpand = (event: { data: StateCode }) => {
    const row = event.data;
    expandedRows.value = { [row.id]: true };
    editingId.value = row.id;
    editForm.value.country_id = row.country_id;
    editForm.value.state_code = row.state_code;
    editForm.value.state_name = row.state_name;
    editForm.value.zipcode = row.zipcode || '';
    editForm.value.area = row.area || '';
    editForm.value.district = row.district || '';
    editForm.value.errors = { country_id: '', state_code: '', state_name: '', zipcode: '', area: '', district: '' };
};

const onRowCollapse = () => {
    resetEditForm();
};

const submitEdit = async () => {
    if (!editingId.value) return;
    editForm.value.processing = true;
    editForm.value.errors = { country_id: '', state_code: '', state_name: '', zipcode: '', area: '', district: '' };
    try {
        const response = await axios.put(route('statecodes.update', editingId.value), {
            country_id: editForm.value.country_id,
            state_code: editForm.value.state_code,
            state_name: editForm.value.state_name,
            zipcode: editForm.value.zipcode,
            area: editForm.value.area,
            district: editForm.value.district,
        });
        store.updateStateCode(response.data.stateCode);
        toast.add({ severity: 'success', summary: 'Updated', detail: 'State code updated successfully.', life: 1500 });
        resetEditForm();
    } catch (error: any) {
        const errs = error?.response?.data?.errors || {};
        editForm.value.errors.country_id = errs.country_id?.[0] || '';
        editForm.value.errors.state_code = errs.state_code?.[0] || '';
        editForm.value.errors.state_name = errs.state_name?.[0] || '';
        editForm.value.errors.zipcode = errs.zipcode?.[0] || '';
        editForm.value.errors.area = errs.area?.[0] || '';
        editForm.value.errors.district = errs.district?.[0] || '';
        if (!Object.keys(errs).length) {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Unable to update state code.', life: 1500 });
        }
    } finally {
        editForm.value.processing = false;
    }
};

const deleteStateCode = (row: StateCode) => {
    Swal.fire({
        title: 'Delete this state code?',
        html: `<span style="font-size:13px;color:#64748b"><b>${row.state_name}</b> will be permanently removed.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete',
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        try {
            await axios.delete(route('statecodes.destroy', row.id));
            store.removeStateCode(row.id);
            if (editingId.value === row.id) {
                resetEditForm();
            }
            toast.add({ severity: 'warn', summary: 'Deleted', detail: 'State code removed.', life: 1500 });
        } catch {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete state code.', life: 1500 });
        }
    });
};

watch(filterCountry, () => {
    resetEditForm();
});
</script>

<template>
    <AppLayout title="State Codes">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <Toast />

        <div class="page-container">
            <div class="page-heading">
                <div class="flex items-center gap-4">
                    <div class="page-logo">
                        <i class="pi pi-map text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="page-title">State Codes</h1>
                        <p class="page-sub">Country-state master with search and inline editing</p>
                    </div>
                </div>
                <div class="page-stat">
                    <i class="pi pi-database text-indigo-400 text-lg"></i>
                    <span>{{ store.stateCodes.length }} total states</span>
                </div>
            </div>

            <div class="create-panel" :class="{ 'create-panel--open': createOpen }">
                <button type="button" class="create-panel__header" @click="createOpen = !createOpen">
                    <div class="flex items-center gap-3">
                        <div class="create-panel__icon">
                            <i class="pi pi-plus text-indigo-500 text-sm"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-xs font-bold text-gray-700 uppercase tracking-widest">Create State Code</p>
                            <p class="text-[11px] text-gray-400 font-medium mt-0.5">Add a new geographic state mapping</p>
                        </div>
                    </div>
                    <div class="create-panel__toggle" :class="{ 'create-panel__toggle--open': createOpen }">
                        <i class="pi pi-plus text-[10px]"></i>
                    </div>
                </button>

                <Transition name="panel-slide">
                    <div v-if="createOpen" class="create-panel__body">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="field-group">
                                <label class="field-label">Country <span class="text-red-500">*</span></label>
                                <BaseSelect
                                    v-model="createForm.country_id"
                                    :options="store.countries"
                                    optionLabel="country_name"
                                    optionValue="id"
                                    placeholder="Select country"
                                    filter
                                    :class="{ 'p-invalid': createForm.errors.country_id }"
                                />
                                <small v-if="createForm.errors.country_id" class="field-error">{{ createForm.errors.country_id }}</small>
                            </div>
                            <div class="field-group">
                                <label class="field-label">State Name <span class="text-red-500">*</span></label>
                                <BaseInput
                                    v-model="createForm.state_name"
                                    placeholder="e.g. Maharashtra"
                                    :class="{ 'p-invalid': createForm.errors.state_name }"
                                />
                                <small v-if="createForm.errors.state_name" class="field-error">{{ createForm.errors.state_name }}</small>
                            </div>
                            <div class="field-group">
                                <label class="field-label">State Code <span class="text-red-500">*</span></label>
                                <BaseInput
                                    v-model="createForm.state_code"
                                    placeholder="e.g. MH"
                                    :class="{ 'p-invalid': createForm.errors.state_code }"
                                />
                                <small v-if="createForm.errors.state_code" class="field-error">{{ createForm.errors.state_code }}</small>
                            </div>
                            <div class="field-group">
                                <label class="field-label">District</label>
                                <BaseInput
                                    v-model="createForm.district"
                                    placeholder="e.g. Coimbatore"
                                    :class="{ 'p-invalid': createForm.errors.district }"
                                />
                                <small v-if="createForm.errors.district" class="field-error">{{ createForm.errors.district }}</small>
                            </div>
                            <div class="field-group">
                                <label class="field-label">Zipcode</label>
                                <BaseInput
                                    v-model="createForm.zipcode"
                                    placeholder="e.g. 641001"
                                    :class="{ 'p-invalid': createForm.errors.zipcode }"
                                />
                                <small v-if="createForm.errors.zipcode" class="field-error">{{ createForm.errors.zipcode }}</small>
                            </div>
                            <div class="field-group">
                                <label class="field-label">Area</label>
                                <BaseInput
                                    v-model="createForm.area"
                                    placeholder="e.g. Town Hall"
                                    :class="{ 'p-invalid': createForm.errors.area }"
                                />
                                <small v-if="createForm.errors.area" class="field-error">{{ createForm.errors.area }}</small>
                            </div>
                        </div>
                        <div class="expansion-actions">
                            <Button label="Save State Code" icon="pi pi-check" :loading="createForm.processing" @click="submitCreate" />
                            <Button label="Reset" text severity="secondary" @click="resetCreateForm" />
                        </div>
                    </div>
                </Transition>
            </div>

            <BaseDataTable
                v-model:expandedRows="expandedRows"
                :value="filteredStateCodes"
                v-model:filters="filters"
                :globalFilterFields="['state_code', 'district', 'zipcode', 'area']"
                showSearch
                showSerial
                heading="State Codes Directory"
                headingIcon="BuildingOfficeIcon"
                :rows="10"
                dataKey="id"
                class="unit-datatable text-xs"
                @rowExpand="onRowExpand"
                @rowCollapse="onRowCollapse"
            >
                <template #toolbar>
                    <BaseSelect
                        v-model="filterCountry"
                        :options="countryFilterOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="!w-44 !h-10 !text-[11px] !font-bold !bg-slate-50 !border-slate-200 !rounded-lg shadow-sm"
                    />
                </template>
                <Column expander style="width: 46px; padding: 0 12px;" />

                <Column field="state_code" header="State Code" sortable style="width: 120px">
                    <template #body="{ data }">
                        <code class="code-chip">{{ data.state_code }}</code>
                    </template>
                </Column>

                <Column field="district" header="District" sortable>
                    <template #body="{ data }">
                        <span class="font-semibold text-gray-800 text-sm">{{ data.district || '-' }}</span>
                    </template>
                </Column>

                <Column field="zipcode" header="Zipcode" sortable style="width: 150px">
                    <template #body="{ data }">
                        <span class="font-semibold text-gray-700 text-sm">{{ data.zipcode || '-' }}</span>
                    </template>
                </Column>

                <Column field="area" header="Area" sortable>
                    <template #body="{ data }">
                        <span class="text-gray-600 text-sm">{{ data.area || '-' }}</span>
                    </template>
                </Column>

                    <Column header="" style="width: 56px; text-align: right">
                        <template #body="{ data }">
                            <Button
                                icon="pi pi-trash"
                                text
                                rounded
                                severity="danger"
                                class="delete-btn"
                                v-tooltip.left="'Delete'"
                                @click.stop="deleteStateCode(data)"
                            />
                        </template>
                    </Column>

                    <template #expansion="{ data }">
                        <div class="expansion-panel">
                            <div class="expansion-label">
                                <i class="pi pi-pen-to-square text-indigo-500 text-xs"></i>
                                <span>Editing — <strong>{{ data.state_name }}</strong></span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4 max-w-4xl">
                                <div class="field-group">
                                    <label class="field-label">Country <span class="text-red-500">*</span></label>
                                    <BaseSelect
                                        v-model="editForm.country_id"
                                        :options="store.countries"
                                        optionLabel="country_name"
                                        optionValue="id"
                                        filter
                                        :class="{ 'p-invalid': editForm.errors.country_id }"
                                    />
                                    <small v-if="editForm.errors.country_id" class="field-error">{{ editForm.errors.country_id }}</small>
                                </div>
                                <div class="field-group">
                                    <label class="field-label">State Name <span class="text-red-500">*</span></label>
                                    <BaseInput
                                        v-model="editForm.state_name"
                                        :class="{ 'p-invalid': editForm.errors.state_name }"
                                    />
                                    <small v-if="editForm.errors.state_name" class="field-error">{{ editForm.errors.state_name }}</small>
                                </div>
                                <div class="field-group">
                                    <label class="field-label">State Code <span class="text-red-500">*</span></label>
                                    <BaseInput
                                        v-model="editForm.state_code"
                                        :class="{ 'p-invalid': editForm.errors.state_code }"
                                    />
                                    <small v-if="editForm.errors.state_code" class="field-error">{{ editForm.errors.state_code }}</small>
                                </div>
                                <div class="field-group">
                                    <label class="field-label">District</label>
                                    <BaseInput
                                        v-model="editForm.district"
                                        placeholder="e.g. Coimbatore"
                                        :class="{ 'p-invalid': editForm.errors.district }"
                                    />
                                    <small v-if="editForm.errors.district" class="field-error">{{ editForm.errors.district }}</small>
                                </div>
                                <div class="field-group">
                                    <label class="field-label">Zipcode</label>
                                    <BaseInput
                                        v-model="editForm.zipcode"
                                        placeholder="e.g. 641001"
                                        :class="{ 'p-invalid': editForm.errors.zipcode }"
                                    />
                                    <small v-if="editForm.errors.zipcode" class="field-error">{{ editForm.errors.zipcode }}</small>
                                </div>
                                <div class="field-group">
                                    <label class="field-label">Area</label>
                                    <BaseInput
                                        v-model="editForm.area"
                                        placeholder="e.g. Town Hall"
                                        :class="{ 'p-invalid': editForm.errors.area }"
                                    />
                                    <small v-if="editForm.errors.area" class="field-error">{{ editForm.errors.area }}</small>
                                </div>
                            </div>

                            <div class="expansion-actions">
                                <Button label="Save Changes" icon="pi pi-check" :loading="editForm.processing" @click="submitEdit" />
                                <Button label="Cancel" text severity="secondary" @click="resetEditForm" />
                            </div>
                        </div>
                    </template>

                    <template #empty>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="pi pi-filter-slash text-2xl text-slate-300"></i>
                            </div>
                            <p class="empty-title">No state codes found</p>
                            <p class="empty-sub">Try clearing your search or changing filters</p>
                        </div>
                    </template>
            </BaseDataTable>
        </div>
    </AppLayout>
</template>
