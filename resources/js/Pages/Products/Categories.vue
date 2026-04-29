<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import ToggleSwitch from 'primevue/toggleswitch';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Swal from 'sweetalert2';

interface Category {
    id: number;
    name: string;
    code?: string | null;
    description?: string | null;
    status: boolean | number;
}

const props = defineProps<{
    categories: Category[];
}>();

const toast = useToast();
const showDialog = ref(false);
const editingId = ref<number | null>(null);
const searchQuery = ref('');
const perPage = ref(15);
const statusFilter = ref<boolean | null>(null);

const statusFilterOptions = [
    { label: 'All statuses', value: null },
    { label: 'Active', value: true },
    { label: 'Archived', value: false },
];

const isActive = (c: Category) => Boolean(c.status);

const filteredCategories = computed(() => {
    let list = props.categories ?? [];
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(
            (c) =>
                c.name?.toLowerCase().includes(q) ||
                (c.code && c.code.toLowerCase().includes(q)) ||
                (c.description && c.description.toLowerCase().includes(q))
        );
    }
    if (statusFilter.value !== null) {
        list = list.filter((c) => isActive(c) === statusFilter.value);
    }
    return list;
});

const form = useForm({
    name: '',
    code: '',
    description: '',
    status: true,
});

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.status = true;
    showDialog.value = true;
};

const startEdit = (c: Category) => {
    editingId.value = c.id;
    form.name = c.name;
    form.code = c.code ?? '';
    form.description = c.description ?? '';
    form.status = isActive(c);
    form.clearErrors();
    showDialog.value = true;
};

const submitForm = () => {
    if (editingId.value) {
        form.put(route('productcategories.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => {
                showDialog.value = false;
                toast.add({ severity: 'success', summary: 'Updated', detail: 'Category saved.', life: 3000 });
            },
        });
    } else {
        form.post(route('productcategories.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showDialog.value = false;
                toast.add({ severity: 'success', summary: 'Added', detail: 'Category created.', life: 3000 });
            },
        });
    }
};

const deleteCategory = (id: number) => {
    Swal.fire({
        title: 'Remove category?',
        text: 'Ensure no products use this category first.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, remove',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('productcategories.destroy', id), {
                preserveScroll: true,
                onSuccess: () =>
                    toast.add({ severity: 'info', summary: 'Removed', detail: 'Category deleted.', life: 3000 }),
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Product categories">
        <template #header>
            <ModuleSubTopNav />
        </template>
        <Head title="Product categories" />
        <Toast />

        <main class="p-6 max-w-7xl mx-auto">
            <div
                class="bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden"
            >
                <!-- Title row: name + create (single container with table below) -->
                <div
                    class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-5 pt-6 pb-2 border-b border-slate-100 dark:border-slate-700/80"
                >
                    <div class="flex items-center gap-4 min-w-0">
                        <div
                            class="flex-shrink-0 flex items-center justify-center w-12 h-12 rounded-lg bg-violet-600 shadow-lg shadow-violet-200/80 dark:shadow-violet-900/30"
                        >
                            <i class="pi pi-sitemap text-white text-xl"></i>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100 leading-tight truncate">
                                Product Category
                            </h1>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="flex h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
                                <p
                                    class="text-[11px] font-bold text-slate-400 uppercase tracking-widest truncate"
                                >
                                    Categories for this plant
                                </p>
                            </div>
                        </div>
                    </div>
                    <Button
                        label="New category"
                        icon="pi pi-plus"
                        class="shrink-0"
                        @click="openCreate"
                    />
                </div>

                <DataTable
                    :value="filteredCategories"
                    data-key="id"
                    striped-rows
                    paginator
                    :rows="perPage"
                    paginator-template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
                    current-page-report-template="{first} to {last} of {totalRecords}"
                    class="p-datatable-sm"
                    row-hover
                >
                    <template #header>
                        <div
                            class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 px-2 py-3 bg-slate-50/80 dark:bg-slate-900/40 border-b border-slate-100 dark:border-slate-800"
                        >
                            <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 w-full lg:flex-1">
                                <div class="relative group w-full sm:max-w-xs lg:max-w-sm">
                                    <BaseInput
                                        v-model="searchQuery"
                                        placeholder="Search name, code…"
                                        input-class="!h-10 !pl-10 !bg-white dark:!bg-slate-900 !border-slate-200 dark:!border-slate-600 !rounded-xl !text-xs font-medium !text-slate-700 dark:!text-slate-200 focus:!ring-2 focus:!ring-violet-100 dark:focus:!ring-violet-900/30"
                                        field-class="!gap-0"
                                    />
                                    <div class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">
                                        <i class="pi pi-search text-slate-400 text-sm"></i>
                                    </div>
                                </div>
                                <BaseSelect
                                    v-model="statusFilter"
                                    :options="statusFilterOptions"
                                    option-label="label"
                                    option-value="value"
                                    placeholder="Status"
                                    class="!h-10 !min-w-[140px] !bg-white dark:!bg-slate-900 !border-slate-200 dark:!border-slate-600 !rounded-xl !text-xs font-bold !text-slate-600 dark:!text-slate-300"
                                    append-to="body"
                                />
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider whitespace-nowrap"
                                        >Per page</span
                                    >
                                    <BaseSelect
                                        v-model="perPage"
                                        :options="[10, 15, 30, 50, 100]"
                                        class="!h-10 !w-20 !bg-white dark:!bg-slate-900 !border-slate-200 !rounded-xl !text-xs font-bold !text-slate-500"
                                        append-to="body"
                                    />
                                </div>
                            </div>
                        </div>
                    </template>

                    <Column field="name" header="Category" sortable style="min-width: 200px">
                        <template #body="slotProps">
                            <div class="flex flex-col gap-0.5">
                                <span
                                    class="font-bold text-slate-800 dark:text-slate-100 uppercase tracking-tight text-sm leading-tight"
                                    >{{ slotProps.data.name }}</span
                                >
                                <span
                                    v-if="slotProps.data.code"
                                    class="text-[10px] font-semibold text-slate-400 uppercase"
                                    >{{ slotProps.data.code }}</span
                                >
                            </div>
                        </template>
                    </Column>

                    <Column header="Description" style="min-width: 160px">
                        <template #body="slotProps">
                            <span class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2">{{
                                slotProps.data.description || '—'
                            }}</span>
                        </template>
                    </Column>

                    <Column field="status" header="Status" sortable style="width: 120px" align="center">
                        <template #body="slotProps">
                            <Tag
                                :value="isActive(slotProps.data) ? 'Active' : 'Archived'"
                                :severity="isActive(slotProps.data) ? 'success' : 'secondary'"
                                rounded
                                pt:root:style="font-size: 10px; font-weight: 700"
                            />
                        </template>
                    </Column>

                    <Column header="" class="text-right" style="width: 110px">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-1">
                                <Button icon="pi pi-pencil" text rounded @click="startEdit(slotProps.data)" />
                                <Button
                                    icon="pi pi-trash"
                                    text
                                    rounded
                                    severity="danger"
                                    @click="deleteCategory(slotProps.data.id)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </main>

        <Dialog
            v-model:visible="showDialog"
            modal
            :header="editingId ? 'Edit category' : 'New category'"
            :style="{ width: 'min(440px, 96vw)' }"
            class="p-fluid"
        >
            <div class="flex flex-col gap-4 py-2">
                <div>
                    <label class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Name *</label>
                    <BaseInput v-model="form.name" placeholder="e.g. Aggregates" class="mt-1 w-full" />
                    <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Code</label>
                    <BaseInput v-model="form.code" placeholder="Optional short code" class="mt-1 w-full" />
                    <small v-if="form.errors.code" class="text-red-500">{{ form.errors.code }}</small>
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Description</label>
                    <BaseInput v-model="form.description" placeholder="Optional" class="mt-1 w-full" />
                    <small v-if="form.errors.description" class="text-red-500">{{ form.errors.description }}</small>
                </div>
                <div class="flex items-center gap-3 pt-1">
                    <ToggleSwitch v-model="form.status" />
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-tight"
                        >Active</span
                    >
                </div>
                <small v-if="form.errors.status" class="text-red-500">{{ form.errors.status }}</small>
                <small v-if="form.errors.plant_id" class="text-red-500">{{ form.errors.plant_id }}</small>
            </div>

            <div class="flex gap-2 justify-end mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <Button label="Cancel" text severity="secondary" @click="showDialog = false" />
                <Button
                    :label="editingId ? 'Save' : 'Create'"
                    icon="pi pi-check"
                    :loading="form.processing"
                    @click="submitForm"
                />
            </div>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable-thead > tr > th) {
    @apply !bg-slate-50/90 dark:!bg-slate-900/50 !text-slate-400 !font-extrabold !text-[10px] !uppercase !tracking-[0.15em] !py-4 !border-b !border-slate-100 dark:!border-slate-800;
}

:deep(.p-datatable-tbody > tr:hover) {
    @apply !bg-violet-50/40 dark:!bg-violet-900/10;
}

:deep(.p-datatable-tbody > tr > td) {
    @apply !py-4 !border-b !border-slate-50 dark:!border-slate-800/60;
}

:deep(.p-paginator) {
    @apply !bg-transparent !border-t !border-slate-100 dark:!border-slate-800 !py-4;
}
</style>
