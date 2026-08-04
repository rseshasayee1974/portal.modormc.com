<script setup lang="ts">
import { ref, computed } from 'vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import ToggleSwitch from 'primevue/toggleswitch';
import { BeakerIcon, UserIcon, CubeIcon, TableCellsIcon, DocumentDuplicateIcon } from '@heroicons/vue/24/outline';
import MixDesignEditForm from './MixDesignEditForm.vue';
import MixDesignCreateForm from './MixDesignCreateForm.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import { usePermissions } from '@/Composables/usePermissions.js';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps<{
    mixDesigns: any[];
    products: any[];
    units: any[];
    partners: any[];
    defaultUomId?: number | null;
    designTypes: any[];
}>();

const {can} = usePermissions();
const expandedRows = ref<Record<number, boolean>>({});
const perPage = ref(30);
const filters = ref({ global: { value: null, matchMode: 'contains' } });

const showDuplicateModal = ref(false);
const selectedDesignForDuplication = ref<any>(null);

const filteredDesigns = computed(() => props.mixDesigns);

const onSaved = () => { expandedRows.value = {}; };

const openDuplicateModal = (mixDesign: any) => {
    selectedDesignForDuplication.value = mixDesign;
    showDuplicateModal.value = true;
};

const closeDuplicateModal = () => {
    showDuplicateModal.value = false;
    selectedDesignForDuplication.value = null;
};

const onDuplicateCreated = () => {
    closeDuplicateModal();
};

const toggleActive = (mixDesign: any) => {
    router.post(route('mixdesigns.toggle-active', mixDesign.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Status updated', timer: 1500, showConfirmButton: false });
        }
    });
};

const getDeleteTooltip = (mixDesign) => {
    const reasons = [];

    // if (mixDesign.is_used_in_quotations) {
    //     reasons.push('quotations');
    // }

    // if (mixDesign.is_used_in_batching) {
    //     reasons.push('batching');
    // }

    if (reasons.length) {
        return `This mix design is used in ${reasons.join(' and ')} . Are you sure you want to delete?`;
    }

    return 'This mix design will be permanently removed.';
};
</script>

<template>
    <div class="mix-table-container">
        <BaseDataTable
            v-model:expandedRows="expandedRows"
            v-model:filters="filters"
            v-model:rows="perPage"
            :value="filteredDesigns"
            dataKey="id"
            :rowsPerPageOptions="[30, 50, 100, 200]"
            showSearch
            class="unit-datatable"
            :globalFilterFields="['design_name', 'design_code', 'design_type']"
            showSerial
            heading="Mix Designs Registry"
            headingIcon="BeakerIcon"
            showExport
            exportFilename="mix-designs-report"
           
        >
            <template #toolbar>
                <div class="flex items-center gap-2 px-3 py-1 bg-indigo-50/50 rounded-lg border border-indigo-100">
                    <BeakerIcon class="w-3.5 h-3.5 text-indigo-500" />
                    <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">{{ filteredDesigns.length }} Recipes</span>
                </div>
            </template>
            <!-- Design Name -->
            <Column header="Design Name" sortable field="design_name">
                <template #body="slotProps">
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-800 uppercase tracking-tight">{{ slotProps.data.design_name }}</span>
                        <div class="flex items-center gap-2 mt-0.5">
                            <BeakerIcon class="w-3 h-3 text-slate-300" />
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ slotProps.data.design_code || '---' }}</span>
                        </div>
                    </div>
                </template>
            </Column>

            <!-- Grade -->
            <Column header="Grade" sortable field="design_type" style="width: 150px">
                <template #body="slotProps">
                    <Tag severity="success" rounded class="text-[9px] font-black uppercase tracking-widest">
                        {{ slotProps.data.design_type || 'N/A' }}
                    </Tag>
                </template>
            </Column>

            <!-- Partner -->
            <Column header="Partner" sortable field="partner.legal_name" style="width: 150px">
                <template #body="slotProps">
                    <div class="flex items-center gap-2">
                        <UserIcon class="w-3.5 h-3.5 text-slate-300" />
                        <span class="text-xs text-slate-600">{{ slotProps.data.partner?.legal_name || '—' }}</span>
                    </div>
                </template>
            </Column>

            <!-- Ingredients -->
            <Column header="Ingredients" style="width: 200px">
                <template #body="slotProps">
                    <div class="items-preview">
                        <div v-for="item in slotProps.data.items.slice(0, 2)" :key="item.id" class="preview-tag">
                            {{ item.product?.title?.split(' ')[0] }}: <span class="font-black">{{ item.actual_quantity }}</span>
                        </div>
                        <div v-if="slotProps.data.items.length > 2" class="preview-tag opacity-60">
                            +{{ slotProps.data.items.length - 2 }} more
                        </div>
                    </div>
                </template>
            </Column>

            <!-- Rate -->
            <Column header="Rate / m³" sortable field="rate_per_qty" style="width: 150px">
                <template #body="slotProps">
                    <span class="font-black text-indigo-600 font-mono text-sm">
                        ₹{{ Number(slotProps.data.rate_per_qty || 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                    </span>
                </template>
            </Column>
            
            <!-- Status -->
            <!-- <Column header="Status" style="width: 130px">
                <template #body="slotProps">
                    <div class="flex items-center gap-2" @click.stop>
                        <ToggleSwitch 
                            :modelValue="Boolean(slotProps.data.is_active)"
                            @change="toggleActive(slotProps.data)"
                        />
                        <span class="text-[10px] font-bold uppercase tracking-widest" :class="slotProps.data.is_active ? 'text-emerald-600' : 'text-slate-400'">
                            {{ slotProps.data.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </template>
            </Column> -->

            <!-- Unit -->
            <!-- <Column header="Unit" style="width: 80px">
                <template #body="slotProps">
                    <span class="text-xs text-slate-500 font-bold">{{ slotProps.data.unit?.unit_code || '—' }}</span>
                </template>
            </Column> -->

            <Column header="Actions" style="width: 100px; text-align: right">
                <template #body="slotProps">
                    <div class="flex justify-end items-center gap-2">
                        <button 
                            type="button" 
                            @click.stop="openDuplicateModal(slotProps.data)" 
                            class="p-1 hover:bg-slate-100 rounded-md text-slate-500 hover:text-indigo-600 transition"
                            title="Duplicate Mix Design"
                        >
                            <DocumentDuplicateIcon class="w-4 h-4" />
                        </button>
                        <!-- :disabled="slotProps.data.is_used_in_quotations || slotProps.data.is_used_in_batching" -->
                        <!-- v-tooltip.right="getDeleteTooltip(slotProps.data)"                         -->
                        <BaseDeleteButton
                        v-if="can('MIX_DESIGN.DELETE')"
                            :url="route('mixdesigns.destroy', slotProps.data.id)"
                            title="Delete Mix Design?"/>
                    </div>
                </template>
            </Column>

            <!-- Row Expansion: Edit Form -->
            <template #expansion="{ data }">
                <div class="expansion-panel">
                    <div class="pb-2 flex items-center gap-2">
                        <BeakerIcon class="w-4 h-4 text-indigo-500" />
                        <span class="text-[11px] font-black uppercase text-indigo-900 tracking-widest">Editing Mix Design</span>
                    </div>
                    <MixDesignEditForm
                        :design="data"
                        :products="products"
                        :units="units"
                        :partners="partners"
                        :defaultUomId="defaultUomId"
                        :designTypes="designTypes"
                        @cancel="expandedRows = {}"
                        @saved="onSaved"
                    />
                </div>
            </template>
        </BaseDataTable>

        <!-- Duplicate Mix Design Dialog Modal -->
        <Dialog 
            v-model:visible="showDuplicateModal" 
            modal 
            header="Duplicate Mix Design" 
            :style="{ width: '90vw', maxWidth: '1200px' }"
            @hide="closeDuplicateModal"
        >
            <div class="p-1">
                <MixDesignCreateForm
                    v-if="showDuplicateModal && selectedDesignForDuplication"
                    :products="products"
                    :units="units"
                    :partners="partners"
                    :defaultUomId="defaultUomId"
                    :designTypes="designTypes"
                    :initialDesign="selectedDesignForDuplication"
                    @created="onDuplicateCreated"
                    @cancel="closeDuplicateModal"
                />
            </div>
        </Dialog>
    </div>
</template>

<style scoped></style>
