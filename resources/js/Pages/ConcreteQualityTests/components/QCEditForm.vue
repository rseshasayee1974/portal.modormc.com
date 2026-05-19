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
    <form @submit.prevent="emit('submit')" class="space-y-6">
        <QCFormFields 
            :form="form" 
            :plants="plants" 
            :batches="batches" 
        />
        
        <!-- Actions -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
            <BaseButton 
                type="button" 
                label="Cancel" 
                severity="secondary" 
                outlined 
                @click="emit('cancel')"
            />
            <BaseButton 
                type="submit" 
                label="Update Passport" 
                severity="primary" 
                :loading="processing"
            />
        </div>
    </form>
</template>
