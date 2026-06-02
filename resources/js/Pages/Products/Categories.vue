<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
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
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';

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
const perPage = ref(30);
const statusFilter = ref<boolean | null>(null);

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});

const statusFilterOptions = [
    { label: 'All statuses', value: null },
    { label: 'Active', value: true },
    { label: 'Archived', value: false },
];

const isActive = (c: Category) => Boolean(c.status);

const filteredCategories = computed(() => {
    let list = props.categories ?? [];
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
                toast.add({ severity: 'success', summary: 'Updated', detail: 'Category saved.', life: 1500 });
            },
        });
    } else {
        form.post(route('productcategories.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showDialog.value = false;
                toast.add({ severity: 'success', summary: 'Added', detail: 'Category created.', life: 1500 });
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
                    toast.add({ severity: 'info', summary: 'Removed', detail: 'Category deleted.', life: 1500 }),
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
            <BaseDataTable
                :value="filteredCategories"
                v-model:filters="filters"
                v-model:rows="perPage"
                :globalFilterFields="['name', 'code', 'description']"
                showSearch
                showSerial
                heading="Product Categories"
                headingIcon="BookOpenIcon"
                class="text-sm"
            >
                <template #toolbar>
                    <div class="flex items-center gap-3">
                        <BaseSelect
                            v-model="statusFilter"
                            :options="statusFilterOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Status"
                            class="!h-10 !min-w-[140px]"
                            append-to="body"
                        />
                        <Button
                            label="New category"
                            icon="pi pi-plus"
                            @click="openCreate"
                        />
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
            </BaseDataTable>
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
                    <BaseInput v-model="form.name" label="Name" required placeholder="e.g. Aggregates" class="w-full" />
                    <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                </div>
                <div>
                    <BaseInput v-model="form.code" label="Code" placeholder="Optional short code" class="w-full" />
                    <small v-if="form.errors.code" class="text-red-500">{{ form.errors.code }}</small>
                </div>
                <div>
                    <BaseInput v-model="form.description" label="Description" placeholder="Optional" class="w-full" />
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
</style>
