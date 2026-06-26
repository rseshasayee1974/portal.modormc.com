<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Swal from 'sweetalert2';

import {
    WrenchScrewdriverIcon, MagnifyingGlassIcon, PencilSquareIcon,
    TrashIcon, PlusIcon, TagIcon, XMarkIcon, CheckIcon, CalendarIcon, UserIcon, ChevronDownIcon, ChevronUpIcon
} from '@heroicons/vue/24/outline';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import DatePicker from 'primevue/datepicker';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';

const page = usePage();

interface Line {
    id?: number;
    name: string;
    product_quantity: string;
    date_planned: any;
    product_uom: number | null;
    product_id: number | null;
    description: string;
    price_unit: number;
    price_subtotal: number;
    price_total: number;
    tax_id: number | null;
    price_tax: number;
    status: number;
    priority: number;
    invoiced_quantity: string;
    received_quantity: string;
    received_price: number | null;
    partner_id: number | null;
}

interface MaintenanceRequest {
    id: number;
    name: string;
    description: string;
    machine_id: number | null;
    plant_id: number | null;
    max_idle_days: string | null;
    inventory_req_lines: string;
    maintanence_type: number;
    service_km: number;
    priority: number;
    responsible_id: number | null;
    repair_location: string;
    repair_vendor_id: number | null;
    bill_no: string | null;
    order_no: string | null;
    discount_amount: number;
    shipping_charges: number;
    shipping_tax_id: number | null;
    adjustment: number;
    rounding_value: number;
    filename: string | null;
    status: number;
    bill_status: number;
    dead_line: any;
    start_date: any;
    end_date: any;
    lines: Line[];
}

const props = defineProps<{
    requests: MaintenanceRequest[];
    machines: any[];
    vendors: any[];
    responsibleUsers: any[];
    taxes: any[];
    products: any[];
    units: any[];
}>();

const editingId = ref<number | null>(null);
const expandedRows = ref<any[]>([]);

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const machineOptions = computed(() => props.machines.map(m => ({ label: m.registration, value: m.id })));
const vendorOptions = computed(() => props.vendors.map(v => ({ label: v.legal_name, value: v.id })));
const userOptions = computed(() => props.responsibleUsers.map(u => ({ label: u.username, value: u.id })));
const taxOptions = computed(() => props.taxes.map(t => ({ label: `${t.tax_name} (${t.tax_rate}%)`, value: t.id, rate: t.tax_rate })));
const productOptions = computed(() => props.products.map(p => ({ label: p.title, value: p.id })));
const unitOptions = computed(() => props.units.map(u => ({ label: u.unit_name, value: u.id })));

const maintenanceTypes = [
    { label: 'Breakdown', value: 0 },
    { label: 'Preventive', value: 1 },
    { label: 'Routine', value: 2 }
];

const priorityLevels = [
    { label: 'Low', value: 0 },
    { label: 'Medium', value: 1 },
    { label: 'High', value: 2 },
    { label: 'Critical', value: 3 }
];

const requestStatuses = [
    { label: 'Draft', value: 0 },
    { label: 'Planned', value: 1 },
    { label: 'In Progress', value: 2 },
    { label: 'Completed', value: 3 },
    { label: 'Cancelled', value: 4 }
];

const billStatuses = [
    { label: 'Unbilled', value: 0 },
    { label: 'Partially Billed', value: 1 },
    { label: 'Fully Billed', value: 2 }
];

const getInitialForm = () => ({
    name: '',
    description: '',
    machine_id: null as number | null,
    max_idle_days: '',
    inventory_req_lines: 'standard',
    maintanence_type: 1,
    service_km: 0,
    priority: 1,
    responsible_id: null as number | null,
    repair_location: 'Workshop',
    repair_vendor_id: null as number | null,
    bill_no: '',
    order_no: '',
    discount_amount: 0,
    shipping_charges: 0,
    shipping_tax_id: null as number | null,
    adjustment: 0,
    rounding_value: 0,
    filename: '',
    status: 1,
    bill_status: 0,
    dead_line: null as any,
    start_date: null as any,
    end_date: null as any,
    lines: [] as Line[]
});

const form = useForm(getInitialForm());

const startEdit = (req: MaintenanceRequest) => {
    editingId.value = req.id;
    form.name = req.name;
    form.description = req.description;
    form.machine_id = req.machine_id;
    form.max_idle_days = req.max_idle_days || '';
    form.inventory_req_lines = req.inventory_req_lines;
    form.maintanence_type = req.maintanence_type;
    form.service_km = Number(req.service_km);
    form.priority = req.priority;
    form.responsible_id = req.responsible_id;
    form.repair_location = req.repair_location;
    form.repair_vendor_id = req.repair_vendor_id;
    form.bill_no = req.bill_no || '';
    form.order_no = req.order_no || '';
    form.discount_amount = Number(req.discount_amount);
    form.shipping_charges = Number(req.shipping_charges);
    form.shipping_tax_id = req.shipping_tax_id;
    form.adjustment = Number(req.adjustment);
    form.rounding_value = Number(req.rounding_value);
    form.filename = req.filename || '';
    form.status = req.status;
    form.bill_status = req.bill_status;
    form.dead_line = req.dead_line ? String(req.dead_line).substring(0, 10) : null;
    form.start_date = req.start_date ? String(req.start_date).substring(0, 10) : null;
    form.end_date = req.end_date ? String(req.end_date).substring(0, 10) : null;
    form.lines = req.lines.map(l => ({
        ...l,
        date_planned: l.date_planned ? new Date(l.date_planned) : null,
        price_unit: Number(l.price_unit),
        price_subtotal: Number(l.price_subtotal),
        price_total: Number(l.price_total),
        price_tax: Number(l.price_tax),
        received_price: l.received_price ? Number(l.received_price) : null,
    }));
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const cancelEdit = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const addLine = () => {
    form.lines.push({
        name: '',
        product_quantity: '1',
        date_planned: new Date(),
        product_uom: null,
        product_id: null,
        description: '',
        price_unit: 0,
        price_subtotal: 0,
        price_total: 0,
        tax_id: null,
        price_tax: 0,
        status: 1,
        priority: 0,
        invoiced_quantity: '0',
        received_quantity: '0',
        received_price: 0,
        partner_id: null,
    });
};

const removeLine = (index: number) => {
    form.lines.splice(index, 1);
};

const calculateLineTotals = (index: number) => {
    const line = form.lines[index];
    const qty = parseFloat(line.product_quantity) || 0;
    const unitPrice = parseFloat(line.price_unit as any) || 0;
    
    line.price_subtotal = qty * unitPrice;
    
    let taxRate = 0;
    if (line.tax_id) {
        const tax = taxOptions.value.find(t => t.value === line.tax_id);
        if (tax) taxRate = parseFloat((tax as any).rate) || 0;
    }
    
    line.price_tax = line.price_subtotal * (taxRate / 100);
    line.price_total = line.price_subtotal + line.price_tax;
};

const submitForm = () => {
    if (editingId.value) {
        form.put(route('maintenance-requests.update', editingId.value), {
            onSuccess: () => {
                cancelEdit();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Request modified', showConfirmButton: false, timer: 1500 });
            }
        });
    } else {
        form.post(route('maintenance-requests.store'), {
            onSuccess: () => {
                form.reset();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Request submitted', showConfirmButton: false, timer: 1500 });
            }
        });
    }
};

const deleteRequest = (id: number) => {
    Swal.fire({
        title: 'Delete Request?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete',
        customClass: { popup: 'rounded-3xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('maintenance-requests.destroy', id), {
                onSuccess: () => {
                    if (editingId.value === id) cancelEdit();
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Deleted successfully', showConfirmButton: false, timer: 1500 });
                }
            });
        }
    });
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: flash.success, showConfirmButton: false, timer: 1500 });
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Fleet Maintenance Requests">
        <template #header><ModuleSubTopNav /></template>

        <div class="my-5">
            <div class="max-w-7xl">

                <!-- ── Create / Edit Form Card ── -->
                <div class="bg-white dark:bg-slate-900 my-6 rounded-lg shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 overflow-hidden transition-all duration-300" :class="editingId ? 'ring-2 ring-indigo-500 ring-offset-4 dark:ring-offset-slate-950' : ''">
                    <div class="p-8">
                        <div class="flex items-center gap-4 mb-8">
                            <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600">
                                <WrenchScrewdriverIcon v-if="!editingId" class="w-6 h-6" />
                                <PencilSquareIcon v-else class="w-6 h-6" />
                            </div>
                            <div>
                                <h2 class="text-sm font-black text-slate-800 dark:text-slate-100 uppercase tracking-widest">
                                    {{ editingId ? 'Modify Maintenance Request' : 'Register Maintenance Request' }}
                                </h2>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">
                                    Add ticketing details, items, scheduled tasks, and vendors
                                </p>
                            </div>
                        </div>

                        <form @submit.prevent="submitForm" class="flex flex-col gap-6">
                            <!-- Request Details -->
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <BaseInput v-model="form.name" label="Ticket Subject" required placeholder="Enter maintenance label" :error="form.errors.name" />
                                </div>
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <label class="field-label">Machine / Vehicle</label>
                                    <BaseSelect v-model="form.machine_id" :options="machineOptions" required optionLabel="label" optionValue="value" placeholder="Select Asset" :error="form.errors.machine_id" />
                                </div>
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <label class="field-label">Responsible Person</label>
                                    <BaseSelect v-model="form.responsible_id" :options="userOptions" required optionLabel="label" optionValue="value" placeholder="Select Handler" :error="form.errors.responsible_id" />
                                </div>

                                <div class="col-span-12 md:col-span-8 field-group">
                                    <BaseInput v-model="form.description" label="Problem Details" required placeholder="Detail description of problem" :error="form.errors.description" />
                                </div>
                                <div class="col-span-12 md:col-span-4 field-group">
                                    <label class="field-label">Repair Vendor</label>
                                    <BaseSelect v-model="form.repair_vendor_id" :options="vendorOptions" required optionLabel="label" optionValue="value" placeholder="Select Vendor" :error="form.errors.repair_vendor_id" />
                                </div>

                                <div class="col-span-6 md:col-span-3 field-group">
                                    <label class="field-label">Maintenance Type</label>
                                    <BaseSelect v-model="form.maintanence_type" :options="maintenanceTypes" optionLabel="label" optionValue="value" :error="form.errors.maintanence_type" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <label class="field-label">Priority</label>
                                    <BaseSelect v-model="form.priority" :options="priorityLevels" optionLabel="label" optionValue="value" :error="form.errors.priority" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <BaseInput v-model="form.repair_location" label="Location" required placeholder="Workshop / Site" :error="form.errors.repair_location" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <label class="field-label">Service Km</label>
                                    <BaseInputNumber v-model="form.service_km" placeholder="0.00" :error="form.errors.service_km" />
                                </div>

                                <div class="col-span-6 md:col-span-4 field-group">
                                    <BaseInput type="date" v-model="form.dead_line" label="Deadline" required :error="form.errors.dead_line" />
                                </div>
                                <div class="col-span-6 md:col-span-4 field-group">
                                    <BaseInput type="date" v-model="form.start_date" label="Start Date" required :error="form.errors.start_date" />
                                </div>
                                <div class="col-span-6 md:col-span-4 field-group">
                                    <BaseInput type="date" v-model="form.end_date" label="End Date" required :error="form.errors.end_date" />
                                </div>

                                <div class="col-span-6 md:col-span-3 field-group">
                                    <BaseInput v-model="form.bill_no" label="Bill No" placeholder="Invoice reference" :error="form.errors.bill_no" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <BaseInput v-model="form.order_no" label="Order No" placeholder="PO reference" :error="form.errors.order_no" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <BaseInput v-model="form.max_idle_days" label="Max Idle Days" placeholder="3" :error="form.errors.max_idle_days" />
                                </div>
                                <div class="col-span-6 md:col-span-3 field-group">
                                    <label class="field-label">Status</label>
                                    <BaseSelect v-model="form.status" :options="requestStatuses" optionLabel="label" optionValue="value" :error="form.errors.status" />
                                </div>
                            </div>

                            <!-- Lines Form Section -->
                            <div class="mt-8 border-t border-slate-100 dark:border-slate-800 pt-6">
                                <div class="flex justify-between items-center mb-4">
                                    <div>
                                        <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-widest">Required Parts & Services (Lines)</h3>
                                        <p v-if="form.errors.lines" class="text-red-500 text-[10px] mt-1 font-medium">{{ form.errors.lines }}</p>
                                    </div>
                                    <button type="button" @click="addLine" class="flex items-center gap-2 px-4 py-2 bg-indigo-50 dark:bg-indigo-900/20 hover:bg-indigo-100 text-indigo-600 rounded-lg text-[9px] font-black uppercase tracking-widest transition-colors">
                                        <PlusIcon class="w-3.5 h-3.5" /> Add Row
                                    </button>
                                </div>

                                <div v-if="form.lines.length === 0" class="py-12 border-2 border-dashed border-slate-100 dark:border-slate-800 rounded-xl flex flex-col items-center justify-center bg-slate-50/20">
                                    <WrenchScrewdriverIcon class="w-8 h-8 text-slate-200 dark:text-slate-700 mb-2" />
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">No maintenance lines added</p>
                                </div>

                                <div v-else class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse min-w-[1200px]">
                                        <thead>
                                            <tr class="text-[9px] font-black uppercase text-slate-400 tracking-wider border-b border-slate-100 dark:border-slate-800 pb-3">
                                                <th class="py-3 pr-2 w-[180px]">Item Label</th>
                                                <th class="py-3 px-2 w-[150px]">Product</th>
                                                <th class="py-3 px-2 w-[120px]">Qty</th>
                                                <th class="py-3 px-2 w-[100px]">UOM</th>
                                                <th class="py-3 px-2 w-[120px]">Unit Price</th>
                                                <th class="py-3 px-2 w-[130px]">Tax Rate</th>
                                                <th class="py-3 px-2 w-[120px]">Total</th>
                                                <th class="py-3 px-2 w-[150px]">Vendor/Partner</th>
                                                <th class="py-3 pl-2 w-[60px] text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(line, index) in form.lines" :key="index" class="border-b border-slate-50 dark:border-slate-800/50 hover:bg-slate-50/30">
                                                <td class="py-2 pr-2">
                                                    <BaseInput v-model="line.name" placeholder="Item name" class="!h-9 text-xs" :error="form.errors[`lines.${index}.name`]" />
                                                </td>
                                                <td class="py-2 px-2">
                                                    <BaseSelect v-model="line.product_id" :options="productOptions" optionLabel="label" optionValue="value" placeholder="Select" class="!h-9 text-xs" :error="form.errors[`lines.${index}.product_id`]" />
                                                </td>
                                                <td class="py-2 px-2">
                                                    <BaseInput v-model="line.product_quantity" placeholder="1" class="!h-9 text-xs" @input="calculateLineTotals(index)" :error="form.errors[`lines.${index}.product_quantity`]" />
                                                </td>
                                                <td class="py-2 px-2">
                                                    <BaseSelect v-model="line.product_uom" :options="unitOptions" optionLabel="label" optionValue="value" placeholder="Select" class="!h-9 text-xs" :error="form.errors[`lines.${index}.product_uom`]" />
                                                </td>
                                                <td class="py-2 px-2">
                                                    <BaseInput v-model="line.price_unit" placeholder="0" class="!h-9 text-xs" @input="calculateLineTotals(index)" :error="form.errors[`lines.${index}.price_unit`]" />
                                                </td>
                                                <td class="py-2 px-2">
                                                    <BaseSelect v-model="line.tax_id" :options="taxOptions" optionLabel="label" optionValue="value" placeholder="No Tax" class="!h-9 text-xs" @change="calculateLineTotals(index)" :error="form.errors[`lines.${index}.tax_id`]" />
                                                </td>
                                                <td class="py-2 px-2 text-xs font-mono font-bold text-slate-600">
                                                    ₹{{ Number(line.price_total).toLocaleString('en-IN', {minimumFractionDigits: 2}) }}
                                                </td>
                                                <td class="py-2 px-2">
                                                    <BaseSelect v-model="line.partner_id" :options="vendorOptions" optionLabel="label" optionValue="value" placeholder="Select" class="!h-9 text-xs" :error="form.errors[`lines.${index}.partner_id`]" />
                                                </td>
                                                <td class="py-2 pl-2 text-right">
                                                    <button type="button" @click="removeLine(index)" class="p-2 text-slate-300 hover:text-red-500 transition-colors">
                                                        <TrashIcon class="w-4 h-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 mt-4">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex items-center justify-center gap-3 h-12 px-8 rounded-2xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-black text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-indigo-100 dark:shadow-none transition-all duration-200 active:scale-95"
                                >
                                    <CheckIcon v-if="!form.processing" class="w-4 h-4 stroke-[3px]" />
                                    <span v-else class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                                    {{ editingId ? 'Update Request' : 'Register Request' }}
                                </button>
                                
                                <button
                                    v-if="editingId"
                                    @click="cancelEdit"
                                    type="button"
                                    class="flex items-center justify-center w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-slate-200 transition-all active:scale-95"
                                >
                                    <XMarkIcon class="w-6 h-6" />
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── DataTable Section ── -->
                <div class="bg-white dark:bg-slate-900 shadow-lg shadow-slate-200/40 dark:shadow-none rounded-lg border border-slate-100 dark:border-slate-800 overflow-hidden">
                    <BaseDataTable
                        :value="requests"
                        v-model:filters="filters"
                        :globalFilterFields="['name', 'description', 'machine.registration']"
                        showSearch
                        showSerial
                        heading="Maintenance Ledger"
                        headingIcon="WrenchScrewdriverIcon"
                        :rows="30"
                        v-model:expandedRows="expandedRows"
                        class="maintenance-table"
                    >
                        <!-- Row Expansion Trigger -->
                        <Column expander style="width: 3rem" />

                        <!-- Subject/Name -->
                        <Column header="Ticket Subject" sortable field="name">
                            <template #body="slotProps">
                                <div class="flex flex-col">
                                    <span class="font-bold text-slate-700 dark:text-slate-200">
                                        {{ slotProps.data.name }}
                                    </span>
                                    <span class="text-[9px] text-slate-400 font-bold uppercase mt-0.5">
                                        {{ slotProps.data.repair_location }}
                                    </span>
                                </div>
                            </template>
                        </Column>

                        <!-- Machine -->
                        <Column header="Asset Registration" sortable field="machine.registration">
                            <template #body="slotProps">
                                <span class="font-mono text-xs font-bold text-slate-600 dark:text-slate-300">
                                    {{ slotProps.data.machine?.registration }}
                                </span>
                            </template>
                        </Column>

                        <!-- Vendor -->
                        <Column header="Repair Vendor">
                            <template #body="slotProps">
                                <span class="text-xs text-slate-600 dark:text-slate-300 font-medium">
                                    {{ slotProps.data.vendor?.legal_name }}
                                </span>
                            </template>
                        </Column>

                        <!-- Type -->
                        <Column header="Maint. Type">
                            <template #body="slotProps">
                                <span class="text-[9px] font-black uppercase tracking-wider text-slate-500">
                                    {{ maintenanceTypes.find(t => t.value === slotProps.data.maintanence_type)?.label }}
                                </span>
                            </template>
                        </Column>

                        <!-- Priority -->
                        <Column header="Priority" sortable field="priority">
                            <template #body="slotProps">
                                <span 
                                    class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full"
                                    :class="{
                                        'bg-red-50 text-red-600': slotProps.data.priority === 3,
                                        'bg-orange-50 text-orange-600': slotProps.data.priority === 2,
                                        'bg-indigo-50 text-indigo-600': slotProps.data.priority === 1,
                                        'bg-slate-100 text-slate-500': slotProps.data.priority === 0
                                    }"
                                >
                                    {{ priorityLevels.find(p => p.value === slotProps.data.priority)?.label }}
                                </span>
                            </template>
                        </Column>

                        <!-- Dates -->
                        <Column header="Deadline">
                            <template #body="slotProps">
                                <span class="text-xs font-mono font-medium text-slate-500">
                                    {{ new Date(slotProps.data.dead_line).toLocaleDateString() }}
                                </span>
                            </template>
                        </Column>

                        <!-- Status -->
                        <Column header="Status" sortable field="status">
                            <template #body="slotProps">
                                <span 
                                    class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full"
                                    :class="{
                                        'bg-emerald-50 text-emerald-600': slotProps.data.status === 3,
                                        'bg-blue-50 text-blue-600': slotProps.data.status === 2,
                                        'bg-yellow-50 text-yellow-600': slotProps.data.status === 1,
                                        'bg-slate-100 text-slate-500': slotProps.data.status === 0 || slotProps.data.status === 4
                                    }"
                                >
                                    {{ requestStatuses.find(s => s.value === slotProps.data.status)?.label }}
                                </span>
                            </template>
                        </Column>

                        <!-- Controls -->
                        <Column header="Control" style="width: 120px" align="right">
                            <template #body="slotProps">
                                <div class="flex justify-end items-center gap-2">
                                    <button
                                        @click="startEdit(slotProps.data)"
                                        class="flex items-center justify-center w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 hover:bg-indigo-100 transition-all active:scale-95"
                                        title="Modify"
                                    >
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </button>
                                    <button
                                        @click="deleteRequest(slotProps.data.id)"
                                        class="flex items-center justify-center w-9 h-9 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-500 hover:bg-red-100 transition-all active:scale-95"
                                        title="Remove"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </template>
                        </Column>

                        <!-- Row Expansion Template (Required Parts List) -->
                        <template #expansion="slotProps">
                            <div class="p-6 bg-slate-50/50 dark:bg-slate-900/50 rounded-xl border border-slate-100 dark:border-slate-800 m-2">
                                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Required Parts & Tasks</h4>
                                <DataTable :value="slotProps.data.lines" class="lines-subtable">
                                    <Column field="name" header="Line Detail" />
                                    <Column field="product.title" header="Product" />
                                    <Column field="product_quantity" header="Qty" />
                                    <Column field="uom.unit_code" header="UOM" />
                                    <Column header="Unit Price">
                                        <template #body="subProps">
                                            ₹{{ Number(subProps.data.price_unit).toLocaleString('en-IN', {minimumFractionDigits: 2}) }}
                                        </template>
                                    </Column>
                                    <Column header="Subtotal">
                                        <template #body="subProps">
                                            ₹{{ Number(subProps.data.price_subtotal).toLocaleString('en-IN', {minimumFractionDigits: 2}) }}
                                        </template>
                                    </Column>
                                    <Column header="Tax">
                                        <template #body="subProps">
                                            ₹{{ Number(subProps.data.price_tax).toLocaleString('en-IN', {minimumFractionDigits: 2}) }}
                                        </template>
                                    </Column>
                                    <Column header="Total Price">
                                        <template #body="subProps">
                                            ₹{{ Number(subProps.data.price_total).toLocaleString('en-IN', {minimumFractionDigits: 2}) }}
                                        </template>
                                    </Column>
                                </DataTable>
                            </div>
                        </template>

                        <!-- Empty State -->
                        <template #empty>
                            <div class="py-20 flex flex-col items-center gap-4">
                                <div class="w-16 h-16 rounded-3xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center">
                                    <WrenchScrewdriverIcon class="w-8 h-8 text-slate-200 dark:text-slate-700" />
                                </div>
                                <div class="text-center">
                                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">No Maintenance Tickets</p>
                                    <p class="text-[10px] font-medium text-slate-300 dark:text-slate-600 mt-1">Submit a new maintenance request above.</p>
                                </div>
                            </div>
                        </template>
                    </BaseDataTable>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datepicker-input) {
    @apply h-10 text-sm font-bold border-slate-200 rounded-md !bg-white;
}

:deep(.lines-subtable .p-datatable-thead > tr > th) {
    @apply !bg-slate-100/50 dark:!bg-slate-800/50 !text-slate-500 !font-bold !text-[9px] !uppercase !py-3;
}
:deep(.lines-subtable .p-datatable-tbody > tr > td) {
    @apply !py-2 !text-xs !text-slate-600 dark:!text-slate-300;
}
</style>
