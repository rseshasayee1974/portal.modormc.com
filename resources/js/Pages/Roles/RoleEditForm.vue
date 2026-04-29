<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import BaseInput from '@/Components/Base/BaseInput.vue';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import BaseButton from '@/Components/Base/BaseButton.vue';

const props = defineProps<{
    roleId: number | null;
    groupedPermissions: Record<string, any[]>;
    visible: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void;
    (e: 'updated'): void;
}>();

const toast = useToast();
const processing = ref(false);
const isFetching = ref(false);
const formErrors = ref<Record<string, string[]>>({});

const form = ref({
    id: null as number | null,
    name: '',
    code: '',
    description: '',
    level: 0,
    status: 'active',
    is_system: false,
    guard_name: 'web',
    permissions: [] as string[],
});

const loadRoleData = async () => {
    if (!props.roleId) return;
    
    isFetching.value = true;
    try {
        const response = await axios.get(`/settings/roles/${props.roleId}`);
        const data = response.data;
        const r = data.role;
        
        form.value = {
            id: r.id,
            name: r.name,
            code: r.code || '',
            description: r.description || '',
            level: r.level || 0,
            status: r.status || 'active',
            is_system: !!r.is_system,
            guard_name: r.guard_name || 'web',
            permissions: data.permissions || [],
        };
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load role data' });
        emit('update:visible', false);
    } finally {
        isFetching.value = false;
    }
};

watch(() => props.visible, (newVal) => {
    if (newVal) {
        loadRoleData();
    }
});

const toggleModuleGroup = (moduleName: string, modulePermissions: any[]) => {
    const allActions = modulePermissions.map(p => p.name);
    const hasAll = allActions.every(p => form.value.permissions.includes(p));

    if (hasAll) {
        form.value.permissions = form.value.permissions.filter(p => !allActions.includes(p));
    } else {
        const newSet = new Set([...form.value.permissions, ...allActions]);
        form.value.permissions = Array.from(newSet);
    }
};

const submit = async () => {
    processing.value = true;
    formErrors.value = {};

    try {
        await axios.put(`/settings/roles/${form.value.id}`, form.value);
        toast.add({ severity: 'success', summary: 'Success', detail: 'Role updated successfully' });
        emit('updated');
        emit('update:visible', false);
    } catch (error: any) {
        if (error.response?.status === 422) {
            formErrors.value = error.response.data.errors;
        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update role' });
        }
    } finally {
        processing.value = false;
    }
};

const close = () => {
    emit('update:visible', false);
};
</script>

<template>
    <Dialog 
        :visible="visible" 
        @update:visible="emit('update:visible', $event)" 
        modal 
        header="EDIT ROLE MATRIX" 
        :style="{ width: '900px' }"
    >
        <div v-if="isFetching" class="flex justify-center py-10">
            <i class="pi pi-spin pi-spinner text-4xl text-primary-500"></i>
        </div>
        <div v-else class="flex flex-col gap-6 py-2">
            <div class="grid grid-cols-12 gap-6">
                <!-- Left: Basic Info -->
                <div class="col-span-12 md:col-span-4 flex flex-col gap-4 border-r pr-6 border-gray-100">
                    <h4 class="text-[10px] font-black uppercase text-indigo-500 tracking-widest border-b pb-2">Identification</h4>
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase text-gray-400">Display Name</label>
                        <BaseInput v-model="form.name" fluid placeholder="e.g. Sales Manager"  />
                        <small v-if="formErrors.name" class="text-red-500 text-[10px]">{{ formErrors.name[0] }}</small>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-bold uppercase text-gray-400">System Code</label>
                        <BaseInput v-model="form.code" :disabled="form.is_system" fluid placeholder="e.g. sales_mgr"  />
                        <small v-if="formErrors.code" class="text-red-500 text-[10px]">{{ formErrors.code[0] }}</small>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] font-bold uppercase text-gray-400">Hierarchy Lvl</label>
                            <BaseInput v-model="form.level" type="number" fluid  />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] font-bold uppercase text-gray-400">Guard</label>
                            <BaseInput v-model="form.guard_name" fluid  />
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 mt-4">
                        <div class="flex items-center gap-3">
                            <Checkbox v-model="form.is_system" :binary="true" inputId="is_system_edit" />
                            <label for="is_system_edit" class="text-xs font-bold text-gray-600">System Protected Role</label>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] font-bold uppercase text-gray-400">Role Status</label>
                            <select v-model="form.status" class="text-xs border border-gray-200 rounded p-1">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-1 mt-4">
                        <label class="text-[10px] font-bold uppercase text-gray-400">Description</label>
                        <textarea v-model="form.description" class="text-xs border border-gray-200 rounded p-2 h-24" placeholder="Briefly describe the purpose of this role..."></textarea>
                    </div>
                </div>

                <!-- Right: Permissions Matrix -->
                <div class="col-span-12 md:col-span-8 flex flex-col gap-4">
                    <h4 class="text-[10px] font-black uppercase text-indigo-500 tracking-widest border-b pb-2">Privilege Matrix</h4>
                    <div class="border rounded-lg overflow-hidden border-gray-100 max-h-[500px] overflow-y-auto shadow-inner bg-gray-50/30">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
                                <tr>
                                    <th class="py-3 px-4 text-left text-[10px] font-bold uppercase tracking-widest text-gray-400">Module</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-gray-400">Actions</th>
                                    <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-widest text-gray-400">All</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white">
                                <tr v-for="(permissions, moduleGroup) in groupedPermissions" :key="moduleGroup" class="hover:bg-blue-50/30 transition-colors">
                                    <td class="py-3 px-4 text-[11px] font-black uppercase tracking-tight text-slate-700">{{ moduleGroup }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-x-4 gap-y-2">
                                            <div v-for="perm in permissions" :key="perm.id" class="flex items-center gap-1.5 group">
                                                <Checkbox v-model="form.permissions" :inputId="'perm_edit_'+perm.id" :value="perm.name"  />
                                                <label :for="'perm_edit_'+perm.id" class="text-[10px] font-bold text-slate-500 group-hover:text-indigo-600 transition-colors cursor-pointer">{{ perm.action_label }}</label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <Button icon="pi pi-check-circle" text  class="p-0 text-slate-300 hover:text-indigo-500" @click="toggleModuleGroup(moduleGroup, permissions)" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <template #footer>
            <div class="flex gap-2 justify-end mt-4">
                <BaseButton label="Discard" text severity="secondary" @click="close" />
                <BaseButton label="Update Role" :loading="processing" icon="pi pi-cloud-upload" @click="submit" />
            </div>
        </template>
    </Dialog>
</template>

<style scoped>
:deep(.p-checkbox .p-checkbox-box) {
    width: 14px;
    height: 14px;
}
:deep(.p-checkbox .p-checkbox-icon) {
    font-size: 8px;
}
</style>
