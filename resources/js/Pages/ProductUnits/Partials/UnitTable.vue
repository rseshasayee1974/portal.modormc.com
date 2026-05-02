<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import { useToast } from 'primevue/usetoast';
import Swal from 'sweetalert2';
import Badge from '@/Components/Mm/Badge.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';

interface ProductUnit {
    id: number;
    unit_type: string;
    unit_name: string;
    unit_code: string | null;
}

const props = defineProps<{
    productUnits: ProductUnit[];
    unitTypes: string[];
}>();

const toast = useToast();
const perPage = ref(50);
const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const unitTypeOptions = computed(() =>
    props.unitTypes.map(t => ({ label: t, value: t }))
);

// ── Row Expansion (Inline Edit) ──────────────────────────────────────────────
const expandedRows   = ref<Record<number, boolean>>({});
const editingUnitId  = ref<number | null>(null);

const editForm = useForm({
    unit_name: '',
    unit_code: '',
    unit_type: null as string | null,
});

/**
 * Populate edit form from the expanded row and enforce single-row expansion.
 */
const onRowExpand = (event: { data: ProductUnit }) => {
    const unit = event.data;
    expandedRows.value   = { [unit.id]: true };   // only one open
    editingUnitId.value  = unit.id;
    editForm.unit_name   = unit.unit_name;
    editForm.unit_code   = unit.unit_code ?? '';
    editForm.unit_type   = unit.unit_type;
    editForm.clearErrors();
};

const onRowCollapse = () => {
    editingUnitId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const cancelEdit = () => {
    expandedRows.value  = {};
    editingUnitId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const saveEdit = () => {
    if (!editingUnitId.value) return;
    editForm.put(route('productunits.update', editingUnitId.value), {
        onSuccess: () => {
            expandedRows.value  = {};
            editingUnitId.value = null;
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Unit saved successfully', life: 3000 });
        },
    });
};

// ── Delete ───────────────────────────────────────────────────────────────────
const deleteUnit = (unit: ProductUnit) => {
    Swal.fire({
        title: 'Delete this unit?',
        html: `<span style="font-size:13px;color:#64748b"><b>${unit.unit_name}</b> will be permanently removed.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor:  '#e2e8f0',
        confirmButtonText:  'Yes, delete',
        cancelButtonText:   'Cancel',
        customClass: { cancelButton: 'swal-cancel-dark' },
    }).then(result => {
        if (result.isConfirmed) {
            router.delete(route('productunits.destroy', unit.id), {
                onSuccess: () =>
                    toast.add({ severity: 'warn', summary: 'Deleted', detail: `${unit.unit_name} removed`, life: 3000 }),
            });
        }
    });
};
</script>

<template>
    <div class="unit-table-card">
        <!-- ── Data Table (Standardized) ────────────────────────────── -->
        <BaseDataTable
            v-model:expandedRows="expandedRows"
            v-model:filters="filters"
            :value="productUnits"
            dataKey="id"
            v-model:rows="perPage"
            :rowsPerPageOptions="[30, 50, 100, 200]"
            stripedRows
            showSearch
            :globalFilterFields="['unit_name', 'unit_code']"
            showSerial
            heading="Measurement Units"
            headingIcon="ListBulletIcon"
            showExport
            exportFilename="measurement-units-report"
        >
            <template #toolbar>
                <div class="flex items-center gap-2 px-3 py-1 bg-slate-50 rounded-lg border border-slate-100">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ productUnits.length }} total units</span>
                </div>
            </template>
            <!-- Unit Name -->
            <Column field="unit_name" header="Unit Name" sortable>
                <template #body="{ data }">
                    <span class="font-bold text-slate-800">{{ data.unit_name }}</span>
                </template>
            </Column>

            <!-- Unit Code -->
            <Column field="unit_code" header="Code" sortable style="width: 120px">
                <template #body="{ data }">
                    <span class="code-chip">{{ data.unit_code || '—' }}</span>
                </template>
            </Column>

            <!-- Type Badge -->
            <Column field="unit_type" header="Type" sortable style="width: 180px">
                <template #body="{ data }">
                    <Badge :value="data.unit_type" />
                </template>
            </Column>

            <!-- Actions -->
            <Column header="Actions" style="width: 80px" class="text-right">
                <template #body="{ data }">
                    <div class="flex justify-end gap-2">
                    <Button
                        icon="pi pi-trash"
                        severity="danger"
                            variant="text"
                            rounded
                            class="!w-8 !h-8"
                        @click.stop="deleteUnit(data)"
                    />
                    </div>
                </template>
            </Column>

            <!-- ── Inline Edit Expansion ── -->
            <template #expansion="{ data }">
                <div class="mm-expansion-panel">
                    <div class="mm-expansion-label">
                        <i class="pi pi-pen-to-square text-indigo-500"></i>
                        <span class="mm-expansion-title">Update Unit Configuration</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4 max-w-4xl bg-white p-6 rounded-sm border border-slate-100 shadow-sm">
                        <div class="field-group">
                            <label class="field-label">Unit Name <span class="text-rose-400">*</span></label>
                            <BaseInput v-model="editForm.unit_name" :class="{ 'p-invalid': editForm.errors.unit_name }" />
                            <small v-if="editForm.errors.unit_name" class="field-error">{{ editForm.errors.unit_name }}</small>
                        </div>
                        <div class="field-group">
                            <label class="field-label">Unit Code</label>
                            <BaseInput v-model="editForm.unit_code" :class="{ 'p-invalid': editForm.errors.unit_code }" />
                            <small v-if="editForm.errors.unit_code" class="field-error">{{ editForm.errors.unit_code }}</small>
                        </div>
                        <div class="field-group">
                            <label class="field-label">Unit Type <span class="text-rose-400">*</span></label>
                            <BaseSelect
                                v-model="editForm.unit_type"
                                :options="unitTypeOptions"
                                optionLabel="label"
                                optionValue="value"
                                :class="{ 'p-invalid': editForm.errors.unit_type }"
                            />
                            <small v-if="editForm.errors.unit_type" class="field-error">{{ editForm.errors.unit_type }}</small>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <Button
                            label="Save Configuration"
                            icon="pi pi-check"
                            class="!text-xs !font-black !uppercase !tracking-widest"
                            :loading="editForm.processing"
                            @click="saveEdit"
                        />
                        <Button 
                            label="Discard" 
                            severity="secondary" 
                            variant="text"
                            class="!text-xs !font-black !uppercase !tracking-widest"
                            @click="cancelEdit" 
                        />
                    </div>
                </div>
            </template>

            <template #empty>
                <div class="empty-state">
                    <div class="empty-icon-wrap">
                        <i class="pi pi-filter-slash text-2xl text-slate-300"></i>
                    </div>
                    <p class="empty-title">No units found matching criteria</p>
                </div>
            </template>
        </BaseDataTable>
    </div>
</template>

<style scoped>
/* ── Card wrapper ─────────────────────────────────────────────────────────── */
.unit-table-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    overflow: hidden;
}

/* ── Toolbar ──────────────────────────────────────────────────────────────── */
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

/* ── Search ───────────────────────────────────────────────────────────────── */
.search-wrap { position: relative; display: flex; align-items: center; }
.search-icon { position: absolute; left: 10px; font-size: 11px; color: #94a3b8; z-index: 1; }
:deep(.search-input) { padding-left: 30px !important; height: 32px !important; font-size: 12px !important; min-width: 200px; }
:deep(.filter-select) { height: 32px !important; font-size: 12px !important; min-width: 130px; }
:deep(.filter-select .p-select-label) { padding: 6px 10px !important; font-size: 12px; }

/* ── DataTable tweaks ─────────────────────────────────────────────────────── */
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

/* ── Row hover / expanded highlight ──────────────────────────────────────── */
:deep(.unit-datatable .p-datatable-tbody > tr:hover > td) {
    background: #fafbff !important;
}
:deep(.p-row-toggler) {
    color: #94a3b8 !important;
    transition: color 0.15s, transform 0.2s;
}
:deep(.p-row-toggler:hover) { color: #6366f1 !important; }

/* ── Inline elements ──────────────────────────────────────────────────────── */
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

/* ── Expansion Panel ──────────────────────────────────────────────────────── */
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

/* ── Field styles ─────────────────────────────────────────────────────────── */
.field-group { display: flex; flex-direction: column; gap: 5px; }
.field-label  { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; }
.field-error  { font-size: 11px; color: #e11d48; }

/* ── Empty State ──────────────────────────────────────────────────────────── */
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 64px 16px; }
.empty-icon  { width: 56px; height: 56px; border-radius: 14px; background: #f8fafc; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }
.empty-title { font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
.empty-sub   { font-size: 11px; color: #94a3b8; margin-top: 4px; }
</style>
