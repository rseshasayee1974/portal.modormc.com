<script setup>
import { defineProps, defineEmits } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { 
    CalendarIcon, 
    UserIcon, 
    HashtagIcon, 
    MapPinIcon,
    CurrencyRupeeIcon,
    ShoppingCartIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    visible: Boolean,
    order: Object
});

const emit = defineEmits(['update:visible']);

const close = () => {
    emit('update:visible', false);
};

const getStatusSeverity = (state) => {
    switch (state) {
        case 'draft': return 'secondary';
        case 'to_approve': return 'warn';
        case 'approved': return 'info';
        case 'purchase': return 'success';
        case 'done': return 'success';
        case 'cancel': return 'danger';
        default: return 'secondary';
    }
};

const formatDate = (date) => {
    if (!date) return '--';
    return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
    }).format(value);
};

const downloadPO = () => {
    if (props.order?.id) {
        window.open(route('purchaseorder.download', props.order.id), '_blank');
    }
};
</script>

<template>
    <Dialog 
        :visible="visible" 
        @update:visible="emit('update:visible', $event)"
        modal 
        header="Purchase Order Details" 
        :style="{ width: '85vw', maxWidth: '1000px' }"
        class="mm-preview-dialog"
    >
        <template #header>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded bg-indigo-50 flex items-center justify-center">
                    <ShoppingCartIcon class="w-6 h-6 text-indigo-600" />
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">{{ order?.po_number }}</h3>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter">Reference Procurement Document</p>
                </div>
            </div>
        </template>

        <div v-if="order" class="space-y-8 py-4">
            <!-- Summary Header -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 p-6 rounded-sm bg-slate-50 border border-slate-100">
                <div class="flex flex-col gap-1">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-[0.1em]">PO Number & Status</span>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black text-slate-800">{{ order.po_number }}</span>
                        <Tag :value="order.state?.toUpperCase()" :severity="getStatusSeverity(order.state)" class="!text-[8px] !font-black !px-2 !py-0.5" />
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-[0.1em]">Key Dates</span>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-[10px] font-bold text-slate-600">
                            <span class="text-slate-400 w-12">Ordered:</span>
                            {{ formatDate(order.date_order) }}
                        </div>
                        <div class="flex items-center gap-2 text-[10px] font-bold text-slate-600">
                            <span class="text-slate-400 w-12">Planned:</span>
                            {{ formatDate(order.date_planned) }}
                        </div>
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-[0.1em]">Vendor</span>
                    <div class="flex items-center gap-2 text-[11px] font-bold text-slate-700">
                        <UserIcon class="w-4 h-4 text-slate-400" />
                        {{ order.vendor?.name }}
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-[0.1em]">Total Commitment</span>
                    <div class="flex items-center gap-2 text-sm font-black text-indigo-600">
                        <CurrencyRupeeIcon class="w-4 h-4" />
                        {{ formatCurrency(order.amount_total) }}
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div>
                <div class="flex items-center justify-between mb-4 px-1">
                    <div class="flex items-center gap-2">
                        <div class="w-1.5 h-4 bg-indigo-500 rounded-full"></div>
                        <h4 class="text-[10px] font-black text-slate-800 uppercase tracking-[0.15em]">Ordered Products</h4>
                    </div>
                </div>
                
                <div class="border border-slate-200 rounded overflow-hidden shadow-sm">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Product Description</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Qty Ordered</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Qty Received</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Unit Price</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Taxes</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 uppercase">
                            <tr v-for="item in order.items" :key="item.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-4 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-slate-700 tracking-tight">{{ item.product?.title }}</span>
                                        <span class="text-[10px] text-slate-400 font-mono">{{ item.product?.hsn_code }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="text-[11px] font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded">
                                        {{ Number(item.product_quantity) }} {{ item.uom?.unit_code }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span 
                                        class="text-[11px] font-bold px-2 py-1 rounded"
                                        :class="Number(item.received_quantity) >= Number(item.product_quantity) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                                    >
                                        {{ Number(item.received_quantity) || 0 }} {{ item.uom?.unit_code }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right text-xs font-bold text-slate-600">{{ formatCurrency(item.unit_price) }}</td>
                                <td class="px-4 py-4 text-right text-xs font-bold text-slate-600">{{ item.tax.tax_name }}</td>
                                <td class="px-4 py-4 text-right text-xs font-black text-indigo-600">{{ formatCurrency(item.price_total) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-indigo-50/20 font-black">
                            <tr>
                                <td colspan="5" class="px-4 py-2 text-right text-[9px] text-slate-400 uppercase tracking-[0.2em]">Untaxed Amount</td>
                                <td class="px-4 py-2 text-right text-xs text-slate-600">{{ formatCurrency(order.amount_untaxed) }}</td>
                            </tr>
                            <tr>
                                <td colspan="5" class="px-4 py-2 text-right text-[9px] text-slate-400 uppercase tracking-[0.2em]">Total Tax</td>
                                <td class="px-4 py-2 text-right text-xs text-slate-600">{{ formatCurrency(order.amount_tax) }}</td>
                            </tr>
                            <tr v-if="order.shipping_charges > 0">
                                <td colspan="5" class="px-4 py-2 text-right text-[9px] text-slate-400 uppercase tracking-[0.2em]">Shipping / Logistics</td>
                                <td class="px-4 py-2 text-right text-xs text-slate-600">{{ formatCurrency(order.shipping_charges) }}</td>
                            </tr>
                            <tr class="bg-indigo-50/50">
                                <td colspan="5" class="px-4 py-3 text-right text-[10px] text-indigo-500 uppercase tracking-[0.2em]">Grand Total</td>
                                <td class="px-4 py-3 text-right text-sm text-indigo-700 border-l border-indigo-100">{{ formatCurrency(order.amount_total) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Inward History -->
            <div v-if="order.items?.some(i => i.history?.length > 0)">
                <div class="flex items-center gap-2 mb-4 px-1">
                    <div class="w-1.5 h-4 bg-emerald-500 rounded-full"></div>
                    <h4 class="text-[10px] font-black text-slate-800 uppercase tracking-[0.15em]">Receipt History (Inwards)</h4>
                </div>
                
                <div class="space-y-2">
                    <div v-for="item in order.items.filter(i => i.history?.length > 0)" :key="'h-'+item.id" class="text-[10px]">
                        <div class="bg-slate-50 p-3 rounded border border-slate-100 flex items-center justify-between">
                            <span class="font-bold text-slate-700">{{ item.product?.title }}</span>
                            <div class="flex gap-4">
                                <div v-for="hist in item.history" :key="hist.id" class="flex items-center gap-2 bg-white px-2 py-1 rounded border border-slate-200 shadow-sm">
                                    <span class="text-slate-400 font-mono">{{ hist.inward_no }}</span>
                                    <span class="font-black text-emerald-600">+{{ Number(hist.received_qty) }} {{ hist.uom?.unit_code }}</span>
                                    <span class="text-slate-400">{{ formatDate(hist.received_date) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes & Extra -->
            <div v-if="order.notes" class="p-4 rounded border border-amber-100 bg-amber-50/30">
                <span class="text-[9px] font-black text-amber-600 uppercase tracking-widest mb-2 block">Special Instructions / Notes</span>
                <p class="text-xs text-amber-900 leading-relaxed">{{ order.notes }}</p>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-3 pt-2">
                <Button label="Close" icon="pi pi-times" @click="close" class="!text-[10px] !font-black !uppercase !tracking-widest !px-6" outlined severity="secondary" />
                <Button label="Print PO" icon="pi pi-print" @click="downloadPO" class="!text-[10px] !font-black !uppercase !tracking-widest !px-6" />
            </div>
        </template>
    </Dialog>
</template>

<style>

</style>
