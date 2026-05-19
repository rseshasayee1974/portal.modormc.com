<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Dialog from 'primevue/dialog';
import BaseButton from '@/Components/Base/BaseButton.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

// Import subcomponents
import QCIndexList from './components/QCIndexList.vue';
import QCCreateForm from './components/QCCreateForm.vue';
import QCEditForm from './components/QCEditForm.vue';

const props = defineProps<{
    tests: any;
    filters: any;
    plants: any[];
    batches: any[];
    activePlantId: number | null;
}>();

const toast = useToast();
const showDialog = ref(false);
const isEditing = ref(false);
const editingId = ref<number | null>(null);
const searchQuery = ref(props.filters?.search || '');
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

// Standard initial state
const getBlankForm = () => ({
    plant_id: props.activePlantId || (props.plants?.[0]?.id ?? null),
    batch_id: null as number | null,
    test_date: new Date().toISOString().substring(0, 10),
    tested_by: '',
    slump_value: 120,
    fresh_temperature: 27,
    air_content: 1.8,
    fresh_density: 2400,
    cube_strength_7_days: 17.5,
    cube_strength_28_days: 25,
    core_test_strength: null as number | null,
    water_permeability: null as number | null,
    rapid_chloride_permeability: null as number | null,
    status: 'pending',
    remarks: '',
    photos: [] as File[],
    existing_photos: [] as any[],
    deleted_photo_ids: [] as number[],
});

const form = useForm(getBlankForm());

// Metrics computed properties
const stats = computed(() => {
    const dataList = props.tests?.data || [];
    const total = dataList.length;
    const passed = dataList.filter((t: any) => t.status === 'passed').length;
    const failed = dataList.filter((t: any) => t.status === 'failed').length;
    const passedRate = total > 0 ? Math.round((passed / total) * 100) : 0;
    
    // Slump average
    const slumpSum = dataList.reduce((sum: number, t: any) => sum + Number(t.slump_value), 0);
    const slumpAvg = total > 0 ? Math.round(slumpSum / total) : 0;

    return { total, passed, failed, passedRate, slumpAvg };
});

const openCreate = () => {
    isEditing.value = false;
    editingId.value = null;
    form.defaults(getBlankForm());
    form.reset();
    showDialog.value = true;
};

const openEdit = (test: any) => {
    isEditing.value = true;
    editingId.value = test.id;
    form.defaults({
        plant_id: test.plant_id,
        batch_id: test.batch_id,
        test_date: test.test_date ? test.test_date.substring(0, 10) : '',
        tested_by: test.tested_by || '',
        slump_value: Number(test.slump_value),
        fresh_temperature: Number(test.fresh_temperature),
        air_content: Number(test.air_content),
        fresh_density: Number(test.fresh_density),
        cube_strength_7_days: Number(test.cube_strength_7_days),
        cube_strength_28_days: Number(test.cube_strength_28_days),
        core_test_strength: test.core_test_strength ? Number(test.core_test_strength) : null,
        water_permeability: test.water_permeability ? Number(test.water_permeability) : null,
        rapid_chloride_permeability: test.rapid_chloride_permeability ? Number(test.rapid_chloride_permeability) : null,
        status: test.status,
        remarks: test.remarks || '',
        photos: [],
        existing_photos: test.photos || [],
        deleted_photo_ids: [],
    });
    form.reset();
    showDialog.value = true;
};

const submitCreate = () => {
    form.post(route('concrete-quality-tests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showDialog.value = false;
            toast.add({ severity: 'success', summary: 'Success', detail: 'Test record saved', life: 1500 });
        }
    });
};

const submitEdit = () => {
    if (!editingId.value) return;
    form.transform((data) => ({
        ...data,
        _method: 'PUT',
    })).post(route('concrete-quality-tests.update', editingId.value), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showDialog.value = false;
            toast.add({ severity: 'success', summary: 'Success', detail: 'Test record updated', life: 1500 });
        }
    });
};

const deleteTest = (id: number) => {
    Swal.fire({
        title: 'Delete Quality Record?',
        text: 'This action cannot be undone and will delete this quality passport.',
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

        <div class="min-h-screen bg-gray-50 dark:bg-gray-950 pb-5">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-3">
                
                <!-- ── Header Info Block ────────────────────────────────── -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1">
                    <div>
                        <h1 class="text-xl font-extrabold text-gray-950 dark:text-gray-50 tracking-tight">
                            Concrete Quality Testing (QC)
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Real-time quality passport control covering fresh concrete workability and hardened structural strength limits.
                        </p>
                    </div>
                    <BaseButton 
                        label="Log Quality Test" 
                        icon="pi pi-plus" 
                        severity="primary" 
                        class="bg-indigo-600 border-none hover:bg-indigo-700 font-semibold px-4 py-2.5 rounded-xl shadow-lg shadow-indigo-500/20"
                        @click="openCreate"
                    />
                </div>

                <!-- ── Table and Stats Grid List ───────────────────────── -->
                <QCIndexList 
                    :tests="tests"
                    :stats="stats"
                    v-model:searchQuery="searchQuery"
                    @edit="openEdit"
                    @delete="deleteTest"
                    @page="handlePageChange"
                    @sort="handleSort"
                />

                <!-- ── Dialog Modals ───────────────────────────────────── -->
                <Dialog 
                    v-model:visible="showDialog" 
                    :header="isEditing ? 'Modify Concrete QC Passport' : 'Log New RMC Quality Test'" 
                    :modal="true" 
                    :draggable="false"
                    class="w-full max-w-4xl dark:bg-gray-900 dark:border-gray-800 rounded-xl overflow-hidden shadow-2xl"
                >
                    <div class="p-1">
                        <QCEditForm 
                            v-if="isEditing"
                            :form="form"
                            :plants="plants"
                            :batches="batches"
                            :processing="form.processing"
                            @submit="submitEdit"
                            @cancel="showDialog = false"
                        />
                        <QCCreateForm 
                            v-else
                            :form="form"
                            :plants="plants"
                            :batches="batches"
                            :processing="form.processing"
                            @submit="submitCreate"
                            @cancel="showDialog = false"
                        />
                    </div>
                </Dialog>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Scoped high-end transitions */
</style>
