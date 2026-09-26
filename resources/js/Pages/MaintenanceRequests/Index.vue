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

function trim(str: string) {
    return str.trim();
}

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
    form.amount_untaxed = Number(req.amount_untaxed || 0);
    form.amount_tax = Number(req.amount_tax || 0);
    form.amount_total = Number(req.amount_total || 0);
    form.discount_amount = Number(req.discount_amount);
    form.shipping_charges = Number(req.shipping_charges);
    form.shipping_tax_id = req.shipping_tax_id;
    form.adjustment = Number(req.adjustment);
    form.rounding_value = Number(req.rounding_value);
    form.filename = req.filename || '';
    form.status = req.status;
    form.tax_inclusive = Boolean(req.tax_inclusive);
    form.bill_status = req.bill_status;
    form.dead_line = req.dead_line ? String(req.dead_line).substring(0, 10) : null;
    form.start_date = req.start_date ? String(req.start_date).substring(0, 10) : null;
    form.end_date = req.end_date ? String(req.end_date).substring(0, 10) : null;
    form.lines = (req.lines || []).map(l => ({
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

const submitForm = () => {
    if (editingId.value) {
        form.put(route('maintenance-requests.update', editingId.value), {
            onSuccess: () => {
                cancelEdit();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Request modified successfully', showConfirmButton: false, timer: 1500 });
            }
        });
    } else {
        form.post(route('maintenance-requests.store'), {
            onSuccess: () => {
                form.reset();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Request registered successfully', showConfirmButton: false, timer: 1500 });
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
                <!-- Create / Edit Form Component -->
                <MaintenanceRequestForm
                    :form="form"
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
                    @submit="submitForm"
                    @cancel="cancelEdit"
                />

                <!-- DataTable Component -->
                <MaintenanceRequestTable
                    :requests="requests"
                    :maintenanceTypes="maintenanceTypes"
                    :priorityLevels="priorityLevels"
                    :requestStatuses="requestStatuses"
                    @edit="startEdit"
                    @delete="deleteRequest"
                />
            </div>
        </div>
    </AppLayout>
</template>
