<script setup lang="ts">
import { ref, computed, reactive, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

import Swal from 'sweetalert2';
import { 
    CurrencyRupeeIcon, MagnifyingGlassIcon, PlusIcon,
    PencilSquareIcon, TrashIcon, CheckCircleIcon, XCircleIcon
} from '@heroicons/vue/24/outline';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Divider from 'primevue/divider';

const page = usePage();

const props = defineProps<{
    rates: any[];
    patrons: { id: number; name: string }[];
    sites: { id: number; name: string }[];
    uoms: { id: number; name: string }[];
    products: { id: number; name: string }[];
}>();

const searchQuery = ref('');
const isModalVisible = ref(false);
const editingId = ref<number | null>(null);

const filteredRates = computed(() => {
    if (!searchQuery.value) return props.rates;
    const q = searchQuery.value.toLowerCase();
    return props.rates.filter((r: any) =>
        (r.patron?.name && r.patron.name.toLowerCase().includes(q)) ||
        (r.product?.name && r.product.name.toLowerCase().includes(q)) ||
        (r.loading_site?.name && r.loading_site.name.toLowerCase().includes(q)) ||
        (r.unloading_site?.name && r.unloading_site.name.toLowerCase().includes(q))
    );
});

const form = useForm({
    patron_id: null as number | null,
    loading_site: null as number | null,
    unloading_site: null as number | null,
    uom_id: null as number | null,
    payment_type: null as string | null,
    product_id: null as number | null,
    product_rate: 0 as number,
    transport_rate: 0 as number,
    rate: 0 as number,
    status: 1 as number | boolean,
});

const patronOptions = computed(() => props.patrons.map(p => ({ label: p.name, value: p.id })));
const siteOptions = computed(() => props.sites.map(s => ({ label: s.name, value: s.id })));
const uomOptions = computed(() => props.uoms.map(u => ({ label: u.name, value: u.id })));
const productOptions = computed(() => props.products.map(p => ({ label: p.name, value: p.id })));
const paymentTypeOptions = [
    { label: 'Credit', value: 'Credit' },
    { label: 'Cash', value: 'Cash' },
    { label: 'Advance', value: 'Advance' },
];

const totalRate = computed(() => (form.product_rate || 0) + (form.transport_rate || 0));
watch(totalRate, (val) => {
    form.rate = val;
});

const openModal = (id: number | null = null) => {
    editingId.value = id;
    if (id) {
        const rate = props.rates.find(r => r.id === id);
        if (rate) {
            form.patron_id = rate.patron_id;
            form.loading_site = rate.loading_site;
            form.unloading_site = rate.unloading_site;
            form.uom_id = rate.uom_id;
            form.payment_type = rate.payment_type;
            form.product_id = rate.product_id;
            form.product_rate = parseFloat(rate.product_rate);
            form.transport_rate = parseFloat(rate.transport_rate);
            form.rate = parseFloat(rate.rate);
            form.status = rate.status;
        }
    } else {
        form.reset();
    }
    isModalVisible.value = true;
};

const submitForm = () => {
    if (editingId.value) {
        form.put(route('partyrates.update', editingId.value), {
            onSuccess: () => {
                isModalVisible.value = false;
                form.reset();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Rate updated successfully', showConfirmButton: false, timer: 3000 });
            }
        });
    } else {
        form.post(route('partyrates.store'), {
            onSuccess: () => {
                isModalVisible.value = false;
                form.reset();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Rate established successfully', showConfirmButton: false, timer: 3000 });
            }
        });
    }
};

const deleteRate = (id: number) => {
    Swal.fire({
        title: 'Delete Rate?',
        text: "This configuration will be removed.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('partyrates.destroy', id), {
                onSuccess: () => {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Rate deleted successfully',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            });
        }
    });
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: flash.success, showConfirmButton: false, timer: 3000 });
}, { immediate: true, deep: true });
</script>

<template>
    <AppLayout title="Party Rates">
        <template #header><ModuleSubTopNav /></template>

        <div class="py-12 bg-slate-50/50 dark:bg-slate-950 min-h-screen font-sans">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

                <!-- Header / Search -->
                <div class="bg-white dark:bg-slate-900 shadow-xl rounded-[2.5rem] p-8 border border-slate-100 dark:border-slate-800 flex flex-col md:flex-row justify-between items-center gap-6">
                    <div class="flex items-center gap-4">
                        <div class="p-4 bg-blue-50 dark:bg-blue-900/10 text-blue-600 dark:text-blue-400 rounded-[1.5rem] shadow-sm">
                            <CurrencyRupeeIcon class="w-8 h-8 stroke-[2.5px]" />
                        </div>
                        <div>
                            <h3 class="text-2xl font-black text-gray-800 dark:text-gray-100 tracking-tighter uppercase leading-none">Party Rate Matrix</h3>
                            <p class="text-[10px] text-gray-400 font-black mt-1 uppercase tracking-widest">Global & Patron-specific rate configurations</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-4 w-full md:w-auto">
                        <span class="relative w-full md:w-72">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <MagnifyingGlassIcon class="w-5 h-5 text-gray-400" />
                            </span>
                            <BaseInput v-model="searchQuery" placeholder="Search rates..." class="w-full pl-10 rounded-full" />
                        </span>
                        <Button severity="primary" @click="openModal()" class="rounded-full px-8 shadow-xl shadow-blue-500/20 uppercase tracking-widest font-black text-xs h-[48px]">
                            <template #icon><PlusIcon class="w-5 h-5 stroke-[3px] mr-1" /></template>
                            Configure Rate
                        </Button>
                    </div>
                </div>

                <!-- Rates Table -->
                <div class="bg-white dark:bg-slate-900 shadow-2xl rounded-[2.5rem] p-6 border border-slate-100 dark:border-slate-800 overflow-hidden">
                    <DataTable :value="filteredRates" stripedRows paginator :rows="10" class="modern-table">
                        <Column field="patron.name" header="Patron">
                            <template #body="slotProps">
                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ slotProps.data.patron?.name || '—' }}</span>
                            </template>
                        </Column>
                        <Column field="product.name" header="Product">
                            <template #body="slotProps">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ slotProps.data.product?.name || '—' }}</span>
                            </template>
                        </Column>
                        <Column header="Route">
                            <template #body="slotProps">
                                <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                    <span class="font-bold text-gray-700 dark:text-gray-200">{{ slotProps.data.loading_site?.name || 'ANY' }}</span>
                                    <span class="opacity-50">→</span>
                                    <span class="font-bold text-gray-700 dark:text-gray-200">{{ slotProps.data.unloading_site?.name || 'ANY' }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column field="payment_type" header="Terms">
                            <template #body="slotProps">
                                <Tag severity="info" rounded class="text-[10px] uppercase font-black tracking-widest">{{ slotProps.data.payment_type || 'N/A' }}</Tag>
                            </template>
                        </Column>
                        <Column field="rate" header="Unit Rate" align="right">
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
                                <div class="flex justify-end gap-1">
                                    <Button icon="pi pi-pencil" severity="secondary" text rounded @click="openModal(slotProps.data.id)" />
                                    <Button icon="pi pi-trash" severity="danger" text rounded @click="deleteRate(slotProps.data.id)" />
                                </div>
                            </template>
                        </Column>
                        <template #empty>
                            <div class="py-20 flex flex-col items-center opacity-30">
                                <i class="pi pi-inbox text-5xl mb-4" />
                                <span class="font-bold">No rates configured</span>
                            </div>
                        </template>
                    </DataTable>
                </div>

            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Dialog v-model:visible="isModalVisible" modal :header="editingId ? 'Modify Rate Configuration' : 'Establish New Rate'" class="rounded-3xl shadow-2xl" :style="{ width: '640px' }">
            <div class="pt-4 space-y-6">
                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Target Patron (Optional: Leave empty for global rate)</label>
                    <BaseSelect v-model="form.patron_id" :options="patronOptions" optionLabel="label" optionValue="value" filter clearable placeholder="Search patrons..." class="w-full" />
                    <small v-if="form.errors.patron_id" class="p-error">{{ form.errors.patron_id }}</small>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Loading Site</label>
                        <BaseSelect v-model="form.loading_site" :options="siteOptions" optionLabel="label" optionValue="value" filter clearable placeholder="Select origin..." class="w-full" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Unloading Site</label>
                        <BaseSelect v-model="form.unloading_site" :options="siteOptions" optionLabel="label" optionValue="value" filter clearable placeholder="Select destination..." class="w-full" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-6">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Product</label>
                        <BaseSelect v-model="form.product_id" :options="productOptions" optionLabel="label" optionValue="value" filter clearable placeholder="Select product..." class="w-full" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">UOM</label>
                        <BaseSelect v-model="form.uom_id" :options="uomOptions" optionLabel="label" optionValue="value" filter clearable placeholder="Select unit..." class="w-full" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Payment Type</label>
                        <BaseSelect v-model="form.payment_type" :options="paymentTypeOptions" optionLabel="label" optionValue="value" filter clearable placeholder="Type..." class="w-full" />
                    </div>
                </div>

                <Divider align="center">
                    <span class="text-[9px] font-black uppercase text-gray-400 tracking-[0.2em]">Rate Breakdown</span>
                </Divider>

                <div class="grid grid-cols-3 gap-6">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Product Rate (₹)</label>
                        <BaseInputNumber v-model="form.product_rate" :minFractionDigits="2" class="w-full" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Transport Rate (₹)</label>
                        <BaseInputNumber v-model="form.transport_rate" :minFractionDigits="2" class="w-full" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Aggregated Rate (₹) *</label>
                        <BaseInputNumber v-model="form.rate" :minFractionDigits="2" class="w-full font-black text-blue-600 dark:text-blue-400" />
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Status</label>
                    <BaseSelect v-model="form.status" :options="[{label: 'Active Configuration', value: 1}, {label: 'Disabled', value: 0}]" optionLabel="label" optionValue="value" class="w-full" />
                </div>

                <div class="flex justify-end gap-3 mt-8 pt-6 border-t dark:border-slate-800">
                    <Button label="Cancel" severity="secondary" text @click="isModalVisible = false" class="px-8 font-bold uppercase tracking-widest text-xs h-12" />
                    <Button :label="editingId ? 'Update Rate' : 'Establish Rate'" severity="primary" :loading="form.processing" @click="submitForm" class="rounded-xl px-10 font-black uppercase tracking-widest text-xs h-12 shadow-lg shadow-blue-500/20" />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable-thead > tr > th) {
    @apply bg-blue-50/50 dark:bg-slate-950 text-blue-500 font-black uppercase text-[10px] tracking-widest py-6 dark:border-slate-800;
}
:deep(.p-datatable-tbody > tr > td) {
    @apply py-6 dark:border-slate-800;
}
</style>


