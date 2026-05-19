<script setup lang="ts">
import BaseButton from '@/Components/Base/BaseButton.vue';
import QCFormFields from './QCFormFields.vue';

const props = defineProps<{
    form: any;
    plants: any[];
    batches: any[];
    processing: boolean;
}>();

const emit = defineEmits<{
    submit: [];
    cancel: [];
}>();
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-3">
        
        
        <!-- Actions -->
        <div class="flex justify-end gap-3  border-t border-gray-100 dark:border-gray-800">
            <!-- <BaseButton 
                type="button" 
                label="Cancel" 
                severity="secondary" 
                outlined 
                @click="emit('cancel')"
            /> -->
            <BaseButton 
                type="submit" class="!bg-indigo-800 !hover:bg-indigo-900"
                label="Save QC Test" 
                severity="primary" 
                :loading="processing"
            />
        </div>
        <QCFormFields 
            :form="form" 
            :plants="plants" 
            :batches="batches" 
        />
    </form>
</template>
