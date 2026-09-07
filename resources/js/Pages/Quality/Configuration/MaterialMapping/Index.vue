<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import Badge from '@/Components/Mm/Badge.vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    materials: any[];
    testTypes: any[];
    mappings: any[];
}>();

const toast = useToast();
const selectedMaterialId = ref<number | null>(props.materials[0]?.id || null);

const selectedMaterial = computed(() => {
    return props.materials.find(m => m.id === selectedMaterialId.value) || null;
});

const activeTestTypeIds = computed(() => {
    if (!selectedMaterialId.value) return [];
    return props.mappings
        .filter(m => m.material_id === selectedMaterialId.value && m.is_active)
        .map(m => m.test_type_id);
});

const form = useForm({
    material_id: selectedMaterialId.value,
    test_type_ids: [] as number[],
});

const selectMaterial = (material: any) => {
    selectedMaterialId.value = material.id;
    form.material_id = material.id;
    form.test_type_ids = props.mappings
        .filter(m => m.material_id === material.id && m.is_active)
        .map(m => m.test_type_id);
};

if (selectedMaterialId.value) {
    form.test_type_ids = [...activeTestTypeIds.value];
}

const toggleTestType = (testTypeId: number) => {
    const idx = form.test_type_ids.indexOf(testTypeId);
    if (idx > -1) {
        form.test_type_ids.splice(idx, 1);
    } else {
        form.test_type_ids.push(testTypeId);
    }
};

const saveConfiguration = () => {
    form.post(route('quality.config.material-mapping.save'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Saved', detail: 'Material QC test mapping saved', life: 2000 });
        }
    });
};
</script>

<template>
    <AppLayout title="Material QC Configuration">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Material Test Mapping" />
        <Toast />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-950 py-8 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-gray-100 tracking-tight">
                        Material Quality Test Mapping
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Define which laboratory test procedures are required for each raw material and concrete product.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Sidebar Material List -->
                <BaseCard class="p-4 space-y-2">
                    <h2 class="text-xs font-black uppercase text-gray-400 tracking-wider mb-3 px-2">
                        Select Material ({{ materials.length }})
                    </h2>
                    <div class="space-y-1 max-h-[600px] overflow-y-auto pr-1">
                        <button
                            v-for="mat in materials"
                            :key="mat.id"
                            @click="selectMaterial(mat)"
                            :class="[
                                'w-full text-left p-3 rounded-xl transition-all flex items-center justify-between',
                                selectedMaterialId === mat.id
                                    ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-500/20'
                                    : 'hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-800 dark:text-gray-200 font-medium'
                            ]"
                        >
                            <div>
                                <div class="text-xs font-bold">{{ mat.title }}</div>
                                <div class="text-[10px] opacity-75 font-mono">{{ mat.code || mat.material_code }}</div>
                            </div>
                            <span
                                :class="[
                                    'px-2 py-0.5 text-[10px] rounded-full font-extrabold',
                                    selectedMaterialId === mat.id ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'
                                ]"
                            >
                                {{ mappings.filter(m => m.material_id === mat.id && m.is_active).length }} tests
                            </span>
                        </button>
                    </div>
                </BaseCard>

                <!-- Applicable Tests Selection Panel -->
                <BaseCard class="lg:col-span-2 p-6 flex flex-col justify-between">
                    <div>
                        <div v-if="selectedMaterial" class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-800 mb-6">
                            <div>
                                <h2 class="text-lg font-black text-gray-900 dark:text-gray-100">
                                    {{ selectedMaterial.title }}
                                </h2>
                                <p class="text-xs text-gray-500 font-mono mt-0.5">
                                    Code: {{ selectedMaterial.code }} &bull; Category: {{ selectedMaterial.category?.name || 'Raw Material' }}
                                </p>
                            </div>
                            <BaseButton @click="saveConfiguration" variant="primary" :disabled="form.processing">
                                <i class="pi pi-check text-xs mr-2"></i> Save Configuration
                            </BaseButton>
                        </div>

                        <div v-if="testTypes.length === 0" class="py-12 text-center text-gray-400">
                            No test types configured yet. Please configure test types first.
                        </div>

                        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div
                                v-for="type in testTypes"
                                :key="type.id"
                                @click="toggleTestType(type.id)"
                                :class="[
                                    'p-4 rounded-xl border-2 cursor-pointer transition-all flex items-start gap-3',
                                    form.test_type_ids.includes(type.id)
                                        ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/20 shadow-sm'
                                        : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700'
                                ]"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.test_type_ids.includes(type.id)"
                                    class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <div>
                                    <div class="font-bold text-xs text-gray-900 dark:text-gray-100">
                                        {{ type.name }}
                                    </div>
                                    <div class="text-[10px] text-indigo-600 font-mono font-bold mt-0.5">
                                        {{ type.code }} &bull; {{ type.category }}
                                    </div>
                                    <div class="text-[11px] text-gray-500 mt-1 line-clamp-2">
                                        {{ type.standard_reference ? 'Standard: ' + type.standard_reference : (type.description || 'Standard quality control test procedure.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-100 dark:border-gray-800 mt-6 flex justify-end">
                        <BaseButton @click="saveConfiguration" variant="primary" :disabled="form.processing">
                            Save QC Configuration
                        </BaseButton>
                    </div>
                </BaseCard>
            </div>
        </div>
    </AppLayout>
</template>
