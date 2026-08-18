<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

import Swal from 'sweetalert2';
import { 
    CurrencyRupeeIcon, PlusIcon,
    PencilSquareIcon, TrashIcon, CheckCircleIcon, XCircleIcon
} from '@heroicons/vue/24/outline';

// PrimeVue & Custom UI Components
import Column from 'primevue/column';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';

import ToggleSwitch from 'primevue/toggleswitch';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Divider from 'primevue/divider';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';

const page = usePage();

const props = defineProps<{
    rates: any[];
    customers: { id: number; legal_name: string }[];
    pumps: { value: number; label: string }[];
    sites: { id: number; name: string; patron_id: any }[];
    uoms: { id: number; unit_name: string; unit_code: string }[];
}>();

const isModalVisible = ref(false);
const editingId = ref<number | null>(null);

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const form = useForm({
    customer_id: null as number | null,
    concrete_pump: null as number | null,
    rate: 0 as number,
    rate_type: 'Flat Rate' as string,
    uom_id: null as number | null,
    name: '' as string,
    site_id: null as number | null,
    status: true as boolean,
});

const customerOptions = computed(() => props.customers.map(c => ({ label: c.legal_name, value: c.id })));
const pumpOptions = computed(() => props.pumps);
const uomOptions = computed(() => props.uoms.map(u => ({ label: u.unit_code, value: u.id })));

const rateTypeOptions = [
    { label: 'Flat Rate', value: 'Flat Rate' },
    { label: 'Per UOM', value: 'Per UOM' },
];

// Filter sites based on selected customer
const filteredSiteOptions = computed(() => {
    if (!form.customer_id) {
        return props.sites.map(s => ({ label: s.name, value: s.id }));
    }
    return props.sites
        .filter(s => {
            const patronIds = Array.isArray(s.patron_id) ? s.patron_id : [s.patron_id];
            return patronIds.map(Number).includes(Number(form.customer_id));
        })
        .map(s => ({ label: s.name, value: s.id }));
});

// Watch customer change to reset site if it's not valid for the selected customer
watch(() => form.customer_id, () => {
    if (form.site_id) {
        const isValid = filteredSiteOptions.value.some(s => s.value === form.site_id);
        if (!isValid) {
            form.site_id = null;
        }
    }
});

// Reset UOM if rate type is Flat Rate
watch(() => form.rate_type, (newType) => {
    if (newType === 'Flat Rate') {
        form.uom_id = null;
    }
});

const openModal = (id: number | null = null) => {
    editingId.value = id;
    if (id) {
        const rate = props.rates.find(r => r.id === id);
        if (rate) {
            form.customer_id = rate.customer_id;
            form.concrete_pump = rate.concrete_pump;
            form.rate = parseFloat(rate.rate);
            form.rate_type = rate.rate_type;
            form.uom_id = rate.uom_id;
            form.name = rate.name || '';
            form.site_id = rate.site_id;
            form.status = rate.status ? true : false;
        }
    } else {
        form.reset();
    }
    isModalVisible.value = true;
};

const submitForm = () => {
    if (editingId.value) {
        form.put(route('pumprates.update', editingId.value), {
            onSuccess: () => {
                isModalVisible.value = false;
                form.reset();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Pump rate updated successfully', showConfirmButton: false, timer: 1500 });
            }
        });
    } else {
        form.post(route('pumprates.store'), {
            onSuccess: () => {
                isModalVisible.value = false;
                form.reset();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Pump rate configured successfully', showConfirmButton: false, timer: 1500 });
            }
        });
    }
};

const deleteRate = (id: number) => {
    Swal.fire({
        title: 'Delete Pump Rate?',
        text: "This rate configuration will be permanently removed.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('pumprates.destroy', id), {
                onSuccess: () => {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Pump rate deleted successfully',
                        showConfirmButton: false,
                        timer: 1500
                    });
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
    <AppLayout title="Pump Rates">
        <template #header><ModuleSubTopNav /></template>

        <div class="py-12 bg-slate-50/50 dark:bg-slate-950 min-h-screen font-sans">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

                <BaseDataTable
                    :value="rates"
                    v-model:filters="filters"
                    :globalFilterFields="['customer.legal_name', 'pump.registration', 'site.name', 'name']"
                    showSearch
                    showSerial
                    heading="Pump Rate Matrix"
                    headingIcon="CurrencyRupeeIcon"
                    :rows="30"
                    class="modern-table text-sm"
                >
                    <template #toolbar>
                        <BaseButton severity="primary" variant="filled" @click="openModal()" class="rounded-full shadow-xl shadow-blue-500/20 uppercase tracking-widest font-black text-xs h-[48px] px-6">
                            <i class="pi pi-plus mr-1"></i>
                            Configure Pump Rate
                        </BaseButton>
                    </template>

                    <Column field="name" header="Name">
                        <template #body="slotProps">
                            <span class="font-bold text-gray-800 dark:text-gray-200">{{ slotProps.data.name || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="customer.legal_name" header="Customer">
                        <template #body="slotProps">
                            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ slotProps.data.customer?.legal_name || 'GLOBAL (ALL CUSTOMERS)' }}</span>
                        </template>
                    </Column>
                    <Column field="pump.registration" header="Pump / Machine">
                        <template #body="slotProps">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ slotProps.data.pump?.registration || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="site.name" header="Site">
                        <template #body="slotProps">
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-bold">{{ slotProps.data.site?.name || 'ANY' }}</span>
                        </template>
                    </Column>
                    <!-- <Column field="rate_type" header="Rate Type">
                        <template #body="slotProps">
                            <Tag severity="info" rounded class="text-[10px] uppercase font-black tracking-widest">
                                {{ slotProps.data.rate_type }} {{ slotProps.data.uom ? `(${slotProps.data.uom.unit_code})` : '' }}
                            </Tag>
                        </template>
                    </Column> -->
                    <Column field="rate" header="Rate" align="right">
                        <template #body="slotProps">
                            <span class="font-black text-blue-600 dark:text-blue-400 font-mono">₹{{ Number(slotProps.data.rate).toLocaleString('en-IN') }}</span>
                        </template>
                    </Column>
                    <Column header="Status" align="center">
                        <template #body="slotProps">
                            <Tag :severity="slotProps.data.status ? 'success' : 'danger'" rounded class="text-[10px] uppercase font-black tracking-widest">
                                {{ slotProps.data.status ? 'Active' : 'Inactive' }}
                            </Tag>
                        </template>
                    </Column>
                    <Column header="Actions" align="right">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-1.5 items-center">
                                <BaseButton icon="pi pi-pencil" severity="secondary" variant="text" rounded @click="openModal(slotProps.data.id)" />
                                <BaseDeleteButton :url="route('pumprates.destroy', slotProps.data.id)" title="Delete Pump Rate?" text="This rate configuration will be permanently removed." successMessage="Pump rate deleted successfully" />
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div class="py-20 flex flex-col items-center opacity-30">
                            <i class="pi pi-inbox text-5xl mb-4" />
                            <span class="font-bold">No pump rates configured</span>
                        </div>
                    </template>
                </BaseDataTable>

            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Dialog v-model:visible="isModalVisible" modal :header="editingId ? 'Modify Pump Rate Configuration' : 'Establish New Pump Rate'" class="rounded-3xl shadow-2xl" :style="{ width: '640px' }">
            <div class="pt-4 space-y-6">
                
                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Configuration Name</label>
                    <BaseInput v-model="form.name" placeholder="e.g. Standard Pump Charge" class="w-full" />
                    <small v-if="form.errors.name" class="p-error">{{ form.errors.name }}</small>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Customer <span class="text-indigo-400 text-[0.5rem]">(Leave empty for global rate)</span></label>
                        <BaseSelect v-model="form.customer_id" :options="customerOptions" optionLabel="label" optionValue="value" filter clearable placeholder="Search customers..." class="w-full" />
                        <small v-if="form.errors.customer_id" class="p-error">{{ form.errors.customer_id }}</small>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Pump / Machine *</label>
                        <BaseSelect v-model="form.concrete_pump" :options="pumpOptions" optionLabel="label" optionValue="value" filter placeholder="Select pump..." class="w-full" />
                        <small v-if="form.errors.concrete_pump" class="p-error">{{ form.errors.concrete_pump }}</small>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Site (Optional)</label>
                        <BaseSelect v-model="form.site_id" :options="filteredSiteOptions" optionLabel="label" optionValue="value" filter clearable placeholder="Select site..." class="w-full" />
                        <small v-if="form.errors.site_id" class="p-error">{{ form.errors.site_id }}</small>
                    </div>
                    <!-- <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Rate Type *</label>
                        <BaseSelect v-model="form.rate_type" :options="rateTypeOptions" optionLabel="label" optionValue="value" class="w-full" />
                        <small v-if="form.errors.rate_type" class="p-error">{{ form.errors.rate_type }}</small>
                    </div> -->
                     <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">UOM / Unit *</label>
                        <BaseSelect v-model="form.uom_id" :options="uomOptions" optionLabel="label" optionValue="value" filter placeholder="Select unit..." class="w-full" />
                        <small v-if="form.errors.uom_id" class="p-error">{{ form.errors.uom_id }}</small>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                   
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Pump Rate (₹) *</label>
                        <BaseInputNumber v-model="form.rate" :minFractionDigits="2" class="w-full font-black text-blue-600 dark:text-blue-400" />
                        <small v-if="form.errors.rate" class="p-error">{{ form.errors.rate }}</small>
                    </div>

                     <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Status</label>
                    <ToggleSwitch v-model="form.status" :binary="true"  class="w-full" />
                </div>  
                </div>

               

                <div class="flex justify-end gap-3 mt-8 pt-6 border-t dark:border-slate-800">
                    <BaseButton label="Cancel" severity="secondary" variant="text" @click="isModalVisible = false" class="px-8 font-bold uppercase tracking-widest text-xs h-12" />
                    <BaseButton :label="editingId ? 'Update Rate' : 'Establish Rate'" severity="primary" variant="filled" :loading="form.processing" @click="submitForm" class="rounded-xl px-10 font-black uppercase tracking-widest text-xs h-12 shadow-lg shadow-blue-500/20" />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
</style>
