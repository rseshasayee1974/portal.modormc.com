<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseCard from '@/Components/Base/BaseCard.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

const props = defineProps<{
    schedule?: any;
    isEditing?: boolean;
    testTypes: any[];
    materials: any[];
    frequencyTypes: string[];
}>();

const form = useForm({
    material_id: props.schedule?.material_id || ('' as any),
    test_type_id: props.schedule?.test_type_id || ('' as any),
    frequency_type: props.schedule?.frequency_type || 'DAILY',
    frequency_value: props.schedule?.frequency_value || '',
    is_active: props.schedule ? Boolean(props.schedule.is_active) : true,
});

const materialOptions = computed(() => {
    return (props.materials || []).map(m => ({ label: `${m.title} (${m.code || m.material_code || 'N/A'})`, value: m.id }));
});

const testTypeOptions = computed(() => {
    return (props.testTypes || []).map(t => ({ label: `${t.code} - ${t.name}`, value: t.id }));
});

const frequencyOptions = computed(() => {
    return (props.frequencyTypes || []).map(f => ({ label: f.replace(/_/g, ' '), value: f }));
});

const submit = () => {
    if (props.isEditing && props.schedule?.id) {
        form.put(route('quality.config.test-schedules.update', props.schedule.id));
    } else {
        form.post(route('quality.config.test-schedules.store'));
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <BaseCard>
            <div class="space-y-6">
                <div class="border-b border-gray-100 dark:border-gray-800 pb-4">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
                        {{ isEditing ? 'Edit Test Schedule' : 'New Test Schedule Details' }}
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Configure testing rules and periodic frequencies for specific materials.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <BaseSelect
                        v-model="form.material_id"
                        label="Material"
                        :options="materialOptions"
                        optionLabel="label"
                        optionValue="value"
                        required
                        :error="form.errors.material_id"
                        placeholder="Select Material..."
                    />

                    <BaseSelect
                        v-model="form.test_type_id"
                        label="QC Test Type"
                        :options="testTypeOptions"
                        optionLabel="label"
                        optionValue="value"
                        required
                        :error="form.errors.test_type_id"
                        placeholder="Select Test Type..."
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <BaseSelect
                        v-model="form.frequency_type"
                        label="Frequency Type"
                        :options="frequencyOptions"
                        optionLabel="label"
                        optionValue="value"
                        required
                        :error="form.errors.frequency_type"
                    />

                    <BaseInput
                        v-model="form.frequency_value"
                        type="number"
                        label="Interval Value (Optional)"
                        placeholder="e.g. 4 (hrs), 50 (cum), 100 (bags)"
                        :error="form.errors.frequency_value"
                    />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.is_active"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4"
                        />
                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">Active Schedule</span>
                    </label>
                </div>

                <div class="pt-4 border-t border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <Link :href="route('quality.config.test-schedules.index')">
                            <BaseButton type="button" variant="secondary" size="small">
                                <i class="pi pi-arrow-left mr-1.5 text-xs"></i> Back to Schedules
                            </BaseButton>
                        </Link>
                        <BaseFormActions
                            :processing="form.processing"
                            :cancelHref="route('quality.config.test-schedules.index')"
                            :submitText="isEditing ? 'Update Schedule' : 'Create Schedule'"
                        />
                    </div>
                </div>
            </div>
        </BaseCard>
    </form>
</template>
