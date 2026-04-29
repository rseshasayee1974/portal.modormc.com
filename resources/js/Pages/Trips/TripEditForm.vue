<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

// PrimeVue Imports
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import DatePicker from 'primevue/datepicker';
import Textarea from 'primevue/textarea';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';

const props = defineProps<{
    trip: any;
    options: any;
}>();

const emit = defineEmits(['updateSuccess', 'close']);

const activeTab = ref('0');

const toNumber = (value: unknown, fallback = 0) => {
    if (value === null || value === undefined || value === '') return fallback;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
};

const toDate = (value: any) => (value ? new Date(value) : null);

const createState = (trip: any) => ({
    reference_id: trip?.reference_id || '',
    truck_id: trip?.truck_id ?? null,
    party_id: trip?.party_id ?? null,
    vendor_id: trip?.vendor_id ?? null,
    load_site_id: trip?.load_site_id ?? null,
    unload_site_id: trip?.unload_site_id ?? null,
    product_id: trip?.product_id ?? null,
    driver_id: trip?.driver_id ?? null,
    cleaner_id: trip?.cleaner_id ?? null,
    maistry_id: trip?.maistry_id ?? null,
    loader_id: trip?.loader_id ?? null,
    operator_id: trip?.operator_id ?? null,
    payment_mode: trip?.payment_mode || 'credit',
    ui_party_type: trip?.ui_party_type || 'Credit',
    ui_product_unit: trip?.ui_product_unit || 'MT',
    ui_transport_unit: trip?.ui_transport_unit || 'MT',
    ui_note: trip?.ui_note || '',
    ui_gate_pass: trip?.ui_gate_pass || '',
    ui_food_token: trip?.ui_food_token || '',
    ui_dc_print: trip?.ui_dc_print || '',
    ui_topup_pad: toNumber(trip?.ui_topup_pad),
    ui_dummy_amount: toNumber(trip?.ui_dummy_amount),
    ui_driver_batta: toNumber(trip?.ui_driver_batta),
    ui_cleaner_batta: toNumber(trip?.ui_cleaner_batta),
    weights: {
        empty_weight_load: toNumber(trip?.weights?.empty_weight_load),
        loaded_weight_load: toNumber(trip?.weights?.loaded_weight_load),
        empty_weight_time: toDate(trip?.weights?.empty_weight_time),
        loaded_weight_time: toDate(trip?.weights?.loaded_weight_time),
        empty_weight_unload: toNumber(trip?.weights?.empty_weight_unload),
        loaded_weight_unload: toNumber(trip?.weights?.loaded_weight_unload),
        round_off: toNumber(trip?.weights?.round_off),
    },
    financials: {
        product_units: toNumber(trip?.financials?.product_units),
        product_amount: toNumber(trip?.financials?.product_amount),
        product_tax_id: trip?.financials?.product_tax_id ?? null,
        product_tax_amount: toNumber(trip?.financials?.product_tax_amount),
        transport_rate: toNumber(trip?.financials?.transport_rate),
        transport_expenses: toNumber(trip?.financials?.transport_expenses),
        pass_amount: toNumber(trip?.financials?.pass_amount),
        discount_amount: toNumber(trip?.financials?.discount_amount),
        round_off: toNumber(trip?.financials?.round_off),
    },
    status: {
        trip_status: trip?.status?.trip_status ?? 0,
        is_closed: Boolean(trip?.status?.is_closed),
        invoice_date: toDate(trip?.status?.invoice_date),
        purchase_date: toDate(trip?.status?.purchase_date),
        transport_date: toDate(trip?.status?.transport_date),
        invoice_number: trip?.status?.invoice_number || '',
    },
});

const form = useForm(createState(props.trip));

const formatToBackendDate = (date: any) => {
    if (!date) return null;
    const d = new Date(date);
    return isNaN(d.getTime()) ? null : d.toISOString().slice(0, 10);
};

const formatToBackendDateTime = (date: any) => {
    if (!date) return null;
    const d = new Date(date);
    return isNaN(d.getTime()) ? null : d.toISOString().slice(0, 19).replace('T', ' ');
};

watch(() => props.trip, (trip) => {
    if (trip) {
        const nextState = createState(trip);
        form.defaults(nextState);
        Object.assign(form, nextState);
    }
}, { immediate: true });

const taxOptions = computed(() => props.options?.taxes?.map((t: any) => ({ label: `${t.tax_name} (${t.tax_rate}%)`, value: t.id, rate: t.tax_rate })) ?? []);
const siteOptions = computed(() => props.options?.sites?.map((s: any) => ({ label: s.name, value: s.id })) ?? []);
const patronOptions = computed(() => props.options?.patrons?.map((p: any) => ({ label: p.legal_name, value: p.id })) ?? []);
const truckOptions = computed(() => props.options?.machines?.map((m: any) => ({ label: m.registration, value: m.id })) ?? []);
const productOptions = computed(() => props.options?.products?.map((p: any) => ({ label: p.title, value: p.id })) ?? []);
const personnelOptions = computed(() => props.options?.personnels?.map((p: any) => ({ label: `${p.first_name} ${p.last_name}`, value: p.id })) ?? []);

const unitOptions = [
    { label: 'MT', value: 'MT' },
    { label: 'Ton', value: 'Ton' },
    { label: 'Trips', value: 'Trips' },
];

const billableNetWeight = computed(() => {
    const nwUnload = Math.max(toNumber(form.weights.loaded_weight_unload) - toNumber(form.weights.empty_weight_unload), 0);
    const nwLoad = Math.max(toNumber(form.weights.loaded_weight_load) - toNumber(form.weights.empty_weight_load), 0);
    return nwUnload > 0 ? nwUnload : nwLoad;
});

const grossAmount = computed(() => billableNetWeight.value * toNumber(form.financials.product_amount));
const taxRate = computed(() => taxOptions.value.find(t => t.value === form.financials.product_tax_id)?.rate || 0);
const totalTax = computed(() => grossAmount.value * (taxRate.value / 100));
const totalAmount = computed(() => (
    grossAmount.value + totalTax.value + toNumber(form.financials.transport_expenses) + toNumber(form.financials.pass_amount) + toNumber(form.financials.round_off) - toNumber(form.financials.discount_amount)
));

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(value);
};

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            weights: {
                ...data.weights,
                empty_weight_time: formatToBackendDateTime(data.weights.empty_weight_time),
                loaded_weight_time: formatToBackendDateTime(data.weights.loaded_weight_time),
            },
            status: {
                ...data.status,
                invoice_date: formatToBackendDate(data.status.invoice_date),
                purchase_date: formatToBackendDate(data.status.purchase_date),
                transport_date: formatToBackendDate(data.status.transport_date),
            }
        }))
        .put(route('trips.update', props.trip.id), {
            onSuccess: () => emit('updateSuccess'),
        });
};
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <Checkbox v-model="form.status.is_closed" binary />
                <label class="text-xs font-bold uppercase text-gray-500">Voyage Completed (Purchased & Sold)</label>
            </div>
            <div class="flex gap-2">
                 <Button label="Cancel" text severity="secondary"  @click="emit('close')" />
                 <Button label="Save Changes" icon="pi pi-check"  :loading="form.processing" @click="submit" />
            </div>
        </div>

        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-12 lg:col-span-9">
                <Tabs v-model:value="activeTab">
                    <TabList>
                        <Tab value="0">1. Loading Details</Tab>
                        <Tab value="1">2. Unloading Details</Tab>
                        <Tab value="2">3. Logistics & Billing</Tab>
                        <Tab value="3">4. Personnel</Tab>
                    </TabList>
                    <TabPanels>
                        <TabPanel value="0">
                            <div class="grid grid-cols-12 gap-4 py-4">
                                <div class="flex flex-col gap-1 col-span-6 md:col-span-3">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Empty Wt.</label>
                                    <BaseInputNumber v-model="form.weights.empty_weight_load" :minFractionDigits="2" fluid  />
                                </div>
                                <div class="flex flex-col gap-1 col-span-6 md:col-span-3">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Full Wt.</label>
                                    <BaseInputNumber v-model="form.weights.loaded_weight_load" :minFractionDigits="2" fluid  />
                                </div>
                                <div class="flex flex-col gap-1 col-span-12 md:col-span-6">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Loading Point</label>
                                    <BaseSelect v-model="form.load_site_id" :options="siteOptions" optionLabel="label" optionValue="value" fluid  filter />
                                </div>
                                <div class="flex flex-col gap-1 col-span-12 md:col-span-6">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Product Rate</label>
                                    <BaseInputNumber v-model="form.financials.product_amount" :minFractionDigits="2" fluid  />
                                </div>
                                <div class="flex flex-col gap-1 col-span-12 md:col-span-6">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Tax Slab</label>
                                    <BaseSelect v-model="form.financials.product_tax_id" :options="taxOptions" optionLabel="label" optionValue="value" fluid  clearable />
                                </div>
                                <div class="flex flex-col gap-1 col-span-6 md:col-span-6">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Empty Time</label>
                                    <DatePicker v-model="form.weights.empty_weight_time" showTime hourFormat="24" fluid  />
                                </div>
                                <div class="flex flex-col gap-1 col-span-6 md:col-span-6">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Loaded Time</label>
                                    <DatePicker v-model="form.weights.loaded_weight_time" showTime hourFormat="24" fluid  />
                                </div>
                            </div>
                        </TabPanel>

                        <TabPanel value="1">
                            <div class="grid grid-cols-12 gap-4 py-4">
                                <div class="flex flex-col gap-1 col-span-6 md:col-span-3">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Empty Wt. (Unload)</label>
                                    <BaseInputNumber v-model="form.weights.empty_weight_unload" :minFractionDigits="2" fluid  />
                                </div>
                                <div class="flex flex-col gap-1 col-span-6 md:col-span-3">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Full Wt. (Unload)</label>
                                    <BaseInputNumber v-model="form.weights.loaded_weight_unload" :minFractionDigits="2" fluid  />
                                </div>
                                <div class="flex flex-col gap-1 col-span-12 md:col-span-6">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Unloading Site</label>
                                    <BaseSelect v-model="form.unload_site_id" :options="siteOptions" optionLabel="label" optionValue="value" fluid  filter />
                                </div>
                                <div class="flex flex-col gap-1 col-span-12 md:col-span-6">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Unload Date</label>
                                    <DatePicker v-model="form.status.transport_date" dateFormat="yy-mm-dd" fluid  />
                                </div>
                                <div class="flex flex-col gap-1 col-span-12 md:col-span-6">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Reference #</label>
                                    <BaseInput v-model="form.reference_id" fluid  />
                                </div>
                            </div>
                        </TabPanel>

                        <TabPanel value="2">
                            <div class="grid grid-cols-12 gap-4 py-4">
                                <div class="flex flex-col gap-1 col-span-12 md:col-span-6">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Vendor / Transporter</label>
                                    <BaseSelect v-model="form.vendor_id" :options="patronOptions" optionLabel="label" optionValue="value" fluid  filter />
                                </div>
                                <div class="flex flex-col gap-1 col-span-12 md:col-span-6">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Hire Charge</label>
                                    <BaseInputNumber v-model="form.financials.transport_expenses" :minFractionDigits="2" fluid  />
                                </div>
                                <div class="flex flex-col gap-1 col-span-6 md:col-span-4">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Pass Amount</label>
                                    <BaseInputNumber v-model="form.financials.pass_amount" :minFractionDigits="2" fluid  />
                                </div>
                                <div class="flex flex-col gap-1 col-span-6 md:col-span-4">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Discount</label>
                                    <BaseInputNumber v-model="form.financials.discount_amount" :minFractionDigits="2" fluid  />
                                </div>
                                <div class="flex flex-col gap-1 col-span-6 md:col-span-4">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Round Off</label>
                                    <BaseInputNumber v-model="form.financials.round_off" :minFractionDigits="2" fluid  />
                                </div>
                            </div>
                        </TabPanel>

                        <TabPanel value="3">
                            <div class="grid grid-cols-12 gap-4 py-4">
                                <div class="flex flex-col gap-1 col-span-12 md:col-span-4">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Driver</label>
                                    <BaseSelect v-model="form.driver_id" :options="personnelOptions" optionLabel="label" optionValue="value" fluid  filter />
                                </div>
                                <div class="flex flex-col gap-1 col-span-12 md:col-span-4">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Cleaner</label>
                                    <BaseSelect v-model="form.cleaner_id" :options="personnelOptions" optionLabel="label" optionValue="value" fluid  filter />
                                </div>
                                <div class="flex flex-col gap-1 col-span-12 md:col-span-4">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Maistry</label>
                                    <BaseSelect v-model="form.maistry_id" :options="personnelOptions" optionLabel="label" optionValue="value" fluid  filter />
                                </div>
                                <div class="flex flex-col gap-1 col-span-6 md:col-span-4">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Loader</label>
                                    <BaseSelect v-model="form.loader_id" :options="personnelOptions" optionLabel="label" optionValue="value" fluid  filter />
                                </div>
                                <div class="flex flex-col gap-1 col-span-6 md:col-span-4">
                                    <label class="text-[10px] font-bold uppercase text-gray-400">Operator</label>
                                    <BaseSelect v-model="form.operator_id" :options="personnelOptions" optionLabel="label" optionValue="value" fluid  filter />
                                </div>
                            </div>
                        </TabPanel>
                    </TabPanels>
                </Tabs>
                
                <div class="mt-4">
                    <label class="text-[10px] font-bold uppercase text-gray-400">Internal Note</label>
                    <Textarea v-model="form.ui_note" rows="2" fluid  class="mt-1" />
                </div>
            </div>

            <div class="col-span-12 lg:col-span-3">
                <div class="p-4 bg-indigo-50/50 border border-indigo-100 rounded-lg flex flex-col gap-3">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-black uppercase text-gray-400">Net Weight</span>
                        <span class="text-xl font-black text-indigo-700">{{ billableNetWeight.toFixed(2) }} <small class="text-xs">MT</small></span>
                    </div>
                    <div class="flex flex-col border-t border-indigo-100 pt-3">
                        <span class="text-[10px] font-black uppercase text-gray-400">Subtotal</span>
                        <span class="font-bold text-gray-700">{{ formatCurrency(grossAmount) }}</span>
                    </div>
                    <div class="flex flex-col border-t border-indigo-100 pt-3">
                        <span class="text-[10px] font-black uppercase text-gray-400">Grand Total</span>
                        <span class="text-2xl font-black text-indigo-900 tracking-tighter">{{ formatCurrency(totalAmount) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

