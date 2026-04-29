<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

import Swal from 'sweetalert2';
import { 
    PencilSquareIcon, 
    TrashIcon,
    PlusIcon,
    Bars3Icon,
    ListBulletIcon,
    LinkIcon,
    CursorArrowRaysIcon,
    ShieldCheckIcon,
    CheckCircleIcon,
    XCircleIcon,
    Squares2X2Icon
} from '@heroicons/vue/24/outline';

// PrimeVue
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import Card from 'primevue/card';

interface Menu {
    id: number;
    title: string;
    alias: string | null;
    link: string;
    icon: string | null;
    menutype: number;
    parent_id: number;
    ordering: number;
    published: boolean;
    permission_name: string | null;
    level?: number;
    children?: Menu[];
}

const props = defineProps<{
    menus: Menu[];
    allMenus: Menu[];
    menutypes: { label: string, value: number }[];
    iconList: string[];
}>();

const page = usePage();
const editingId = ref<number | null>(null);

const form = useForm({
    title: '',
    alias: '',
    link: '',
    icon: null as string | null,
    menutype: 2,
    parent_id: 0,
    ordering: 0,
    published: true,
    permission_name: '',
});

const parentOptions = computed(() => {
    const options = [{ label: 'Top Level (Root)', value: 0 }];
    props.allMenus.forEach(m => {
        options.push({ label: m.title, value: m.id });
    });
    return options;
});

const iconOptions = computed(() => props.iconList.map(i => ({ label: i, value: i })));

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const editMenu = (menu: Menu) => {
    editingId.value = menu.id;
    form.title = menu.title;
    form.alias = menu.alias || '';
    form.link = menu.link;
    form.icon = menu.icon;
    form.menutype = menu.menutype;
    form.parent_id = menu.parent_id;
    form.ordering = menu.ordering;
    form.published = menu.published;
    form.permission_name = menu.permission_name || '';
};

const submit = () => {
    if (editingId.value) {
        form.put(route('menus.update', editingId.value), {
            onSuccess: () => resetForm(),
        });
    } else {
        form.post(route('menus.store'), {
            onSuccess: () => resetForm(),
        });
    }
};

const deleteMenu = (id: number) => {
    Swal.fire({
        title: 'Delete Menu Item?',
        text: "Sub-menus may become inaccessible if parent is deleted.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'Yes, remove it!'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('menus.destroy', id), {
                onSuccess: () => {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Menu item deleted', showConfirmButton: false, timer: 3000 });
                }
            });
        }
    });
};

// Flat list for table display (with level calculated)
const flatMenus = computed(() => {
    const list: any[] = [];
    const traverse = (items: Menu[], level: number = 0) => {
        items.forEach(item => {
            list.push({ ...item, level });
            if (item.children && item.children.length > 0) {
                traverse(item.children, level + 1);
            }
        });
    };
    traverse(props.menus);
    return list;
});

// Watch for flash messages
watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.success) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                icon: 'success',
                title: flash.success
            });
        }
    },
    { immediate: true, deep: true }
);

</script>

<template>
    <AppLayout title="Navigation Menu Management">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <div class="py-12 bg-slate-50 dark:bg-slate-950 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-24 gap-6">
                    <!-- Form Column -->
                    <div class="col-span-24 lg:col-span-8">
                        <Card class="shadow-lg border-t-4 border-indigo-600 rounded-3xl overflow-hidden">
                            <template #title>
                                <span class="text-xl font-bold text-gray-800 dark:text-gray-100 uppercase tracking-tighter">
                                    {{ editingId ? 'Modify Menu Instance' : 'Register New Interface Link' }}
                                </span>
                            </template>
                            <template #content>
                                <form @submit.prevent="submit" class="space-y-6 pt-2">
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Internal Title *</label>
                                        <BaseInput v-model="form.title" placeholder="e.g. Machine Fleet" fluid />
                                        <small v-if="form.errors.title" class="p-error">{{ form.errors.title }}</small>
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Parent Attachment *</label>
                                        <BaseSelect v-model="form.parent_id" :options="parentOptions" optionLabel="label" optionValue="value" filter fluid />
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="flex flex-col gap-1.5">
                                            <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Menu Priority</label>
                                            <BaseInputNumber v-model="form.ordering" fluid />
                                        </div>
                                        <div class="flex flex-col gap-1.5 items-center">
                                            <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Published</label>
                                            <ToggleSwitch v-model="form.published" />
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Primary Route/Link *</label>
                                        <span class="relative">
                                            <LinkIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                            <BaseInput v-model="form.link" placeholder="machines or #" class="pl-10" fluid />
                                        </span>
                                        <small v-if="form.errors.link" class="p-error">{{ form.errors.link }}</small>
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Visual Icon Signature</label>
                                        <BaseSelect v-model="form.icon" :options="iconOptions" optionLabel="label" optionValue="value" filter placeholder="Select Heroicon" fluid />
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Gate Permission Name</label>
                                        <span class="relative">
                                            <ShieldCheckIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                            <BaseInput v-model="form.permission_name" placeholder="machines.menu" class="pl-10" fluid />
                                        </span>
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">System Category *</label>
                                        <BaseSelect v-model="form.menutype" :options="menutypes" optionLabel="label" optionValue="value" fluid />
                                    </div>

                                    <div class="flex flex-col gap-3 pt-4">
                                        <Button type="submit" severity="primary" :loading="form.processing" class="uppercase tracking-widest font-black text-xs h-12 shadow-lg shadow-indigo-500/20">
                                            {{ editingId ? 'Update Configuration' : 'Sync New Menu Item' }}
                                        </Button>
                                        <Button v-if="editingId" label="Reset / Cancel" severity="secondary" text @click="resetForm" class="uppercase font-black text-[10px] tracking-widest" />
                                    </div>
                                </form>
                            </template>
                        </Card>
                    </div>

                    <!-- Table Column -->
                    <div class="col-span-24 lg:col-span-16">
                        <div class="bg-white dark:bg-slate-900 shadow-xl rounded-[2rem] p-8 border border-slate-100 dark:border-slate-800">
                            <div class="flex justify-between items-center mb-8">
                                <div class="flex items-center gap-3">
                                    <div class="p-3 bg-indigo-50 dark:bg-indigo-900/10 text-indigo-600 rounded-2xl">
                                        <Bars3Icon class="w-6 h-6 stroke-[2.5px]" />
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-black text-gray-800 dark:text-gray-100 tracking-tighter uppercase">
                                            Navigation Architecture
                                        </h3>
                                        <Tag severity="info" rounded class="text-[9px] font-black uppercase px-3 tracking-widest">Active Tree</Tag>
                                    </div>
                                </div>
                                <div class="hidden md:flex gap-2 text-[10px] font-black text-gray-400 items-center uppercase tracking-widest">
                                    <Squares2X2Icon class="w-4 h-4"/>
                                    Hierarchy Visualizer
                                </div>
                            </div>
                            
                            <DataTable :value="flatMenus" stripedRows class="modern-table text-sm">
                                <Column field="id" header="ID" style="width: 70px" />
                                <Column header="Menu Title">
                                    <template #body="slotProps">
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-300 dark:text-gray-600 font-mono tracking-tighter">
                                                {{ '--'.repeat(slotProps.data.level || 0) }}
                                            </span>
                                            <span class="font-bold text-gray-800 dark:text-gray-200">
                                                {{ slotProps.data.title }}
                                            </span>
                                        </div>
                                    </template>
                                </Column>
                                <Column header="Type">
                                    <template #body="slotProps">
                                        <Tag :severity="slotProps.data.menutype === 1 ? 'info' : 'success'" rounded class="text-[9px] font-black uppercase tracking-widest">
                                            {{ slotProps.data.menutype === 1 ? 'Top Nav' : 'Module' }}
                                        </Tag>
                                    </template>
                                </Column>
                                <Column header="Link">
                                    <template #body="slotProps">
                                        <span class="text-xs text-gray-400 dark:text-gray-500 font-mono">{{ slotProps.data.link }}</span>
                                    </template>
                                </Column>
                                <Column header="Status" align="center">
                                    <template #body="slotProps">
                                        <CheckCircleIcon v-if="slotProps.data.published" class="w-5 h-5 text-green-500" />
                                        <XCircleIcon v-else class="w-5 h-5 text-red-400" />
                                    </template>
                                </Column>
                                <Column header="Actions" align="right">
                                    <template #body="slotProps">
                                        <div class="flex justify-end gap-1">
                                            <Button icon="pi pi-pencil" severity="info" text rounded @click="editMenu(slotProps.data)" />
                                            <Button icon="pi pi-trash" severity="danger" text rounded @click="deleteMenu(slotProps.data.id)" />
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable-thead > tr > th) {
    @apply bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-black uppercase text-[10px] tracking-widest py-5 dark:border-slate-800;
}
:deep(.p-datatable-tbody > tr > td) {
    @apply py-4 dark:border-slate-800;
}
</style>


