<script setup lang="ts">
import { ref, computed } from 'vue';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import axios from 'axios';

const props = defineProps<{
    uploadId: number;
    rawHeaders: Record<string, any>;
    rawMaterials: Array<{ material_name: string; target_qty?: number; actual_qty?: number }>;
    canonicalKeys: Array<{ key: string; label: string }>;
    initialMapping: Record<string, string>;
    products: Array<{ id: number; title: string }>;
}>();

const emit = defineEmits(['save', 'cancel']);

const templateName = ref('');
const mappings = ref<Record<string, string>>({ ...props.initialMapping });

// raw_material_name -> product_id
const materialMappings = ref<Record<string, number | null>>({});

// AI suggestion state
const suggesting = ref(false);
const suggestError = ref<string | null>(null);
const suggestionProvider = ref<string | null>(null);
const fieldConfidence = ref<Record<string, { confidence: number; reasoning: string }>>({});
const materialConfidence = ref<Record<string, { confidence: number; reasoning: string; is_new_material: boolean }>>({});
const hasSuggested = ref(false);

const uniqueMaterialNames = computed(() => {
    const seen = new Set<string>();
    return props.rawMaterials
        .map((m) => m.material_name)
        .filter((name) => {
            if (!name || seen.has(name)) return false;
            seen.add(name);
            return true;
        });
});

const confidenceClass = (score: number) => {
    if (score >= 85) return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    if (score >= 60) return 'bg-amber-50 text-amber-700 border-amber-200';
    return 'bg-red-50 text-red-700 border-red-200';
};

const requestAiSuggestions = async () => {
    suggesting.value = true;
    suggestError.value = null;
    try {
        const response = await axios.post(route('batch-sheets.suggest-mapping', props.uploadId));
        const suggestions = response.data.suggestions;
        suggestionProvider.value = suggestions.provider ?? null;

        // Apply field suggestions
        const newFieldConfidence: Record<string, { confidence: number; reasoning: string }> = {};
        (suggestions.field_mappings || []).forEach((f: any) => {
            if (f.raw_label) {
                mappings.value[f.canonical_key] = f.raw_label;
            }
            newFieldConfidence[f.canonical_key] = {
                confidence: f.confidence ?? 0,
                reasoning: f.reasoning ?? '',
            };
        });
        fieldConfidence.value = newFieldConfidence;

        // Apply material suggestions
        const newMaterialConfidence: Record<string, { confidence: number; reasoning: string; is_new_material: boolean }> = {};
        (suggestions.material_mappings || []).forEach((m: any) => {
            if (m.suggested_product_id) {
                materialMappings.value[m.raw_material_name] = m.suggested_product_id;
            }
            newMaterialConfidence[m.raw_material_name] = {
                confidence: m.confidence ?? 0,
                reasoning: m.reasoning ?? '',
                is_new_material: !!m.is_new_material,
            };
        });
        materialConfidence.value = newMaterialConfidence;

        hasSuggested.value = true;
    } catch (e: any) {
        suggestError.value = e.response?.data?.error || 'Failed to get AI suggestions.';
    } finally {
        suggesting.value = false;
    }
};

const saveTemplate = () => {
    if (!templateName.value.trim()) {
        return;
    }
    emit('save', {
        name: templateName.value,
        mappings: mappings.value,
        materialMappings: materialMappings.value,
    });
};
</script>

<template>
    <div class="bg-white border border-gray-200 rounded-xl shadow-lg p-6 max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-4 border-b border-gray-100 pb-3">
            <h3 class="text-md font-bold text-gray-800 flex items-center gap-2">
                <i class="pi pi-cog text-indigo-600"></i>
                Save Layout &amp; Material Mapping as Template
            </h3>
            <span class="text-xs text-gray-500 font-medium">Learning engine</span>
        </div>

        <p class="text-xs text-gray-500 mb-4 leading-relaxed">
            By naming and saving this mapping, future uploads that contain matching headers or keywords will
            auto-map exactly without needing manual corrections.
        </p>

        <!-- AI Suggest bar -->
        <div class="mb-6 flex items-center justify-between bg-indigo-50 border border-indigo-100 rounded-lg px-4 py-3">
            <div class="text-xs text-indigo-800">
                <span class="font-semibold">Not sure how to map this?</span>
                Let AI analyze the extracted fields and materials and propose a mapping for you to review.
                <span v-if="suggestionProvider" class="block mt-1 text-indigo-500">Suggested via {{ suggestionProvider }} — review before saving.</span>
            </div>
            <Button
                :label="suggesting ? 'Analyzing…' : (hasSuggested ? 'Re-run AI Suggestion' : 'Suggest with AI')"
                icon="pi pi-sparkles"
                :loading="suggesting"
                class="p-button-sm p-button-outlined"
                @click="requestAiSuggestions"
            />
        </div>
        <p v-if="suggestError" class="text-xs text-red-600 mb-4">{{ suggestError }}</p>

        <!-- Template Name -->
        <div class="mb-6">
            <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Template Name</label>
            <InputText
                v-model="templateName"
                placeholder="e.g. VJ Mix Plant 121 Schwing Stetter"
                class="w-full"
                autofocus
            />
        </div>

        <!-- Field Mappings Table -->
        <div class="mb-3 flex items-center justify-between">
            <label class="block text-xs font-bold text-gray-600 uppercase">Header Fields</label>
        </div>
        <div class="mb-8 max-h-72 overflow-y-auto border border-gray-150 rounded-lg">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 uppercase tracking-wider font-bold text-[10px] border-b border-gray-200">
                        <th class="px-4 py-2">Canonical Field</th>
                        <th class="px-4 py-2">Mapped Label in Sheet</th>
                        <th class="px-4 py-2 w-20">AI Confidence</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="canonical in canonicalKeys" :key="canonical.key">
                        <td class="px-4 py-3 font-semibold text-gray-700">
                            {{ canonical.label }}
                        </td>
                        <td class="px-4 py-2">
                            <select
                                v-model="mappings[canonical.key]"
                                class="w-full px-2 py-1.5 bg-gray-50 border border-gray-200 rounded text-xs focus:bg-white focus:border-indigo-500 focus:outline-none"
                            >
                                <option value="">-- Do Not Map --</option>
                                <option
                                    v-for="(val, label) in rawHeaders"
                                    :key="label"
                                    :value="label"
                                >
                                    {{ label }} (e.g. "{{ val }}")
                                </option>
                            </select>
                        </td>
                        <td class="px-4 py-2">
                            <span
                                v-if="fieldConfidence[canonical.key]"
                                :title="fieldConfidence[canonical.key].reasoning"
                                class="inline-block px-2 py-0.5 rounded-full border text-[10px] font-bold cursor-help"
                                :class="confidenceClass(fieldConfidence[canonical.key].confidence)"
                            >
                                {{ fieldConfidence[canonical.key].confidence }}%
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Material Mappings Table -->
        <div class="mb-3 flex items-center justify-between">
            <label class="block text-xs font-bold text-gray-600 uppercase">Material Mapping</label>
        </div>
        <div v-if="uniqueMaterialNames.length" class="mb-6 max-h-72 overflow-y-auto border border-gray-150 rounded-lg">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 uppercase tracking-wider font-bold text-[10px] border-b border-gray-200">
                        <th class="px-4 py-2">Report Column</th>
                        <th class="px-4 py-2">Application Material</th>
                        <th class="px-4 py-2 w-20">AI Confidence</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="name in uniqueMaterialNames" :key="name">
                        <td class="px-4 py-3 font-semibold text-gray-700">
                            {{ name }}
                            <span
                                v-if="materialConfidence[name]?.is_new_material"
                                class="ml-1 inline-block px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 text-[9px] font-bold border border-blue-100"
                            >
                                NEW
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <select
                                v-model="materialMappings[name]"
                                class="w-full px-2 py-1.5 bg-gray-50 border border-gray-200 rounded text-xs focus:bg-white focus:border-indigo-500 focus:outline-none"
                            >
                                <option :value="null">-- Do Not Map --</option>
                                <option
                                    v-for="product in products"
                                    :key="product.id"
                                    :value="product.id"
                                >
                                    {{ product.title }}
                                </option>
                            </select>
                        </td>
                        <td class="px-4 py-2">
                            <span
                                v-if="materialConfidence[name]"
                                :title="materialConfidence[name].reasoning"
                                class="inline-block px-2 py-0.5 rounded-full border text-[10px] font-bold cursor-help"
                                :class="confidenceClass(materialConfidence[name].confidence)"
                            >
                                {{ materialConfidence[name].confidence }}%
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="text-xs text-gray-400 mb-6 italic">No material rows were extracted from this upload.</p>

        <!-- Footer -->
        <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
            <Button
                label="Cancel"
                class="p-button-text p-button-secondary p-button-sm"
                @click="emit('cancel')"
            />
            <Button
                label="Save Template"
                icon="pi pi-check"
                class="p-button-primary p-button-sm"
                :disabled="!templateName.trim()"
                @click="saveTemplate"
            />
        </div>
    </div>
</template>
