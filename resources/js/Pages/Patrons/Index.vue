<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Swal from 'sweetalert2';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';

import PatronCreateForm from './components/PatronCreateForm.vue';
import PatronIndexList from './components/PatronIndexList.vue';
import type { Patron } from './types';

const props = defineProps<{
    patrons: Patron[];
    ledgers: any[];
    contactTypes: any[];
    addressTypes: any[];
    bankAccountTypes: any[];
    states: any[];
    operationalStatuses: any[];
    patronTypes: any[];
}>();

const toast = useToast();

const searchQuery = ref('');
const editingId = ref<number | null>(null);
const expandedRows = ref<Record<number, boolean>>({});
const importDialogVisible = ref(false);
const importText = ref('');
const importFileName = ref('');
const importFileInput = ref<HTMLInputElement | null>(null);

const importTemplateColumns = [
    'legal_name',
    'patron_type',
    'operational_status',
    'pan_no',
    'gstin',
    'status',
    'displayed',
];

const filteredPatrons = computed(() => {
    if (!searchQuery.value) return props.patrons;
    const q = searchQuery.value.toLowerCase();
    return props.patrons.filter((p: Patron) =>
        // Safe check for legal_name
        (p.legal_name?.toLowerCase().includes(q) ?? false) ||
        // Safe check for gstin (prevents crash if null)
        (p.gstin?.toLowerCase().includes(q) ?? false)
    );
});


const initialPatronForm = () => ({
    code: '',
    legal_name: '',
    patron_type: [] as string[],
    operational_status: 'active',
    pan_no: '',
    gstin: '',
    status: true,
    displayed: true,
    ledger_id: null as number | null,
    contact_name: '',
    contact_mobile: '',
    contact_email: '',
    contact_type_id: null as number | null,
    address_line_1: '',
    address_line_2: '',
    address_city: '',
    address_zipcode: '',
    address_state_id: null as number | null,
    address_type_id: null as number | null,
    bank_accounts: [
        {
            id: null,
            bank_account_type: null,
            account_holder_name: '',
            account_number: '',
            bank_name: '',
            branch_name: '',
            ifsc_code: '',
            status: true,
            is_primary: true,
        }
    ] as any[],
});

const createForm = useForm(initialPatronForm());
const editForm = useForm(initialPatronForm());

const resetCreateForm = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.code = '';
    createForm.displayed = true;
    createForm.bank_accounts = [
        {
            id: null,
            bank_account_type: null,
            account_holder_name: '',
            account_number: '',
            bank_name: '',
            branch_name: '',
            ifsc_code: '',
            status: true,
            is_primary: true,
        }
    ];
};

const resetEditForm = () => {
    editingId.value = null;
    expandedRows.value = {};
    editForm.reset();
    editForm.clearErrors();
    editForm.displayed = true;
    editForm.bank_accounts = [];
};

const populatePatronForm = (form: any, patron: Patron) => {
    form.code = patron.code || '';
    form.legal_name = patron.legal_name;
    form.patron_type = Array.isArray(patron.patron_type) ? [...patron.patron_type] : [patron.patron_type];
    form.operational_status = patron.operational_status;
    form.pan_no = patron.pan_no || '';
    form.gstin = patron.gstin || '';
    form.status = patron.status;
    form.displayed = patron.displayed ?? true;
    form.ledger_id = patron.ledger_id;

    form.contact_name = '';
    form.contact_mobile = '';
    form.contact_email = '';
    form.contact_type_id = null;
    form.address_line_1 = '';
    form.address_line_2 = '';
    form.address_city = '';
    form.address_zipcode = '';
    form.address_state_id = null;
    form.address_type_id = null;

    if (patron.contacts?.[0]) {
        const contact = patron.contacts[0];
        form.contact_name = contact.name || '';
        form.contact_mobile = contact.mobile || '';
        form.contact_email = contact.email || '';
        form.contact_type_id = contact.contact_type_id || null;

        if (contact.addresses?.[0]) {
            const address = contact.addresses[0];
            form.address_line_1 = address.line_1 || '';
            form.address_line_2 = address.line_2 || '';
            form.address_city = address.city || '';
            form.address_zipcode = address.zipcode || '';
            form.address_state_id = address.state_id || null;
            form.address_type_id = address.address_type_id || null;
        }
    }

    const mappedBanks = (patron.bank_accounts || []).map((acc: any) => ({
        id: acc.id,
        bank_account_type: acc.bank_account_type,
        account_holder_name: acc.account_holder_name || '',
        account_number: acc.account_number || '',
        bank_name: acc.bank_name || '',
        branch_name: acc.branch_name || '',
        ifsc_code: acc.ifsc_code || '',
        status: acc.status ?? true,
        is_primary: acc.is_primary ?? false,
    }));

    if (mappedBanks.length === 0) {
        mappedBanks.push({
            id: null,
            bank_account_type: null,
            account_holder_name: '',
            account_number: '',
            bank_name: '',
            branch_name: '',
            ifsc_code: '',
            status: true,
            is_primary: true,
        });
    }

    form.bank_accounts = mappedBanks;
};

const openEdit = (patron: Patron) => {
    editingId.value = patron.id;
    editForm.clearErrors();
    populatePatronForm(editForm, patron);
    expandedRows.value = { [patron.id]: true };
};

const handleExpandedRowsUpdate = (rows: Record<number, boolean>) => {
    const nextRows = rows || {};
    const expandedId = Object.keys(nextRows).find((key) => nextRows[Number(key)]);

    if (!expandedId) {
        resetEditForm();
        return;
    }

    const patronId = Number(expandedId);
    const patron = props.patrons.find((item) => item.id === patronId);

    if (!patron) {
        resetEditForm();
        return;
    }

    if (editingId.value !== patronId) {
        editingId.value = patronId;
        editForm.clearErrors();
        populatePatronForm(editForm, patron);
    }

    expandedRows.value = { [patronId]: true };
};

const submitCreate = () => {
    createForm.post(route('patrons.store'), {
        onSuccess: () => {
            resetCreateForm();
            toast.add({ severity: 'success', summary: 'Success', detail: 'Patron created successfully', life: 3000 });
        }
    });
};

const submitEdit = () => {
    if (!editingId.value) {
        return;
    }

    editForm.put(route('patrons.update', editingId.value), {
        onSuccess: () => {
            resetEditForm();
            toast.add({ severity: 'success', summary: 'Success', detail: 'Patron updated successfully', life: 3000 });
        }
    });
};

const deletePatron = (id: number) => {
    Swal.fire({
        title: 'Delete Patron?',
        text: 'This will permanently remove this record.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('patrons.destroy', id), {
                onSuccess: () => {
                    if (editingId.value === id) {
                        resetEditForm();
                    }
                    toast.add({ severity: 'info', summary: 'Deleted', detail: 'Patron removed', life: 3000 });
                }
            });
        }
    });
};

const addBank = (form: any) => {
    form.bank_accounts.push({
        id: null,
        bank_account_type: null,
        account_holder_name: '',
        account_number: '',
        bank_name: '',
        branch_name: '',
        ifsc_code: '',
        status: true,
        is_primary: form.bank_accounts.length === 0,
    });
};

const removeBank = (form: any, index: number) => form.bank_accounts.splice(index, 1);

const escapeCsvValue = (value: unknown) => {
    const stringValue = String(value ?? '');
    if (/[",\n]/.test(stringValue)) {
        return `"${stringValue.replace(/"/g, '""')}"`;
    }

    return stringValue;
};

const downloadCsv = (filename: string, rows: string[][]) => {
    const csv = rows.map((row) => row.map(escapeCsvValue).join(',')).join('\n');
    const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
};

const exportPatrons = () => {
    const rows = props.patrons.map((patron) => [
        patron.legal_name,
        (Array.isArray(patron.patron_type) ? patron.patron_type : [patron.patron_type]).join('|'),
        patron.operational_status || 'active',
        patron.pan_no || '',
        patron.gstin || '',
        patron.status ? 'true' : 'false',
        (patron.displayed ?? true) ? 'true' : 'false',
    ]);

    downloadCsv(`patrons-${new Date().toISOString().slice(0, 10)}.csv`, [importTemplateColumns, ...rows]);
    toast.add({ severity: 'success', summary: 'Export ready', detail: 'Patron CSV downloaded.', life: 3000 });
};

const downloadImportTemplate = () => {
    const sampleRows = [
        importTemplateColumns,
        ['Acme Transit', 'Customer|Transporter', 'active', '', '', 'true', 'true'],
        ['North Supply Co', 'Vendor|Supplier', 'paused', 'ABCDE1234F', '27ABCDE1234F1Z5', 'true', 'true'],
    ];

    downloadCsv('patron-import-template.csv', sampleRows);
    toast.add({ severity: 'info', summary: 'Template downloaded', detail: 'Use this CSV format for patron import.', life: 3000 });
};

const openImportDialog = () => {
    importDialogVisible.value = true;
};

const openImportFilePicker = () => {
    importFileInput.value?.click();
};

const parseCsvLine = (line: string) => {
    const values: string[] = [];
    let current = '';
    let inQuotes = false;

    for (let i = 0; i < line.length; i += 1) {
        const char = line[i];
        const nextChar = line[i + 1];

        if (char === '"') {
            if (inQuotes && nextChar === '"') {
                current += '"';
                i += 1;
            } else {
                inQuotes = !inQuotes;
            }
            continue;
        }

        if (char === ',' && !inQuotes) {
            values.push(current.trim());
            current = '';
            continue;
        }

        current += char;
    }

    values.push(current.trim());

    return values;
};

const normalizeBoolean = (value: string | undefined, fallback = true) => {
    if (value == null || value === '') {
        return fallback;
    }

    const normalized = value.trim().toLowerCase();
    return ['true', '1', 'yes', 'y'].includes(normalized);
};

const parsePatronImportText = (text: string) => {
    const lines = text
        .replace(/^\uFEFF/, '')
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);

    if (!lines.length) {
        throw new Error('Paste a CSV file or choose one to import.');
    }

    const headers = parseCsvLine(lines[0]).map((header) => header.trim().toLowerCase());
    const requiredHeaders = ['legal_name', 'patron_type'];

    for (const header of requiredHeaders) {
        if (!headers.includes(header)) {
            throw new Error(`Missing required column: ${header}`);
        }
    }

    return lines.slice(1).map((line, index) => {
        const values = parseCsvLine(line);
        const row = Object.fromEntries(headers.map((header, columnIndex) => [header, values[columnIndex] ?? '']));
        const patronTypes = String(row.patron_type || '')
            .split('|')
            .map((type) => type.trim())
            .filter(Boolean);

        if (!String(row.legal_name || '').trim()) {
            throw new Error(`Row ${index + 2}: legal_name is required.`);
        }

        if (!patronTypes.length) {
            throw new Error(`Row ${index + 2}: patron_type is required.`);
        }

        return {
            legal_name: String(row.legal_name).trim(),
            patron_type: patronTypes,
            operational_status: String(row.operational_status || 'active').trim() || 'active',
            pan_no: String(row.pan_no || '').trim() || null,
            gstin: String(row.gstin || '').trim() || null,
            status: normalizeBoolean(row.status, true),
            displayed: normalizeBoolean(row.displayed, true),
        };
    });
};

const handleImportFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    importFileName.value = file.name;
    const reader = new FileReader();
    reader.onload = () => {
        importText.value = String(reader.result || '');
    };
    reader.readAsText(file);
    input.value = '';
};

const submitImport = () => {
    let patrons: ReturnType<typeof parsePatronImportText>;

    try {
        patrons = parsePatronImportText(importText.value);
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Import failed',
            detail: error instanceof Error ? error.message : 'Unable to parse patron CSV.',
            life: 5000,
        });
        return;
    }

    router.post(route('patrons.batchstore'), { patrons }, {
        onSuccess: () => {
            importDialogVisible.value = false;
            importText.value = '';
            importFileName.value = '';
            toast.add({
                severity: 'success',
                summary: 'Import complete',
                detail: `${patrons.length} patron record(s) imported successfully.`,
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Import failed',
                detail: 'Please review the CSV data and try again.',
                life: 5000,
            });
        },
    });
};
</script>

<template>
    <AppLayout title="Patron Directory">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Toast />
        <input
            ref="importFileInput"
            type="file"
            accept=".csv,text/csv"
            class="hidden"
            @change="handleImportFileChange"
        />

        <main class="p-6 flex flex-col gap-6">
            <!-- <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-gray-800 uppercase">Patron Directory</h1>
                    <p class="text-sm text-gray-500 font-medium">Manage corporate accounts, customers, and vendors.</p>
                </div>
            </div> -->

            <section class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <PatronCreateForm
                    :form="createForm"
                    :patron-types="patronTypes"
                    :operational-statuses="operationalStatuses"
                    :states="states"
                    :add-bank="() => addBank(createForm)"
                    :remove-bank="(index: number) => removeBank(createForm, index)"
                    @submit="submitCreate"
                    @export="exportPatrons"
                    @import="openImportDialog"
                    @template="downloadImportTemplate"
                />
            </section>

            <PatronIndexList
                :patrons="filteredPatrons"
                :search-query="searchQuery"
                :expanded-rows="expandedRows"
                :editing-id="editingId"
                :edit-form="editForm"
                :patron-types="patronTypes"
                :operational-statuses="operationalStatuses"
                :states="states"
                :add-bank="addBank"
                :remove-bank="removeBank"
                @update:search-query="searchQuery = $event"
                @update:expanded-rows="handleExpandedRowsUpdate"
                @edit="openEdit"
                @delete="deletePatron"
                @submit-edit="submitEdit"
                @cancel-edit="resetEditForm"
            />
        </main>

        <Dialog
            v-model:visible="importDialogVisible"
            modal
            header="Import Patrons"
            :style="{ width: '42rem' }"
        >
            <div class="flex flex-col gap-4">
                <p class="text-sm text-gray-600">
                    Import patrons from CSV using the template columns:
                    <span class="font-semibold">{{ importTemplateColumns.join(', ') }}</span>
                </p>

                <div class="flex flex-wrap gap-2">
                    <Button label="Choose CSV" icon="pi pi-upload" type="button" @click="openImportFilePicker" />
                    <Button label="Download Template" icon="pi pi-download" type="button" text @click="downloadImportTemplate" />
                </div>

                <p v-if="importFileName" class="text-xs font-medium text-gray-500">
                    Loaded file: {{ importFileName }}
                </p>

                <div class="flex flex-col gap-2">
                    <label class="text-xs font-bold uppercase tracking-wide text-gray-400">CSV Content</label>
                    <Textarea
                        v-model="importText"
                        rows="12"
                        autoResize
                        class="w-full font-mono text-xs"
                        placeholder="Paste CSV rows here or choose a CSV file."
                    />
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" text type="button" @click="importDialogVisible = false" />
                <Button label="Import Patrons" icon="pi pi-check" type="button" @click="submitImport" />
            </template>
        </Dialog>
    </AppLayout>
</template>
