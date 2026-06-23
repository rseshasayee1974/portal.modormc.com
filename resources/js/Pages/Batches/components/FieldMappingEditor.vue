<script setup lang="ts">
import { ref } from 'vue';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

const props = defineProps<{
    rawHeaders: Record<string, any>;
    canonicalKeys: Array<{ key: string; label: string }>;
    initialMapping: Record<string, string>;
}>();

const emit = defineEmits(['save', 'cancel']);

const templateName = ref('');
const mappings = ref<Record<string, string>>({ ...props.initialMapping });

const saveTemplate = () => {
    if (!templateName.value.trim()) {
        return;
    }
    emit('save', {
        name: templateName.value,
        mappings: mappings.value
    });
};
</script>

<template>
    <div class="bg-white border border-gray-200 rounded-xl shadow-lg p-6 max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-4 border-b border-gray-100 pb-3">
            <h3 class="text-md font-bold text-gray-800 flex items-center gap-2">
                <i class="pi pi-cog text-indigo-600"></i>
                Save Layout Mapping as Template
            </h3>
            <span class="text-xs text-gray-500 font-medium">Learning engine</span>
        </div>

        <p class="text-xs text-gray-500 mb-6 leading-relaxed">
            By naming and saving this mapping, future uploads that contain matching headers or keywords will auto-map exactly without needing manual corrections.
        </p>

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

        <!-- Mappings Table -->
        <div class="mb-6 max-h-96 overflow-y-auto border border-gray-150 rounded-lg">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 uppercase tracking-wider font-bold text-[10px] border-b border-gray-200">
                        <th class="px-4 py-2">Canonical Field</th>
                        <th class="px-4 py-2">Mapped Label in Sheet</th>
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
                    </tr>
                </tbody>
            </table>
        </div>

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
