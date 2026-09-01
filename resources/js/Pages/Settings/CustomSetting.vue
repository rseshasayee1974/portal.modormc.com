<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputSwitch from 'primevue/inputswitch';
import Swal from 'sweetalert2';
import {
    Cog6ToothIcon,
    VideoCameraIcon,
    ScaleIcon,
    DocumentArrowUpIcon,
    EyeSlashIcon,
    PencilSquareIcon,
    CheckCircleIcon,
    XCircleIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    PlusIcon,
    DocumentTextIcon,
    PrinterIcon
} from '@heroicons/vue/24/outline';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { usePermissions } from '@/Composables/usePermissions';

const { isSassOwner } = usePermissions();

const props = defineProps<{
    batchingSettings: any;
    customSettings: any[];
    plantId: number;
    plantName: string;
}>();

// ─── Form state ───────────────────────────────────────────────────────────────
const form = useForm({
    module: 'batching',
    settings: {
        newweight:         props.batchingSettings?.newweight == 1,
        manual_weight:     props.batchingSettings?.manual_weight == 1,
        with_inventory:        props.batchingSettings?.with_inventory !== undefined ? (props.batchingSettings?.with_inventory == 1 || props.batchingSettings?.with_inventory === true || props.batchingSettings?.with_inventory === "true") : true,
        camera:            props.batchingSettings?.camera == 1,
        camera_url:        props.batchingSettings?.camera_url  || '',
        camera_url_1:      props.batchingSettings?.camera_url_1 || '',
        camera_url_2:      props.batchingSettings?.camera_url_2 || '',
        loader_gif:        props.batchingSettings?.loader_gif   || '',
        sheet_upload:      props.batchingSettings?.sheet_upload == 1,
        hide_batch_form:   props.batchingSettings?.hide_batch_form == 1,
        po_prefix:         props.batchingSettings?.po_prefix    || 'PO',
        cpo_prefix:        props.batchingSettings?.cpo_prefix    || 'CPO',
        so_prefix:         props.batchingSettings?.so_prefix    || 'SO',
        quote_prefix:      props.batchingSettings?.quote_prefix || 'QT',
        target_to_actual:  props.batchingSettings?.target_to_actual == 1,
        auto_carry_pump:   props.batchingSettings?.auto_carry_pump == 1,
        default_transport: props.batchingSettings?.default_transport || '',
        quote_validity:    props.batchingSettings?.quote_validity !== undefined ? props.batchingSettings.quote_validity : 15,
        print_delivery_ingredients: props.batchingSettings?.print_delivery_ingredients !== undefined 
            ? (props.batchingSettings?.print_delivery_ingredients == 1 || props.batchingSettings?.print_delivery_ingredients === true || props.batchingSettings?.print_delivery_ingredients === "true") 
            : true,

        custom_params:     props.batchingSettings?.custom_params || [],
    }
});

// ─── Section expand / collapse ────────────────────────────────────────────────
const expanded = ref<Record<string, boolean>>({
    weighbridge: true,
    camera: true,
    batch_sync: true,
    print: true,
    pouring_settings: true,
    prefixes: true,
    appearance: true,
    custom: true,
});

const toggle = (key: string) => { expanded.value[key] = !expanded.value[key]; };

// ─── Summary table ─────────────────────────────────────────────────────────
const settingRows = computed(() => [
    // Weighbridge
    { section: 'Weighbridge', key: 'newweight',          label: 'Local API Proxy (V2)',            value: form.settings.newweight,           type: 'bool' },
    { section: 'Weighbridge', key: 'manual_weight',      label: 'Manual Weight Entry',             value: form.settings.manual_weight,        type: 'bool' },
    { section: 'Weighbridge', key: 'with_inventory',         label: 'Stock Deduction',                 value: form.settings.with_inventory,           type: 'bool' },
    // Camera
    { section: 'Camera',      key: 'camera',             label: 'Enable Snapshots',               value: form.settings.camera,              type: 'bool' },
    { section: 'Camera',      key: 'camera_url',         label: 'Default Camera URL',              value: form.settings.camera_url,          type: 'text' },
    { section: 'Camera',      key: 'camera_url_1',       label: 'Camera 1 (Entry/Empty)',          value: form.settings.camera_url_1,        type: 'text' },
    { section: 'Camera',      key: 'camera_url_2',       label: 'Camera 2 (Exit/Loaded)',          value: form.settings.camera_url_2,        type: 'text' },
    // Batch Sheet
    { section: 'Batch Sheet', key: 'sheet_upload',       label: 'Upload Batch Sheet',             value: form.settings.sheet_upload,        type: 'bool' },
    { section: 'Batch Sheet', key: 'hide_batch_form',    label: 'Hide Add & Edit Batch Forms',    value: form.settings.hide_batch_form,     type: 'bool' },
    { section: 'Batch Sheet', key: 'target_to_actual',   label: 'One-Click Target to Actual',     value: form.settings.target_to_actual,    type: 'bool' },
    { section: 'Batch Sheet', key: 'auto_carry_pump',    label: 'Auto-Select Previous Batch Pump', value: form.settings.auto_carry_pump,   type: 'bool' },
    { section: 'Defaults',    key: 'default_transport',  label: 'Default Transporter Name',        value: form.settings.default_transport,   type: 'text' },
    { section: 'Defaults',    key: 'quote_validity',     label: 'Quotation Validity (Days)',       value: form.settings.quote_validity,      type: 'text' },
    // Print
    { section: 'Print',       key: 'print_delivery_ingredients', label: 'Delivery Token Ingredients', value: form.settings.print_delivery_ingredients, type: 'bool' },
    // Appearance
    { section: 'Appearance',  key: 'loader_gif',         label: 'Custom Global Loader (GIF URL)', value: form.settings.loader_gif,          type: 'text' },
    // Document Prefixes
    { section: 'Document Prefixes', key: 'po_prefix',         label: 'Purchase Order Prefix',           value: form.settings.po_prefix,          type: 'text' },
    { section: 'Document Prefixes', key: 'so_prefix',         label: 'Sales Order Prefix',              value: form.settings.so_prefix,          type: 'text' },
    { section: 'Document Prefixes', key: 'cpo_prefix',         label: 'Customer Order Prefix',           value: form.settings.cpo_prefix,          type: 'text' },
    { section: 'Document Prefixes', key: 'quote_prefix',      label: 'Quotation Prefix',                value: form.settings.quote_prefix,       type: 'text' },
    // Custom / Module-specific dynamic parameters
    ...form.settings.custom_params.map((p: any) => ({
        section: modules.find(m => m.value === p.module)?.label || 'Custom',
        key: p.key,
        label: p.label,
        value: p.value,
        type: p.type
    }))
]);

// ─── Submit ───────────────────────────────────────────────────────────────────
const submit = () => {
    const payload = {
        ...form.settings,
        newweight:          form.settings.newweight          ? 1 : 0,
        manual_weight:      form.settings.manual_weight      ? 1 : 0,
        with_inventory:         form.settings.with_inventory         ? 1 : 0,
        camera:             form.settings.camera             ? 1 : 0,
        sheet_upload:       form.settings.sheet_upload       ? 1 : 0,
        hide_batch_form:    form.settings.hide_batch_form    ? 1 : 0,
        target_to_actual:   form.settings.target_to_actual   ? 1 : 0,
        auto_carry_pump:    form.settings.auto_carry_pump    ? 1 : 0,
        quote_validity:     form.settings.quote_validity     ? parseInt(form.settings.quote_validity as any, 10) : 15,
        print_delivery_ingredients: form.settings.print_delivery_ingredients ? 1 : 0,
        material_print_mode: form.settings.material_print_mode || 'run',
    };

    form.transform((data) => ({ ...data, settings: payload }))
        .post(route('settings.customsetting.update'), {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Settings saved', showConfirmButton: false, timer: 1500 });
            }
        });
};

// ─── New Module Dialog ────────────────────────────────────────────────────────
const showNewDialog = ref(false);
const newModuleForm = useForm({
    module: '',
    settings: {}
});

const modules = [
    { label: 'Batching', value: 'batching' },
    { label: 'Inventory', value: 'inventory' },
    { label: 'Fleet', value: 'fleet' },
    { label: 'Finance', value: 'finance' },
];

// ─── Custom Parameter Logic ──────────────────────────────────────────────────
const showParamDialog = ref(false);
const paramForm = ref({
    module: 'batching',
    label: '',
    key: '',
    type: 'text'
});

const addParameter = () => {
    if (!paramForm.value.key || !paramForm.value.label) return;
    
    form.settings.custom_params.push({
        ...paramForm.value,
        value: paramForm.value.type === 'bool' ? false : ''
    });
    
    paramForm.value = { module: 'batching', label: '', key: '', type: 'text' };
    showParamDialog.value = false;
};

const removeParameter = (index: number) => {
    form.settings.custom_params.splice(index, 1);
};

const saveNewModule = () => {
    if (!newModuleForm.module) return;
    newModuleForm.post(route('settings.customsetting.store'), {
        onSuccess: () => {
            showNewDialog.value = false;
            newModuleForm.reset();
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Module added successfully', showConfirmButton: false, timer: 1500 });
        }
    });
};

const deleteModule = (id: number) => {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this! All settings for this module will be permanently deleted.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const deleteForm = useForm({});
            deleteForm.delete(route('settings.customsetting.destroy', id), {
                onSuccess: () => {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Module settings deleted', showConfirmButton: false, timer: 1500 });
                }
            });
        }
    });
};

</script>

<template>
    <AppLayout title="Custom Settings">
        <!-- <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Custom Settings</h2>
                <div class="flex items-center gap-2 px-3 py-1 bg-indigo-50 border border-indigo-100 rounded-full">
                    <span class="text-[10px] font-bold uppercase text-indigo-400">Active Plant:</span>
                    <span class="text-xs font-bold text-indigo-700">{{ plantName }}</span>
                </div>
            </div>
        </template> -->

        <div class="py-2">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <ModuleSubTopNav />
            </div>
            
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 mt-4">

                <!-- ── Summary Table ──────────────────────────────────── -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                        <div class="flex items-center gap-2">
                            <Cog6ToothIcon class="w-5 h-5 text-slate-400" />
                            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-600">Current Settings Summary</h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <template v-if="isSassOwner">
                                <Button
                                    icon="pi pi-plus-circle"
                                    label="Add Parameter"
                                    size="small"
                                    severity="help"
                                    text
                                    @click="showParamDialog = true"
                                />
                                <Button
                                    icon="pi pi-plus"
                                    label="New Module"
                                    size="small"
                                    severity="secondary"
                                    text
                                    @click="showNewDialog = true"
                                />
                            </template>
                            <Button
                                icon="pi pi-save"
                                label="Save All Settings"
                                size="small"
                                :loading="form.processing"
                                @click="submit"
                            />
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/30">
                                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[18%]">Section</th>
                                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[25%]">Setting</th>
                                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[20%]">Key</th>
                                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">Value / Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="row in settingRows" :key="row.key" class="hover:bg-slate-50/40 transition-colors">
                                    <td class="px-5 py-3">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ row.section }}</span>
                                    </td>
                                    <td class="px-5 py-3 font-semibold text-slate-700 text-xs">{{ row.label }}</td>
                                    <td class="px-5 py-3">
                                        <code class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded">{{ row.key }}</code>
                                    </td>
                                    <td class="px-5 py-3">
                                        <template v-if="row.type === 'bool'">
                                            <span v-if="row.value" class="inline-flex items-center gap-1 text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100 px-2 py-0.5 rounded-full">
                                                <CheckCircleIcon class="w-3 h-3" /> Enabled
                                            </span>
                                            <span v-else class="inline-flex items-center gap-1 text-[10px] font-bold bg-slate-100 text-slate-400 border border-slate-200 px-2 py-0.5 rounded-full">
                                                <XCircleIcon class="w-3 h-3" /> Disabled
                                            </span>
                                        </template>
                                        <template v-else>
                                            <span v-if="row.value" class="text-xs text-slate-600 font-mono truncate max-w-[260px] block">{{ row.value }}</span>
                                            <span v-else class="text-[10px] italic text-slate-300">— not set —</span>
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Active Modules List -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400">Active Module Configurations</h3>
                        <span class="text-[10px] bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full font-bold uppercase">{{ customSettings.length }} Modules</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div v-for="setting in customSettings" :key="setting.id" 
                            class="flex items-center justify-between p-3.5 bg-slate-50 border border-slate-100 rounded-xl hover:shadow-sm transition-all">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                <span class="text-xs font-bold capitalize text-slate-700">{{ setting.module_name }}</span>
                            </div>
                            <Button 
                                icon="pi pi-trash" 
                                severity="danger" 
                                size="small"
                                text 
                                rounded
                                @click="deleteModule(setting.id)"
                            />
                        </div>
                    </div>
                </div>

                <!-- ── Edit Form: Accordion sections ──────────────────── -->
                <div class="space-y-4">
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-400 px-1">Edit Settings — click a section to expand</p>

                    <!-- 1. Weighbridge -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <button type="button"
                            class="w-full flex items-center justify-between px-6 py-4 border-b border-slate-100 hover:bg-emerald-50/30 transition-colors"
                            @click="toggle('weighbridge')">
                            <div class="flex items-center gap-2">
                                <div class="p-1.5 bg-emerald-100 rounded-lg"><ScaleIcon class="w-4 h-4 text-emerald-600" /></div>
                                <span class="text-sm font-bold text-slate-700">Weighbridge Configuration</span>
                                <span v-if="form.settings.newweight || form.settings.manual_weight"
                                    class="text-[9px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full font-bold uppercase">Active</span>
                            </div>
                            <ChevronDownIcon v-if="!expanded.weighbridge" class="w-4 h-4 text-slate-400" />
                            <ChevronUpIcon   v-else                        class="w-4 h-4 text-slate-400" />
                        </button>

                        <div v-if="expanded.weighbridge" class="p-6 space-y-4 animate-fade-in">
                            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                                <div>
                                    <h4 class="font-bold text-slate-700 text-sm">Local API Proxy (V2) <code class="text-[9px] text-slate-400 ml-1 font-normal">[newweight]</code></h4>
                                    <p class="text-xs text-slate-500 mt-0.5">Use localhost:8089 instead of direct Serial Port</p>
                                </div>
                                <InputSwitch v-model="form.settings.newweight" />
                            </div>

                            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                                <div>
                                    <h4 class="font-bold text-slate-700 text-sm">Manual Weight Entry <code class="text-[9px] text-slate-400 ml-1 font-normal">[manual_weight]</code></h4>
                                    <p class="text-xs text-slate-500 mt-0.5">Allow users to type weight if scale is disconnected</p>
                                </div>
                                <InputSwitch v-model="form.settings.manual_weight" />
                            </div>



                            <div class="flex items-center justify-between p-4 bg-emerald-50 rounded-xl border border-emerald-100">
                                <div>
                                    <h4 class="font-bold text-emerald-700 text-sm">Stock Deduction <code class="text-[9px] text-emerald-400 ml-1 font-normal">[with_inventory]</code></h4>
                                    <p class="text-xs text-emerald-500 mt-0.5">Deduct raw material inventory automatically during batch processing</p>
                                </div>
                                <InputSwitch v-model="form.settings.with_inventory" />
                            </div>

                            <!-- Dynamic Batching Params -->
                            <div v-for="(p, idx) in form.settings.custom_params.filter(x => x.module === 'batching')" :key="'b-'+idx" 
                                class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100 border-dashed">
                                <div class="flex-1">
                                    <h4 class="font-bold text-slate-700 text-sm">{{ p.label }} <code class="text-[9px] text-slate-400 ml-1 font-normal">[{{ p.key }}]</code></h4>
                                    <div v-if="p.type === 'text'" class="mt-2 max-w-md"><InputText v-model="p.value" class="w-full text-sm" /></div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <InputSwitch v-if="p.type === 'bool'" v-model="p.value" />
                                    <Button icon="pi pi-trash" severity="danger" text rounded @click="removeParameter(form.settings.custom_params.indexOf(p))" />
                                </div>
                            </div>

                            <!-- Default Transporter -->
                            <div class="p-4 bg-orange-50 rounded-xl border border-orange-100">
                                <h4 class="font-bold text-orange-700 text-sm">Default Transporter <code class="text-[9px] text-orange-400 ml-1 font-normal">[default_transport]</code></h4>
                                <p class="text-xs text-orange-500 mt-0.5 mb-2">Enter the exact transporter name. The batch create form will auto-select this transporter on load.</p>
                                <InputText
                                    v-model="form.settings.default_transport"
                                    placeholder="e.g. ABC Logistics Pvt Ltd"
                                    class="w-full text-sm max-w-md"
                                />
                                <p v-if="form.settings.default_transport" class="text-[10px] text-orange-600 mt-1 font-semibold">
                                    ✓ Auto-selecting: {{ form.settings.default_transport }}
                                </p>
                            </div>

                            <!-- Quotation Validity Offset -->
                            <div class="p-4 bg-blue-50 rounded-xl border border-blue-100">
                                <h4 class="font-bold text-blue-700 text-sm">Quotation Validity Offset (Days) <code class="text-[9px] text-blue-400 ml-1 font-normal">[quote_validity]</code></h4>
                                <p class="text-xs text-blue-500 mt-0.5 mb-2">Enter the number of days a quotation is valid. When creating or editing a quotation, the validity date will be automatically set to Quote Date + this number of days.</p>
                                <InputText
                                    v-model="form.settings.quote_validity"
                                    type="number"
                                    placeholder="e.g. 15"
                                    class="w-full text-sm max-w-xs"
                                    min="1"
                                />
                            </div>

                            <!-- Add Pouring Rates to Total -->
                            <!-- <div class="flex items-center justify-between p-4 bg-emerald-50 rounded-xl border border-emerald-100">
                                <div>
                                    <h4 class="font-bold text-emerald-700 text-sm">Add Pouring Rates to Quotation Total <code class="text-[9px] text-emerald-400 ml-1 font-normal">[add_pouring_rates_to_total]</code></h4>
                                    <p class="text-xs text-slate-500 mt-0.5">If enabled, the grand total is calculated using the pouring rates (Manual/Pump/Boom Pump) matching the selected concrete type. If disabled, it uses the actual rate from line items.</p>
                                </div>
                                <InputSwitch v-model="form.settings.add_pouring_rates_to_total" />
                            </div> -->

                            <!-- Pouring Rates Charge Type -->
                            <!-- <div class="flex flex-col gap-2 p-4 bg-emerald-50/50 rounded-xl border border-emerald-100">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-bold text-emerald-700 text-sm">Pouring Rate Charge Type <code class="text-[9px] text-emerald-400 ml-1 font-normal">[pouring_rate_charge_type]</code></h4>
                                        <p class="text-xs text-slate-500 mt-0.5">Specify whether the pouring rate is charged per cubic meter (m³) or as a flat rate for the entire quotation.</p>
                                    </div>
                                </div>
                                <div class="mt-2 max-w-xs">
                                    <Dropdown
                                        v-model="form.settings.pouring_rate_charge_type"
                                        :options="[
                                            { label: 'Charged Per m³', value: 'per_m3' },
                                            { label: 'Flat Rate (Total Value)', value: 'flat_rate' }
                                        ]"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Select charge type"
                                        class="w-full text-sm animate-fade-in"
                                    />
                                </div>
                            </div> -->
                        </div>
                    </div>

                    <!-- 2. Camera -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <button type="button"
                            class="w-full flex items-center justify-between px-6 py-4 border-b border-slate-100 hover:bg-cyan-50/30 transition-colors"
                            @click="toggle('camera')">
                            <div class="flex items-center gap-2">
                                <div class="p-1.5 bg-cyan-100 rounded-lg"><VideoCameraIcon class="w-4 h-4 text-cyan-600" /></div>
                                <span class="text-sm font-bold text-slate-700">Camera Integration</span>
                                <span v-if="form.settings.camera" class="text-[9px] bg-cyan-100 text-cyan-700 px-1.5 py-0.5 rounded-full font-bold uppercase">Active</span>
                            </div>
                            <ChevronDownIcon v-if="!expanded.camera" class="w-4 h-4 text-slate-400" />
                            <ChevronUpIcon   v-else                   class="w-4 h-4 text-slate-400" />
                        </button>

                        <div v-if="expanded.camera" class="p-6 space-y-4 animate-fade-in">
                            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                                <div>
                                    <h4 class="font-bold text-slate-700 text-sm">Enable Snapshots <code class="text-[9px] text-slate-400 ml-1 font-normal">[camera]</code></h4>
                                    <p class="text-xs text-slate-500 mt-0.5">Capture truck images during weight capture</p>
                                </div>
                                <InputSwitch v-model="form.settings.camera" />
                            </div>

                            <div v-if="form.settings.camera" class="space-y-3 animate-fade-in">
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Default Camera URL <code class="text-[9px] text-slate-300 font-normal normal-case">[camera_url]</code></label>
                                    <InputText v-model="form.settings.camera_url" placeholder="http://192.168.1.10/snap.jpg?usr=admin&pwd=admin" class="w-full text-sm" />
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-bold text-slate-500 uppercase">Camera 1 — Entry/Empty <code class="text-[9px] text-slate-300 font-normal normal-case">[camera_url_1]</code></label>
                                        <InputText v-model="form.settings.camera_url_1" placeholder="Optional specific URL" class="w-full text-sm" />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-bold text-slate-500 uppercase">Camera 2 — Exit/Loaded <code class="text-[9px] text-slate-300 font-normal normal-case">[camera_url_2]</code></label>
                                        <InputText v-model="form.settings.camera_url_2" placeholder="Optional specific URL" class="w-full text-sm" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Batch Sheet Sync -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <button type="button"
                            class="w-full flex items-center justify-between px-6 py-4 border-b border-slate-100 hover:bg-violet-50/30 transition-colors"
                            @click="toggle('batch_sync')">
                            <div class="flex items-center gap-2">
                                <div class="p-1.5 bg-violet-100 rounded-lg"><DocumentArrowUpIcon class="w-4 h-4 text-violet-600" /></div>
                                <span class="text-sm font-bold text-slate-700">Batch Sheet Sync</span>
                                <span v-if="form.settings.sheet_upload || form.settings.hide_batch_form"
                                    class="text-[9px] bg-violet-100 text-violet-700 px-1.5 py-0.5 rounded-full font-bold uppercase">Active</span>
                            </div>
                            <ChevronDownIcon v-if="!expanded.batch_sync" class="w-4 h-4 text-slate-400" />
                            <ChevronUpIcon   v-else                       class="w-4 h-4 text-slate-400" />
                        </button>

                        <div v-if="expanded.batch_sync" class="p-6 space-y-4 animate-fade-in">
                            <!-- sheet_upload -->
                            <div class="flex items-center justify-between p-4 bg-violet-50 rounded-xl border border-violet-100">
                                <div>
                                    <h4 class="font-bold text-violet-700 text-sm">Upload Batch Sheet <code class="text-[9px] text-violet-400 ml-1 font-normal">[sheet_upload]</code></h4>
                                    <p class="text-xs text-violet-500 mt-0.5">Allow users to upload a PDF or photo of the printed batch sheet to auto-fill Actual weights in the Input Reconciliation table.</p>
                                    <div v-if="form.settings.sheet_upload" class="mt-2">
                                        <span class="text-[9px] bg-violet-100 text-violet-700 px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">Upload Button Visible in Batch Edit</span>
                                    </div>
                                </div>
                                <InputSwitch v-model="form.settings.sheet_upload" />
                            </div>

                            <!-- hide_batch_form -->
                            <div class="flex items-center justify-between p-4 bg-rose-50 rounded-xl border border-rose-100">
                                <div>
                                    <h4 class="font-bold text-rose-700 text-sm">Hide Add &amp; Edit Batch Forms <code class="text-[9px] text-rose-400 ml-1 font-normal">[hide_batch_form]</code></h4>
                                    <p class="text-xs text-rose-500 mt-0.5">Hides the "New Batch" create form and the inline Edit form on the Batches page. Use when batch creation/editing is managed only via the batch sheet upload or an external scheduler.</p>
                                    <div v-if="form.settings.hide_batch_form" class="mt-2">
                                        <span class="text-[9px] bg-rose-100 text-rose-700 px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">⚠ Add &amp; Edit forms are currently HIDDEN</span>
                                    </div>
                                </div>
                                <InputSwitch v-model="form.settings.hide_batch_form" />
                            </div>

                            <!-- target_to_actual -->
                            <div class="flex items-center justify-between p-4 bg-indigo-50 rounded-xl border border-indigo-100">
                                <div>
                                    <h4 class="font-bold text-indigo-700 text-sm">One-Click Target to Actual <code class="text-[9px] text-indigo-400 ml-1 font-normal">[target_to_actual]</code></h4>
                                    <p class="text-xs text-indigo-500 mt-0.5">Show a quick-action button in the materials table to copy all recipe targets directly into actual quantities.</p>
                                    <div v-if="form.settings.target_to_actual" class="mt-2">
                                        <span class="text-[9px] bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">Target to Actual button Enabled</span>
                                    </div>
                                </div>
                                <InputSwitch v-model="form.settings.target_to_actual" />
                            </div>

                            <!-- auto_carry_pump -->
                            <div class="flex items-center justify-between p-4 bg-sky-50 rounded-xl border border-sky-100">
                                <div>
                                    <h4 class="font-bold text-sky-700 text-sm">Auto-Select Previous Batch Pump <code class="text-[9px] text-sky-400 ml-1 font-normal">[auto_carry_pump]</code></h4>
                                    <p class="text-xs text-sky-500 mt-0.5">Automatically select the concrete pump type from the previous batch of the same sales order.</p>
                                    <div v-if="form.settings.auto_carry_pump" class="mt-2">
                                        <span class="text-[9px] bg-sky-100 text-sky-700 px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">Pump Auto-Selection Enabled</span>
                                    </div>
                                </div>
                                <InputSwitch v-model="form.settings.auto_carry_pump" />
                            </div>

                            <!-- material_print_mode -->
                            <!-- <div class="flex flex-col gap-2 p-4 bg-violet-50 rounded-xl border border-violet-100">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-bold text-violet-700 text-sm">Material Print Mode on PDFs <code class="text-[9px] text-violet-400 ml-1 font-normal">[material_print_mode]</code></h4>
                                        <p class="text-xs text-violet-500 mt-0.5">Choose how material quantities (Target &amp; Actual) are printed on PDF tokens and passes.</p>
                                    </div>
                                </div>
                                <div class="mt-2 max-w-md">
                                    <Dropdown
                                        v-model="form.settings.material_print_mode"
                                        :options="[
                                            { label: 'By Run (As Synced)', value: 'run' },
                                            { label: 'By Batch Size (Total Load)', value: 'batch_size' },
                                            { label: 'By Mix Design (Per m³)', value: 'mix_design' }
                                        ]"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Select print mode"
                                        class="w-full text-sm"
                                    />
                                </div>
                            </div> -->

                            <!-- Dynamic Sync Params -->
                            <div v-for="(p, idx) in form.settings.custom_params.filter(x => x.module === 'sync' || (x.module === 'batching' && x.key.includes('sync')))" :key="'s-'+idx" 
                                class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100 border-dashed">
                                <div class="flex-1">
                                    <h4 class="font-bold text-slate-700 text-sm">{{ p.label }} <code class="text-[9px] text-slate-400 ml-1 font-normal">[{{ p.key }}]</code></h4>
                                    <div v-if="p.type === 'text'" class="mt-2 max-w-md"><InputText v-model="p.value" class="w-full text-sm" /></div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <InputSwitch v-if="p.type === 'bool'" v-model="p.value" />
                                    <Button icon="pi pi-trash" severity="danger" text rounded @click="removeParameter(form.settings.custom_params.indexOf(p))" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Print Configuration -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <button type="button"
                            class="w-full flex items-center justify-between px-6 py-4 border-b border-slate-100 hover:bg-purple-50/30 transition-colors"
                            @click="toggle('print')">
                            <div class="flex items-center gap-2">
                                <div class="p-1.5 bg-purple-100 rounded-lg"><PrinterIcon class="w-4 h-4 text-purple-600" /></div>
                                <span class="text-sm font-bold text-slate-700">Print Configuration</span>
                                <span v-if="form.settings.print_delivery_ingredients" 
                                    class="text-[9px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full font-bold uppercase">Active</span>
                            </div>
                            <ChevronDownIcon v-if="!expanded.print" class="w-4 h-4 text-slate-400" />
                            <ChevronUpIcon   v-else                 class="w-4 h-4 text-slate-400" />
                        </button>

                        <div v-if="expanded.print" class="p-6 space-y-4 animate-fade-in">
                            <!-- print_delivery_ingredients -->
                            <div class="flex items-center justify-between p-4 bg-purple-50 rounded-xl border border-purple-100">
                                <div>
                                    <h4 class="font-bold text-purple-700 text-sm">Batching &amp; Ingredients Details on Delivery Token <code class="text-[9px] text-purple-400 ml-1 font-normal">[print_delivery_ingredients]</code></h4>
                                    <p class="text-xs text-purple-500 mt-0.5">Show or hide the Recipe, Actual Quantity, and Deviation breakdown table on the Delivery Token (A4).</p>
                                    <div v-if="form.settings.print_delivery_ingredients" class="mt-2">
                                        <span class="text-[9px] bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">Ingredients Section Visible on Delivery Token</span>
                                    </div>
                                </div>
                                <InputSwitch v-model="form.settings.print_delivery_ingredients" />
                            </div>
                        </div>
                    </div>

                    <!-- Document Prefixes -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <button type="button"
                            class="w-full flex items-center justify-between px-6 py-4 border-b border-slate-100 hover:bg-indigo-50/30 transition-colors"
                            @click="toggle('prefixes')">
                            <div class="flex items-center gap-2">
                                <div class="p-1.5 bg-indigo-100 rounded-lg"><DocumentTextIcon class="w-4 h-4 text-indigo-600" /></div>
                                <span class="text-sm font-bold text-slate-700">Document Prefix Configuration</span>
                                <span v-if="form.settings.po_prefix || form.settings.so_prefix || form.settings.quote_prefix" 
                                    class="text-[9px] bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded-full font-bold uppercase">Active</span>
                            </div>
                            <ChevronDownIcon v-if="!expanded.prefixes" class="w-4 h-4 text-slate-400" />
                            <ChevronUpIcon   v-else                        class="w-4 h-4 text-slate-400" />
                        </button>

                        <div v-if="expanded.prefixes" class="p-6 space-y-4 animate-fade-in">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Purchase Order (PO) Prefix</label>
                                    <InputText v-model="form.settings.po_prefix" placeholder="PO" class="w-full text-sm" />
                                    <p class="text-[10px] text-slate-400 italic mt-0.5">Used for generated Purchase Orders.</p>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Sales Order Prefix</label>
                                    <InputText v-model="form.settings.so_prefix" placeholder="SO" class="w-full text-sm" />
                                    <p class="text-[10px] text-slate-400 italic mt-0.5">Used for generated Sales Orders.</p>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Customer Purchase Order Prefix</label>
                                    <InputText v-model="form.settings.cpo_prefix" placeholder="CPO" class="w-full text-sm" />
                                    <p class="text-[10px] text-slate-400 italic mt-0.5">Used for generated Customer Purchase Orders.</p>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Quotation Prefix</label>
                                    <InputText v-model="form.settings.quote_prefix" placeholder="QT" class="w-full text-sm" />
                                    <p class="text-[10px] text-slate-400 italic mt-0.5">Used for generated Quotations.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Appearance -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <button type="button"
                            class="w-full flex items-center justify-between px-6 py-4 border-b border-slate-100 hover:bg-indigo-50/30 transition-colors"
                            @click="toggle('appearance')">
                            <div class="flex items-center gap-2">
                                <div class="p-1.5 bg-indigo-100 rounded-lg"><Cog6ToothIcon class="w-4 h-4 text-indigo-600" /></div>
                                <span class="text-sm font-bold text-slate-700">System Appearance</span>
                                <span v-if="form.settings.loader_gif" class="text-[9px] bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded-full font-bold uppercase">Custom Loader Set</span>
                            </div>
                            <ChevronDownIcon v-if="!expanded.appearance" class="w-4 h-4 text-slate-400" />
                            <ChevronUpIcon   v-else                       class="w-4 h-4 text-slate-400" />
                        </button>

                        <div v-if="expanded.appearance" class="p-6 animate-fade-in">
                            <div class="flex flex-col gap-1 max-w-lg">
                                <label class="text-xs font-bold text-slate-500 uppercase">Custom Global Loader (GIF URL) <code class="text-[9px] text-slate-300 font-normal normal-case">[loader_gif]</code></label>
                                <InputText v-model="form.settings.loader_gif" placeholder="/storage/loaders/truck.gif" class="w-full text-sm" />
                                <p class="text-[10px] text-slate-400 mt-1 italic">Use the "Image to GIF" tool to generate your custom loader.</p>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Custom Parameters -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <button type="button"
                            class="w-full flex items-center justify-between px-6 py-4 border-b border-slate-100 hover:bg-amber-50/30 transition-colors"
                            @click="toggle('custom')">
                            <div class="flex items-center gap-2">
                                <div class="p-1.5 bg-amber-100 rounded-lg"><PencilSquareIcon class="w-4 h-4 text-amber-600" /></div>
                                <span class="text-sm font-bold text-slate-700">Custom Parameters</span>
                                <span v-if="form.settings.custom_params.length" class="text-[9px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-bold uppercase">{{ form.settings.custom_params.length }} Set</span>
                            </div>
                            <ChevronDownIcon v-if="!expanded.custom" class="w-4 h-4 text-slate-400" />
                            <ChevronUpIcon   v-else                   class="w-4 h-4 text-slate-400" />
                        </button>

                        <div v-if="expanded.custom" class="p-6 space-y-4 animate-fade-in">
                            <div v-if="!form.settings.custom_params.length" class="text-center py-8">
                                <p class="text-xs text-slate-400">No custom parameters added yet. Click "Add Parameter" to start.</p>
                            </div>
                            <div v-for="(p, index) in form.settings.custom_params" :key="index" 
                                class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                                <div class="flex-1">
                                    <h4 class="font-bold text-slate-700 text-sm">{{ p.label }} <code class="text-[9px] text-slate-400 ml-1 font-normal">[{{ p.key }}]</code></h4>
                                    
                                    <div v-if="p.type === 'text'" class="mt-2 max-w-md">
                                        <InputText v-model="p.value" placeholder="Enter value" class="w-full text-sm" />
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <InputSwitch v-if="p.type === 'bool'" v-model="p.value" />
                                    <Button icon="pi pi-trash" severity="danger" text rounded @click="removeParameter(index)" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save footer -->
                    <div class="flex justify-end pt-2 pb-6">
                        <Button
                            icon="pi pi-save"
                            label="Save All Settings"
                            size="large"
                            :loading="form.processing"
                            @click="submit"
                        />
                    </div>
                </div>

            </div>
        </div>

        <Dialog v-model:visible="showNewDialog" header="Add New Module Settings" :style="{ width: '400px' }" modal>
            <div class="space-y-4 py-2">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-bold text-slate-500 uppercase">Module Name</label>
                    <Dropdown v-model="newModuleForm.module" :options="modules" optionLabel="label" optionValue="value" placeholder="Select a module" class="w-full" />
                </div>
                <p class="text-[10px] text-slate-400 italic">Adding a module will create a default settings record for it.</p>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" text @click="showNewDialog = false" />
                <Button label="Create" icon="pi pi-check" :loading="newModuleForm.processing" @click="saveNewModule" />
            </template>
        </Dialog>

        <!-- New Custom Parameter Dialog -->
        <Dialog v-model:visible="showParamDialog" header="Add Setting to Module" :style="{ width: '400px' }" modal>
            <div class="space-y-4 py-2">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-bold text-slate-500 uppercase">Target Module</label>
                    <Dropdown v-model="paramForm.module" :options="modules" optionLabel="label" optionValue="value" class="w-full" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-bold text-slate-500 uppercase">Display Label</label>
                    <InputText v-model="paramForm.label" placeholder="e.g. Max Batch Size" class="w-full" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-bold text-slate-500 uppercase">System Key (unique)</label>
                    <InputText v-model="paramForm.key" placeholder="e.g. max_batch_size" class="w-full" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-bold text-slate-500 uppercase">Value Type</label>
                    <Dropdown v-model="paramForm.type" :options="[{label: 'Text / Number', value: 'text'}, {label: 'Toggle (On/Off)', value: 'bool'}]" optionLabel="label" optionValue="value" class="w-full" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" text @click="showParamDialog = false" />
                <Button label="Add to Module" icon="pi pi-check" @click="addParameter" />
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.2s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
