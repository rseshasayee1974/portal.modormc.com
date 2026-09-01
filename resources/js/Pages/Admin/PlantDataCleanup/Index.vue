<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import { 
    TrashIcon, 
    ArrowPathIcon, 
    ExclamationTriangleIcon, 
    CheckCircleIcon,
    ShieldExclamationIcon,
    FolderIcon,
    MagnifyingGlassIcon
} from '@heroicons/vue/24/outline';

interface ModuleStat {
    key: string;
    name: string;
    active_count: number;
    trashed_count: number;
    supports_trash: boolean;
}

interface RecordItem {
    id: number;
    reference: string;
    status: string;
    created_at: string;
    deleted_at: string | null;
}

interface PaginatedRecords {
    data: RecordItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    plant: any;
    activePlantId: number;
    modules: Record<string, ModuleStat>;
    selectedModule: string;
    viewTrashed: boolean;
    records: PaginatedRecords;
    filters: {
        module: string;
        trashed: boolean;
        search: string;
    };
}>();

const search = ref(props.filters.search || '');
const selectedIds = ref<number[]>([]);
const isProcessing = ref(false);

// Clear selection when module or tab changes
watch(() => [props.selectedModule, props.viewTrashed], () => {
    selectedIds.value = [];
});

const isAllSelected = computed(() => {
    return props.records.data.length > 0 && props.records.data.every(r => selectedIds.value.includes(r.id));
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.records.data.map(r => r.id);
    }
};

const toggleSelectRow = (id: number) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
};

// Switch Module
const switchModule = (moduleKey: string) => {
    router.get(route('plant-data-cleanup.index'), {
        module: moduleKey,
        trashed: props.viewTrashed ? 1 : 0,
        search: search.value || undefined,
    }, { preserveState: false });
};

// Switch Tab (Active vs Trashed)
const switchTab = (trashed: boolean) => {
    router.get(route('plant-data-cleanup.index'), {
        module: props.selectedModule,
        trashed: trashed ? 1 : 0,
        search: search.value || undefined,
    }, { preserveState: false });
};

// Search handling with debounce
let searchTimer: any = null;
const handleSearch = () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('plant-data-cleanup.index'), {
            module: props.selectedModule,
            trashed: props.viewTrashed ? 1 : 0,
            search: search.value || undefined,
        }, { preserveState: true });
    }, 400);
};

// Execute Bulk Delete
const executeBulkDelete = async (options: { deleteAll?: boolean; forceDelete?: boolean }) => {
    const isForce = !!options.forceDelete;
    const isAll = !!options.deleteAll;
    const count = isAll ? (props.viewTrashed ? props.modules[props.selectedModule]?.trashed_count : props.modules[props.selectedModule]?.active_count) : selectedIds.value.length;
    const moduleName = props.modules[props.selectedModule]?.name || props.selectedModule;

    if (!isAll && selectedIds.value.length === 0) {
        Swal.fire('No selection', 'Please select at least one record to delete.', 'info');
        return;
    }

    let confirmationPrompt = {};
    if (isForce || isAll) {
        confirmationPrompt = {
            input: 'text',
            inputPlaceholder: 'Type DELETE to confirm',
            inputValidator: (value: string) => {
                if (value !== 'DELETE') {
                    return 'You must type DELETE to proceed!';
                }
            }
        };
    }

    const result = await Swal.fire({
        title: isForce ? `Permanently Delete ${count} record(s)?` : `Move ${count} record(s) to Trash?`,
        html: `
            <div class="text-left text-sm space-y-2">
                <p>Module: <strong>${moduleName}</strong></p>
                <p>Target: <strong>Plant #${props.activePlantId} (${props.plant?.name || 'Active Plant'})</strong></p>
                <div class="p-2.5 rounded bg-red-50 text-red-700 text-xs mt-2 border border-red-200">
                    ${isForce ? '⚠️ PERMANENT ACTION: These records cannot be recovered.' : 'ℹ️ Records will be soft deleted and can be restored later.'}
                </div>
            </div>
        `,
        icon: isForce ? 'error' : 'warning',
        showCancelButton: true,
        confirmButtonColor: isForce ? '#dc2626' : '#d97706',
        confirmButtonText: isForce ? 'Yes, Permanently Delete' : 'Yes, Move to Trash',
        cancelButtonText: 'Cancel',
        ...confirmationPrompt
    });

    if (!result.isConfirmed) return;

    isProcessing.value = true;
    router.post(route('plant-data-cleanup.bulk-delete'), {
        module: props.selectedModule,
        ids: isAll ? [] : selectedIds.value,
        delete_all: isAll,
        force_delete: isForce,
    }, {
        onSuccess: () => {
            selectedIds.value = [];
            isProcessing.value = false;
        },
        onError: () => {
            isProcessing.value = false;
        }
    });
};

// Execute Bulk Restore
const executeBulkRestore = async (restoreAll = false) => {
    const count = restoreAll ? props.modules[props.selectedModule]?.trashed_count : selectedIds.value.length;
    const moduleName = props.modules[props.selectedModule]?.name || props.selectedModule;

    if (!restoreAll && selectedIds.value.length === 0) {
        Swal.fire('No selection', 'Please select at least one trashed record to restore.', 'info');
        return;
    }

    const result = await Swal.fire({
        title: `Restore ${count} record(s)?`,
        text: `Restoring ${count} records to active list in ${moduleName} for Plant #${props.activePlantId}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        confirmButtonText: 'Yes, Restore',
        cancelButtonText: 'Cancel',
    });

    if (!result.isConfirmed) return;

    isProcessing.value = true;
    router.post(route('plant-data-cleanup.bulk-restore'), {
        module: props.selectedModule,
        ids: restoreAll ? [] : selectedIds.value,
        restore_all: restoreAll,
    }, {
        onSuccess: () => {
            selectedIds.value = [];
            isProcessing.value = false;
        },
        onError: () => {
            isProcessing.value = false;
        }
    });
};
</script>

<template>
    <AppLayout title="Plant Data Cleanup">
        <Head title="Active Plant Data Cleanup" />

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            
            <!-- Header & Plant Context Safety Badge -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-xl">
                            <ShieldExclamationIcon class="w-6 h-6" />
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Active Plant Data Cleanup</h1>
                            <p class="text-xs text-gray-500 mt-0.5">Bulk cleanup and restore operations strictly scoped to the active session plant.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 px-4 py-2.5 rounded-xl text-sm">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <div>
                        <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">Active Plant Scope</p>
                        <p class="font-bold text-gray-900 dark:text-white text-sm">
                            {{ plant?.name || 'Plant' }} (ID #{{ activePlantId }})
                        </p>
                    </div>
                </div>
            </div>

            <!-- Modules Summary Grid -->
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-3 flex items-center gap-2">
                    <FolderIcon class="w-4 h-4" />
                    Available Modules
                </h2>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <button
                        v-for="(mod, key) in modules"
                        :key="key"
                        @click="switchModule(key)"
                        :class="[
                            'text-left p-3.5 rounded-xl border transition-all relative flex flex-col justify-between',
                            selectedModule === key 
                                ? 'bg-primary/10 border-primary text-primary ring-2 ring-primary/20 shadow-sm' 
                                : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600'
                        ]"
                    >
                        <div>
                            <p class="text-xs font-semibold truncate">{{ mod.name }}</p>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-[11px]">
                            <span class="text-gray-500">Active: <strong>{{ mod.active_count }}</strong></span>
                            <span v-if="mod.trashed_count > 0" class="text-amber-600 font-medium">Trash: {{ mod.trashed_count }}</span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Active Module Management Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                
                <!-- Card Header with Tabs & Module Actions -->
                <div class="p-4 sm:p-6 border-b border-gray-200 dark:border-gray-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span>{{ modules[selectedModule]?.name }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-mono">
                                {{ selectedModule }}
                            </span>
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Total in Plant: {{ modules[selectedModule]?.active_count }} active, {{ modules[selectedModule]?.trashed_count }} trashed
                        </p>
                    </div>

                    <!-- Right Top Actions: All Module Actions & Tab Switch -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Tabs -->
                        <div class="inline-flex p-1 bg-gray-100 dark:bg-gray-700 rounded-xl text-xs font-medium">
                            <button
                                @click="switchTab(false)"
                                :class="[
                                    'px-3 py-1.5 rounded-lg transition',
                                    !viewTrashed 
                                        ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs' 
                                        : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'
                                ]"
                            >
                                Active ({{ modules[selectedModule]?.active_count }})
                            </button>
                            <button
                                @click="switchTab(true)"
                                :class="[
                                    'px-3 py-1.5 rounded-lg transition',
                                    viewTrashed 
                                        ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs' 
                                        : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'
                                ]"
                            >
                                Trash ({{ modules[selectedModule]?.trashed_count }})
                            </button>
                        </div>

                        <!-- All-Module Action Buttons -->
                        <button
                            v-if="!viewTrashed && (modules[selectedModule]?.active_count ?? 0) > 0"
                            @click="executeBulkDelete({ deleteAll: true, forceDelete: false })"
                            :disabled="isProcessing"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg text-amber-700 bg-amber-100 hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-300 transition"
                        >
                            Trash All Active
                        </button>

                        <button
                            v-if="viewTrashed && (modules[selectedModule]?.trashed_count ?? 0) > 0"
                            @click="executeBulkRestore(true)"
                            :disabled="isProcessing"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg text-emerald-700 bg-emerald-100 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300 transition"
                        >
                            Restore All Trashed
                        </button>

                        <button
                            v-if="viewTrashed && (modules[selectedModule]?.trashed_count ?? 0) > 0"
                            @click="executeBulkDelete({ deleteAll: true, forceDelete: true })"
                            :disabled="isProcessing"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg text-red-700 bg-red-100 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300 transition"
                        >
                            Empty Trash (Force Delete)
                        </button>
                    </div>
                </div>

                <!-- Action Bar & Search Filter -->
                <div class="p-4 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <!-- Bulk Selection Actions -->
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <span class="text-xs text-gray-500 font-medium mr-1">
                            Selected: <strong class="text-gray-800 dark:text-white">{{ selectedIds.length }}</strong>
                        </span>

                        <button
                            v-if="!viewTrashed && selectedIds.length > 0"
                            @click="executeBulkDelete({ forceDelete: false })"
                            :disabled="isProcessing"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg text-white bg-amber-600 hover:bg-amber-700 shadow-xs transition flex items-center gap-1.5"
                        >
                            <TrashIcon class="w-3.5 h-3.5" />
                            Trash Selected ({{ selectedIds.length }})
                        </button>

                        <button
                            v-if="viewTrashed && selectedIds.length > 0"
                            @click="executeBulkRestore(false)"
                            :disabled="isProcessing"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 shadow-xs transition flex items-center gap-1.5"
                        >
                            <ArrowPathIcon class="w-3.5 h-3.5" />
                            Restore Selected ({{ selectedIds.length }})
                        </button>

                        <button
                            v-if="viewTrashed && selectedIds.length > 0"
                            @click="executeBulkDelete({ forceDelete: true })"
                            :disabled="isProcessing"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg text-white bg-red-600 hover:bg-red-700 shadow-xs transition flex items-center gap-1.5"
                        >
                            <ExclamationTriangleIcon class="w-3.5 h-3.5" />
                            Force Delete Selected ({{ selectedIds.length }})
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full sm:w-64">
                        <MagnifyingGlassIcon class="w-4 h-4 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
                        <input
                            v-model="search"
                            @input="handleSearch"
                            type="text"
                            placeholder="Search by ID or Ref..."
                            class="w-full pl-9 pr-4 py-1.5 text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-primary focus:border-primary"
                        />
                    </div>
                </div>

                <!-- Records Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-left text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-900/40 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="py-3 pl-4 pr-3 w-10">
                                    <input
                                        type="checkbox"
                                        :checked="isAllSelected"
                                        @change="toggleSelectAll"
                                        class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                    />
                                </th>
                                <th class="py-3 px-3 font-semibold">ID</th>
                                <th class="py-3 px-3 font-semibold">Reference / Title</th>
                                <th class="py-3 px-3 font-semibold">Status</th>
                                <th class="py-3 px-3 font-semibold">Created Date</th>
                                <th v-if="viewTrashed" class="py-3 px-3 font-semibold">Deleted Date</th>
                                <th class="py-3 pr-4 pl-3 text-right font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-700 dark:text-gray-300">
                            <tr 
                                v-for="item in records.data" 
                                :key="item.id"
                                :class="[
                                    'hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition',
                                    selectedIds.includes(item.id) ? 'bg-primary/5 dark:bg-primary/10' : ''
                                ]"
                            >
                                <td class="py-3 pl-4 pr-3">
                                    <input
                                        type="checkbox"
                                        :checked="selectedIds.includes(item.id)"
                                        @change="toggleSelectRow(item.id)"
                                        class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                    />
                                </td>
                                <td class="py-3 px-3 font-mono font-medium text-gray-900 dark:text-white">
                                    #{{ item.id }}
                                </td>
                                <td class="py-3 px-3 font-semibold text-gray-800 dark:text-gray-200">
                                    {{ item.reference }}
                                </td>
                                <td class="py-3 px-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                        {{ item.status }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-gray-500">
                                    {{ item.created_at }}
                                </td>
                                <td v-if="viewTrashed" class="py-3 px-3 text-amber-600 font-mono text-[11px]">
                                    {{ item.deleted_at }}
                                </td>
                                <td class="py-3 pr-4 pl-3 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            v-if="!viewTrashed"
                                            @click="selectedIds = [item.id]; executeBulkDelete({ forceDelete: false })"
                                            class="p-1 text-amber-600 hover:text-amber-800 hover:bg-amber-50 rounded"
                                            title="Move to Trash"
                                        >
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                        <button
                                            v-if="viewTrashed"
                                            @click="selectedIds = [item.id]; executeBulkRestore(false)"
                                            class="p-1 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded"
                                            title="Restore"
                                        >
                                            <ArrowPathIcon class="w-4 h-4" />
                                        </button>
                                        <button
                                            v-if="viewTrashed"
                                            @click="selectedIds = [item.id]; executeBulkDelete({ forceDelete: true })"
                                            class="p-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded"
                                            title="Force Delete"
                                        >
                                            <ExclamationTriangleIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="records.data.length === 0">
                                <td :colspan="viewTrashed ? 7 : 6" class="py-8 text-center text-gray-400 text-xs">
                                    No {{ viewTrashed ? 'trashed' : 'active' }} records found for this module in Plant #{{ activePlantId }}.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div v-if="records.total > records.per_page" class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between text-xs text-gray-500">
                    <span>Showing {{ records.data.length }} of {{ records.total }} records</span>

                    <div class="flex items-center gap-1">
                        <template v-for="(link, idx) in records.links" :key="idx">
                            <button
                                v-if="link.url"
                                @click="router.get(link.url, {}, { preserveState: true })"
                                :class="[
                                    'px-2.5 py-1 rounded border text-xs transition',
                                    link.active 
                                        ? 'bg-primary text-white border-primary font-bold' 
                                        : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:bg-gray-50'
                                ]"
                                v-html="link.label"
                            />
                            <span v-else class="px-2.5 py-1 text-gray-400 text-xs" v-html="link.label" />
                        </template>
                    </div>
                </div>

            </div>

        </div>
    </AppLayout>
</template>
