<script setup lang="ts">
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';

defineProps<{
    visible: boolean;
    duplicateInfo: {
        id: number;
        original_filename: string;
        status: string;
        created_at: string;
    } | null;
}>();

const emit = defineEmits(['close', 'reprocess', 'viewExisting']);
</script>

<template>
    <Dialog 
        :visible="visible" 
        modal 
        header="Duplicate File Detected" 
        :style="{ width: '450px' }" 
        :closable="false"
        class="p-fluid shadow-2xl"
    >
        <div class="flex flex-col gap-4 text-center py-2">
            <div class="mx-auto bg-amber-50 text-amber-500 rounded-full w-14 h-14 flex items-center justify-center border border-amber-200">
                <i class="pi pi-exclamation-triangle text-2xl"></i>
            </div>
            
            <div>
                <p class="text-sm text-gray-600 leading-relaxed">
                    A file with the same content hash has already been uploaded to this plant.
                </p>
                <div v-if="duplicateInfo" class="mt-4 p-3 bg-gray-50 border border-gray-150 rounded-lg text-left text-xs font-mono text-gray-700">
                    <div class="mb-1"><span class="font-bold text-gray-500">File:</span> {{ duplicateInfo.original_filename }}</div>
                    <div class="mb-1"><span class="font-bold text-gray-500">Date:</span> {{ duplicateInfo.created_at }}</div>
                    <div><span class="font-bold text-gray-500">Status:</span> 
                        <span class="ml-1 uppercase font-semibold px-2 py-0.5 rounded text-[10px]"
                            :class="[
                                duplicateInfo.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'
                            ]">
                            {{ duplicateInfo.status }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex gap-2 justify-end w-full">
                <Button 
                    label="Cancel" 
                    icon="pi pi-times" 
                    class="p-button-text p-button-secondary" 
                    @click="emit('close')" 
                />
                <Button 
                    label="Reprocess File" 
                    icon="pi pi-refresh" 
                    class="p-button-outlined p-button-warning" 
                    @click="emit('reprocess')" 
                />
                <Button 
                    v-if="duplicateInfo && duplicateInfo.status === 'completed'"
                    label="View Batch" 
                    icon="pi pi-eye" 
                    class="p-button-primary" 
                    @click="emit('viewExisting')" 
                />
            </div>
        </template>
    </Dialog>
</template>
