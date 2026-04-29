<script setup lang="ts">
import { ref } from 'vue';
import { TruckIcon } from '@heroicons/vue/24/outline';
import MachineFormFields from './MachineFormFields.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

const props = defineProps<{
    form: any;
    vehicleOptions: any[];
    docTypeOptions: any[];
    transportOwnerOptions: any[];
    addDocument: () => void;
    removeDocument: (index: number) => void;
    addLoan: () => void;
    removeLoan: (index: number) => void;
}>();

const emit = defineEmits(['submit']);
const isOpen = ref(false);

const toggle = () => {
    isOpen.value = !isOpen.value;
    if (!isOpen.value) {
        props.form.reset();
        props.form.clearErrors();
    }
};
</script>

<template>
    <div class="create-panel" :class="{ 'create-panel--open': isOpen }">
        <button class="create-panel__header" @click="toggle" type="button">
            <div class="flex items-center gap-3">
                <div class="create-panel__icon">
                    <TruckIcon class="w-4 h-4 text-indigo-500" />
                </div>
                <div class="text-left">
                    <p class="text-xs font-bold text-gray-700 uppercase tracking-widest leading-none">Enroll New Asset</p>
                    <p class="text-[11px] text-gray-400 font-medium mt-1.5 uppercase tracking-wider">Register vehicle, documentation & equipment</p>
                </div>
            </div>
            <div class="create-panel__toggle" :class="{ 'create-panel__toggle--open': isOpen }">
                <i class="pi pi-plus text-[10px]"></i>
            </div>
        </button>

        <Transition name="panel-slide">
            <div v-if="isOpen" class="create-panel__body">
                <MachineFormFields
                    :form="form"
                    :vehicle-options="vehicleOptions"
                    :doc-type-options="docTypeOptions"
                    :add-document="addDocument"
                    :remove-document="removeDocument"
                    :add-loan="addLoan"
                    :transportOwnerOptions="transportOwnerOptions"
                    :remove-loan="removeLoan"
                />

                <div class="expansion-actions mt-8">
                    <BaseFormActions
                        label="Enroll Asset"
                        cancel-label="Reset Registry"
                        :loading="form.processing"
                        @submit="$emit('submit')"
                        @reset="form.reset(); form.clearErrors()"
                    />
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.create-panel { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; transition: border-color 0.2s ease, box-shadow 0.2s ease; margin-bottom: 1.5rem; }
.create-panel--open { border-color: #c7d2fe; box-shadow: 0 0 0 3px rgba(99,102,241,0.07), 0 1px 3px rgba(0,0,0,0.05); }
.create-panel__header { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 14px 18px; background: transparent; border: none; cursor: pointer; text-align: left; transition: background 0.15s ease; }
.create-panel__header:hover { background: #f8fafc; }
.create-panel__icon { width: 34px; height: 34px; border-radius: 8px; background: linear-gradient(135deg, #eef2ff, #e0e7ff); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.create-panel__toggle { width: 26px; height: 26px; border-radius: 50%; background: #eef2ff; display: flex; align-items: center; justify-content: center; color: #6366f1; transition: background 0.2s, transform 0.25s ease; }
.create-panel__toggle--open { transform: rotate(45deg); background: #6366f1; color: white; }
.create-panel__body { padding: 24px; border-top: 1px solid #eef2ff; background: linear-gradient(180deg, #fafbff 0%, #ffffff 100%); }

.expansion-actions { display: flex; justify-content: flex-end; padding-top: 20px; border-top: 1px solid #eef2ff; }

.panel-slide-enter-active { transition: all 0.22s cubic-bezier(0.4,0,0.2,1); }
.panel-slide-leave-active { transition: all 0.16s ease; }
.panel-slide-enter-from, .panel-slide-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
