<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import BaseButton from '@/Components/Base/BaseButton.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import QCIndexList from './components/QCIndexList.vue';

const props = defineProps<{
    tests: any;
    filters: any;
    plants: any[];
    batches: any[];
    activePlantId: number | null;
}>();

const toast = useToast();
const searchQuery = ref(props.filters?.search || '');
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

// Metrics computed properties
const stats = computed(() => {
    const dataList = props.tests?.data || [];
    const total = dataList.length;
    const passed = dataList.filter((t: any) => t.status === 'passed').length;
    const failed = dataList.filter((t: any) => t.status === 'failed').length;
    const passedRate = total > 0 ? Math.round((passed / total) * 100) : 0;
    
    const slumpSum = dataList.reduce((sum: number, t: any) => sum + Number(t.slump_value || 0), 0);
    const slumpAvg = total > 0 ? Math.round(slumpSum / total) : 0;

    return { total, passed, failed, passedRate, slumpAvg };
});

const openCreate = () => {
    router.get(route('concrete-quality-tests.create'));
};

const openEdit = (test: any) => {
    router.get(route('concrete-quality-tests.edit', test.id));
};

const openView = (test: any) => {
    router.get(route('concrete-quality-tests.show', test.id));
};

const deleteTest = (id: number) => {
    Swal.fire({
        title: 'Delete Quality Record?',
        text: 'This action cannot be undone and will delete this quality test record.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('concrete-quality-tests.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({ severity: 'info', summary: 'Deleted', detail: 'Quality record removed', life: 1500 });
                }
            });
        }
    });
};

const fetchTests = () => {
    router.get(route('concrete-quality-tests.index'), {
        search: searchQuery.value,
        sort_field: props.filters?.sort_field || 'id',
        sort_direction: props.filters?.sort_direction || 'desc',
    }, { preserveState: true, preserveScroll: true });
};

const handlePageChange = (event: any) => {
    router.get(route('concrete-quality-tests.index'), {
        page: (event.page || 0) + 1,
        search: searchQuery.value,
        sort_field: props.filters?.sort_field || 'id',
        sort_direction: props.filters?.sort_direction || 'desc',
    }, { preserveState: true, preserveScroll: true });
};

const handleSort = (event: any) => {
    router.get(route('concrete-quality-tests.index'), {
        search: searchQuery.value,
        sort_field: event.sortField,
        sort_direction: event.sortOrder === 1 ? 'asc' : 'desc',
    }, { preserveState: true, preserveScroll: true });
};

watch(searchQuery, () => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    searchDebounceTimer = setTimeout(() => {
        fetchTests();
    }, 300);
});
</script>

<template>
    <AppLayout title="Concrete Quality Testing (QC)">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Concrete Quality Controls" />
        <Toast />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-950 pb-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-4">
                
                <!-- ── Header Info Block ── -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                    <div>
                        <h1 class="text-xl font-extrabold text-gray-950 dark:text-gray-50 tracking-tight">
                            Concrete Quality Testing (QC)
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Concrete compressive strength testing, cube crushing results, and workability quality assurance passports.
                        </p>
                    </div>
                    <BaseButton 
                        label="Log Quality Test" 
                        icon="pi pi-plus" 
                        severity="primary" 
                        class="bg-[#0088cc] hover:bg-[#0077b5] text-white border-none font-semibold px-5 py-2.5 rounded-xl shadow-lg shadow-sky-500/20"
                        @click="openCreate"
                    />
                </div>

                <!-- ── Table and Stats Grid List ── -->
                <QCIndexList 
                    :tests="tests"
                    :stats="stats"
                    v-model:searchQuery="searchQuery"
                    @view="openView"
                    @edit="openEdit"
                    @delete="deleteTest"
                    @page="handlePageChange"
                    @sort="handleSort"
                />

            </div>
        </div>
    </AppLayout>
</template>
