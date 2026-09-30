<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Swal from 'sweetalert2';
import MaintenanceRequestForm from './Partials/MaintenanceRequestForm.vue';
import MaintenanceRequestTable from './Partials/MaintenanceRequestTable.vue';

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
    amount_untaxed: number;
    amount_tax: number;
    amount_total: number;
    discount_amount: number;
    shipping_charges: number;
    shipping_tax_id: number | null;
    adjustment: number;
    rounding_value: number;
    filename: string | null;
    status: number;
    tax_inclusive: boolean;
    bill_status: number;
    dead_line: any;
    start_date: any;
    end_date: any;
    lines: Line[];
    machine?: any;
    vendor?: any;
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

const page = usePage();
const editingId = ref<number | null>(null);
const expandedRows = ref<any[]>([]);

const machineOptions = computed(() => props.machines.map(m => ({ label: m.registration, value: m.id })));
const vendorOptions = computed(() => props.vendors.map(v => ({ label: v.legal_name, value: v.id })));
const userOptions = computed(() => props.responsibleUsers.map(u => ({ label: u.username || trim(`${u.first_name || ''} ${u.last_name || ''}`), value: u.id })));
const taxOptions = computed(() => props.taxes.map(t => ({ label: `${t.tax_name} (${t.tax_rate}%)`, value: t.id, rate: t.tax_rate })));
const productOptions = computed(() => props.products.map(p => ({ label: p.title, value: p.id })));
const unitOptions = computed(() => props.units.map(u => ({ label: u.unit_name || u.unit_code, value: u.id })));

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
    amount_untaxed: 0,
    amount_tax: 0,
    amount_total: 0,
    discount_amount: 0,
    shipping_charges: 0,
    shipping_tax_id: null as number | null,
    adjustment: 0,
    rounding_value: 0,
    filename: '',
    status: 1,
    tax_inclusive: false,
    bill_status: 0,
    dead_line: null as any,
    start_date: null as any,
    end_date: null as any,
    lines: [] as Line[]
});

const form = useForm(getInitialForm());
const editForm = useForm(getInitialForm());

function trim(str: string) {
    return str.trim();
}

const startEdit = (req: MaintenanceRequest) => {
    if (editingId.value === req.id) {
        cancelEdit();
        return;
    }

    editingId.value = req.id;
    editForm.name = req.name;
    editForm.description = req.description;
    editForm.machine_id = req.machine_id;
    editForm.max_idle_days = req.max_idle_days || '';
    editForm.inventory_req_lines = req.inventory_req_lines;
    editForm.maintanence_type = req.maintanence_type;
    editForm.service_km = Number(req.service_km);
    editForm.priority = req.priority;
    editForm.responsible_id = req.responsible_id;
    editForm.repair_location = req.repair_location;
    editForm.repair_vendor_id = req.repair_vendor_id;
    editForm.bill_no = req.bill_no || '';
    editForm.order_no = req.order_no || '';
    editForm.amount_untaxed = Number(req.amount_untaxed || 0);
    editForm.amount_tax = Number(req.amount_tax || 0);
    editForm.amount_total = Number(req.amount_total || 0);
    editForm.discount_amount = Number(req.discount_amount);
    editForm.shipping_charges = Number(req.shipping_charges);
    editForm.shipping_tax_id = req.shipping_tax_id;
    editForm.adjustment = Number(req.adjustment);
    editForm.rounding_value = Number(req.rounding_value);
    editForm.filename = req.filename || '';
    editForm.status = req.status;
    editForm.tax_inclusive = Boolean(req.tax_inclusive);
    editForm.bill_status = req.bill_status;
    editForm.dead_line = req.dead_line ? String(req.dead_line).substring(0, 10) : null;
    editForm.start_date = req.start_date ? String(req.start_date).substring(0, 10) : null;
    editForm.end_date = req.end_date ? String(req.end_date).substring(0, 10) : null;
    editForm.lines = (req.lines || []).map(l => ({
        ...l,
        date_planned: l.date_planned ? new Date(l.date_planned) : null,
        price_unit: Number(l.price_unit),
        price_subtotal: Number(l.price_subtotal),
        price_total: Number(l.price_total),
        price_tax: Number(l.price_tax),
        received_price: l.received_price ? Number(l.received_price) : null,
    }));
    
    // Programmatically expand the row to show the edit form
    expandedRows.value = [req];
};

const cancelEdit = () => {
    editingId.value = null;
    expandedRows.value = [];
    editForm.reset();
    editForm.clearErrors();
};

const submitCreate = () => {
    form.post(route('maintenance-requests.store'), {
        onSuccess: () => {
            form.reset();
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Request registered successfully', showConfirmButton: false, timer: 1500 });
        }
    });
};

const submitEdit = () => {
    if (editingId.value) {
        editForm.put(route('maintenance-requests.update', editingId.value), {
            onSuccess: () => {
                cancelEdit();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Request modified successfully', showConfirmButton: false, timer: 1500 });
            }
        });
    }
};

const deleteRequest = (id: number) => {
    Swal.fire({
        title: 'Delete Request?',
        text: 'This action cannot be undone.',
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
    if (flash?.success) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: flash.success, showConfirmButton: false, timer: 1500 });
    }
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Fleet Maintenance Requests">
        <template #header><ModuleSubTopNav /></template>

        <div class="my-5">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Create Form Component -->
                <MaintenanceRequestForm
                    :form="form"
                    :editingId="null"
                    :machineOptions="machineOptions"
                    :vendorOptions="vendorOptions"
                    :userOptions="userOptions"
                    :taxOptions="taxOptions"
                    :productOptions="productOptions"
                    :unitOptions="unitOptions"
                    :maintenanceTypes="maintenanceTypes"
                    :priorityLevels="priorityLevels"
                    :requestStatuses="requestStatuses"
                    @submit="submitCreate"
                />

                <!-- DataTable Component -->
                <MaintenanceRequestTable
                    :requests="requests"
                    :maintenanceTypes="maintenanceTypes"
                    :priorityLevels="priorityLevels"
                    :requestStatuses="requestStatuses"
                    :editingId="editingId"
                    v-model:expandedRows="expandedRows"
                    @edit="startEdit"
                    @delete="deleteRequest"
                >
                    <template #edit-form="{ data }">
                        <div class="p-4 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 rounded-2xl m-2 relative">
                            <MaintenanceRequestForm
                                :form="editForm"
                                :editingId="editingId"
                                :machineOptions="machineOptions"
                                :vendorOptions="vendorOptions"
                                :userOptions="userOptions"
                                :taxOptions="taxOptions"
                                :productOptions="productOptions"
                                :unitOptions="unitOptions"
                                :maintenanceTypes="maintenanceTypes"
                                :priorityLevels="priorityLevels"
                                :requestStatuses="requestStatuses"
                                @submit="submitEdit"
                                @cancel="cancelEdit"
                                class="!my-0 !shadow-none !border-none !ring-0"
                            />
                        </div>
                    </template>
                </MaintenanceRequestTable>
            </div>
        </div>
    </AppLayout>
</template>
