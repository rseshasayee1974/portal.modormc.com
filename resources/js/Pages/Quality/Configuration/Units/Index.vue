<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';
import Badge from '@/Components/Mm/Badge.vue';
import Toast from 'primevue/toast';

const props = defineProps<{
    units: any;
    filters: any;
    dimensions: string[];
}>();
</script>

<template>
    <AppLayout title="QC Units of Measurement Configuration">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="QC Units Master" />
        <Toast />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-950 py-8 px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-gray-100 tracking-tight">
                        Units of Measurement (QC Units Master)
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Manage dynamic units of measurement (g, kg, mm, N/mm², MPa, kN, %, °C, kg/m³) used across test parameters & reports.
                    </p>
                </div>
                <div>
                    <Link :href="route('quality.config.units.create')">
                        <BaseButton variant="primary">
                            <i class="pi pi-plus text-xs mr-2"></i> Add QC Unit
                        </BaseButton>
                    </Link>
                </div>
            </div>

            <!-- BaseDataTable -->
            <BaseCard class="!p-0 overflow-hidden">
                <BaseDataTable
                    :value="units.data || []"
                    dataKey="id"
                    :paginator="true"
                    :rows="15"
                    :totalRecords="units.total"
                >
                    <Column field="code" header="Code" sortable>
                        <template #body="{ data }">
                            <span class="font-mono font-bold text-indigo-600">{{ data.code }}</span>
                        </template>
                    </Column>

                    <Column field="name" header="Unit Name" sortable>
                        <template #body="{ data }">
                            <span class="font-bold text-gray-900 dark:text-gray-100">{{ data.name }}</span>
                        </template>
                    </Column>

                    <Column field="symbol" header="Symbol / Unit Text">
                        <template #body="{ data }">
                            <span class="px-2.5 py-1 bg-purple-50 text-purple-700 font-mono font-black text-xs rounded-lg border border-purple-200">
                                {{ data.symbol }}
                            </span>
                        </template>
                    </Column>

                    <Column field="dimension" header="Dimension Category">
                        <template #body="{ data }">
                            <span class="uppercase text-[10px] font-bold text-gray-500 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded">
                                {{ data.dimension || 'General' }}
                            </span>
                        </template>
                    </Column>

                    <Column field="is_active" header="Status">
                        <template #body="{ data }">
                            <Badge
                                :value="data.is_active ? 'Active' : 'Inactive'"
                                :colorMap="{
                                    'Active': 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    'Inactive': 'bg-red-50 text-red-700 ring-red-200'
                                }"
                            />
                        </template>
                    </Column>

                    <Column header="Actions" alignFrozen="right" freezeRight>
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <Link :href="route('quality.config.units.edit', data.id)">
                                    <BaseButton size="small" variant="secondary">
                                        Edit
                                    </BaseButton>
                                </Link>
                                <BaseDeleteButton :url="route('quality.config.units.destroy', data.id)" />
                            </div>
                        </template>
                    </Column>
                </BaseDataTable>
            </BaseCard>
        </div>
    </AppLayout>
</template>
