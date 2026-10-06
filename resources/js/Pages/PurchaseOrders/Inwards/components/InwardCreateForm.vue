<script setup lang="ts">
import { entityToday, entityLocaleDate } from '@/Utils/entityDateTime';
import { ref, watch, computed } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';

// Components
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseDatePicker from '@/Components/Base/BaseDatePicker.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import Dialog from 'primevue/dialog';
import InwardTruckSelect from './InwardTruckSelect.vue';
import Swal from 'sweetalert2';
import {
    ArchiveBoxIcon,
    DocumentTextIcon,
    CalendarIcon,
    Bars3CenterLeftIcon,
    ArrowPathIcon,
    CheckCircleIcon,
    ArrowDownTrayIcon,
    EyeIcon,
    TrashIcon,
    ScaleIcon
} from '@heroicons/vue/24/outline';
import { useWeighbridge } from '@/Composables/useWeighbridge';

const page = usePage();
const { isScaleConnected, captureWeight, captureCameraSnap } = useWeighbridge();

const imageModalVisible = ref(false);
const imageModalSrc = ref('');
const imageModalTitle = ref('');

const openImageModal = (src: string, title: string = 'Weight Snapshot') => {
    imageModalSrc.value = src;
    imageModalTitle.value = title;
    imageModalVisible.value = true;
};

const props = defineProps<{
    purchase_order?: any;
    purchaseOrders: any[];
    vehicles?: any[];
    units?: any[];
    embedded?: boolean;
}>();

const selectedPoId = ref(props.purchase_order?.id || null);
const createdTrucks = ref<any[]>([]);
const truckOptions = computed(() => [
    ...(props.vehicles || []),
    ...createdTrucks.value.filter(truck => !props.vehicles?.some(vehicle => vehicle.value === truck.value)),
]);

const poOptions = computed(() => {
    return props.purchaseOrders.map(po => ({
        label: `${po.po_number} - ${po.vendor?.legal_name} (${formatDate(po.date_order)})`,
        value: po.id
    }));
});

const form = useForm({
    order_id: props.purchase_order?.id || (null as number | null),
    received_date: entityToday(),
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
    const untUnit = props.units?.find((u: any) => String(u.label).toUpperCase() === 'UNT');
    const defaultConvUomId = untUnit ? untUnit.value : (props.units?.[0]?.value ?? null);

    form.items = po.items.map((item: any) => {
        const prodConvRate = Number(item.product?.conversion_quantity ?? item.product?.convertsion_quantity ?? 0);
        let uomCode = typeof item.uom === 'string' ? item.uom : (item.uom?.unit_code || item.uom?.unit_name);
        let convUomId = defaultConvUomId ?? item.product?.unit_id ?? item.product_uom ?? item.uom_id ?? null;

        return {
            order_item_id: item.id,
            product_id: item.product_id,
            product_title: item.product?.title,
            product: item.product,
            ordered_qty: Number(item.product_quantity),
            received_qty_previously: Number(item.received_quantity || 0),
            uom: uomCode,
            uom_id: item.uom_id || item.product_uom,
            received_qty: 0,
            conversion_quantity: 0,
            convert_volume: null,
            conversion_uom_id: convUomId,
            prodConvRate: prodConvRate,
            truck_id: null,
            truck_loaded: 0,
            loaded_weight_photo: null
        };
    });
};

watch(() => props.units, (newUnits) => {
    if (newUnits && newUnits.length > 0 && form.items) {
        const untUnit = newUnits.find((u: any) => String(u.label).toUpperCase() === 'UNT');
        const defaultId = untUnit ? untUnit.value : newUnits[0].value;
        form.items.forEach((item: any) => {
            if (!item.conversion_uom_id || !newUnits.some((u: any) => u.value == item.conversion_uom_id)) {
                item.conversion_uom_id = defaultId;
            }
        });
    }
}, { immediate: true });

const recalcVolumeConversion = (item: any) => {
    if (Number(item.convert_volume) > 0) {
        item.conversion_quantity = Number((Number(item.received_qty || 0) / Number(item.convert_volume)).toFixed(4));
    }
};

const recalcReceivedQty = (item: any) => {
    item.received_qty = Math.max(0, Number(item.truck_loaded) || 0);
    if (Number(item.convert_volume) > 0) {
        item.conversion_quantity = Number((item.received_qty / Number(item.convert_volume)).toFixed(4));
        return;
    }

    const uomStr = String(item.uom || '').toUpperCase();
    if (uomStr === 'UNT' || uomStr === 'UNT/UNT' || uomStr === 'UNIT') {
        item.conversion_quantity = 0;
        return;
    }

    const rate = Number(item.prodConvRate || item.product?.conversion_quantity || item.product?.convertsion_quantity || 0);
    if (rate > 0) {
        item.conversion_quantity = Number((item.received_qty / rate).toFixed(4));
    }
};

const captureInwardLoadedWeight = async (item: any) => {
    await captureWeight(async (w: number) => {
        item.truck_loaded = w;
        recalcReceivedQty(item);

        const customSettings: any = page.props.custom_settings || {};
        if (customSettings?.batching?.camera == 1 && (customSettings?.batching?.camera_url || customSettings?.batching?.camera_url_1 || customSettings?.batching?.camera_url_2)) {
            const cameraUrl = customSettings.batching.camera_url_2 || customSettings.batching.camera_url || customSettings.batching.camera_url_1;
            try {
                const snap = await captureCameraSnap(cameraUrl);
                item.loaded_weight_photo = snap;
            } catch (err) {
                console.error('Inward full-weight camera capture failed:', err);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'warning',
                    title: 'Full weight captured, but camera failed',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }
    });
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
    return entityLocaleDate(date, 'en-IN', { day: '2-digit', month: 'short' });
};

const submit = () => {
    if (!form.order_id) {
        Swal.fire('Warning', 'Please select a Purchase Order', 'warning');
        return;
    }

    // Record the full weight as the received quantity.
    form.items.forEach(recalcReceivedQty);

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
            if (props.embedded) {
                selectedPoId.value = null;
                form.reset();
            }
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Stock inward recorded successfully', showConfirmButton: false, timer: 1500 });
        },
        onError: (errors) => {
            Swal.fire('Could not save inward', Object.values(errors).join('\n'), 'error');
        },
    });
};

const remainingToReceive = (item: any) => {
    return Math.max(0, item.ordered_qty - item.received_qty_previously);
};

</script>

<template>
    <div :class="embedded ? 'w-full' : 'py-4 px-3 md:px-6 bg-[#f8fafc] min-h-screen'">
        <div :class="embedded ? 'w-full' : 'max-w-5xl md:max-w-6xl mx-auto'">
            <form @submit.prevent="submit" class="space-y-4">
                <!-- Top Section: Selection -->
                <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-5 md:p-6 border-b border-slate-100 bg-slate-50/50">
                        <div class="flex items-center gap-3.5 mb-5">
                            <div
                                class="w-10 h-10 rounded-lg bg-indigo-600 flex items-center justify-center shadow-md shadow-indigo-100 shrink-0">
                                <ArrowPathIcon class="w-5 h-5 text-white" />
                            </div>
                            <div>
                                <h1 class="text-lg font-black text-slate-800 uppercase tracking-tight">Record Goods
                                    Receipt</h1>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">
                                    Inventory
                                    Acquisition Portal</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label
                                    class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                                    <DocumentTextIcon class="w-3.5 h-3.5" />
                                    Purchase Order Reference
                                </label>
                                <BaseSelect v-model="selectedPoId" :options="poOptions"
                                    placeholder="Select reference order..." optionLabel="label" optionValue="value"
                                    filter class="w-full !rounded-md !h-9 !bg-white" />
                            </div>
                            <div class="space-y-1.5">
                                <label
                                    class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                                    <CalendarIcon class="w-3.5 h-3.5" />
                                    Arrival Date
                                </label>
                                <BaseDatePicker v-model="form.received_date" placeholder="Pick Date"
                                    dateFormat="yy-mm-dd" class="w-full !rounded-md !h-9" />
                            </div>
                        </div>
                    </div>

                    <!-- Item Entry Section -->
                    <div v-if="form.items.length > 0" class="p-0 overflow-x-auto border-t border-slate-200">
                        <table class="w-full border-collapse border-b border-slate-200">
                            <thead>
                                <tr class="bg-slate-100/90 border-b border-slate-300">
                                    <th
                                        class="px-3.5 py-2.5 text-left text-[11px] font-black text-slate-700 uppercase tracking-wider border-r border-slate-200/90 w-[22%]">
                                        Product Details</th>
                                    <th
                                        class="px-3 py-2.5 text-center text-[11px] font-black text-slate-700 uppercase tracking-wider border-r border-slate-200/90 w-[16%]">
                                        Procurement Status</th>
                                    <th
                                        class="px-3 py-2.5 text-center text-[11px] font-black text-slate-700 uppercase tracking-wider border-r border-slate-200/90 w-[16%]">
                                        Truck</th>
                                    <th
                                        class="px-3 py-2.5 text-center text-[11px] font-black text-slate-700 uppercase tracking-wider border-r border-slate-200/90 w-[30%]">
                                        Conversion Qty & UOM</th>
                                    <th
                                        class="px-3.5 py-2.5 text-center text-[11px] font-black text-slate-700 uppercase tracking-wider w-[16%]">
                                        Full Weight with Snap</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/70 bg-white">
                                <tr v-for="(item, idx) in form.items" :key="idx"
                                    class="hover:bg-indigo-50/20 transition-colors">
                                    <!-- Col 1: Product Details -->
                                    <td class="px-3.5 py-2.5 align-middle border-r border-slate-200/80 bg-slate-50/30">
                                        <div class="flex flex-col">
                                            <span
                                                class="text-xs font-extrabold text-slate-800 uppercase tracking-tight">{{
                                                    item.product_title }}</span>
                                            <div class="flex items-center gap-2 mt-1.5">
                                                <span
                                                    class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Ordered:
                                                    <strong class="text-slate-700 font-mono">{{ item.ordered_qty }} {{
                                                        item.uom }}</strong></span>
                                                <span class="text-slate-300">•</span>
                                                <span
                                                    class="text-[10px] text-emerald-700 font-bold uppercase tracking-wider">Accepted:
                                                    <strong class="text-emerald-700 font-mono">{{
                                                        item.received_qty_previously }}</strong></span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Col 2: Procurement Status -->
                                    <td
                                        class="px-3 py-2.5 text-center align-middle border-r border-slate-200/80 bg-white">
                                        <div v-if="remainingToReceive(item) <= 0"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-md border border-emerald-200">
                                            <CheckCircleIcon class="w-3.5 h-3.5" />
                                            <span class="text-[9px] font-extrabold uppercase tracking-wider">Fully
                                                Received</span>
                                        </div>
                                        <div v-else class="flex flex-col items-center gap-1 px-1">
                                            <div class="flex items-center justify-between w-full text-[10px] font-bold">
                                                <span
                                                    class="text-slate-400 uppercase tracking-wider text-[9px]">Pending</span>
                                                <span class="text-amber-700 font-mono font-extrabold">
                                                    {{ Number(remainingToReceive(item).toFixed(2)).toLocaleString() }}
                                                    {{ item.uom }}
                                                </span>
                                            </div>
                                            <div
                                                class="w-full bg-slate-100 h-2 rounded-full overflow-hidden border border-slate-200/80">
                                                <div class="bg-amber-400 h-full transition-all duration-500 rounded-full"
                                                    :style="{ width: Math.min(100, Math.max(0, (item.received_qty_previously / item.ordered_qty * 100))) + '%' }">
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Col 3: Truck Selection -->
                                    <td class="px-3 py-2.5 align-middle border-r border-slate-200/80 bg-slate-50/30">
                                        <InwardTruckSelect v-model="item.truck_id" :options="truckOptions"
                                            @created="createdTrucks.push($event)" />
                                    </td>

                                    <!-- Col 4: Conversion Qty & UOM -->
                                    <td class="px-3 py-2.5 align-middle border-r border-slate-200/80 bg-white">
                                        <div class="grid grid-cols-3 gap-1.5 w-full">
                                            <BaseInputNumber v-model="item.convert_volume"
                                                placeholder="Factor (e.g. 4.5)" :min="0.000001" :maxFractionDigits="6"
                                                @update:modelValue="recalcVolumeConversion(item)"
                                                class="w-full !rounded-md overflow-hidden border border-slate-300"
                                                inputClass="!text-right !h-9 text-xs w-full"
                                                :error="form.errors[`items.${idx}.convert_volume`]" />
                                            <BaseInputNumber v-model="item.conversion_quantity" placeholder="Conv Qty"
                                                :disabled="Number(item.convert_volume) > 0" :minFractionDigits="2"
                                                :maxFractionDigits="4"
                                                class="w-full !rounded-md overflow-hidden border border-slate-300"
                                                inputClass="!text-right font-bold !h-9 !bg-slate-50/70 text-xs font-mono w-full" />
                                            <BaseSelect v-model="item.conversion_uom_id" :options="units || []"
                                                placeholder="UOM" optionLabel="label" optionValue="value" filter
                                                class="w-full !rounded-md !h-9 !bg-white text-xs border border-slate-300" />
                                        </div>
                                        <p class="mt-1 text-[10px] text-slate-500">Optional: leave blank to use
                                            Conversion Qty. Enter a factor to calculate received quantity ÷ Convert
                                            Volume.</p>
                                    </td>

                                    <!-- Col 5: Full Weight with Snap -->
                                    <td class="px-3.5 py-2.5 align-middle bg-slate-50/30">
                                        <div class="flex flex-col items-end gap-1.5 w-full">
                                            <div class="flex items-center justify-end gap-1.5 w-full">
                                                <BaseInputNumber v-model="item.truck_loaded" placeholder="Full Wt"
                                                    :disabled="page.props.custom_settings?.batching?.manual_weight == 0"
                                                    :minFractionDigits="2"
                                                    class="flex-1 min-w-0 text-right !rounded-md overflow-hidden border border-slate-300"
                                                    inputClass="!text-right font-extrabold !h-9 !bg-white text-xs font-mono text-slate-800"
                                                    @update:model-value="recalcReceivedQty(item)" />

                                                <!-- Capture Weight Animated Button -->
                                                <button
                                                    v-if="remainingToReceive(item) > 0 && page.props.custom_settings?.batching?.manual_weight == 0"
                                                    @click="captureInwardLoadedWeight(item)" type="button" :class="[
                                                        'relative px-2.5 h-9 rounded-md transition-all border shrink-0 flex items-center justify-center gap-1 shadow-xs font-bold group cursor-pointer',
                                                        isScaleConnected
                                                            ? 'bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600 ring-2 ring-emerald-400/30'
                                                            : 'bg-amber-500 hover:bg-amber-600 text-white border-amber-500'
                                                    ]"
                                                    :title="isScaleConnected ? 'Capture Full Weight & Snap' : 'Connect Weighbridge & Capture Full'">
                                                    <!-- Pulsing Indicator Dot -->
                                                    <span v-if="remainingToReceive(item) > 0 && !item.truck_loaded"
                                                        class="absolute -top-1 -right-1 flex h-2.5 w-2.5">
                                                        <span
                                                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-300 opacity-75"></span>
                                                        <span
                                                            class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-400"></span>
                                                    </span>

                                                    <!-- Animated Arrow Down / Scale Icon -->
                                                    <ArrowDownTrayIcon
                                                        class="w-4 h-4 text-white transition-transform group-hover:scale-110 animate-bounce" />
                                                    <span v-if="page.props.custom_settings?.batching?.camera == 1"
                                                        class="text-[9px] font-black uppercase tracking-wider text-white">Full</span>
                                                </button>
                                            </div>

                                            <div v-if="item.loaded_weight_photo"
                                                class="relative group w-full h-12 rounded-md overflow-hidden border border-slate-300 shadow-xs bg-slate-900">
                                                <img :src="item.loaded_weight_photo"
                                                    class="w-full h-full object-cover cursor-pointer hover:opacity-90 transition-opacity"
                                                    @click="openImageModal(item.loaded_weight_photo, 'Full Weight Snap')" />
                                                <div
                                                    class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                                    <button
                                                        @click="openImageModal(item.loaded_weight_photo, 'Full Weight Snap')"
                                                        type="button"
                                                        class="p-1 bg-white text-slate-800 rounded hover:bg-slate-100 text-[10px] font-bold px-2 flex items-center gap-1"
                                                        title="View Full Image">
                                                        <EyeIcon class="w-3 h-3" /> View
                                                    </button>
                                                    <button @click="item.loaded_weight_photo = null" type="button"
                                                        class="p-1 bg-red-600 hover:bg-red-700 text-white rounded text-[10px] font-bold px-2 flex items-center gap-1"
                                                        title="Remove Snap">
                                                        <TrashIcon class="w-3 h-3" />
                                                    </button>
                                                </div>
                                                <span
                                                    class="absolute bottom-0.5 right-0.5 bg-black/75 text-white text-[7px] font-mono font-bold px-1 rounded">FULL</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="py-16 text-center bg-white">
                        <div
                            class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-3 border border-slate-100">
                            <Bars3CenterLeftIcon class="w-6 h-6 text-slate-300" />
                        </div>
                        <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest">Select Purchase Order
                        </h3>
                        <p class="text-[11px] text-slate-400 mt-1">Choose an approved order to begin recording inventory
                            inward.</p>
                    </div>

                    <!-- Footer -->
                    <div v-if="form.items.length > 0"
                        class="p-5 md:p-6 bg-slate-50/50 border-t border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4">
                        <div class="flex flex-col gap-1 w-full md:w-auto">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Manual GRN
                                Reference</label>
                            <BaseInput v-model="form.inward_no" placeholder="e.g. INV-12345"
                                class="!rounded-md !h-9 border-slate-200 w-full md:w-64 text-xs font-mono" />
                        </div>

                        <div class="flex gap-3 w-full md:w-auto justify-end">
                            <BaseButton v-if="embedded" label="Clear Items" variant="text" severity="secondary"
                                @click="selectedPoId = null" class="!text-xs !font-bold" />
                            <BaseButton v-else label="Back to Registry" variant="text" severity="secondary"
                                @click="router.visit(route('inwards.index'))" class="!text-xs !font-bold" />
                            <BaseButton label="Confirm Goods Receipt" icon="pi pi-check-circle" variant="filled"
                                :loading="form.processing" @click="submit"
                                class="!rounded-md !px-6 !h-9 !font-black !text-[10px] !uppercase !tracking-widest !bg-indigo-600 shadow-sm" />
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <Dialog v-model:visible="imageModalVisible" modal :header="imageModalTitle"
        :style="{ width: '560px', maxWidth: '95vw' }" class="p-fluid rounded-2xl overflow-hidden shadow-2xl border-0">
        <div class="p-3 flex flex-col items-center justify-center bg-slate-900/5 rounded-xl">
            <img :src="imageModalSrc"
                class="w-full max-h-[70vh] object-contain rounded-lg border border-slate-200 shadow-md bg-white"
                alt="Weight Snapshot" />
        </div>
    </Dialog>
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
