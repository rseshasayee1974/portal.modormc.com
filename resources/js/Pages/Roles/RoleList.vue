<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import { useToast } from 'primevue/usetoast';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';

const props = defineProps<{
    roles: any;
    filters: { search?: string };
}>();

const emit = defineEmits<{
    (e: 'edit', role: any): void;
}>();

const toast = useToast();
const searchQuery = ref(props.filters.search || '');

const onPage = (event: any) => {
    router.get('/settings/roles', { page: event.page + 1, search: searchQuery.value }, { preserveState: true });
};

const handleSearch = debounce(() => {
    router.get('/settings/roles', { search: searchQuery.value }, { preserveState: true, replace: true });
}, 300);

const confirmDelete = (role: any) => {
    if (role.is_system) {
        toast.add({ severity: 'warn', summary: 'System Protected', detail: 'This role is required by the system and cannot be deleted.' });
        return;
    }

    Swal.fire({
        title: 'Delete Role?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#fe0000',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/settings/roles/${role.id}`, {
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Deleted', detail: 'Role removed' });
                }
            });
        }
    });
};
</script>

<template>
    <BaseCard>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-xl font-semibold uppercase tracking-tight">Access Roles</span>
                <div class="flex items-center gap-2">
                    <BaseInput v-model="searchQuery" placeholder="Search Roles..." class="p-inputtext-sm" @input="handleSearch" />
                </div>
            </div>
        </template>

        <BaseDataTable 
            :value="roles.data" 
            stripedRows 
            class="p-datatable-sm text-sm"
            :lazy="true"
            :paginator="true"
            :totalRecords="roles.total"
            :rows="roles.per_page"
            @page="onPage($event)"
        >
            <Column header="S.No" style="width: 70px">
                <template #body="slotProps">
                    <span class="text-gray-400 font-bold">{{ slotProps.index + 1 }}</span>
                </template>
            </Column>
            <Column field="name" header="Role / Code" sortable>
                <template #body="slotProps">
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                            {{ slotProps.data.name }}
                            <i v-if="slotProps.data.is_system" class="pi pi-shield text-indigo-500" />
                        </span>
                        <span class="text-[10px] font-mono text-gray-400 uppercase tracking-tighter">{{ slotProps.data.code }}</span>
                    </div>
                </template>
            </Column>
            <Column field="level" header="Lvl" sortable style="width: 60px">
                <template #body="slotProps">
                    <span class="font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded-full text-xs">{{ slotProps.data.level }}</span>
                </template>
            </Column>
            <Column field="status" header="Status" style="width: 100px">
                <template #body="slotProps">
                    <span :class="[
                        'px-2 py-0.5 rounded text-[10px] uppercase font-bold',
                        slotProps.data.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                    ]">{{ slotProps.data.status }}</span>
                </template>
            </Column>
            <Column field="guard_name" header="Guard" style="width: 80px">
                <template #body="slotProps">
                    <span class="text-[10px] text-gray-400 font-bold uppercase">{{ slotProps.data.guard_name }}</span>
                </template>
            </Column>
            <Column header="Actions" class="text-right" style="width: 120px">
                <template #body="slotProps">
                    <div class="flex justify-end gap-2">
                        <BaseButton icon="pi pi-pencil" text rounded  severity="info" @click="emit('edit', slotProps.data)" />
                        <BaseButton 
                            icon="pi pi-trash" 
                            text rounded  
                            :severity="slotProps.data.is_system ? 'secondary' : 'danger'" 
                            :disabled="slotProps.data.is_system"
                            @click="confirmDelete(slotProps.data)" 
                        />
                    </div>
                </template>
            </Column>
        </BaseDataTable>
    </BaseCard>
</template>
