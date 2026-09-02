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

// console.log('props.dispatch?.status?.invoice_status',props.dispatch?.status?.invoice_status);

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
    (e: 'generateInvoice'): void;
    (e: 'generateEInvoice'): void;
    (e: 'deleteInvoice'): void;
    (e: 'cancel'): void;
}>();

const getResolvedFormData = () => {
    const currentBatch = props.batch;
    const currentDispatch = props.dispatch || currentBatch?.dispatches?.[0];
    const currentSO = props.salesOrder || currentBatch?.sales_order || currentBatch?.salesOrder;

    const soIsInclusive = Boolean(
        currentSO?.is_tax_inclusive ??
        currentBatch?.is_tax_inclusive ??
        false
    );
console.log(currentBatch,'batch');
    const latestDispatch = currentSO?.latest_dispatch;

    if (currentDispatch && currentDispatch.id) {
        const isTaxInc = (currentDispatch.status?.is_tax_inclusive !== undefined && currentDispatch.status?.is_tax_inclusive !== null)
            ? Boolean(currentDispatch.status.is_tax_inclusive)
            : ((currentDispatch.is_tax_inclusive !== undefined && currentDispatch.is_tax_inclusive !== null)
                ? Boolean(currentDispatch.is_tax_inclusive)
                : soIsInclusive);

        const loadedWeight = currentDispatch.loaded_weight_truck || 0;
        const emptyWeight = currentDispatch.empty_weight_truck || 0;
        const netWeightUnits = loadedWeight ? Number((Number(loadedWeight) - Number(emptyWeight)).toFixed(3)) : (currentDispatch.delivered_qty || currentBatch?.batch_size || 0);

        return {
            id: currentDispatch.id,
            sales_order_id: currentDispatch.sales_order_id || currentSO?.id || currentBatch?.sales_order_id || null,
            batch_id: currentDispatch.batch_id || currentBatch?.id || null,
            batch_size: Number(currentBatch?.batch_size || currentDispatch.delivered_qty || 0),
            prefix: currentDispatch.prefix || '',
            dispatch_no: currentDispatch.dispatch_no || '',
            dispatch_reference: currentDispatch.dispatch_reference || '',
            dispatch_time: currentDispatch.dispatch_time ? new Date(currentDispatch.dispatch_time) : new Date(),
            delivered_qty: Number(currentDispatch.delivered_qty || currentBatch?.batch_size || 0),
            truck_id: currentDispatch.truck_id ? Number(currentDispatch.truck_id) : (currentBatch?.truck_id ? Number(currentBatch.truck_id) : null),
            transport_id: currentDispatch.transport_id ? Number(currentDispatch.transport_id) : (currentBatch?.transport_id ? Number(currentBatch.transport_id) : null),
            driver_id: currentDispatch.driver_id ? Number(currentDispatch.driver_id) : (currentBatch?.driver_id ? Number(currentBatch.driver_id) : null),
            operator_id: currentDispatch.operator_id ? Number(currentDispatch.operator_id) : (currentBatch?.operator_id ? Number(currentBatch.operator_id) : null),
            sales_executive_id: currentDispatch.sales_executive_id ? Number(currentDispatch.sales_executive_id) : (currentBatch?.sales_executive_id ? Number(currentBatch.sales_executive_id) : (currentSO?.sales_executive_id ? Number(currentSO.sales_executive_id) : null)),
            customer_id: currentDispatch.customer_id ? Number(currentDispatch.customer_id) : (currentBatch?.customer_id ? Number(currentBatch.customer_id) : (currentSO?.customer_id ? Number(currentSO.customer_id) : null)),
            mixdesign_id: currentDispatch.mixdesign_id ? Number(currentDispatch.mixdesign_id) : (currentBatch?.mix_design_id ? Number(currentBatch.mix_design_id) : (currentSO?.mix_design_id ? Number(currentSO.mix_design_id) : null)),
            uom_id: currentDispatch.uom_id || currentBatch?.uom_id || null,
            load_site_id: currentDispatch.load_site_id ? Number(currentDispatch.load_site_id) : (currentBatch?.load_site_id ? Number(currentBatch.load_site_id) : null),
            unload_site_id: currentDispatch.unload_site_id ? Number(currentDispatch.unload_site_id) : (currentBatch?.unload_site_id ? Number(currentBatch.unload_site_id) : (currentSO?.site_id ? Number(currentSO.site_id) : null)),
            payment_mode: currentDispatch.payment_mode || 'credit',
            dispatch_status: currentDispatch.dispatch_status || 'Draft',
            generate_invoice: true,
            ledger_id: currentDispatch.ledger_id || currentDispatch.status?.invoice?.account_id || null,
            invoice_date: currentDispatch.invoice_date ? new Date(currentDispatch.invoice_date) : (currentDispatch.status?.invoice_date ? new Date(currentDispatch.status.invoice_date) : new Date()),
            invoice_number: currentDispatch.status?.invoice?.invoice_number || currentDispatch.status?.invoice_number || '',
            invoice_notes: currentDispatch.status?.invoice?.notes || '',
            created_at: currentDispatch.created_at || null,
            updated_at: currentDispatch.updated_at || null,
            creator: currentDispatch.creator || null,
            modifier: currentDispatch.modifier || null,
            pump_charge_with_tax: Boolean(currentDispatch.pump_charge_with_tax),

            weights: {
                empty_weight_truck: Number(currentDispatch.empty_weight_truck || 0),
                loaded_weight_truck: Number(currentDispatch.loaded_weight_truck || 0),
                empty_weight_time_load: currentDispatch.empty_time ? new Date(currentDispatch.empty_time) : null,
                loaded_weight_time_load: currentDispatch.load_time ? new Date(currentDispatch.load_time) : null,
                empty_weight_unload: Number(currentDispatch.empty_weight_unload || 0),
                loaded_weight_unload: Number(currentDispatch.loaded_weight_unload || 0),
                empty_weight_time_unload: currentDispatch.empty_weight_time_unload ? new Date(currentDispatch.empty_weight_time_unload) : null,
                loaded_weight_time_unload: currentDispatch.loaded_weight_time_unload ? new Date(currentDispatch.loaded_weight_time_unload) : null,
                round_off: Number(currentDispatch.round_off || 0)
            },

            financials: {
                load_units: currentDispatch.load_units !== undefined ? Number(currentDispatch.load_units) : netWeightUnits,
                load_rate: currentDispatch.load_rate !== undefined ? Number(currentDispatch.load_rate) : Number(currentSO?.rate || currentBatch?.rate || 0),
                load_tax_id: currentDispatch.load_tax_id ? Number(currentDispatch.load_tax_id) : (currentSO?.tax_id || currentBatch?.tax_id || null),
                load_uom_id: currentDispatch.uom_id || currentBatch?.uom_id || null,
                unload_units: currentDispatch.unload_units !== undefined ? Number(currentDispatch.unload_units) : (currentBatch?.batch_size || 0),
                unload_rate: Number(currentDispatch.unload_rate || 0),
                unload_tax_id: currentDispatch.unload_tax_id || null,
                unload_uom_id: currentDispatch.unload_uom_id || currentBatch?.uom_id || null,
                transport_units: currentDispatch.transport_units !== undefined ? Number(currentDispatch.transport_units) : (currentBatch?.batch_size || 0),
                transport_rate: Number(currentDispatch.transport_rate || 0),
                transport_tax_id: currentDispatch.transport_tax_id || null,
                transport_uom_id: currentDispatch.transport_uom_id || currentBatch?.uom_id || null,
                load_tax_amount: Number(currentDispatch.load_tax_amount || 0),
                load_untax_amount: Number(currentDispatch.load_untax_amount || 0),
                load_total_amount: Number(currentDispatch.load_total_amount || 0),
                pump_charges: currentDispatch.pump_charges !== undefined ? Number(currentDispatch.pump_charges) : Number(currentSO?.pump_rate || 0),
                pass_amount: Number(currentDispatch.pass_amount || 0),
                discount_amount: Number(currentDispatch.discount_amount || 0),
                transport_expenses: Number(currentDispatch.transport_expenses || 0),
                adjustment_amount: Number(currentDispatch.adjustment_amount || 0),
                round_off: Number(currentDispatch.round_off || 0),
            },

            status: {
                is_tax_inclusive: isTaxInc,
                invoice_id: currentDispatch.status?.invoice_id || null,
                invoice_date: currentDispatch.status?.invoice_date || null,
                invoice_number: currentDispatch.status?.invoice_number || '',
                invoice_status: currentDispatch.status?.invoice_status || 0,
                invoice: currentDispatch.status?.invoice || null,
                transport_units: currentDispatch.status?.transport_units || currentBatch?.batch_size || 0,
                transport_rate: currentDispatch.status?.transport_rate || 0,
                transport_tax_id: currentDispatch.status?.transport_tax_id || null,
                transport_tax_amount: currentDispatch.status?.transport_tax_amount || 0,
                transport_total_amount: currentDispatch.status?.transport_total_amount || 0,
                total_amount: currentDispatch.status?.total_amount || 0,
                transport_reference: currentDispatch.status?.transport_reference || '',
                transport_km: currentDispatch.status?.transport_km || 0,
                receiver_name: currentDispatch.status?.receiver_name || '',
                receive_mobile: currentDispatch.status?.receive_mobile || '',
                note: currentDispatch.status?.note || '',
            },

            payment: {
                payment_method_id: currentDispatch.payments?.[0]?.payment_method_id ? Number(currentDispatch.payments[0].payment_method_id) : null,
                amount: currentDispatch.payments?.[0]?.amount || 0,
                collected_by: currentDispatch.payments?.[0]?.collected_by || '',
                reference: currentDispatch.payments?.[0]?.reference || ''
            },
            settings: props.settings || {}
        };
    }

    // Default Draft state from batch / SO
    const truckId = currentBatch?.truck_id ? Number(currentBatch.truck_id) 
        : (latestDispatch?.truck_id ? Number(latestDispatch.truck_id) : null);

    const driverId = currentBatch?.driver_id ? Number(currentBatch.driver_id) 
        : (latestDispatch?.driver_id ? Number(latestDispatch.driver_id) : null);

    const transportId = currentBatch?.transport_id ? Number(currentBatch.transport_id) 
        : (latestDispatch?.transport_id ? Number(latestDispatch.transport_id) 
           : (driverId ? (() => {
                 const driverObj = props.drivers?.find((d: any) => Number(d.id) === Number(driverId));
                 return driverObj?.transporter_id ? Number(driverObj.transporter_id) : null;
              })() : null));

    const salesExecId = currentBatch?.sales_executive_id ? Number(currentBatch.sales_executive_id)
        : (currentSO?.sales_executive_id ? Number(currentSO.sales_executive_id)
           : (currentSO?.customer_p_o?.sales_executive_id ? Number(currentSO.customer_p_o.sales_executive_id)
              : (currentSO?.customerPO?.sales_executive_id ? Number(currentSO.customerPO.sales_executive_id)
                 : (latestDispatch?.sales_executive_id ? Number(latestDispatch.sales_executive_id) : null))));

    const emptyWeightTruck = currentBatch?.dispatches?.[0]?.empty_weight_truck || 0;
    const loadedWeightTruck = currentBatch?.dispatches?.[0]?.loaded_weight_truck || 0;
    const netWeight = loadedWeightTruck ? Number((Number(loadedWeightTruck) - Number(emptyWeightTruck)).toFixed(3)) : Number(currentBatch?.batch_size || 0);

    return {
        id: null,
        sales_order_id: currentSO?.id || currentBatch?.sales_order_id || null,
        batch_id: currentBatch?.id || null,
        batch_size: Number(currentBatch?.batch_size || 0),
        prefix: '',
        dispatch_no: '',
        dispatch_reference: '',
        dispatch_time: new Date(),
        delivered_qty: Number(currentBatch?.batch_size || 0),
        truck_id: truckId,
        transport_id: transportId,
        driver_id: driverId,
        operator_id: currentBatch?.operator_id ? Number(currentBatch.operator_id) : (latestDispatch?.operator_id ? Number(latestDispatch.operator_id) : null),
        sales_executive_id: salesExecId,
        customer_id: currentBatch?.customer_id ? Number(currentBatch.customer_id) : (currentSO?.customer_id ? Number(currentSO.customer_id) : null),
        mixdesign_id: currentBatch?.mix_design_id ? Number(currentBatch.mix_design_id) : (currentSO?.mix_design_id ? Number(currentSO.mix_design_id) : null),
        uom_id: currentBatch?.uom_id ? Number(currentBatch.uom_id) : null,
        load_site_id: currentBatch?.load_site_id ? Number(currentBatch.load_site_id) : null,
        unload_site_id: currentBatch?.unload_site_id ? Number(currentBatch.unload_site_id) : (currentSO?.site_id ? Number(currentSO.site_id) : null),
        payment_mode: 'credit',
        dispatch_status: 'Draft',
        generate_invoice: false,
        ledger_id: null,
        invoice_date: new Date(),
        invoice_number: '',
        invoice_notes: '',
        created_at: null,
        updated_at: null,
        creator: null,
        modifier: null,

        weights: {
            empty_weight_truck: Number(emptyWeightTruck),
            loaded_weight_truck: Number(loadedWeightTruck),
            empty_weight_time_load: currentBatch?.dispatches?.[0]?.empty_time ? new Date(currentBatch.dispatches[0].empty_time) : null,
            loaded_weight_time_load: currentBatch?.dispatches?.[0]?.load_time ? new Date(currentBatch.dispatches[0].load_time) : null,
            empty_weight_unload: 0,
            loaded_weight_unload: 0,
            empty_weight_time_unload: null,
            loaded_weight_time_unload: null,
            round_off: 0
        },

        financials: {
            load_units: netWeight,
            load_rate: Number(currentBatch?.rate || currentSO?.rate || 0),
            load_tax_id: currentBatch?.tax_id || currentSO?.tax_id || null,
            load_uom_id: currentBatch?.uom_id || null,
            unload_units: Number(currentBatch?.batch_size || 0),
            unload_rate: 0,
            unload_tax_id: null,
            unload_uom_id: currentBatch?.uom_id || null,
            transport_units: Number(currentBatch?.batch_size || 0),
            transport_rate: 0,
            transport_tax_id: null,
            transport_uom_id: currentBatch?.uom_id || null,
            load_tax_amount: 0,
            load_untax_amount: 0,
            load_total_amount: 0,
            pump_charges: Number(currentSO?.pump_rate || 0),
            pass_amount: 0,
            discount_amount: 0,
            transport_expenses: 0,
            adjustment_amount: 0,
            round_off: 0,
        },

        status: {
            is_tax_inclusive: soIsInclusive,
            invoice_id: null,
            invoice_date: null,
            invoice_number: '',
            invoice_status: 0,
            invoice: null,
            transport_units: Number(currentBatch?.batch_size || 0),
            transport_rate: 0,
            transport_tax_id: null,
            transport_tax_amount: 0,
            transport_total_amount: 0,
            total_amount: 0,
            transport_reference: '',
            transport_km: 0,
            receiver_name: '',
            receive_mobile: '',
            note: '',
        },

        payment: {
            payment_method_id: null,
            amount: 0,
            collected_by: '',
            reference: ''
        },
        settings: props.settings || {}
    };
};

const form = useForm(getResolvedFormData());

onMounted(async () => {
    try {
        const response = await axios.get(route('dispatches.dropdowns'));
        if (response.data.prefix && !form.prefix) {
            form.prefix = response.data.prefix;
        }
        if (response.data.nextDispatchNo && !form.dispatch_no) {
            form.dispatch_no = response.data.nextDispatchNo;
        }
    } catch (error) {
        console.error('Failed to fetch next dispatch number:', error);
    }
});

const syncDispatchData = () => {
    const data = getResolvedFormData();
    for (const key in data) {
        // Do not wipe user-entered pending invoice values if invoice is not generated yet
        if (['invoice_number', 'invoice_notes'].includes(key) && form.status?.invoice_status !== 1 && form[key]) {
            continue;
        }
        if (data[key] && typeof data[key] === 'object' && !Array.isArray(data[key]) && !(data[key] instanceof Date)) {
            if (!form[key]) form[key] = {};
            Object.assign(form[key], data[key]);
        } else {
            form[key] = data[key];
        }
    }
};

// Unified watcher for syncing dispatch data when batch, dispatch, or salesOrder changes/expands
watch([() => props.batch, () => props.dispatch, () => props.salesOrder], () => {
    syncDispatchData();
}, { deep: true });

watch(() => form.driver_id, (newDriverId) => {
    if (newDriverId) {
        const driverObj = props.drivers?.find((d: any) => Number(d.id) === Number(newDriverId));
        if (driverObj?.transporter_id) {
            form.transport_id = Number(driverObj.transporter_id);
        }
    }
});

const displayUnits = computed(() => {
    return form.batch_size;
});

const pumpRate = computed(() => Number(props.salesOrder?.pump_rate || props.batch?.pump_rate || 0));
const baseRate = computed(() => Number(props.salesOrder?.rate || props.batch?.rate || 0));
const baseTaxId = computed(() => props.salesOrder?.tax_id || props.batch?.tax_id || null);

watch([
    () => form.batch_size, 
    () => form.financials.load_rate, 
    () => form.financials.load_tax_id, 
    () => form.status.is_tax_inclusive, 
    () => form.pump_charge_with_tax, 
    () => form.financials.discount_amount, 
    () => form.financials.pass_amount, 
    () => form.financials.round_off, 
    () => form.financials.adjustment_amount, 
    () => form.financials.transport_expenses, 
    () => form.financials.pump_charges, 
    () => baseRate.value,
    () => baseTaxId.value
], () => {
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
    const tax = props.dropdownData?.taxes?.find((t: any) => t.id === form.financials.load_tax_id);
    const taxRate = tax ? Number(tax.tax_rate || tax.rate || 0) : 0;
    const isInclusive = Boolean(form.status?.is_tax_inclusive);

    let materialUntax = 0;
    let materialTax = 0;

    if (isInclusive) {
        // TAX INCLUSIVE: load_rate includes tax
        const grossMaterial = units * loadRate;
        materialUntax = taxRate > 0 ? (grossMaterial * 100) / (100 + taxRate) : grossMaterial;
        materialTax = grossMaterial - materialUntax;
    } else {
        // TAX EXCLUSIVE: load_rate excludes tax
        materialUntax = units * loadRate;
        materialTax = (materialUntax * taxRate) / 100;
    }

    if (form.financials.pump_charges === null || form.financials.pump_charges === undefined) {
        if (pumpRate.value) {
            form.financials.pump_charges = Number((pumpRate.value).toFixed(2));
        }
    }

    const pumpCharge = Number(form.financials.pump_charges || 0);
    const pumpWithTax = Boolean(form.pump_charge_with_tax);

    let pumpUntax = 0;
    let pumpTax = 0;

    if (pumpWithTax) {
        pumpUntax = taxRate > 0 ? (pumpCharge * 100) / (100 + taxRate) : pumpCharge;
        pumpTax = pumpCharge - pumpUntax;
    } else {
        pumpUntax = pumpCharge;
        pumpTax = 0;
    }

    const totalUntax = materialUntax + pumpUntax;
    const totalTax = materialTax + pumpTax;

    const totalAmountVal = totalUntax + totalTax
        - Number(form.financials.discount_amount || 0)
        + Number(form.financials.pass_amount || 0)
        + Number(form.financials.round_off || 0)
        + Number(form.financials.adjustment_amount || 0)
        + Number(form.financials.transport_expenses || 0);

    form.financials.load_untax_amount = Number(totalUntax.toFixed(2));
    form.financials.load_tax_amount = Number(totalTax.toFixed(2));
    form.financials.load_total_amount = Number(totalAmountVal.toFixed(2));
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

const handleGenerateInvoice = (payload?: any) => {
    if (!form.id) {
        Swal.fire({
            icon: 'warning',
            title: 'Action Required',
            text: 'Please save the dispatch record first before generating an invoice.',
            confirmButtonColor: '#4f46e5'
        });
        return;
    }

    const ledgerId = payload?.ledger_id ?? form.ledger_id;
    const invoiceDate = payload?.invoice_date ?? form.invoice_date;
    const invoiceNumber = (payload?.invoice_number !== undefined && payload?.invoice_number !== null && payload?.invoice_number !== '')
        ? payload.invoice_number 
        : form.invoice_number;
    const invoiceNotes = payload?.invoice_notes ?? payload?.notes ?? form.invoice_notes;

    form.clearErrors();
    router.post(route('dispatches.generate-invoice', form.id), {
        ledger_id: ledgerId,
        invoice_date: invoiceDate,
        invoice_number: invoiceNumber,
        notes: invoiceNotes,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            form.clearErrors();
            if (props.onSaved) {
                props.onSaved({ batchId: props.batch.id, type: 'dispatch' });
            } else {
                emit('saved', { batchId: props.batch.id, type: 'dispatch' });
            }
            emit('generateInvoice', { batchId: props.batch.id, type: 'dispatch' });
        },
        onError: (errors: any) => {
            if (typeof form.setError === 'function') {
                for (const key in errors) {
                    form.setError(key as any, errors[key]);
                }
            }
            const firstError = errors.invoice_number || errors.error || Object.values(errors)[0] || 'Failed to generate invoice.';
            Swal.fire({
                icon: 'error',
                title: 'Invoice Generation Failed',
                text: String(firstError),
                confirmButtonColor: '#d33'
            });
        }
    });
};

const handleGenerateEInvoice = (passedInvoiceIdOrObj?: any) => {
    let invoiceId: number | string | null = null;
    let invoiceNumber = '';

    if (typeof passedInvoiceIdOrObj === 'number' || typeof passedInvoiceIdOrObj === 'string') {
        invoiceId = passedInvoiceIdOrObj;
        invoiceNumber = form.status?.invoice?.full_number || form.status?.invoice?.invoice_number || `#${invoiceId}`;
    } else if (passedInvoiceIdOrObj && typeof passedInvoiceIdOrObj === 'object') {
        invoiceId = passedInvoiceIdOrObj.id;
        invoiceNumber = passedInvoiceIdOrObj.full_number || passedInvoiceIdOrObj.invoice_number || `#${invoiceId}`;
    } else if (form.status?.invoice?.id) {
        invoiceId = form.status.invoice.id;
        invoiceNumber = form.status.invoice.full_number || form.status.invoice.invoice_number || `#${invoiceId}`;
    }

    if (!invoiceId) {
        Swal.fire({
            icon: 'warning',
            title: 'Action Required',
            text: 'Please generate the invoice first before generating an E-Invoice.',
            confirmButtonColor: '#4f46e5'
        });
        return;
    }

    Swal.fire({
        title: 'Generate E-Invoice',
        text: `Are you sure you want to generate E-Invoice IRN for Invoice ${invoiceNumber}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#7c3aed',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Generate IRN'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('invoices.generate-einvoice', invoiceId), {
                invoice_id: invoiceId,
                form: {
                    id: invoiceId,
                    dispatch_id: form.id,
                }
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'E-Invoice IRN generated successfully.',
                        showConfirmButton: false,
                        timer: 2000
                    });
                    if (props.onSaved) {
                        props.onSaved({ batchId: props.batch.id, type: 'dispatch' });
                    } else {
                        emit('saved', { batchId: props.batch.id, type: 'dispatch' });
                    }
                },
                onError: (errors: any) => {
                    const msg = errors.error || errors.message || Object.values(errors)[0] || 'Failed to generate E-Invoice.';
                    Swal.fire({
                        icon: 'error',
                        confirmButtonColor: '#d33'
                    });
                }
            });
        }
    });
};

const handleGenerateEwayBill = () => {
    const invoiceId = form.status?.invoice_id || form.status?.invoice?.id;
    if (!invoiceId) {
        Swal.fire({
            icon: 'warning',
            title: 'Action Required',
            text: 'Please generate the invoice first before generating an E-Way Bill.',
            confirmButtonColor: '#4f46e5'
        });
        return;
    }

    const defaultVehNo = props.batch?.truck_registration 
        || form.truck_id 
        || '';
    const defaultDistance = form.status?.transport_km || 20;

    Swal.fire({
        title: 'Generate E-Way Bill',
        html: `
            <div class="text-left space-y-3">
                <p class="text-xs text-slate-500 mb-2">
                    Generate a standard E-Way Bill directly without requiring an E-Invoice (IRN).
                </p>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Vehicle Number *</label>
                    <input id="swal-dispatch-ewb-veh" type="text" value="${defaultVehNo}" placeholder="e.g. TN09AB1234" class="w-full px-3 py-2 border rounded-md text-sm uppercase dark:bg-slate-700 dark:border-slate-600 dark:text-white" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Distance (in KM)</label>
                    <input id="swal-dispatch-ewb-dist" type="number" min="1" value="${defaultDistance}" placeholder="e.g. 25" class="w-full px-3 py-2 border rounded-md text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white" />
                </div>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Generate E-Way Bill',
        confirmButtonColor: '#0d9488',
        cancelButtonColor: '#64748b',
        preConfirm: () => {
            const vehNo = (document.getElementById('swal-dispatch-ewb-veh') as HTMLInputElement)?.value?.trim();
            const distance = (document.getElementById('swal-dispatch-ewb-dist') as HTMLInputElement)?.value?.trim();
            if (!vehNo) {
                Swal.showValidationMessage('Please enter a vehicle number');
                return false;
            }
            return { vehNo, distance: Number(distance) || 20 };
        },
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            router.post(route('batches.generate-ewaybill', props.batch.id), {
                veh_no: result.value.vehNo,
                distance: result.value.distance,
                invoice_id: invoiceId,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'E-Way Bill generated successfully.',
                        showConfirmButton: false,
                        timer: 2000
                    });
                    if (props.onSaved) {
                        props.onSaved({ batchId: props.batch.id, type: 'dispatch' });
                    } else {
                        emit('saved', { batchId: props.batch.id, type: 'dispatch' });
                    }
                },
                onError: (errors: any) => {
                    const msg = errors.error || errors.message || Object.values(errors)[0] || 'Failed to generate E-Way Bill.';
                    Swal.fire({
                        icon: 'error',
                        title: 'E-Way Bill Failed',
                        text: String(msg),
                        confirmButtonColor: '#d33'
                    });
                }
            });
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
                @submit="submit"
                @generateInvoice="handleGenerateInvoice"
                @generateEInvoice="handleGenerateEInvoice"
                @generateEwayBill="handleGenerateEwayBill"
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
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Gross Amount</span>
                                        <span v-if="form.status?.is_tax_inclusive" class="text-[9px] font-bold text-emerald-600 uppercase bg-emerald-50 px-1 py-0.5 rounded border border-emerald-200">Inc.</span>
                                    </div>
                                    <span class="text-xs font-black text-slate-700">₹ {{ form.financials.load_untax_amount.toLocaleString(undefined, {minimumFractionDigits: 2}) }}</span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Tax</span>
                                    <span class="text-xs font-black text-slate-700">₹ {{ form.financials.load_tax_amount.toFixed(2) }}</span>
                                </div>

                                <!-- Adjustments -->
                                <div class="pt-2 space-y-2">
                                    <div class="flex items-center justify-between" ><!-- v-if="form.financials.discount_amount" -->
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-rose-400">Discount</span>
                                        <span class="text-xs font-bold text-rose-500">- ₹ {{ Number(form.financials.discount_amount || 0).toFixed(2) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between" ><!-- v-if="form.financials.pump_charges" -->
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Pump Charge</span>
                                        <span class="text-xs font-bold text-slate-600">₹ {{ Number(form.financials.pump_charges || 0).toFixed(2) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between" ><!-- v-if="form.financials.discount_amount" -->
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Adjustment</span>
                                        <span class="text-xs font-bold text-gray-700">₹ {{ Number(form.financials.adjustment_amount || 0).toFixed(2) }}</span>
                                    </div>

                                    <div class="flex items-center justify-between" ><!-- v-if="form.financials.transport_expenses" -->
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Hire Charge</span>
                                        <span class="text-xs font-bold text-slate-600">₹ {{ Number(form.financials.transport_expenses || 0).toFixed(2) }}</span>
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
                                        <span class="text-xs font-bold text-slate-600">₹ {{ Number(form.financials.pass_amount || 0).toFixed(2) }}</span>
                                    </div>
                                    
                                    <div class="flex items-center justify-between" ><!-- v-if="form.financials.discount_amount" -->
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600">Round</span>
                                        <span class="text-xs font-bold text-gray-700">₹ {{ Number(form.financials.round_off || 0).toFixed(2) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Total -->
                            <div class="bg-slate-50 -mx-4 px-4 py-3 border-t border-b border-slate-100 flex items-center justify-between">
                                <span class="text-xs font-black uppercase tracking-[0.2em] text-indigo-500">Total Amount</span>
                                <span class="text-xl font-black text-slate-900 tracking-tighter">₹ {{ Number(form.financials.load_total_amount || 0).toFixed(2) }}</span>
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
