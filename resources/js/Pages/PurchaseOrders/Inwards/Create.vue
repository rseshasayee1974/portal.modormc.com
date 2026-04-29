<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

// Components
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import Swal from 'sweetalert2';
import { 
    ArchiveBoxIcon, 
    DocumentTextIcon, 
    CalendarIcon,
    Bars3CenterLeftIcon,
    ArrowPathIcon,
    CheckCircleIcon,
    ArrowDownTrayIcon
} from '@heroicons/vue/24/outline';
import { useWeighbridge } from '@/Composables/useWeighbridge';

const { isScaleConnected, captureWeight } = useWeighbridge();

const props = defineProps<{
    purchase_order?: any;
    purchaseOrders: any[];
}>();

const selectedPoId = ref(props.purchase_order?.id || null);

const poOptions = computed(() => {
    return props.purchaseOrders.map(po => ({
        label: `${po.po_number} - ${po.vendor?.legal_name} (${formatDate(po.date_order)})`,
        value: po.id
    }));
});

const form = useForm({
    order_id: props.purchase_order?.id || (null as number | null),
    received_date: new Date().toISOString().substring(0, 10),
    inward_no: '',
    items: [] as any[]
});

const loadPoDetails = (poId: number | null) => {
    if (!poId) {
        form.items = [];
        return;
    }
    const po = props.purchaseOrders.find(p => p.id === poId);
    if (!po) {
        if (props.purchase_order && props.purchase_order.id === poId) {
            setupItems(props.purchase_order);
        }
        return;
    }
    setupItems(po);
};

const setupItems = (po: any) => {
    form.items = po.items.map((item: any) => ({
        order_item_id: item.id,
        product_id: item.product_id,
        product_title: item.product?.title,
        ordered_qty: Number(item.product_quantity),
        received_qty_previously: Number(item.received_quantity || 0),
        uom: item.uom?.unit_code,
        received_qty: 0
    }));
};

if (props.purchase_order) {
    setupItems(props.purchase_order);
}

watch(selectedPoId, (newId) => {
    form.order_id = newId;
    loadPoDetails(newId);
});

const formatDate = (date: string) => {
    if (!date) return '--';
    return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
};

const submit = () => {
    if (!form.order_id) {
        Swal.fire('Warning', 'Please select a Purchase Order', 'warning');
        return;
    }

    const someReceived = form.items.some(i => i.received_qty > 0);
    if (!someReceived) {
        Swal.fire('Warning', 'Please enter received quantity for at least one item', 'warning');
        return;
    }

    const exceeding = form.items.find(i => (i.received_qty_previously + i.received_qty) > i.ordered_qty);
    if (exceeding) {
        Swal.fire('Error', `Received quantity for ${exceeding.product_title} exceeds ordered quantity.`, 'error');
        return;
    }

    form.transform((data) => ({
        ...data,
        received_date: data.received_date ? new Date(data.received_date).toISOString().split('T')[0] : null
    })).post(route('inwards.store'), {
        onSuccess: () => {
             Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Stock inward recorded successfully', showConfirmButton: false, timer: 3000 });
        }
    });
};

const remainingToReceive = (item: any) => {
    return Math.max(0, item.ordered_qty - item.received_qty_previously);
};

</script>

<template>
    <AppLayout title="Record Stock Inward">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-6 px-4 md:px-8 bg-[#f8fafc] min-h-screen">
            <div class="max-w-4xl mx-auto">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Top Section: Selection -->
                    <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
                        <div class="p-8 border-b border-slate-100 bg-slate-50/50">
                            <div class="flex items-center gap-4 mb-8">
                                <div class="w-12 h-12 rounded-xl bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-100">
                                    <ArrowPathIcon class="w-6 h-6 text-white" />
                                </div>
                                <div>
                                    <h1 class="text-xl font-black text-slate-800 uppercase tracking-tight">Record Goods Receipt</h1>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Inventory Acquisition Portal</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                        <DocumentTextIcon class="w-3.5 h-3.5" />
                                        Purchase Order Reference
                                    </label>
                                    <BaseSelect 
                                        v-model="selectedPoId" 
                                        :options="poOptions" 
                                        placeholder="Select reference order..." 
                                        optionLabel="label"
                                        optionValue="value"
                                        filter 
                                        class="w-full !rounded-md !h-10 !bg-white"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                        <CalendarIcon class="w-3.5 h-3.5" />
                                        Arrival Date
                                    </label>
                                    <BaseDatePicker 
                                        v-model="form.received_date" 
                                        placeholder="Pick Date"
                                        dateFormat="yy-mm-dd"
                                        class="w-full !rounded-md !h-10"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Item Entry Section -->
                        <div v-if="form.items.length > 0" class="p-0">
                            <table class="w-full border-collapse">
                                <thead class="bg-white border-b border-slate-100">
                                    <tr>
                                        <th class="px-8 py-4 text-left text-[9px] font-black text-slate-400 uppercase tracking-widest">Product Details</th>
                                        <th class="px-6 py-4 text-center text-[9px] font-black text-slate-400 uppercase tracking-widest w-40">Procurement Status</th>
                                        <th class="px-8 py-4 text-right text-[9px] font-black text-slate-400 uppercase tracking-widest w-52">Quantity Entry</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <tr v-for="(item, idx) in form.items" :key="idx" class="hover:bg-slate-50/30 transition-colors">
                                        <td class="px-8 py-6">
                                            <div class="flex flex-col">
                                                <span class="text-[13px] font-bold text-slate-800 uppercase tracking-tight">{{ item.product_title }}</span>
                                                <div class="flex items-center gap-3 mt-1.5">
                                                    <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Ordered: {{ item.ordered_qty }} {{ item.uom }}</span>
                                                    <div class="w-1 h-1 rounded-full bg-slate-300"></div>
                                                    <span class="text-[9px] text-emerald-600 font-bold uppercase tracking-wider">Accepted: {{ item.received_qty_previously }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-6 text-center">
                                            <div v-if="remainingToReceive(item) <= 0" class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 rounded-full border border-emerald-100">
                                                <CheckCircleIcon class="w-3.5 h-3.5" />
                                                <span class="text-[8px] font-black uppercase tracking-widest">Fully Received</span>
                                            </div>
                                            <div v-else class="flex flex-col items-center gap-1.5">
                                                <span class="text-[8px] font-bold text-amber-500 uppercase tracking-widest">Pending: {{ remainingToReceive(item) }} {{ item.uom }}</span>
                                                <div class="w-24 bg-slate-100 h-1 rounded-full overflow-hidden">
                                                    <div class="bg-amber-400 h-full transition-all duration-500" :style="{ width: (item.received_qty_previously / item.ordered_qty * 100) + '%' }"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-8 py-6">
                                            <div class="flex items-center justify-end gap-3">
                                                
                                                <BaseInputNumber 
                                                    v-model="item.received_qty" 
                                                    :disabled="remainingToReceive(item) <= 0"
                                                    :max="remainingToReceive(item)"
                                                    :minFractionDigits="2"
                                                    class="w-32 text-right !rounded-md overflow-hidden border border-slate-200"
                                                    inputClass="!text-right font-black !h-9 !bg-slate-50/50"
                                                />
                                                <span class="text-[10px] font-black text-slate-400 w-8">{{ item.uom }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-else class="py-24 text-center bg-white">
                            <div class="w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-4 border border-slate-100">
                                <Bars3CenterLeftIcon class="w-8 h-8 text-slate-200" />
                            </div>
                            <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest">Select Purchase Order</h3>
                            <p class="text-xs text-slate-400 mt-2">Choose an approved order to begin recording inventory inward.</p>
                        </div>

                        <!-- Footer -->
                        <div v-if="form.items.length > 0" class="p-8 bg-slate-50/50 border-t border-slate-100 flex flex-col md:flex-row justify-between items-center gap-6">
                             <div class="flex flex-col gap-1.5 w-full md:w-auto">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Manual GRN Reference</label>
                                <BaseInput v-model="form.inward_no" placeholder="e.g. INV-12345" class="!rounded-md !h-9 border-slate-200 w-full md:w-64 text-xs font-mono" />
                            </div>

                            <div class="flex gap-3 w-full md:w-auto">
                                <BaseButton label="Back to Registry" variant="text" severity="secondary" @click="router.visit(route('inwards.index'))" class="!text-xs !font-bold" />
                                <BaseButton 
                                    label="Confirm Goods Receipt" 
                                    icon="pi pi-check-circle" 
                                    variant="filled" 
                                    :loading="form.processing"
                                    @click="submit"
                                    class="!rounded-md !px-8 !h-10 !font-black !text-[10px] !uppercase !tracking-widest !bg-indigo-600"
                                />
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-inputnumber-input) {
    border: none !important;
    box-shadow: none !important;
}

:deep(.p-inputtext:focus) {
    box-shadow: none !important;
}
</style>
