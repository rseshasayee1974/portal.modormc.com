<script setup lang="ts">
import { ref, onMounted, watch, onUnmounted, onUpdated } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DispatchWeightsForm from './DispatchWeightsForm.vue';
// import DispatchTransportForm from './DispatchTransportForm.vue';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import { PaperAirplaneIcon, ReceiptPercentIcon, CalculatorIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { usePermissions } from '@/Composables/usePermissions';

declare const route: any;

onUnmounted(() => {
    console.log('DispatchSection: onUnmounted called');
});

onUpdated(() => {
    console.log('DispatchSection: onUpdated called');
});

const props = defineProps<{
    batch: any;
    salesOrder: any;
    sales_executives:any;
    drivers:any;
    operators?:any;
    
    dropdownData: {
        trucks: any[];
        transporters: any[];
        loading_sites: any[];
        unloading_sites: any[];
        personnel: any[];
        operators?: any[];
        taxes: any[];
        uoms: any[];
        payment_methods: any[];
        sales_ledgers: any[];
    };
    settings?: any;
    dispatch?: any;
    onSaved?: (payload?: { batchId: number, type: 'batching' | 'dispatch' }) => void;
}>();

const { isAdmin, isSuperAdmin, can } = usePermissions();

const canExportInvoice = computed(() => isAdmin.value);

const hasEditBypass = computed(() => isAdmin || isSuperAdmin);
const hasDispatchActivity = computed(() => {
    if (!props.dispatch) return false;

    return (
        Number(props.dispatch.load_rate) > 0 ||
        props.dispatch.status?.invoice_status === 1 ||
        (props.dispatch.dispatch_status &&
            props.dispatch.dispatch_status !== 'Draft') ||
        (props.dispatch.payments?.length ?? 0) > 0
    );
});

const isReadOnly = computed(() => {
    if(props.dispatch?.status?.invoice_status === 1) return true;
});

const showInvoiceSection = computed(() => {
    if (!props.dispatch || !props.dispatch.id) return false;
    // Always show if invoice is already linked/generated
    if (props.dispatch.status?.invoice_status === 1) return true;
    
    // Otherwise, show only if pricing and quantities have data and are saved in the db
    return (Number(props.dispatch.load_rate) > 0) && 
           (Number(props.dispatch.delivered_qty || props.dispatch.load_units || 0) > 0) &&
           (props.dispatch.uom_id !== null && props.dispatch.uom_id !== undefined);
});


const emit = defineEmits<{
    (e: 'tripSaved'): void;
    (e: 'saved', payload?: { batchId: number, type: 'batching' | 'dispatch' }): void;
    (e: 'cancel'): void;
}>();

const form = useForm({
    id: props.dispatch?.id || null,
    sales_order_id: props.dispatch?.sales_order_id || props.salesOrder?.id,
    batch_id: props.dispatch?.batch_id || props.batch?.id,
    batch_size: props.dispatch?.batch_size || props.batch?.batch_size || 0,
    prefix: props.dispatch?.prefix || '',
    dispatch_no: props.dispatch?.dispatch_no || '',
    dispatch_reference: props.dispatch?.dispatch_reference || '',
    dispatch_time: props.dispatch?.dispatch_time ? new Date(props.dispatch.dispatch_time) : new Date(),
    delivered_qty: props.dispatch?.delivered_qty || props.batch?.batch_size || 0,
    truck_id: (() => {
        const direct = props.dispatch?.truck_id || props.batch?.truck_id;
        if (direct) return Number(direct);
        return props.salesOrder?.latest_dispatch?.truck_id ? Number(props.salesOrder.latest_dispatch.truck_id) : null;
    })(),
    transport_id: (() => {
        const direct = props.dispatch?.transport_id || props.batch?.transport_id;
        if (direct) return Number(direct);
        if (props.salesOrder?.latest_dispatch?.transport_id) return Number(props.salesOrder.latest_dispatch.transport_id);
        const drId = props.dispatch?.driver_id || props.batch?.driver_id || props.salesOrder?.latest_dispatch?.driver_id;
        if (drId) {
            const driverObj = props.drivers?.find((d: any) => Number(d.id) === Number(drId));
            if (driverObj?.transporter_id) return Number(driverObj.transporter_id);
        }
        return null;
    })(),
    customer_id: (props.dispatch?.customer_id || props.batch?.customer_id) ? Number(props.dispatch?.customer_id || props.batch?.customer_id) : null,
    mixdesign_id: (props.dispatch?.mixdesign_id || props.batch?.mix_design_id) ? Number(props.dispatch?.mixdesign_id || props.batch?.mix_design_id) : null,
    uom_id: (props.dispatch?.uom_id || props.batch?.uom_id) ? Number(props.dispatch?.uom_id || props.batch?.uom_id) : null,
    load_site_id: (props.dispatch?.load_site_id || props.batch?.load_site_id) ? Number(props.dispatch?.load_site_id || props.batch?.load_site_id) : null,
    unload_site_id: (props.dispatch?.unload_site_id || props.batch?.unload_site_id) ? Number(props.dispatch?.unload_site_id || props.batch?.unload_site_id) : null,
    driver_id: (() => {
        const direct = props.dispatch?.driver_id || props.batch?.driver_id;
        if (direct) return Number(direct);
        return props.salesOrder?.latest_dispatch?.driver_id ? Number(props.salesOrder.latest_dispatch.driver_id) : null;
    })(),
    operator_id: (() => {
        const direct = props.dispatch?.operator_id || props.batch?.operator_id;
        if (direct) return Number(direct);
        return props.salesOrder?.latest_dispatch?.operator_id ? Number(props.salesOrder.latest_dispatch.operator_id) : null;
    })(),
    sales_executive_id: (() => {
        const direct = props.dispatch?.sales_executive_id || props.batch?.sales_executive_id;
        if (direct) return Number(direct);
        const val = props.salesOrder?.sales_executive_id
            || props.salesOrder?.customer_p_o?.sales_executive_id
            || props.salesOrder?.customer_p_o?.quotation?.sales_executive_id
            || props.salesOrder?.latest_dispatch?.sales_executive_id
            || null;
        return val ? Number(val) : null;
    })(),
    payment_mode: props.dispatch?.payment_mode || 'credit',
    dispatch_status: props.dispatch?.dispatch_status || 'Draft',
    generate_invoice: false,
    ledger_id: (props.dispatch?.ledger_id || props.dispatch?.status?.invoice?.account_id) ? Number(props.dispatch?.ledger_id || props.dispatch?.status?.invoice?.account_id) : null,
    invoice_date: props.dispatch?.invoice_date ? new Date(props.dispatch.invoice_date) : (props.dispatch?.status?.invoice_date ? new Date(props.dispatch.status.invoice_date) : new Date()),
    created_at: props.dispatch?.created_at || null,
    updated_at: props.dispatch?.updated_at || null,
    creator: props.dispatch?.creator || null,
    modifier: props.dispatch?.modifier || null,

    weights: {
        empty_weight_truck: props.dispatch?.empty_weight_truck || props.batch?.dispatches?.[0]?.empty_weight_truck || 0,
        loaded_weight_truck: props.dispatch?.loaded_weight_truck || props.batch?.dispatches?.[0]?.loaded_weight_truck || 0,
        empty_weight_time_load: props.dispatch?.empty_time ? new Date(props.dispatch.empty_time) : (props.batch?.dispatches?.[0]?.empty_time ? new Date(props.batch.dispatches[0].empty_time) : null),
        loaded_weight_time_load: props.dispatch?.load_time ? new Date(props.dispatch.load_time) : (props.batch?.dispatches?.[0]?.load_time ? new Date(props.batch.dispatches[0].load_time) : null),
        empty_weight_unload: props.dispatch?.empty_weight_unload || 0,
        loaded_weight_unload: props.dispatch?.loaded_weight_unload || 0,
        empty_weight_time_unload: props.dispatch?.empty_weight_time_unload ? new Date(props.dispatch.empty_weight_time_unload) : null,
        loaded_weight_time_unload: props.dispatch?.loaded_weight_time_unload ? new Date(props.dispatch.loaded_weight_time_unload) : null,
        round_off: props.dispatch?.round_off || 0
    },

    financials: {
        load_units: props.dispatch?.load_units || ((props.dispatch?.loaded_weight_truck || props.batch?.dispatches?.[0]?.loaded_weight_truck) ? Number((Number(props.dispatch?.loaded_weight_truck || props.batch?.dispatches?.[0]?.loaded_weight_truck) - Number(props.dispatch?.empty_weight_truck || props.batch?.dispatches?.[0]?.empty_weight_truck || 0)).toFixed(3)) : (props.batch?.batch_size || 0)),
        load_rate: props.dispatch?.load_rate || Number(props.batch?.rate || props.salesOrder?.rate || 0),
        load_tax_id: props.dispatch?.load_tax_id ? Number(props.dispatch.load_tax_id) : (props.batch?.tax_id || props.salesOrder?.tax_id || null),
        load_uom_id: props.dispatch?.load_uom_id || props.batch?.uom_id,
        unload_units: props.dispatch?.unload_units || props.batch?.batch_size || 0,
        unload_rate: props.dispatch?.unload_rate || 0,
        unload_tax_id: props.dispatch?.unload_tax_id || null,
        unload_uom_id: props.dispatch?.unload_uom_id || props.batch?.uom_id,
        transport_units: props.dispatch?.transport_units || props.batch?.batch_size || 0,
        transport_rate: props.dispatch?.transport_rate || 0,
        transport_tax_id: props.dispatch?.transport_tax_id || null,
        transport_uom_id: props.dispatch?.transport_uom_id || props.batch?.uom_id,
        load_tax_amount: props.dispatch?.load_tax_amount || 0,
        load_untax_amount: props.dispatch?.load_untax_amount || 0,
        load_total_amount: props.dispatch?.load_total_amount || 0,
        pump_charges: props.dispatch?.pump_charges || 0,
        pass_amount: props.dispatch?.pass_amount || 0,
        discount_amount: props.dispatch?.discount_amount || 0,
        transport_expenses: props.dispatch?.transport_expenses || 0,
        adjustment_amount: props.dispatch?.adjustment_amount || 0,
        round_off: props.dispatch?.round_off || 0,
    },

    status: {
        is_tax_inclusive: props.dispatch?.status?.is_tax_inclusive || false,
        invoice_id: props.dispatch?.status?.invoice_id || null,
        invoice_date: props.dispatch?.status?.invoice_date || null,
        invoice_number: props.dispatch?.status?.invoice_number || '',
        invoice_status: props.dispatch?.status?.invoice_status || 0,
        invoice: props.dispatch?.status?.invoice || null,
        transport_units: props.dispatch?.status?.transport_units || props.batch?.batch_size || 0,
        transport_rate: props.dispatch?.status?.transport_rate || 0,
        transport_tax_id: props.dispatch?.status?.transport_tax_id || null,
        transport_tax_amount: props.dispatch?.status?.transport_tax_amount || 0,
        transport_total_amount: props.dispatch?.status?.transport_total_amount || 0,
        total_amount: props.dispatch?.status?.total_amount || 0,
        transport_reference: props.dispatch?.status?.transport_reference || '',
        transport_km: props.dispatch?.status?.transport_km || 0,
        receiver_name: props.dispatch?.status?.receiver_name || '',
        receive_mobile: props.dispatch?.status?.receive_mobile || '',
        note: props.dispatch?.status?.note || '',
    },
    payment: {
        payment_method_id: props.dispatch?.payments?.[0]?.payment_method_id ? Number(props.dispatch.payments[0].payment_method_id) : null,
        amount: props.dispatch?.payments?.[0]?.amount || 0,
        collected_by: props.dispatch?.payments?.[0]?.collected_by || '',
        reference: props.dispatch?.payments?.[0]?.reference || ''
    },
    settings: props.settings || {}
});
// console.log('form',props);

onMounted(async () => {
    try {
        const response = await axios.get(route('dispatches.dropdowns'));
        if (response.data.prefix) {
            form.prefix = response.data.prefix;
        }
        if (response.data.nextDispatchNo && !form.dispatch_no) {
            form.dispatch_no = response.data.nextDispatchNo;
        }
    } catch (error) {
        console.error('Failed to fetch next dispatch number:', error);
    }
});

// Watch for changes in props.batch (important for syncing batch weights and details to new dispatches)
watch(() => props.batch, (newBatch) => {
    if (newBatch && !form.id) {
        form.sales_order_id = props.salesOrder?.id || newBatch.sales_order_id;
        form.batch_id = newBatch.id;
        form.batch_size = newBatch.batch_size || 0;
        form.delivered_qty = newBatch.batch_size || 0;

        const latestDispatch = props.salesOrder?.latest_dispatch;

        form.truck_id = newBatch.truck_id ? Number(newBatch.truck_id) 
            : (latestDispatch?.truck_id ? Number(latestDispatch.truck_id) : form.truck_id);

        form.driver_id = newBatch.driver_id ? Number(newBatch.driver_id) 
            : (latestDispatch?.driver_id ? Number(latestDispatch.driver_id) : form.driver_id);

        form.transport_id = newBatch.transport_id ? Number(newBatch.transport_id) 
            : (latestDispatch?.transport_id ? Number(latestDispatch.transport_id) 
               : (form.driver_id ? (() => {
                     const driverObj = props.drivers?.find((d: any) => Number(d.id) === Number(form.driver_id));
                     return driverObj?.transporter_id ? Number(driverObj.transporter_id) : form.transport_id;
                  })() : form.transport_id));

        form.customer_id = newBatch.customer_id ? Number(newBatch.customer_id) : form.customer_id;
        form.mixdesign_id = newBatch.mix_design_id ? Number(newBatch.mix_design_id) : form.mixdesign_id;
        form.uom_id = newBatch.uom_id ? Number(newBatch.uom_id) : form.uom_id;
        form.load_site_id = newBatch.load_site_id ? Number(newBatch.load_site_id) : form.load_site_id;
        form.unload_site_id = newBatch.unload_site_id ? Number(newBatch.unload_site_id) : form.unload_site_id;

        form.sales_executive_id = newBatch.sales_executive_id ? Number(newBatch.sales_executive_id)
            : (props.salesOrder?.sales_executive_id ? Number(props.salesOrder.sales_executive_id)
               : (props.salesOrder?.customer_p_o?.sales_executive_id ? Number(props.salesOrder.customer_p_o.sales_executive_id)
                  : (props.salesOrder?.customer_p_o?.quotation?.sales_executive_id ? Number(props.salesOrder.customer_p_o.quotation.sales_executive_id)
                     : (latestDispatch?.sales_executive_id ? Number(latestDispatch.sales_executive_id) : form.sales_executive_id))));
        form.weights.empty_weight_truck = newBatch.dispatches?.[0]?.empty_weight_truck || 0;
        form.weights.loaded_weight_truck = newBatch.dispatches?.[0]?.loaded_weight_truck || 0;
        form.weights.empty_weight_time_load = newBatch.dispatches?.[0]?.empty_time ? new Date(newBatch.dispatches[0].empty_time) : null;
        form.weights.loaded_weight_time_load = newBatch.dispatches?.[0]?.load_time ? new Date(newBatch.dispatches[0].load_time) : null;

        form.financials.load_units = newBatch.dispatches?.[0]?.loaded_weight_truck ? Number((Number(newBatch.dispatches[0].loaded_weight_truck) - Number(newBatch.dispatches[0].empty_weight_truck || 0)).toFixed(3)) : (newBatch.batch_size || 0);
        
        const tempNet = newBatch.dispatches?.[0]?.loaded_weight_truck ? (Number(newBatch.dispatches[0].loaded_weight_truck) - Number(newBatch.dispatches[0].empty_weight_truck || 0)) : 0;
        const bRate = Number(newBatch.rate || props.salesOrder?.rate || 0);
        form.financials.load_rate = bRate;
        form.financials.load_tax_id = newBatch.tax_id || props.salesOrder?.tax_id || null;

        form.financials.load_uom_id = newBatch.uom_id || form.financials.load_uom_id;
        form.financials.unload_units = newBatch.batch_size || 0;
        form.financials.unload_uom_id = newBatch.uom_id || form.financials.unload_uom_id;
        form.financials.transport_units = newBatch.batch_size || 0;
        form.financials.transport_uom_id = newBatch.uom_id || form.financials.transport_uom_id;
    }
}, { deep: true, immediate: true });

// Watch for changes in props.dispatch (important for async loading in expansion)
watch(() => props.dispatch, (newDispatch) => {
    if (newDispatch) {
        // console.log('Dispatch Updated:', newDispatch);
        // console.log('Invoice Status from Props:', newDispatch.status?.invoice_status);
        
        // Sync basic fields
        form.id = newDispatch.id || null;
        form.generate_invoice = !!newDispatch.id;
        form.prefix = newDispatch.prefix || form.prefix;
        form.dispatch_no = newDispatch.dispatch_no || form.dispatch_no;
        form.dispatch_reference = newDispatch.dispatch_reference || '';
        form.truck_id = newDispatch.truck_id || null;
        form.transport_id = newDispatch.transport_id || null;
        form.driver_id = newDispatch.driver_id || null;
        form.sales_executive_id = newDispatch.sales_executive_id || null;
        form.unload_site_id = newDispatch.unload_site_id || null;
        form.uom_id = newDispatch.uom_id || props.batch?.uom_id || null;
        form.delivered_qty = newDispatch.delivered_qty || 0;
        form.payment_mode = newDispatch.payment_mode || 'credit';
        form.ledger_id = newDispatch.ledger_id || newDispatch.status?.invoice?.account_id || null;
        form.invoice_date = newDispatch.invoice_date ? new Date(newDispatch.invoice_date) : (newDispatch.status?.invoice_date ? new Date(newDispatch.status.invoice_date) : (form.invoice_date || new Date()));
        form.created_at = newDispatch.created_at || null;
        form.updated_at = newDispatch.updated_at || null;
        form.creator = newDispatch.creator || null;
        form.modifier = newDispatch.modifier || null;
        
        // Sync Status nested object
        if (newDispatch.status) {
            form.status.is_tax_inclusive = !!newDispatch.status.is_tax_inclusive;
            form.status.invoice_id = newDispatch.status.invoice_id;
            form.status.invoice_date = newDispatch.status.invoice_date;
            form.status.invoice_number = newDispatch.status.invoice_number;
            form.status.invoice_status = newDispatch.status.invoice_status || 0;
            form.status.invoice = newDispatch.status.invoice;
            
            form.status.transport_units = newDispatch.status.transport_units;
            form.status.transport_rate = newDispatch.status.transport_rate;
            form.status.transport_tax_id = newDispatch.status.transport_tax_id;
            form.status.transport_tax_amount = newDispatch.status.transport_tax_amount;
            form.status.transport_total_amount = newDispatch.status.transport_total_amount;
            form.status.total_amount = newDispatch.status.total_amount;
            form.status.transport_reference = newDispatch.status.transport_reference;
            form.status.transport_km = newDispatch.status.transport_km;
            form.status.receiver_name = newDispatch.status.receiver_name;
            form.status.receive_mobile = newDispatch.status.receive_mobile;
            form.status.note = newDispatch.status.note;
        }

        // Sync Weights
        form.weights.empty_weight_truck = newDispatch.empty_weight_truck || props.batch?.dispatches?.[0]?.empty_weight_truck || 0;
        form.weights.loaded_weight_truck = newDispatch.loaded_weight_truck || props.batch?.dispatches?.[0]?.loaded_weight_truck || 0;
        form.weights.empty_weight_time_load = newDispatch.empty_time ? new Date(newDispatch.empty_time) : (props.batch?.dispatches?.[0]?.empty_time ? new Date(props.batch.dispatches[0].empty_time) : null);
        form.weights.loaded_weight_time_load = newDispatch.load_time ? new Date(newDispatch.load_time) : (props.batch?.dispatches?.[0]?.load_time ? new Date(props.batch.dispatches[0].load_time) : null);
        form.weights.empty_weight_unload = newDispatch.empty_weight_unload || 0;
        form.weights.loaded_weight_unload = newDispatch.loaded_weight_unload || 0;
        form.weights.empty_weight_time_unload = newDispatch.empty_weight_time_unload ? new Date(newDispatch.empty_weight_time_unload) : null;
        form.weights.loaded_weight_time_unload = newDispatch.loaded_weight_time_unload ? new Date(newDispatch.loaded_weight_time_unload) : null;
        form.weights.round_off = newDispatch.round_off || 0;

        // Sync Financials
        form.financials.load_units = newDispatch.load_units !== undefined ? newDispatch.load_units : ((newDispatch.loaded_weight_truck || props.batch?.dispatches?.[0]?.loaded_weight_truck) ? Number((Number(newDispatch.loaded_weight_truck || props.batch?.dispatches?.[0]?.loaded_weight_truck) - Number(newDispatch.empty_weight_truck || props.batch?.dispatches?.[0]?.empty_weight_truck || 0)).toFixed(3)) : (props.batch?.batch_size || 0));
        form.financials.load_rate = newDispatch.load_rate !== undefined ? newDispatch.load_rate : 0;
        form.financials.load_tax_id = newDispatch.load_tax_id || null;
        form.financials.load_uom_id = newDispatch.load_uom_id || props.batch?.uom_id;
        form.financials.unload_units = newDispatch.unload_units !== undefined ? newDispatch.unload_units : (props.batch?.batch_size || 0);
        form.financials.unload_rate = newDispatch.unload_rate !== undefined ? newDispatch.unload_rate : 0;
        form.financials.unload_tax_id = newDispatch.unload_tax_id || null;
        form.financials.unload_uom_id = newDispatch.unload_uom_id || props.batch?.uom_id;
        form.financials.transport_units = newDispatch.transport_units !== undefined ? newDispatch.transport_units : (props.batch?.batch_size || 0);
        form.financials.transport_rate = newDispatch.transport_rate !== undefined ? newDispatch.transport_rate : 0;
        form.financials.transport_tax_id = newDispatch.transport_tax_id || null;
        form.financials.transport_uom_id = newDispatch.transport_uom_id || props.batch?.uom_id;
        form.financials.load_tax_amount = newDispatch.load_tax_amount !== undefined ? newDispatch.load_tax_amount : 0;
        form.financials.load_untax_amount = newDispatch.load_untax_amount !== undefined ? newDispatch.load_untax_amount : 0;
        form.financials.load_total_amount = newDispatch.load_total_amount !== undefined ? newDispatch.load_total_amount : 0;
        form.financials.pump_charges = newDispatch.pump_charges !== undefined ? newDispatch.pump_charges : (form.financials.pump_charges || 0);
        form.financials.pass_amount = newDispatch.pass_amount !== undefined ? newDispatch.pass_amount : 0;
        form.financials.discount_amount = newDispatch.discount_amount !== undefined ? newDispatch.discount_amount : 0;
        form.financials.transport_expenses = newDispatch.transport_expenses !== undefined ? newDispatch.transport_expenses : 0;
        form.financials.adjustment_amount = newDispatch.adjustment_amount !== undefined ? newDispatch.adjustment_amount : 0;
        form.financials.round_off = newDispatch.round_off !== undefined ? newDispatch.round_off : 0;

        // Sync Payment
        if (newDispatch.payments?.length) {
            form.payment.payment_method_id = newDispatch.payments[0].payment_method_id;
            form.payment.amount = newDispatch.payments[0].amount;
            form.payment.collected_by = newDispatch.payments[0].collected_by;
            form.payment.reference = newDispatch.payments[0].reference;
        }
    }
}, { deep: true, immediate: true });

watch(() => form.driver_id, (newDriverId) => {
    if (newDriverId) {
        const driverObj = props.drivers?.find((d: any) => Number(d.id) === Number(newDriverId));
        if (driverObj?.transporter_id) {
            form.transport_id = Number(driverObj.transporter_id);
        }
    }
});

const baseRate = computed(() => {
    return Number(props.batch?.rate || props.salesOrder?.rate || 0);
});

const baseTaxId = computed(() => {
    return props.batch?.tax_id || props.salesOrder?.tax_id || null;
});

const displayUnits = computed(() => {
    return form.batch_size;
});

const pumpRate = computed(() => Number(props.salesOrder?.pump_rate || props.batch?.pump_rate || 0));

watch([() => form.batch_size, () => form.financials.load_rate, () => form.financials.load_tax_id, () => form.financials.discount_amount, () => form.financials.pass_amount, () => form.financials.round_off, () => form.financials.adjustment_amount, () => form.financials.transport_expenses, () => form.financials.pump_charges, baseRate], () => {
    if ((form.financials.load_rate === null || form.financials.load_rate === undefined || form.financials.load_rate === 0) && baseRate.value) {
        form.financials.load_rate = baseRate.value;
    }

    if (!form.financials.load_tax_id && baseTaxId.value) {
        form.financials.load_tax_id = baseTaxId.value;
    }

    const units = Number(form.batch_size || 0);
    form.delivered_qty = units;
    form.financials.load_units = units;

    // Calculate amounts
    const loadRate = Number(form.financials.load_rate || 0);
    const untaxAmount = units * loadRate;

    const tax = props.dropdownData.taxes.find(t => t.id === form.financials.load_tax_id);
    const taxRate = tax ? Number(tax.tax_rate || 0) : 0;
    const taxAmountVal = (untaxAmount * taxRate) / 100;

    if ((form.financials.pump_charges === null || form.financials.pump_charges === undefined || Number(form.financials.pump_charges) === 0) && pumpRate.value) {
        form.financials.pump_charges = Number((pumpRate.value).toFixed(2));
    }

    const pumpCharge = Number(form.financials.pump_charges || 0);

    const totalAmountVal = untaxAmount + taxAmountVal
        + pumpCharge
        - Number(form.financials.discount_amount || 0)
        + Number(form.financials.pass_amount || 0)
        + Number(form.financials.round_off || 0)
        + Number(form.financials.adjustment_amount || 0)
        + Number(form.financials.transport_expenses || 0);

    form.financials.load_untax_amount = untaxAmount;
    form.financials.load_tax_amount = taxAmountVal;
    form.financials.load_total_amount = totalAmountVal;
}, { immediate: true, deep: true });

// Auto-switch to cash if immediate payment is entered
watch(() => form.payment.amount, (newVal) => {
    if (Number(newVal) > 0) {
        form.payment_mode = 'cash';
    }
});

watch(() => form.payment.payment_method_id, (newVal) => {
    if (newVal) {
        form.payment_mode = 'cash';
    }
});

const selectedUom = computed(() => {
    const uom = props.dropdownData.uoms.find(u => u.id === form.uom_id);
    return uom ? uom.unit_code : 'UNIT';
});

const submit = () => {
    // console.log('DispatchSection: submit called! Trigger trace:');
    // console.trace();
    if (form.id) {
        form.put(route('dispatches.update', form.id), {
            preserveScroll: true,
            preserveState: true,
            onBefore: () => {
                console.log('DispatchSection: put request starting');
            },
            onSuccess: () => {
                console.log('DispatchSection: put onSuccess called');
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Dispatch updated successfully',
                    showConfirmButton: false,
                    timer: 1500
                });
                console.log('DispatchSection: invoking onSaved prop');
                if (props.onSaved) {
                    props.onSaved({ batchId: props.batch.id, type: 'dispatch' });
                } else {
                    emit('saved', { batchId: props.batch.id, type: 'dispatch' });
                }
            },
            onError: (errors) => {
                console.error('DispatchSection: put errors:', errors);
            },
            onFinish: () => {
                console.log('DispatchSection: put request finished');
            }
        });
    } else {
        console.log('DispatchSection: posting to dispatches.store');
        form.post(route('dispatches.store'), {
            preserveScroll: true,
            preserveState: true,
            onBefore: () => {
                console.log('DispatchSection: post request starting');
            },
            onSuccess: () => {
                console.log('DispatchSection: post onSuccess called');
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Dispatch created successfully',
                    showConfirmButton: false,
                    timer: 1500
                });
                console.log('DispatchSection: invoking onSaved prop');
                if (props.onSaved) {
                    props.onSaved({ batchId: props.batch.id, type: 'dispatch' });
                } else {
                    emit('saved', { batchId: props.batch.id, type: 'dispatch' });
                }
            },
            onError: (errors) => {
                console.error('DispatchSection: post errors:', errors);
            },
            onFinish: () => {
                console.log('DispatchSection: post request finished');
            }
        });
    }
};

const handleGenerateInvoice = () => {
    if (!form.id) {
        Swal.fire({
            icon: 'warning',
            title: 'Action Required',
            text: 'Please save the dispatch record first before generating an invoice.',
            confirmButtonColor: '#4f46e5'
        });
        return;
    }

    router.post(route('dispatches.generate-invoice', form.id), {
        ledger_id: form.ledger_id,
        invoice_date: form.invoice_date,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            if (props.onSaved) {
                props.onSaved({ batchId: props.batch.id, type: 'dispatch' });
            } else {
                emit('saved', { batchId: props.batch.id, type: 'dispatch' });
            }
        }
    });
};

const handleDeleteInvoice = () => {
    Swal.fire({
        title: 'Are you sure?',
        text: "This will delete the generated invoice and reset the dispatch billing status. This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('dispatches.delete-invoice', form.id), {
                preserveScroll: true,
                onSuccess: () => {
                    if (props.onSaved) {
                        props.onSaved({ batchId: props.batch.id, type: 'dispatch' });
                    } else {
                        emit('saved', { batchId: props.batch.id, type: 'dispatch' });
                    }
                }
            });
        }
    });
};
</script>

<template>
    <div class="grid grid-cols-12 gap-6">
        <!-- Left Side: Forms -->
        <div class="col-span-12 lg:col-span-8 space-y-2">
            <DispatchWeightsForm 
                v-model="form" 
                :uoms="dropdownData.uoms"
                :taxes="dropdownData.taxes"
                :loading_sites="dropdownData.loading_sites"
                :unloading_sites="dropdownData.unloading_sites"
                :trucks="dropdownData.trucks"
                :transporters="dropdownData.transporters"
                :personnel="dropdownData.personnel"
                :payment_methods="dropdownData.payment_methods"
                :sales_ledgers="dropdownData.sales_ledgers"
                :drivers="drivers"
                :operators="operators || dropdownData.operators"
                :sales_executives="sales_executives"
                :errors="form.errors"
                :isReadOnly="isReadOnly"
                :showInvoiceSection="showInvoiceSection"
                :add-pump-to-total="addPumpToTotal"
                @submit="submit"
                @generateInvoice="handleGenerateInvoice"
                @deleteInvoice="handleDeleteInvoice"
            />
 <!-- <hr class="border-slate-100" /> -->
             <!-- <DispatchTransportForm 
                v-model="form" 
                :trucks="dropdownData.trucks" 
                :transporters="dropdownData.transporters"
                :personnel="dropdownData.personnel"
                :errors="form.errors"
            /> -->
            </div>

            <!-- Right Side: Receipt Sidebar -->
            <div class="col-span-12 lg:col-span-4  relative  ">
                <div class="sticky top-6">
                    <div class="bg-white   shadow-amber-900/5 border border-amber-200/50 overflow-hidden">
                        <!-- Receipt Header -->
                        <div class="bg-amber-50/50 px-4 py-3 border-b border-amber-100 text-center">
                            <span class="text-[15px] font-black uppercase tracking-[0.2em] text-slate-800">Receipt Summary</span>
                        </div>

                        <div class="p-4 space-y-4">
                            <!-- Receipt # -->
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Batch Number</span>
                                <span class="text-sm font-black text-slate-800 tracking-tight">#{{ batch.batch_no || '---' }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Dispatch Number</span>
                                <span class="text-sm font-black text-indigo-600 tracking-tight">{{ form.prefix }}{{ form.dispatch_no || 'Draft' }}</span>
                            </div>

                            <div class="border-t border-dashed border-slate-200"></div>

                            <!-- Net Weight / Batch Size -->
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-widest text-slate-500">
                                    Batch Size
                                </span>
                                <div class="flex items-baseline gap-1">
                                    <span class="text-sm font-black text-indigo-600">{{ displayUnits }}</span>
                                    <span class="text-[10px] font-black text-indigo-300 uppercase">m³</span>
                                </div>
                            </div>

                            <!-- Amount Breakdown -->
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Gross Amount</span>
                                    <span class="text-xs font-black text-slate-700">₹ {{ form.financials.load_untax_amount.toLocaleString(undefined, {minimumFractionDigits: 2}) }}</span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Tax</span>
                                    <span class="text-xs font-black text-slate-700">₹ {{ form.financials.load_tax_amount.toLocaleString(undefined, {minimumFractionDigits: 2}) }}</span>
                                </div>

                                <!-- Adjustments -->
                                <div class="pt-2 space-y-2">
                                    <div class="flex items-center justify-between" ><!-- v-if="form.financials.discount_amount" -->
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-rose-400">Discount</span>
                                        <span class="text-xs font-bold text-rose-500">- ₹ {{ Number(form.financials.discount_amount || 0).toLocaleString() }}</span>
                                    </div>
                                    <div class="flex items-center justify-between" ><!-- v-if="form.financials.pump_charges" -->
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Pump Charge</span>
                                        <span class="text-xs font-bold text-slate-600">₹ {{ Number(form.financials.pump_charges || 0).toLocaleString() }}</span>
                                    </div>
                                    <div class="flex items-center justify-between" ><!-- v-if="form.financials.discount_amount" -->
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Adjustment</span>
                                        <span class="text-xs font-bold text-gray-700">₹ {{ Number(form.financials.adjustment_amount || 0).toLocaleString() }}</span>
                                    </div>

                                    <div class="flex items-center justify-between" ><!-- v-if="form.financials.transport_expenses" -->
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Hire Charge</span>
                                        <span class="text-xs font-bold text-slate-600">₹ {{ Number(form.financials.transport_expenses || 0).toLocaleString() }}</span>
                                    </div>
                                    <!-- <div v-if="pumpRate > 0" class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-amber-500">
                                            Pump Charge
                                            <span class="normal-case font-normal text-amber-400 ml-1">({{ addPumpToTotal ? 'flat' : 'per m³' }})</span>
                                        </span>
                                        <span class="text-xs font-bold text-amber-600">₹ {{ Number(form.financials.pump_charges || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</span>
                                    </div> -->
                                    <div class="flex items-center justify-between" ><!-- v-if="form.financials.pass_amount" -->
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Pass Amount</span>
                                        <span class="text-xs font-bold text-slate-600">₹ {{ Number(form.financials.pass_amount || 0).toLocaleString() }}</span>
                                    </div>
                                    
                                    <div class="flex items-center justify-between" ><!-- v-if="form.financials.discount_amount" -->
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600">Round</span>
                                        <span class="text-xs font-bold text-gray-700">₹ {{ Number(form.financials.round_off || 0).toLocaleString() }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Total -->
                            <div class="bg-slate-50 -mx-4 px-4 py-3 border-t border-b border-slate-100 flex items-center justify-between">
                                <span class="text-xs font-black uppercase tracking-[0.2em] text-indigo-500">Total Amount</span>
                                <span class="text-xl font-black text-slate-900 tracking-tighter">₹ {{ form.financials.load_total_amount.toLocaleString(undefined, {minimumFractionDigits: 0}) }}</span>
                            </div>

                            <!-- Action Buttons -->
                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <!-- <BaseButton 
                                    label="Cancel" 
                                    variant="outlined" 
                                    severity="contrast" 
                                    class="!py-3 !text-[10px] !border-slate-200 !font-black uppercase tracking-widest"
                                    @click="emit('cancel')"
                                /> -->
                                
                                <BaseButton 
                                    label="Save Dispatch" 
                                    variant="filled" 
                                    severity="primary" 
                                    class="!py-3 !text-[10px] !font-black uppercase tracking-widest shadow-lg shadow-indigo-200/50"
                                    :loading="form.processing"
                                    :disabled="isReadOnly"
                                    @click="submit"
                                />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Safety Note -->
                    <!-- <div class="mt-4 px-4 py-3 rounded-xl bg-amber-100/50 border border-amber-200/50 flex items-start gap-3">
                        <div class="mt-0.5 rounded-full bg-amber-500 p-1 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="text-[9px] font-bold text-amber-800 uppercase leading-normal">
                            Please verify all weights and rates before saving. Dispatches are final once processed.
                        </p>
                    </div> -->
                </div>
            </div>
        </div>
</template>

<style scoped>
:deep(.p-tabview-nav) {
    @apply border-b border-slate-100 bg-transparent;
}

:deep(.p-tabview-nav-link) {
    @apply !text-[10px] !font-black !uppercase !tracking-widest !text-slate-400 !border-b-2 !border-transparent !bg-transparent !py-4 !px-6 transition-all;
}

:deep(.p-tabview-selected .p-tabview-nav-link) {
    @apply !text-indigo-600 !border-indigo-600 !bg-transparent;
}

:deep(.p-tabview-panels) {
    @apply !p-0;
} 
</style> 
