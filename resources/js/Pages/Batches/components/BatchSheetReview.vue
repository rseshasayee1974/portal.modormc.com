<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import FieldMappingEditor from './FieldMappingEditor.vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps<{
    uploadId: number;
}>();

const emit = defineEmits(['close', 'saved']);

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);

const upload = ref<any>(null);
const dropdowns = ref<any>({
    customers: [],
    trucks: [],
    drivers: [],
    operators: [],
    products: [],
    sales_orders: [],
});

// Extracted headers & materials for binding
const headerData = ref<Record<string, any>>({});
const materialsData = ref<Array<any>>([]);

const showTemplateCreator = ref(false);

const canonicalKeys = [
    { key: 'batch_number', label: 'Batch/Docket Number' },
    { key: 'batch_date', label: 'Batch Date' },
    { key: 'batch_start_time', label: 'Start Time' },
    { key: 'batch_end_time', label: 'End Time' },
    { key: 'batch_size', label: 'Batch Size (m³)' },
    { key: 'customer', label: 'Customer' },
    { key: 'site', label: 'Site Location' },
    { key: 'truck_number', label: 'Truck Plate' },
    { key: 'driver', label: 'Driver' },
    { key: 'recipe_name', label: 'Recipe Name' },
    { key: 'recipe_code', label: 'Recipe Code' },
    { key: 'order_number', label: 'Order/Work Order Number' },
];

onMounted(() => {
    fetchReviewData();
});

const fetchReviewData = async () => {
    loading.value = true;
    error.value = null;
    try {
        const response = await axios.get(route('batch-sheets.verify', props.uploadId));
        upload.value = response.data.upload;
        dropdowns.value = response.data.dropdowns;

        // Initialize form fields
        const norm = upload.value.normalized_json || {};
        headerData.value = {
            batch_no: norm.header?.batch_no || '',
            batch_size: norm.header?.batch_size || 1.0,
            start_time: norm.header?.start_time || '',
            end_time: norm.header?.end_time || '',
            customer_id: norm.header?.customer_id || null,
            truck_id: norm.header?.truck_id || null,
            driver_id: norm.header?.driver_id || null,
            operator_id: norm.header?.operator_id || null,
            sales_order_id: norm.header?.sales_order_id || null,
        };

        materialsData.value = (norm.materials || []).map((m: any) => ({
            material_name: m.material_name,
            product_id: m.product_id,
            target_qty: m.target_qty,
            actual_qty: m.actual_qty,
            deviation_quantity: m.deviation_quantity,
        }));
    } catch (e: any) {
        error.value = e.response?.data?.error || 'Failed to load verification data.';
    } finally {
        loading.value = false;
    }
};

const getConfidenceClass = (score: number) => {
    if (score >= 85) return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    if (score >= 60) return 'bg-amber-50 text-amber-700 border-amber-200';
    return 'bg-red-50 text-red-700 border-red-200';
};

const getFieldScore = (fieldKey: string) => {
    return upload.value?.field_scores?.[fieldKey] || 0;
};

// Auto-build current mappings to pass to template editor
const currentMappings = computed(() => {
    const maps: Record<string, string> = {};
    const extractedHeader = upload.value?.parsed_json?.header_fields || {};
    const normHeader = upload.value?.normalized_json?.header || {};

    canonicalKeys.forEach(ck => {
        const fieldData = upload.value?.normalized_json?.header?.[ck.key];
        for (const [rawLabel, rawVal] of Object.entries(extractedHeader)) {
            if (rawVal === fieldData) {
                maps[ck.key] = rawLabel;
                break;
            }
        }
    });

    return maps;
});

const totalTargetWeight = computed(() => {
    return materialsData.value.reduce((acc, m) => acc + (parseFloat(m.target_qty) || 0), 0);
});

const totalActualWeight = computed(() => {
    return materialsData.value.reduce((acc, m) => acc + (parseFloat(m.actual_qty) || 0), 0);
});

const netVarianceKg = computed(() => {
    return totalActualWeight.value - totalTargetWeight.value;
});

const netVariancePercent = computed(() => {
    if (totalTargetWeight.value === 0) return 0;
    return (netVarianceKg.value / totalTargetWeight.value) * 100;
});

const isToleranceValid = computed(() => {
    return Math.abs(netVariancePercent.value) <= 2.0; // within ±2% tolerance
});

const handleSaveTemplate = async (templateData: { name: string; mappings: Record<string, string>; materialMappings?: Record<string, number | null> }) => {
    try {
        const response = await axios.post(route('batch-sheets.save-template', props.uploadId), {
            template_name: templateData.name,
            corrections: templateData.mappings,
            material_mapping: templateData.materialMappings || {},
        });

        Swal.fire({
            title: 'Template Saved',
            text: response.data.message,
            icon: 'success',
            toast: true,
            position: 'top-end',
            timer: 3000,
            showConfirmButton: false,
        });
        showTemplateCreator.value = false;
        if (upload.value) {
            upload.value.template_id = response.data.template_id;
        }
    } catch (e: any) {
        Swal.fire('Error', e.response?.data?.error || 'Failed to save template.', 'error');
    }
};

const savingMaterialMapping = ref(false);

const saveMaterialMappingsForPlant = async () => {
    savingMaterialMapping.value = true;
    try {
        const matMapping: Record<string, number> = {};
        materialsData.value.forEach(m => {
            if (m.material_name && m.product_id) {
                matMapping[m.material_name] = m.product_id;
            }
        });

        const response = await axios.post(route('batch-sheets.save-template', props.uploadId), {
            template_name: `Plant Material Mapping (${upload.value?.original_filename || 'Auto'})`,
            material_mapping: matMapping,
            corrections: currentMappings.value,
        });

        Swal.fire({
            title: 'Plant Material Mapping Saved!',
            text: 'All future batch sheets for this plant will automatically reconcile these materials.',
            icon: 'success',
            toast: true,
            position: 'top-end',
            timer: 4000,
            showConfirmButton: false,
        });

        if (upload.value) {
            upload.value.template_id = response.data.template_id;
        }
    } catch (e: any) {
        Swal.fire('Error', e.response?.data?.error || 'Failed to save material mapping.', 'error');
    } finally {
        savingMaterialMapping.value = false;
    }
};

const syncTargetToActual = () => {
    materialsData.value.forEach(m => {
        m.actual_qty = m.target_qty;
        m.deviation_quantity = 0;
    });
    Swal.fire({
        title: 'Zero Difference Mode Applied',
        text: 'All actual weights have been matched exactly to targets (0.00 kg variance).',
        icon: 'info',
        toast: true,
        position: 'top-end',
        timer: 3000,
        showConfirmButton: false,
    });
};

const submitVerification = async () => {
    saving.value = true;
    try {
        // Auto-save material mappings for future AI recognition
        const matMapping: Record<string, number> = {};
        materialsData.value.forEach(m => {
            if (m.material_name && m.product_id) {
                matMapping[m.material_name] = m.product_id;
            }
        });

        if (Object.keys(matMapping).length > 0) {
            axios.post(route('batch-sheets.save-template', props.uploadId), {
                template_name: `Auto Mapped Materials`,
                material_mapping: matMapping,
                corrections: currentMappings.value,
            }).catch(() => {});
        }

        await axios.post(route('batch-sheets.save', props.uploadId), {
            header: headerData.value,
            materials: materialsData.value,
        });

        Swal.fire({
            title: 'Material Consumption Posted!',
            text: 'Batch material consumption and actuals saved successfully.',
            icon: 'success',
        });
        emit('saved');
    } catch (e: any) {
        Swal.fire('Validation Error', e.response?.data?.error || 'Please correct values before saving.', 'error');
    } finally {
        saving.value = false;
    }
};

const isPdf = computed(() => {
    return upload.value?.mime_type === 'application/pdf';
});

// console.log('sdcsfcds',props);

</script>

<template>
    <div class="min-h-screen bg-gray-50 flex flex-col">
        <!-- Top bar -->
        <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between sticky top-0 z-50">
            <div class="flex items-center gap-3">
                <Button 
                    icon="pi pi-arrow-left" 
                    class="p-button-text p-button-secondary p-button-rounded"
                    @click="emit('close')"
                />
                <div>
                    <h2 class="text-md font-bold text-gray-800">Verify Batch Sheet Data</h2>
                    <p class="text-xs text-gray-500" v-if="upload">
                        File: {{ upload.original_filename }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button 
                    v-if="upload && !upload.template_id && !showTemplateCreator"
                    label="Save Template" 
                    icon="pi pi-cog" 
                    class="p-button-outlined p-button-sm"
                    @click="showTemplateCreator = true"
                />
                <Button 
                    label="Cancel" 
                    class="p-button-text p-button-secondary p-button-sm" 
                    @click="emit('close')"
                />
                <Button 
                    label="Approve & Save Batch" 
                    icon="pi pi-check" 
                    class="p-button-primary p-button-sm"
                    :loading="saving"
                    @click="submitVerification"
                />
            </div>
        </header>

        <!-- Main Body split screen -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Left Side: Original Document Preview -->
            <div class="w-1/2 bg-gray-100 border-r border-gray-200 overflow-y-auto p-6 flex items-center justify-center">
                <div v-if="loading" class="text-center text-gray-500">
                    <i class="pi pi-spin pi-spinner text-2xl mb-2"></i>
                    <div>Loading document preview...</div>
                </div>
                <div v-else-if="upload" class="w-full max-w-2xl h-full flex flex-col justify-center">
                    <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden flex-1 flex flex-col">
                        <div class="bg-gray-550 border-b border-gray-150 px-4 py-2 flex items-center justify-between text-xs font-bold text-gray-600">
                            <span>Document Visualizer</span>
                            <a :href="upload.file_url" target="_blank" class="text-indigo-600 hover:underline flex items-center gap-1">
                                <i class="pi pi-external-link text-[10px]"></i> Open in New Tab
                            </a>
                        </div>
                        <div class="flex-1 p-4 bg-gray-50 overflow-auto flex items-center justify-center min-h-[500px]">
                            <!-- PDF iframe -->
                            <iframe 
                                v-if="isPdf"
                                :src="upload.file_url" 
                                class="w-full h-full border-none min-h-[600px] rounded"
                            ></iframe>
                            <!-- Image viewer -->
                            <img 
                                v-else 
                                :src="upload.file_url" 
                                class="max-w-full max-h-[600px] object-contain rounded shadow"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Extracted Fields Review Panel -->
            <div class="w-1/2 bg-white overflow-y-auto p-8">
                <div v-if="loading" class="space-y-6">
                    <div class="h-8 bg-gray-100 rounded w-1/3 animate-pulse"></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div v-for="i in 6" :key="i" class="h-12 bg-gray-100 rounded animate-pulse"></div>
                    </div>
                </div>

                <div v-else-if="error" class="max-w-md mx-auto mt-10">
                    <Message severity="error" :closable="false">{{ error }}</Message>
                </div>

                <div v-else-if="showTemplateCreator">
                    <FieldMappingEditor 
                        :uploadId="upload.id"
                        :rawHeaders="upload.parsed_json?.header_fields || {}"
                        :rawMaterials="upload.parsed_json?.materials || []"
                        :canonicalKeys="canonicalKeys"
                        :initialMapping="currentMappings"
                        :products="dropdowns.products || []"
                        @save="handleSaveTemplate"
                        @cancel="showTemplateCreator = false"
                    />
                </div>

                <div v-else-if="upload" class="space-y-8">
                    <!-- Confidence score summary card -->
                    <div class="p-4 rounded-xl border flex items-center justify-between" :class="getConfidenceClass(upload.confidence_score)">
                        <div class="flex items-center gap-3">
                            <i class="pi pi-chart-bar text-xl"></i>
                            <div>
                                <h4 class="text-sm font-bold">Extraction Confidence</h4>
                                <p class="text-xs opacity-80">
                                    Overall confidence score based on AI parsing mapping templates.
                                </p>
                            </div>
                        </div>
                        <span class="text-lg font-black tracking-tight">{{ upload.confidence_score }}%</span>
                    </div>

                    <!-- Header inputs section -->
                    <div>
                        <h3 class="text-sm font-black uppercase text-gray-500 tracking-wider mb-4 border-b border-gray-100 pb-2">
                            Header Fields
                        </h3>
                        <div class="grid grid-cols-2 gap-x-6 gap-y-4">
                            <!-- Batch Number -->
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1 flex items-center justify-between">
                                    <span>Batch Number</span>
                                    <span class="text-[9px] px-1 rounded bg-gray-100 text-gray-500">Confidence: {{ getFieldScore('batch_number') }}%</span>
                                </label>
                                <InputText v-model="headerData.batch_no" class="w-full text-xs" />
                            </div>

                            <!-- Batch Size -->
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1 flex items-center justify-between">
                                    <span>Batch Size (m³)</span>
                                    <span class="text-[9px] px-1 rounded bg-gray-100 text-gray-500">Confidence: {{ getFieldScore('batch_size') }}%</span>
                                </label>
                                <InputText v-model="headerData.batch_size" type="number" step="0.01" class="w-full text-xs" />
                            </div>

                            <!-- Start Time -->
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Start Time (H:i:s)</label>
                                <InputText v-model="headerData.start_time" class="w-full text-xs" placeholder="e.g. 10:15:30" />
                            </div>

                            <!-- End Time -->
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">End Time (H:i:s)</label>
                                <InputText v-model="headerData.end_time" class="w-full text-xs" placeholder="e.g. 10:20:45" />
                            </div>

                            <!-- Customer Dropdown mapping -->
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Customer</label>
                                <select v-model="headerData.customer_id" class="w-full px-3 py-2 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-indigo-500">
                                    <option :value="null">-- Select Customer --</option>
                                    <option v-for="c in dropdowns.customers" :key="c.id" :value="c.id">{{ c.legal_name }}</option>
                                </select>
                            </div>

                            <!-- Sales Order Dropdown mapping -->
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Sales Order Link</label>
                                <select v-model="headerData.sales_order_id" class="w-full px-3 py-2 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-indigo-500">
                                    <option :value="null">-- Map Sales Order --</option>
                                    <option v-for="so in (dropdowns.sales_orders || dropdowns.work_orders)" :key="so.id" :value="so.id">
                                        Order #{{ so.order_no }} (Qty: {{ so.produced_qty }}/{{ so.total_qty }} m³)
                                    </option>
                                </select>
                            </div>

                            <!-- Truck Plate Dropdown mapping -->
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Truck / Transit Mixer</label>
                                <select v-model="headerData.truck_id" class="w-full px-3 py-2 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-indigo-500">
                                    <option :value="null">-- Select Truck --</option>
                                    <option v-for="t in dropdowns.trucks" :key="t.id" :value="t.id">{{ t.registration }}</option>
                                </select>
                            </div>

                            <!-- Driver Dropdown mapping -->
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Driver</label>
                                <select v-model="headerData.driver_id" class="w-full px-3 py-2 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-indigo-500">
                                    <option :value="null">-- Select Driver --</option>
                                    <option v-for="d in dropdowns.drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
                                </select>
                            </div>

                            <!-- Operator Dropdown mapping -->
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Plant Operator</label>
                                <select v-model="headerData.operator_id" class="w-full px-3 py-2 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-indigo-500">
                                    <option :value="null">-- Select Operator --</option>
                                    <option v-for="op in dropdowns.operators" :key="op.id" :value="op.id">{{ op.name }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Materials Table mapping section -->
                    <div>
                        <div class="flex items-center justify-between mb-3 border-b border-gray-100 pb-2">
                            <div>
                                <h3 class="text-sm font-black uppercase text-gray-700 tracking-wider">
                                    Material Consumption & Reconciliation
                                </h3>
                                <p class="text-[11px] text-gray-500">Map your batch sheet material labels to system products once to automate future uploads.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button 
                                    label="Zero Difference (Sync Targets)" 
                                    icon="pi pi-check-circle" 
                                    class="p-button-secondary p-button-outlined p-button-sm !text-xs !py-1.5"
                                    @click="syncTargetToActual"
                                />
                                <Button 
                                    label="Remember Mappings for this Plant" 
                                    icon="pi pi-bookmark" 
                                    class="p-button-outlined p-button-sm !text-xs !py-1.5"
                                    :loading="savingMaterialMapping"
                                    @click="saveMaterialMappingsForPlant"
                                />
                            </div>
                        </div>
                        <div class="border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 text-gray-500 uppercase tracking-wider font-bold text-[10px] border-b border-gray-200">
                                        <th class="px-4 py-3">Label in Sheet</th>
                                        <th class="px-4 py-3">System Product Mapping</th>
                                        <th class="px-4 py-3 text-right">Target (kg)</th>
                                        <th class="px-4 py-3 text-right">Actual (kg)</th>
                                        <th class="px-4 py-3 text-right">Variance</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(mat, idx) in materialsData" :key="idx">
                                        <td class="px-4 py-3 font-semibold text-gray-700">
                                            {{ mat.material_name }}
                                        </td>
                                        <td class="px-4 py-2">
                                            <select v-model="mat.product_id" class="w-full px-2 py-1.5 bg-gray-50 border border-gray-200 rounded text-xs focus:bg-white focus:outline-none">
                                                <option :value="null">-- Map System Product --</option>
                                                <option v-for="p in dropdowns.products" :key="p.id" :value="p.id">{{ p.title }}</option>
                                            </select>
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <input type="number" v-model="mat.target_qty" class="w-20 px-2 py-1 text-right bg-gray-50 border border-gray-200 rounded text-xs" />
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <input type="number" v-model="mat.actual_qty" class="w-20 px-2 py-1 text-right bg-white border border-gray-200 rounded text-xs" />
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono text-[11px]">
                                            <span :class="(mat.actual_qty - mat.target_qty) === 0 ? 'text-gray-400' : ((mat.actual_qty - mat.target_qty) > 0 ? 'text-blue-600' : 'text-amber-600')">
                                                {{ ((mat.actual_qty || 0) - (mat.target_qty || 0)) >= 0 ? '+' : '' }}{{ ((mat.actual_qty || 0) - (mat.target_qty || 0)).toFixed(2) }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 font-bold border-t-2 border-gray-200 text-gray-800">
                                    <tr>
                                        <td colspan="2" class="px-4 py-3 text-xs uppercase tracking-wider">
                                            Total Batch Mass (kg)
                                        </td>
                                        <td class="px-4 py-3 text-right text-xs font-mono">
                                            {{ totalTargetWeight.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-xs font-mono">
                                            {{ totalActualWeight.toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                        </td>
                                    </tr>
                                    <tr :class="isToleranceValid ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'">
                                        <td colspan="2" class="px-4 py-2.5 text-xs flex items-center gap-2">
                                            <i :class="isToleranceValid ? 'pi pi-check-circle text-emerald-600' : 'pi pi-exclamation-triangle text-amber-600'"></i>
                                            <span>Load Tolerance: {{ isToleranceValid ? '✓ Within ±2.0% Tolerance' : '⚠ Tolerance Limit Exceeded' }}</span>
                                        </td>
                                        <td colspan="2" class="px-4 py-2.5 text-right text-xs font-mono">
                                            Variance: {{ netVarianceKg >= 0 ? '+' : '' }}{{ netVarianceKg.toFixed(2) }} kg ({{ netVariancePercent >= 0 ? '+' : '' }}{{ netVariancePercent.toFixed(2) }}%)
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

