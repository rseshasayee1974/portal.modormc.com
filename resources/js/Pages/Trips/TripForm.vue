<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

// PrimeVue Imports
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import DatePicker from 'primevue/datepicker';
import SelectButton from 'primevue/selectbutton';
import Button from 'primevue/button';

const props = defineProps<{
    options: {
        patrons: any[];
        sites: any[];
        machines: any[];
        products: any[];
        personnels: any[];
        taxes: any[];
        transports?: any[];
    }
}>();

const emit = defineEmits(['success', 'cancel']);

const form = useForm({
    trip_type: 'outbound',
    party_id: null as number | null,
    vendor_id: null as number | null,
    truck_id: null as number | null,
    load_site_id: null as number | null,
    unload_site_id: null as number | null,
    product_id: null as number | null,
    driver_id: null as number | null,
    payment_mode: 'credit',
    product_units: null as number | null,
    product_amount: null as number | null,
    product_tax_id: null as number | null,
    empty_weight_load: null as number | null,
    ui_party_type: null as string | null,
    ui_reference_number: '',
    ui_requested_unit: 'MT',
    ui_empty_time: null as Date | null, // Calendar usually expects a Date object
    ui_maistry_id: null as number | null,
});

const partyOptions = props.options.patrons ?? [];
const siteOptions = props.options.sites ?? [];
const truckOptions = props.options.machines ?? [];
const productOptions = props.options.products ?? [];
const personnelOptions = props.options.personnels ?? [];
const taxOptions = props.options.taxes ?? [];
const transportOptions = (props.options.transports ?? partyOptions).map(p => ({ label: p.legal_name, value: p.id }));

const partyTypeOptions = [
    { label: 'Customer', value: 'customer' },
    { label: 'Vendor', value: 'vendor' },
    { label: 'Transporter', value: 'transporter' },
];

const unitOptions = [
    { label: 'MT', value: 'MT' },
    { label: 'Ton', value: 'Ton' },
    { label: 'Trips', value: 'Trips' },
];

const tripTypeOptions = [
    { label: 'Sales', value: 'outbound' },
    { label: 'Purchase', value: 'inbound' },
];

const tripBannerTitle = computed(() => (
    form.trip_type === 'inbound' ? 'TRIP - INCOMING (PURCHASE)' : 'TRIP - OUTGOING (SALES)'
));

const normalizeNumber = (value: number | string | null | undefined) => (
    value === '' || value === null || value === undefined ? null : Number(value)
);

const submit = () => {
    form
        .transform((data) => ({
            trip_type: data.trip_type,
            party_id: data.party_id,
            vendor_id: data.vendor_id,
            truck_id: data.truck_id,
            load_site_id: data.load_site_id,
            unload_site_id: data.unload_site_id,
            product_id: data.product_id,
            driver_id: data.driver_id,
            payment_mode: data.payment_mode,
            product_units: normalizeNumber(data.product_units),
            product_amount: normalizeNumber(data.product_amount),
            product_tax_id: data.product_tax_id,
            empty_weight_load: normalizeNumber(data.empty_weight_load),
            ui_empty_time: data.ui_empty_time ? data.ui_empty_time.toISOString().slice(0, 19).replace('T', ' ') : null,
        }))
        .post(route('trips.store'), {
            onSuccess: () => {
                form.reset();
                form.trip_type = 'outbound';
                form.payment_mode = 'credit';
                form.ui_requested_unit = 'MT';
                emit('success');
            }
        });
};
</script>

<template>
    <div class="trip-form-wrapper">
        <div class="form-tabs">
            <div class="form-tab active">
                {{ tripBannerTitle }}
            </div>
        </div>

        <form @submit.prevent="submit" class="trip-form-main">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-8">
                
                <!-- Left Column -->
                <div class="form-section">
                    <h4 class="section-title">PARTY/WEIGHTAGE</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-3">
                        <div class="form-group">
                            <label>Truck <span class="req">*</span></label>
                            <BaseSelect 
                                v-model="form.truck_id" 
                                :options="truckOptions" 
                                optionLabel="registration" 
                                optionValue="id" 
                                placeholder="Select Truck" 
                                fluid
                            />
                        </div>
                        <div class="form-group">
                            <label>Transport <span class="req">*</span></label>
                            <BaseSelect 
                                v-model="form.vendor_id" 
                                :options="transportOptions" 
                                optionLabel="label" 
                                optionValue="value" 
                                placeholder="None" 
                                fluid
                            />
                        </div>
                        <div class="form-group">
                            <label>Party <span class="req">*</span></label>
                            <BaseSelect 
                                v-model="form.party_id" 
                                :options="partyOptions" 
                                optionLabel="legal_name" 
                                optionValue="id" 
                                placeholder="Select Party" 
                                fluid
                            />
                        </div>

                        <div class="form-group">
                            <label>Party Type <span class="req">*</span></label>
                            <BaseSelect 
                                v-model="form.ui_party_type" 
                                :options="partyTypeOptions" 
                                optionLabel="label" 
                                optionValue="value" 
                                placeholder="Select Type" 
                                fluid
                            />
                        </div>
                        <div class="form-group">
                            <label>Loading <span class="req">*</span></label>
                            <BaseSelect 
                                v-model="form.load_site_id" 
                                :options="siteOptions" 
                                optionLabel="name" 
                                optionValue="id" 
                                placeholder="Select Site" 
                                fluid
                            />
                        </div>
                        <div class="form-group">
                            <label>Unloading Point <span class="req">*</span></label>
                            <BaseSelect 
                                v-model="form.unload_site_id" 
                                :options="siteOptions" 
                                optionLabel="name" 
                                optionValue="id" 
                                placeholder="Select Site" 
                                fluid
                            />
                        </div>

                        <div class="form-group">
                            <label>Empty Weight <span class="req">*</span></label>
                            <BaseInputNumber 
                                v-model="form.empty_weight_load" 
                                :minFractionDigits="2" 
                                mode="decimal" 
                                fluid 
                                placeholder="0.00"
                            />
                        </div>
                        <div class="form-group">
                            <label>Reference #</label>
                            <BaseInput v-model="form.ui_reference_number" placeholder="400" fluid class="bg-gray-100" />
                        </div>
                        <div class="form-group">
                            <label>Requested UNIT</label>
                            <BaseInputNumber 
                                v-model="form.product_units" 
                                :minFractionDigits="2" 
                                mode="decimal" 
                                fluid 
                                placeholder="0.00"
                            />
                        </div>

                        <div class="form-group col-span-1 md:col-span-2">
                            <label>Empty Time</label>
                            <DatePicker 
                                v-model="form.ui_empty_time" 
                                showTime 
                                hourFormat="24" 
                                fluid 
                                showIcon 
                                iconDisplay="input"
                            />
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-12">
                    <div class="form-section">
                        <h4 class="section-title">PRODUCT / DRIVER DETAILS</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-3">
                            <div class="form-group">
                                <label>Product <span class="req">*</span></label>
                                <BaseSelect 
                                    v-model="form.product_id" 
                                    :options="productOptions" 
                                    optionLabel="title" 
                                    optionValue="id" 
                                    placeholder="Select" 
                                    fluid
                                />
                            </div>
                            <div class="form-group">
                                <label>Product Rate</label>
                                <BaseInputNumber 
                                    v-model="form.product_amount" 
                                    :minFractionDigits="2" 
                                    mode="decimal" 
                                    fluid 
                                    placeholder="0.00"
                                />
                            </div>
                            <div class="form-group">
                                <label>Units</label>
                                <BaseSelect 
                                    v-model="form.ui_requested_unit" 
                                    :options="unitOptions" 
                                    optionLabel="label" 
                                    optionValue="value" 
                                    fluid
                                />
                            </div>

                            <div class="form-group">
                                <label>Sales Tax Type</label>
                                <BaseSelect 
                                    v-model="form.product_tax_id" 
                                    :options="taxOptions" 
                                    optionLabel="tax_name" 
                                    optionValue="id" 
                                    placeholder="None" 
                                    fluid
                                />
                            </div>
                            <div class="form-group">
                                <label>Driver</label>
                                <BaseSelect 
                                    v-model="form.driver_id" 
                                    :options="personnelOptions" 
                                    :optionLabel="(p) => `${p.first_name} ${p.last_name}`" 
                                    optionValue="id" 
                                    placeholder="None" 
                                    fluid
                                />
                            </div>
                            <div class="form-group">
                                <label>Maistry</label>
                                <BaseSelect 
                                    v-model="form.ui_maistry_id" 
                                    :options="personnelOptions" 
                                    :optionLabel="(p) => `${p.first_name} ${p.last_name}`" 
                                    optionValue="id" 
                                    placeholder="None" 
                                    fluid
                                />
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h4 class="section-title">PURCHASE DETAILS</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-3 items-end">
                            <div class="form-group">
                                <label>Purchase/Sales</label>
                                <SelectButton 
                                    v-model="form.trip_type" 
                                    :options="tripTypeOptions" 
                                    optionLabel="label" 
                                    optionValue="value" 
                                    class="p-selectbutton-sm"
                                />
                            </div>
                            <div class="form-group">
                                <label>Purchase Party <span class="req">*</span></label>
                                <BaseSelect 
                                    v-model="form.vendor_id" 
                                    :options="transportOptions" 
                                    optionLabel="label" 
                                    optionValue="value" 
                                    fluid
                                />
                            </div>
                            <div class="form-group">
                                <label>Purchase Tax Type <span class="req">*</span></label>
                                <BaseSelect 
                                    v-model="form.product_tax_id" 
                                    :options="taxOptions" 
                                    optionLabel="tax_name" 
                                    optionValue="id" 
                                    fluid
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="submit-area">
                <Button 
                    type="submit" 
                    icon="pi pi-arrow-right" 
                    iconPos="right" 
                    :label="form.processing ? 'ADDING...' : 'ADD TRIP'" 
                    :loading="form.processing"
                    class="p-button-outlined p-button-secondary w-full md:w-auto px-12"
                />
            </div>
        </form>
    </div>
</template>

<style scoped>
/* Keeping requested layout but using PrimeVue spacing */
.trip-form-wrapper {
    background: #eef4fb;
    padding: 2px;
    border: 1px solid #c8d9ea;
}

.form-tabs {
    display: flex;
    margin-bottom: -1px;
}

.form-tab {
    background: #eef4fb;
    border: 1px solid #c8d9ea;
    border-bottom: 1px solid #eef4fb;
    padding: 8px 30px;
    font-weight: 900;
    color: #4a7ab5;
    font-size: 14px;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.trip-form-main {
    border: 1px solid #c8d9ea;
    background: #eef4fb;
    padding: 30px 40px;
    position: relative;
}

.section-title {
    color: #1d99d3;
    font-weight: 800;
    font-size: 13px;
    margin-bottom: 20px;
    letter-spacing: 0.2px;
    text-transform: uppercase;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    font-size: 13px;
    font-weight: 600;
    color: #334155;
}

.req {
    color: #ee4444;
    margin-left: 2px;
}

.submit-area {
    display: flex;
    justify-content: center;
    margin-top: 40px;
}

/* Customizing PrimeVue components to match the classical blue design if needed, 
   but letting PrimeVue v4 styles shine through by default. */

:deep(.p-select), :deep(.p-inputtext), :deep(.p-inputnumber), :deep(.p-datepicker) {
    width: 150px !important;
    height: 27px !important;
    font-size: 12px;
    border-radius: 2px;
}

:deep(.p-select .p-select-label),
:deep(.p-inputtext),
:deep(.p-inputnumber-input),
:deep(.p-datepicker-input) {
    height: 25px !important;
    line-height: 25px !important;
    padding: 0 8px !important;
}

:deep(.p-select-dropdown),
:deep(.p-datepicker-trigger) {
    width: 25px !important;
}

:deep(.p-select-overlay .p-select-option) {
    padding: 4px !important;
    font-size: 12px;
}

:deep(.p-select-overlay .p-select-option-selected) {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

:deep(.p-select-overlay .p-select-option-selected::after) {
    content: '\e909'; /* PrimeIcons 'picheck' */
    font-family: 'primeicons';
    font-size: 12px;
    font-weight: bold;
}

/* Matching the SelectButton to the 'pinkish' active color from the image if possible */
:deep(.p-selectbutton .p-togglebutton.p-togglebutton-checked::before) {
    background: #f1a6a6 !important;
}

:deep(.p-selectbutton .p-togglebutton.p-togglebutton-checked) {
    color: #1e293b !important;
    background: #f1a6a6 !important;
}
</style>

