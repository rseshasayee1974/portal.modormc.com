<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useStateCodeStore } from '@/Pages/StateCodes/useStateCodeStore';
import Toast from 'primevue/toast';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import { useToast } from 'primevue/usetoast';

const store = useStateCodeStore();
const toast = useToast();

interface StateCode {
    id: number;
    country_id: number;
    state_code: string;
    state_name: string;
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
const searchQuery = ref('');
const filterCountry = ref<number | null>(null);
const perPage = ref(10);
const expandedRows = ref<Record<number, boolean>>({});
const editingId = ref<number | null>(null);

const countryFilterOptions = computed(() => [
    { label: 'All countries', value: null },
    ...store.countries.map((c) => ({ label: c.country_name, value: c.id })),
]);

const createForm = ref({
    country_id: null as number | null,
    state_code: '',
    state_name: '',
    processing: false,
    errors: { country_id: '', state_code: '', state_name: '' },
});

const editForm = ref({
    country_id: null as number | null,
    state_code: '',
    state_name: '',
    processing: false,
    errors: { country_id: '', state_code: '', state_name: '' },
});

const resetCreateForm = () => {
    createForm.value.country_id = null;
    createForm.value.state_code = '';
    createForm.value.state_name = '';
    createForm.value.errors = { country_id: '', state_code: '', state_name: '' };
};

const resetEditForm = () => {
    editingId.value = null;
    expandedRows.value = {};
    editForm.value.country_id = null;
    editForm.value.state_code = '';
    editForm.value.state_name = '';
    editForm.value.errors = { country_id: '', state_code: '', state_name: '' };
};

const getCountryName = (id: number) => {
    return store.countries.find((c) => c.id === id)?.country_name || 'Unknown';
};

const filteredStateCodes = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return store.stateCodes.filter((item) => {
        const matchCountry = !filterCountry.value || item.country_id === filterCountry.value;
        const matchSearch =
            !q ||
            item.state_name.toLowerCase().includes(q) ||
            item.state_code.toLowerCase().includes(q) ||
            getCountryName(item.country_id).toLowerCase().includes(q);
        return matchCountry && matchSearch;
    });
});

const submitCreate = async () => {
    createForm.value.processing = true;
    createForm.value.errors = { country_id: '', state_code: '', state_name: '' };
    try {
        const response = await axios.post(route('statecodes.store'), {
            country_id: createForm.value.country_id,
            state_code: createForm.value.state_code,
            state_name: createForm.value.state_name,
        });
        store.addStateCode(response.data.stateCode);
        toast.add({ severity: 'success', summary: 'Created', detail: 'State code created successfully.', life: 3000 });
        createOpen.value = false;
        resetCreateForm();
    } catch (error: any) {
        const errs = error?.response?.data?.errors || {};
        createForm.value.errors.country_id = errs.country_id?.[0] || '';
        createForm.value.errors.state_code = errs.state_code?.[0] || '';
        createForm.value.errors.state_name = errs.state_name?.[0] || '';
        if (!Object.keys(errs).length) {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Unable to create state code.', life: 3000 });
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
    editForm.value.errors = { country_id: '', state_code: '', state_name: '' };
};

const onRowCollapse = () => {
    resetEditForm();
};

const submitEdit = async () => {
    if (!editingId.value) return;
    editForm.value.processing = true;
    editForm.value.errors = { country_id: '', state_code: '', state_name: '' };
    try {
        const response = await axios.put(route('statecodes.update', editingId.value), {
            country_id: editForm.value.country_id,
            state_code: editForm.value.state_code,
            state_name: editForm.value.state_name,
        });
        store.updateStateCode(response.data.stateCode);
        toast.add({ severity: 'success', summary: 'Updated', detail: 'State code updated successfully.', life: 3000 });
        resetEditForm();
    } catch (error: any) {
        const errs = error?.response?.data?.errors || {};
        editForm.value.errors.country_id = errs.country_id?.[0] || '';
        editForm.value.errors.state_code = errs.state_code?.[0] || '';
        editForm.value.errors.state_name = errs.state_name?.[0] || '';
        if (!Object.keys(errs).length) {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Unable to update state code.', life: 3000 });
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
            toast.add({ severity: 'warn', summary: 'Deleted', detail: 'State code removed.', life: 3000 });
        } catch {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete state code.', life: 3000 });
        }
    });
};

watch([searchQuery, filterCountry], () => {
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
                                <label class="field-label">Country <span class="text-rose-400">*</span></label>
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
                                <label class="field-label">State Name <span class="text-rose-400">*</span></label>
                                <BaseInput
                                    v-model="createForm.state_name"
                                    placeholder="e.g. Maharashtra"
                                    :class="{ 'p-invalid': createForm.errors.state_name }"
                                />
                                <small v-if="createForm.errors.state_name" class="field-error">{{ createForm.errors.state_name }}</small>
                            </div>
                            <div class="field-group">
                                <label class="field-label">State Code <span class="text-rose-400">*</span></label>
                                <BaseInput
                                    v-model="createForm.state_code"
                                    placeholder="e.g. MH"
                                    :class="{ 'p-invalid': createForm.errors.state_code }"
                                />
                                <small v-if="createForm.errors.state_code" class="field-error">{{ createForm.errors.state_code }}</small>
                            </div>
                        </div>
                        <div class="expansion-actions">
                            <Button label="Save State Code" icon="pi pi-check" :loading="createForm.processing" @click="submitCreate" />
                            <Button label="Reset" text severity="secondary" @click="resetCreateForm" />
                        </div>
                    </div>
                </Transition>
            </div>

            <div class="unit-table-card">
                <div class="unit-toolbar">
                    <div class="flex items-center gap-2">
                        <span class="toolbar-accent"></span>
                        <span class="toolbar-title">State Codes Directory</span>
                        <span class="toolbar-count">{{ filteredStateCodes.length }} records</span>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="search-wrap">
                            <i class="pi pi-search search-icon"></i>
                            <BaseInput
                                v-model="searchQuery"
                                placeholder="Search state, code, country..."
                                class="search-input"
                            />
                        </div>
                        <BaseSelect
                            v-model="filterCountry"
                            :options="countryFilterOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="filter-select"
                        />
                    </div>
                </div>

                <DataTable
                    v-model:expandedRows="expandedRows"
                    :value="filteredStateCodes"
                    dataKey="id"
                    paginator
                    :rows="perPage"
                    :rowsPerPageOptions="[5, 10, 25, 50]"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
                    currentPageReportTemplate="{first}–{last} of {totalRecords}"
                    stripedRows
                    class="unit-datatable"
                    rowHover
                    @rowExpand="onRowExpand"
                    @rowCollapse="onRowCollapse"
                >
                    <Column expander style="width: 46px; padding: 0 12px;" />

                    <Column field="country_id" header="Country" sortable>
                        <template #body="{ data }">
                            <span class="font-semibold text-gray-700 text-sm">{{ getCountryName(data.country_id) }}</span>
                        </template>
                    </Column>

                    <Column field="state_name" header="State Name" sortable>
                        <template #body="{ data }">
                            <span class="font-semibold text-gray-800 text-sm">{{ data.state_name }}</span>
                        </template>
                    </Column>

                    <Column field="state_code" header="Code" sortable style="width: 120px">
                        <template #body="{ data }">
                            <code class="code-chip">{{ data.state_code }}</code>
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
                                    <label class="field-label">Country <span class="text-rose-400">*</span></label>
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
                                    <label class="field-label">State Name <span class="text-rose-400">*</span></label>
                                    <BaseInput
                                        v-model="editForm.state_name"
                                        :class="{ 'p-invalid': editForm.errors.state_name }"
                                    />
                                    <small v-if="editForm.errors.state_name" class="field-error">{{ editForm.errors.state_name }}</small>
                                </div>
                                <div class="field-group">
                                    <label class="field-label">State Code <span class="text-rose-400">*</span></label>
                                    <BaseInput
                                        v-model="editForm.state_code"
                                        :class="{ 'p-invalid': editForm.errors.state_code }"
                                    />
                                    <small v-if="editForm.errors.state_code" class="field-error">{{ editForm.errors.state_code }}</small>
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
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.page-container {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    max-width: 1200px;
}

.page-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    padding: 0 2px;
}

.page-logo {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
}

.page-title {
    font-size: 18px;
    font-weight: 800;
    color: #1e293b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0;
}

.page-sub {
    font-size: 12px;
    color: #94a3b8;
    margin: 3px 0 0;
    font-weight: 500;
}

.page-stat {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #64748b;
    font-weight: 600;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 6px 12px;
}

.create-panel {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.create-panel--open {
    border-color: #c7d2fe;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.07), 0 1px 3px rgba(0,0,0,0.05);
}

.create-panel__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 14px 18px;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    transition: background 0.15s ease;
}

.create-panel__header:hover { background: #f8fafc; }

.create-panel__icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: linear-gradient(135deg, #eef2ff, #e0e7ff);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.create-panel__toggle {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #eef2ff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
    transition: background 0.2s, transform 0.25s ease;
    flex-shrink: 0;
}

.create-panel__toggle--open {
    transform: rotate(45deg);
    background: #6366f1;
    color: white;
}

.create-panel__body {
    padding: 20px 18px;
    border-top: 1px solid #eef2ff;
    background: linear-gradient(180deg, #fafbff 0%, #ffffff 100%);
}

.unit-table-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    overflow: hidden;
}

.unit-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    padding: 13px 16px;
    background: #fafbfc;
    border-bottom: 1px solid #f1f5f9;
}

.toolbar-accent {
    display: inline-block;
    width: 3px;
    height: 18px;
    border-radius: 99px;
    background: linear-gradient(180deg, #6366f1, #8b5cf6);
}

.toolbar-title {
    font-size: 12px;
    font-weight: 800;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.toolbar-count {
    font-size: 11px;
    color: #94a3b8;
    font-weight: 500;
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 99px;
    margin-left: 4px;
}

.search-wrap { position: relative; display: flex; align-items: center; }
.search-icon { position: absolute; left: 10px; font-size: 11px; color: #94a3b8; z-index: 1; }
:deep(.search-input) { padding-left: 30px !important; height: 32px !important; font-size: 12px !important; min-width: 220px; }
:deep(.filter-select) { height: 32px !important; font-size: 12px !important; min-width: 160px; }
:deep(.filter-select .p-select-label) { padding: 6px 10px !important; font-size: 12px; }

:deep(.unit-datatable .p-datatable-thead > tr > th) {
    background: #f8fafc;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #94a3b8;
    font-weight: 700;
    padding: 10px 16px;
    border-color: #f1f5f9;
}

:deep(.unit-datatable .p-datatable-tbody > tr > td) {
    padding: 10px 16px;
    border-color: #f8fafc;
    vertical-align: middle;
}

:deep(.unit-datatable .p-datatable-row-expansion > td) {
    padding: 0 !important;
    border-color: #e0e7ff !important;
}

:deep(.unit-datatable .p-paginator) {
    background: #fafbfc;
    border-top: 1px solid #f1f5f9;
    padding: 8px 16px;
    font-size: 12px;
}

:deep(.unit-datatable .p-paginator .p-paginator-page.p-highlight) {
    background: #6366f1;
    color: white;
    border-radius: 6px;
}

:deep(.unit-datatable .p-datatable-tbody > tr:hover > td) {
    background: #fafbff !important;
}

:deep(.p-row-toggler) {
    color: #94a3b8 !important;
    transition: color 0.15s, transform 0.2s;
}

:deep(.p-row-toggler:hover) { color: #6366f1 !important; }

.code-chip {
    display: inline-block;
    padding: 2px 8px;
    background: #f1f5f9;
    color: #475569;
    border-radius: 4px;
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
}

.delete-btn { opacity: 0.35; transition: opacity 0.15s; }
.delete-btn:hover { opacity: 1; }

.expansion-panel {
    padding: 20px 24px;
    background: linear-gradient(135deg, #eef2ff 0%, #f0f9ff 100%);
    border-left: 4px solid #6366f1;
    border-top: 1px solid #c7d2fe;
    border-bottom: 1px solid #c7d2fe;
}

.expansion-label {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 11px;
    font-weight: 600;
    color: #4f46e5;
    letter-spacing: 0.02em;
}

.expansion-actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid #e0e7ff;
}

.field-group { display: flex; flex-direction: column; gap: 5px; }
.field-label  { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; }
.field-error  { font-size: 11px; color: #e11d48; }

.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 64px 16px; }
.empty-icon  { width: 56px; height: 56px; border-radius: 14px; background: #f8fafc; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }
.empty-title { font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
.empty-sub   { font-size: 11px; color: #94a3b8; margin-top: 4px; }

.panel-slide-enter-active { transition: all 0.22s cubic-bezier(0.4,0,0.2,1); }
.panel-slide-leave-active { transition: all 0.16s ease; }
.panel-slide-enter-from,
.panel-slide-leave-to     { opacity: 0; transform: translateY(-6px); }
</style>

