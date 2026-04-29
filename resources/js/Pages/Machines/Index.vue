<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Swal from 'sweetalert2';
import { useToast } from 'primevue/usetoast';
import Toast from 'primevue/toast';

// Heroicons
import { TruckIcon } from '@heroicons/vue/24/outline';

// Components
import MachineCreateForm from './components/MachineCreateForm.vue';
import MachineIndexList from './components/MachineIndexList.vue';

interface Document {
    id?: number;
    machine_id?: number;
    type: string;
    issue_date: any;
    expiry_date: any;
    amount: number | null;
}

interface Loan {
    id?: number;
    machine_id?: number;
    loan_amount: number;
    emi_amount: number;
    tenure_months: number;
    start_date: any;
    end_date: any;
}

interface Machine {
    id: number;
    registration: string;
    vehicle_model: string | null;
    make_year: number | null;
    engine_no: string | null;
    chassis_no: string | null;
    vehicle_type: string | null;
    capacity: number | null;
    documents: Document[];
    loans: Loan[];
}

const props = defineProps<{
    machines: Machine[];
    vehicleTypes: any[];
    documentTypes: string[];
    paymentStatuses: string[];
    transportOwners: any[];
}>();

const toast = useToast();
const page = usePage();

const searchQuery = ref('');
const editingId = ref<number | null>(null);
const expandedRows = ref<Record<number, boolean>>({});

const filteredMachines = computed(() => {
    if (!searchQuery.value) return props.machines;
    const q = searchQuery.value.toLowerCase();
    return props.machines.filter((m: Machine) => 
        m.registration?.toLowerCase().includes(q) ||
        (m.vehicle_model && m.vehicle_model.toLowerCase().includes(q)) ||
        (m.vehicle_type && m.vehicle_type.toLowerCase().includes(q))
    );
});

const vehicleOptions = computed(() => props.vehicleTypes.map(v => ({ 
    label: v.name, 
    value: v.name 
})));

const docTypeOptions = computed(() => props.documentTypes.map(d => ({ label: d, value: d })));
const transportOwnerOptions = computed(() => props.transportOwners.map(o => ({ label: o.legal_name, value: o.id })));

const getInitialForm = () => ({
    registration: '',
    vehicle_model: '',
    make_year: null as number | null,
    engine_no: '',
    chassis_no: '',
    vehicle_type: null as string | null,
    capacity: null as number | null,
    owner_id: null as number | null,
    documents: [] as Document[],
    loans: [] as Loan[],
});

const createForm = useForm(getInitialForm());
const editForm = useForm(getInitialForm());

const resetEditForm = () => {
    editingId.value = null;
    expandedRows.value = {};
    editForm.reset();
    editForm.clearErrors();
    editForm.documents = [];
    editForm.loans = [];
};

const openEdit = (m: Machine) => {
    editingId.value = m.id;
    editForm.registration = m.registration;
    editForm.vehicle_model = m.vehicle_model || '';
    editForm.make_year = m.make_year ? Number(m.make_year) : null;
    editForm.engine_no = m.engine_no || '';
    editForm.chassis_no = m.chassis_no || '';
    editForm.vehicle_type = m.vehicle_type;
    editForm.capacity = m.capacity ? Number(m.capacity) : null;
    editForm.owner_id = (m as any).owner_id ? Number((m as any).owner_id) : null;
    editForm.documents = m.documents.map(d => ({ 
        ...d, 
        issue_date: d.issue_date ? new Date(d.issue_date) : null, 
        expiry_date: d.expiry_date ? new Date(d.expiry_date) : null 
    }));
    editForm.loans = m.loans.map(l => ({ 
        ...l, 
        start_date: l.start_date ? new Date(l.start_date) : null, 
        end_date: l.end_date ? new Date(l.end_date) : null 
    }));
};

const addDocument = (form: any) => {
    form.documents.push({ type: '', issue_date: null, expiry_date: null, amount: 0 });
};

const removeDocument = (form: any, index: number) => {
    form.documents.splice(index, 1);
};

const addLoan = (form: any) => {
    form.loans.push({ loan_amount: 0, emi_amount: 0, tenure_months: 0, start_date: null, end_date: null });
};

const removeLoan = (form: any, index: number) => {
    form.loans.splice(index, 1);
};

const submitCreate = () => {
    createForm.post(route('machines.store'), {
        onSuccess: () => {
            createForm.reset();
            createForm.documents = [];
            createForm.loans = [];
            toast.add({ severity: 'success', summary: 'Enrolled', detail: 'New asset added to fleet', life: 3000 });
        }
    });
};

const submitEdit = () => {
    if (!editingId.value) return;
    editForm.put(route('machines.update', editingId.value), {
        onSuccess: () => {
            resetEditForm();
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Asset record synchronized', life: 3000 });
        }
    });
};

const deleteMachine = (id: number) => {
    Swal.fire({
        title: 'Decommission Asset?',
        text: "This will remove the machine from active reporting.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Decommission',
        customClass: { popup: 'rounded-3xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('machines.destroy', id), {
                onSuccess: () => {
                   if (editingId.value === id) resetEditForm();
                   toast.add({ severity: 'info', summary: 'Removed', detail: 'Asset decommissioned', life: 3000 });
                }
            });
        }
    });
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) toast.add({ severity: 'success', summary: 'Success', detail: flash.success, life: 3000 });
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Fleet Management">
        <template #header><ModuleSubTopNav /></template>
        <Toast />

        <div class=" my-5">
            <div class="max-w-7xl">

                <!-- ── Page Header ── -->
                <!-- <div class="flex flex-col md:flex-row justify-between items-end gap-4 px-4 sm:px-0">
                    <div class="flex items-center gap-5">
                        <div class="flex items-center justify-center w-16 h-16 rounded-[22px] bg-gradient-to-br from-indigo-500 to-indigo-700 shadow-xl shadow-indigo-100 dark:shadow-none">
                            <TruckIcon class="w-9 h-9 text-white" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-400 leading-none">Logistics Operations</span>
                            </div>
                            <h1 class="text-3xl font-black text-slate-800 dark:text-slate-100 tracking-tight leading-none uppercase">
                                Fleet Inventory
                            </h1>
                        </div>
                    </div>
                </div> -->

                <!-- ── Create Form ── -->
                <MachineCreateForm
                    :form="createForm"
                    :vehicle-options="vehicleOptions"
                    :doc-type-options="docTypeOptions"
                    :transport-owner-options="transportOwnerOptions"
                    :add-document="() => addDocument(createForm)"
                    :remove-document="(index: number) => removeDocument(createForm, index)"
                    :add-loan="() => addLoan(createForm)"
                    :remove-loan="(index: number) => removeLoan(createForm, index)"
                    @submit="submitCreate"
                />

                <!-- ── Inventory List ── -->
                <MachineIndexList
                    :machines="filteredMachines"
                    :search-query="searchQuery"
                    :expanded-rows="expandedRows"
                    :editing-id="editingId"
                    :edit-form="editForm"
                    :vehicle-options="vehicleOptions"
                    :doc-type-options="docTypeOptions"
                    :transport-owner-options="transportOwnerOptions"
                    :add-document="addDocument"
                    :remove-document="removeDocument"
                    :add-loan="addLoan"
                    :remove-loan="removeLoan"
                    @update:search-query="searchQuery = $event"
                    @update:expanded-rows="expandedRows = $event"
                    @edit="openEdit"
                    @delete="deleteMachine"
                    @submit-edit="submitEdit"
                    @cancel-edit="resetEditForm"
                />

            </div>
        </div>
    </AppLayout>
</template>
